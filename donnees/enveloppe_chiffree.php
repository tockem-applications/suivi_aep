<?php

/**
 * Chiffrement des fichiers de relevé par code d'accès.
 *
 * Pendant serveur de lib/data/crypto/enveloppe_chiffree.dart, dans le dépôt
 * SÉPARÉ de l'application mobile Tockem Collect. Les deux implémentations
 * DOIVENT rester alignées ; toute évolution ici impose la même de l'autre côté.
 *
 * Vivant dans deux dépôts, elles ne peuvent pas être modifiées d'un seul
 * commit : rien n'empêchera plus mécaniquement une divergence. Le garde-fou est
 * le champ `chiffrement.algo` de l'enveloppe, qu'un lecteur trop ancien
 * refusera explicitement au lieu de produire des données fausses — d'où
 * l'importance de le faire évoluer à chaque changement de protocole.
 *
 * Protocole (cf. docs/refonte/CAHIER_DES_CHARGES_MOBILE.md §4) :
 *
 *   cle       = PBKDF2-HMAC-SHA256(code, sel, 100 000 itérations, 32 octets)
 *   chiffre   = AES-256-CBC(json, cle, iv)                 padding PKCS#7
 *   signature = HMAC-SHA256(sel || iv || chiffre, cle)     chiffrer-puis-signer
 *
 * Contraintes PHP 5.3 assumées : ni AES-GCM ni hash_pbkdf2() n'existent sur
 * cette version, d'où la dérivation écrite à la main et le mode CBC signé.
 */

/**
 * Le code fourni ne correspond pas à l'enveloppe.
 *
 * Exception distincte des autres échecs : c'est le seul cas où redemander un
 * code à l'utilisateur a un sens. Une enveloppe corrompue ou un algorithme
 * inconnu ne se résoudront pas en ressaisissant le code.
 */
class CodeAccesInvalideException extends Exception
{
}

class EnveloppeChiffree
{
    const ALGO = 'aes-256-cbc';
    const KDF = 'pbkdf2-hmac-sha256';
    const ITERATIONS = 100000;
    const TAILLE_SEL = 16;
    const TAILLE_IV = 16;
    const TAILLE_CLE = 32;

    /**
     * L'extension OpenSSL est-elle disponible ? Sans elle, aucun chiffrement
     * n'est possible et l'export doit rester en clair.
     */
    public static function estDisponible()
    {
        return function_exists('openssl_encrypt') && function_exists('hash_hmac');
    }

    /**
     * Le document porte-t-il une enveloppe chiffrée ?
     *
     * @param array $document
     * @return bool
     */
    public static function estChiffre($document)
    {
        return is_array($document)
            && isset($document['chiffrement'], $document['donnees'])
            && is_array($document['chiffrement'])
            && is_string($document['donnees']);
    }

    /**
     * Enveloppe un contenu avec le code fourni.
     *
     * @param array  $contenu
     * @param string $code
     * @return array
     * @throws Exception si OpenSSL est absent
     */
    public static function chiffrer(array $contenu, $code)
    {
        if (!self::estDisponible()) {
            throw new Exception("L'extension OpenSSL est requise pour protéger un fichier.");
        }

        $sel = self::octetsAleatoires(self::TAILLE_SEL);
        $iv = self::octetsAleatoires(self::TAILLE_IV);
        $cle = self::deriverCle($code, $sel, self::ITERATIONS);

        $clair = json_encode($contenu);
        $chiffre = openssl_encrypt($clair, self::ALGO, $cle, true, $iv);
        if ($chiffre === false) {
            throw new Exception('Échec du chiffrement.');
        }
        $signature = hash_hmac('sha256', $sel . $iv . $chiffre, $cle, true);

        return array(
            'chiffrement' => array(
                'algo' => self::ALGO,
                'kdf' => self::KDF,
                'iterations' => self::ITERATIONS,
                'sel' => base64_encode($sel),
                'iv' => base64_encode($iv),
                'signature' => base64_encode($signature),
            ),
            'donnees' => base64_encode($chiffre),
        );
    }

