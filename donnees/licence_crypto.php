<?php

/**
 * Signature et vérification des fichiers licence (.lic).
 * Compatible PHP 5.3+ (OpenSSL).
 */
class LicenceCrypto
{
    const FORMAT_VERSION = 1;

    /**
     * @return int
     */
    public static function signAlgorithm()
    {
        if (defined('OPENSSL_ALGO_SHA256')) {
            return OPENSSL_ALGO_SHA256;
        }
        return OPENSSL_ALGO_SHA1;
    }

    /**
     * @return string
     */
    public static function getPublicKeyPath()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'licence' . DIRECTORY_SEPARATOR . 'public.pem';
    }

    /**
     * @param array $payload
     * @param string $privateKeyPath
     * @return array ok, content|error
     */
    public static function buildLicenceFile($payload, $privateKeyPath)
    {
        if (!is_array($payload)) {
            return array('ok' => false, 'error' => 'Payload invalide.');
        }
        if (!is_readable($privateKeyPath)) {
            return array('ok' => false, 'error' => 'Clé privée introuvable.');
        }

        $payloadJson = json_encode($payload);
        if ($payloadJson === false || $payloadJson === 'null') {
            return array('ok' => false, 'error' => 'Impossible d\'encoder le payload JSON.');
        }

        if (!function_exists('openssl_sign')) {
            return array('ok' => false, 'error' => 'Extension OpenSSL absente dans PHP.');
        }

        $privateKey = @openssl_pkey_get_private(file_get_contents($privateKeyPath));
        if ($privateKey === false) {
            $privateKey = @openssl_get_privatekey(file_get_contents($privateKeyPath));
        }

        if ($privateKey === false) {
            return array('ok' => false, 'error' => 'Clé privée illisible.');
        }

        $signature = '';
        $signed = @openssl_sign($payloadJson, $signature, $privateKey, self::signAlgorithm());
        if (function_exists('openssl_free_key')) {
            @openssl_free_key($privateKey);
        }
        if (!$signed) {
            return array('ok' => false, 'error' => 'Échec de la signature.');
        }

        $envelope = array(
            'v' => self::FORMAT_VERSION,
            'payload' => base64_encode($payloadJson),
            'signature' => base64_encode($signature),
        );

        $content = json_encode($envelope);
        if ($content === false) {
            return array('ok' => false, 'error' => 'Impossible d\'encoder le fichier licence.');
        }

        return array('ok' => true, 'content' => $content);
    }

    /**
     * @param string $rawContent
     * @param string|null $publicKeyPath
     * @return array ok, payload|error
     */
    public static function verifyLicenceContent($rawContent, $publicKeyPath = null)
    {
        if ($publicKeyPath === null) {
            $publicKeyPath = self::getPublicKeyPath();
        }

        $rawContent = trim((string) $rawContent);
        if ($rawContent === '') {
            return array('ok' => false, 'error' => 'Fichier licence vide.');
        }

        $envelope = json_decode($rawContent, true);
        if (!is_array($envelope)) {
            return array('ok' => false, 'error' => 'Format licence invalide.');
        }

        if (!isset($envelope['payload'], $envelope['signature'])) {
            return array('ok' => false, 'error' => 'Champs payload/signature manquants.');
        }

        $payloadJson = base64_decode($envelope['payload']);
        $signature = base64_decode($envelope['signature']);
        if ($payloadJson === false || $payloadJson === '' || $signature === false || $signature === '') {
            return array('ok' => false, 'error' => 'Encodage base64 invalide.');
        }

        if (!function_exists('openssl_verify')) {
            return array('ok' => false, 'error' => 'Extension OpenSSL absente dans PHP.');
        }

        if (!is_readable($publicKeyPath)) {
            return array('ok' => false, 'error' => 'Clé publique introuvable.');
        }

        $publicKey = @openssl_pkey_get_public(file_get_contents($publicKeyPath));
        if ($publicKey === false) {
            $publicKey = @openssl_get_publickey(file_get_contents($publicKeyPath));
        }
        if ($publicKey === false) {
            return array('ok' => false, 'error' => 'Clé publique illisible.');
        }

        $verified = @openssl_verify($payloadJson, $signature, $publicKey, self::signAlgorithm());
        if (function_exists('openssl_free_key')) {
            @openssl_free_key($publicKey);
        }

        if ($verified !== 1) {
            return array('ok' => false, 'error' => 'Signature licence invalide.');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return array('ok' => false, 'error' => 'Payload licence illisible.');
        }

        return array('ok' => true, 'payload' => $payload);
    }
}
