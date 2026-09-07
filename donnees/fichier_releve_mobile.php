<?php
/**
 * Lecture d'un fichier de relevé renvoyé par l'application mobile.
 *
 * Quatre formes sont acceptées, et il faut toutes les accepter : le parc de
 * téléphones n'est pas homogène et les anciennes versions de l'application
 * resteront en service un moment.
 *
 *   1. JSON en clair              -- ancienne application, et export non protégé
 *   2. JSON chiffré               -- enveloppe EnveloppeChiffree
 *   3. ZIP { releve.json, photos/ }
 *   4. ZIP dont le releve.json est chiffré
 *
 * Le format est déduit du contenu, jamais de l'extension : un fichier qui a
 * transité par une messagerie arrive souvent renommé.
 */

@include_once(__DIR__ . '/enveloppe_chiffree.php');

class FichierReleveMobile
{
    /** Nom attendu du document à l'intérieur d'une archive. */
    const NOM_JSON_DANS_ZIP = 'releve.json';

    /** Garde-fous : une tournée n'a aucune raison de dépasser ces ordres de grandeur. */
    const MAX_PHOTOS = 500;
    const MAX_OCTETS_PHOTO = 8388608;    // 8 Mo par image
    const MAX_OCTETS_JSON = 33554432;    // 32 Mo de JSON décompressé

    /** Extensions d'image tolérées dans l'archive. */
    private static $extensionsImage = array('jpg', 'jpeg', 'png');

    /**
     * Lit le fichier déposé.
     *
     * @param string $chemin  chemin du fichier téléversé (tmp_name)
     * @param string $code    code d'accès, vide si le fichier n'est pas protégé
     * @return array  array(
     *     'ok'       => bool,
     *     'erreur'   => string,          message affichable tel quel
     *     'code_requis' => bool,         il faut (re)demander un code
     *     'document' => array,           le relevé décodé
     *     'photos'   => array,           chemin relatif => contenu binaire
     *     'protege'  => bool
     * )
     */
    public static function lire($chemin, $code = '')
    {
        if (!is_readable($chemin)) {
            return self::echec("Le fichier n'a pas pu être lu sur le serveur.");
        }

        $octets = file_get_contents($chemin);
        if ($octets === false || $octets === '') {
            return self::echec('Le fichier est vide.');
        }

        $photos = array();
        if (self::ressembleAUnZip($octets)) {
            $extrait = self::extraireArchive($chemin);
            if (!$extrait['ok']) {
                return $extrait;
            }
            $octets = $extrait['json'];
            $photos = $extrait['photos'];
        }

        $document = json_decode($octets, true);
        if (!is_array($document)) {
            return self::echec(
                "Ce fichier n'est pas un relevé exploitable. "
                . "Vérifie qu'il s'agit bien du fichier renvoyé par l'application mobile."
            );
        }

        $protege = false;
        if (class_exists('EnveloppeChiffree') && EnveloppeChiffree::estChiffre($document)) {
            $protege = true;
            if ($code === '') {
                return array(
                    'ok' => false,
                    'erreur' => "Ce fichier est protégé : saisis le code d'accès communiqué à l'agent.",
                    'code_requis' => true,
                    'document' => array(),
                    'photos' => array(),
                    'protege' => true,
                );
            }
            if (!EnveloppeChiffree::estDisponible()) {
                return self::echec(
                    "Ce fichier est chiffré mais l'extension OpenSSL de PHP est désactivée. "
                    . 'Lance app-setup/enable_openssl_php.bat puis redémarre Wamp.'
                );
            }
            try {
                $document = EnveloppeChiffree::dechiffrer($document, $code);
            } catch (CodeAccesInvalideException $e) {
                // Seul cas où redemander un code a un sens.
                return array(
                    'ok' => false,
                    'erreur' => "Le code d'accès ne correspond pas à ce fichier.",
                    'code_requis' => true,
                    'document' => array(),
                    'photos' => array(),
                    'protege' => true,
                );
            } catch (Exception $e) {
                return self::echec('Fichier protégé illisible : ' . $e->getMessage());
            }
        }

        if (!isset($document['releve'])) {
            return self::echec("La structure du fichier ne correspond pas à un relevé d'index.");
        }

        return array(
            'ok' => true,
            'erreur' => '',
            'code_requis' => false,
            'document' => $document,
            'photos' => $photos,
            'protege' => $protege,
        );
    }

    /**
     * Signature « PK\x03\x04 » en tête d'archive. On ne se fie pas à
     * l'extension : un .zip renvoyé par WhatsApp arrive parfois en .bin.
     */
    public static function ressembleAUnZip($octets)
    {
        return strlen($octets) >= 4 && substr($octets, 0, 4) === "PK\x03\x04";
    }

    /**
     * Extrait le document et les photos d'une archive.
     *
     * Chaque entrée est filtrée avant d'être lue : nom, extension, taille
     * décompressée. Une archive reçue est une donnée hostile par défaut, même
     * quand elle vient d'un agent de confiance -- elle a transité par des
     * messageries grand public.
     */
    private static function extraireArchive($chemin)
    {
        if (!class_exists('ZipArchive')) {
            return self::echec("L'extension ZipArchive n'est pas disponible sur ce serveur.");
        }

        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            return self::echec("L'archive est illisible ou endommagée.");
        }

        $json = null;
        $photos = array();
        $total = $zip->numFiles;

        for ($i = 0; $i < $total; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $nom = str_replace('\\', '/', $stat['name']);
            $taille = isset($stat['size']) ? (int) $stat['size'] : 0;

            // Répertoires, chemins remontants et chemins absolus : ignorés.
            if (substr($nom, -1) === '/' || strpos($nom, '..') !== false || substr($nom, 0, 1) === '/') {
                continue;
            }

            if (strtolower(basename($nom)) === self::NOM_JSON_DANS_ZIP) {
                if ($taille > self::MAX_OCTETS_JSON) {
                    $zip->close();
                    return self::echec('Le relevé contenu dans l\'archive est anormalement volumineux.');
                }
                $json = $zip->getFromIndex($i);
                continue;
            }

            if (strpos($nom, 'photos/') !== 0) {
                continue;
            }
            if ($taille > self::MAX_OCTETS_PHOTO || count($photos) >= self::MAX_PHOTOS) {
                continue;
            }
            $extension = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
            if (!in_array($extension, self::$extensionsImage)) {
                continue;
            }

            $contenu = $zip->getFromIndex($i);
            if ($contenu !== false && $contenu !== '') {
                $photos[$nom] = $contenu;
            }
        }

        $zip->close();

        if ($json === null || $json === false) {
            return self::echec(
                "L'archive ne contient pas de fichier " . self::NOM_JSON_DANS_ZIP . '.'
            );
        }

        return array('ok' => true, 'erreur' => '', 'json' => $json, 'photos' => $photos);
    }

    private static function echec($message)
    {
        return array(
            'ok' => false,
            'erreur' => $message,
            'code_requis' => false,
            'document' => array(),
            'photos' => array(),
            'protege' => false,
        );
    }
}