    /**
     * Ouvre une enveloppe.
     *
     * La signature est vérifiée AVANT tout déchiffrement : un code erroné se
     * détecte sans jamais exposer de contenu.
     *
     * @param array  $document
     * @param string $code
     * @return array le contenu déchiffré
     * @throws Exception message destiné à l'utilisateur
     */
    public static function dechiffrer($document, $code)
    {
        if (!self::estDisponible()) {
            throw new Exception("L'extension OpenSSL est requise pour ouvrir un fichier protégé.");
        }
        if (!self::estChiffre($document)) {
            throw new Exception("Le fichier ne contient pas d'enveloppe chiffrée.");
        }

        $entete = $document['chiffrement'];
        $algo = isset($entete['algo']) ? $entete['algo'] : '';
        $kdf = isset($entete['kdf']) ? $entete['kdf'] : '';
        if ($algo !== self::ALGO || $kdf !== self::KDF) {
            throw new Exception(
                'Protection non reconnue (' . $algo . ' / ' . $kdf . '). '
                . 'Ce fichier provient peut-être d\'une version plus récente.'
            );
        }

        $sel = base64_decode(isset($entete['sel']) ? $entete['sel'] : '', true);
        $iv = base64_decode(isset($entete['iv']) ? $entete['iv'] : '', true);
        $signatureAttendue = base64_decode(isset($entete['signature']) ? $entete['signature'] : '', true);
        $chiffre = base64_decode($document['donnees'], true);

        if ($sel === false || $iv === false || $signatureAttendue === false || $chiffre === false) {
            throw new Exception('Enveloppe corrompue.');
        }

        $iterations = isset($entete['iterations']) ? (int) $entete['iterations'] : self::ITERATIONS;
        if ($iterations < 1000 || $iterations > 1000000) {
            throw new Exception('Paramètre de dérivation hors limites.');
        }

        $cle = self::deriverCle($code, $sel, $iterations);
        $signature = hash_hmac('sha256', $sel . $iv . $chiffre, $cle, true);

        if (!self::egaliteConstante($signature, $signatureAttendue)) {
            throw new CodeAccesInvalideException("Code d'accès incorrect.");
        }

        $clair = openssl_decrypt($chiffre, self::ALGO, $cle, true, $iv);
        if ($clair === false) {
            throw new Exception('Déchiffrement impossible.');
        }

        $contenu = json_decode($clair, true);
        if (!is_array($contenu)) {
            throw new Exception('Contenu déchiffré illisible.');
        }
        return $contenu;
    }

    /**
     * PBKDF2-HMAC-SHA256, écrit à la main : hash_pbkdf2() n'existe qu'à partir
     * de PHP 5.5.
     *
     * @param string $code
     * @param string $sel
     * @param int    $iterations
     * @return string clé binaire de TAILLE_CLE octets
     */
    private static function deriverCle($code, $sel, $iterations)
    {
        $tailleHash = 32; // SHA-256
        $blocs = (int) ceil(self::TAILLE_CLE / $tailleHash);
        $sortie = '';

        for ($i = 1; $i <= $blocs; $i++) {
            $bloc = hash_hmac('sha256', $sel . pack('N', $i), $code, true);
            $cumul = $bloc;
            for ($j = 1; $j < $iterations; $j++) {
                $bloc = hash_hmac('sha256', $bloc, $code, true);
                $cumul ^= $bloc;
            }
            $sortie .= $cumul;
        }
        return substr($sortie, 0, self::TAILLE_CLE);
    }

    /**
     * Octets aléatoires de qualité cryptographique.
     *
     * mcrypt est essayé en premier pour une raison de performance mesurée :
     * sur ce build PHP 5.3 pour Windows, openssl_random_pseudo_bytes() met
     * environ 2 secondes par appel, contre 5 millisecondes pour mcrypt. Sans
     * cette préférence, chaque export protégé traînerait plusieurs secondes.
     *
     * mcrypt est déprécié à partir de PHP 7.1 : le repli OpenSSL garantit que
     * le code reste fonctionnel si l'extension venait à disparaître.
     *
     * @param int $longueur
     * @return string
     * @throws Exception si aucune source fiable n'est disponible
     */
    private static function octetsAleatoires($longueur)
    {
        if (function_exists('mcrypt_create_iv')) {
            $octets = @mcrypt_create_iv($longueur, MCRYPT_DEV_URANDOM);
            if ($octets !== false && strlen($octets) === $longueur) {
                return $octets;
            }
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $fort = false;
            $octets = openssl_random_pseudo_bytes($longueur, $fort);
            if ($octets !== false && $fort) {
                return $octets;
            }
        }

        // Aucun repli approximatif ici : un sel ou un IV mal engendré
        // affaiblirait silencieusement la protection. Mieux vaut refuser.
        throw new Exception(
            "Aucune source d'aléa cryptographique disponible sur ce serveur "
            . "(activer l'extension mcrypt ou openssl)."
        );
    }

    /**
     * Comparaison à durée constante : une comparaison naïve laisserait fuir,
     * par son temps d'exécution, le nombre d'octets corrects.
     */
    private static function egaliteConstante($a, $b)
    {
        if (!is_string($a) || !is_string($b) || strlen($a) !== strlen($b)) {
            return false;
        }
        $difference = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            $difference |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $difference === 0;
    }
}
