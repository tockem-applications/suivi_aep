<?php
/**
 * Dépôt d'attente des imports de relevé.
 *
 * L'import se fait en deux temps : le responsable dépose le fichier, examine
 * ce qu'il contient, puis confirme. Entre les deux, le fichier décodé attend
 * ici — déjà déchiffré et désarchivé, pour ne pas redemander le code d'accès
 * ni faire retéléverser une archive de plusieurs mégaoctets.
 *
 * Chaque dépôt est identifié par un jeton aléatoire, rattaché à la session :
 * un autre utilisateur connecté ne peut pas confirmer un import qui n'est pas
 * le sien, même s'il devine le jeton.
 */
class ImportTemporaire
{
    /** Au-delà, un dépôt oublié est effacé au prochain passage. */
    const DUREE_VIE_SECONDES = 3600;

    /**
     * Range un document décodé et ses photos, et renvoie le jeton.
     *
     * @param array $document  le relevé, déjà déchiffré
     * @param array $photos    chemin relatif dans l'archive => contenu binaire
     * @param int   $id_mois
     * @return string|false le jeton, ou false si l'écriture a échoué
     */
    public static function deposer($document, $photos, $id_mois)
    {
        $dossier = self::dossier();
        if ($dossier === false) {
            return false;
        }
        self::purger();

        $jeton = self::jeton();
        $chemin = $dossier . '/' . $jeton;
        if (!@mkdir($chemin, 0777, true)) {
            return false;
        }

        // Les photos sont écrites à part : les garder en base64 dans le JSON
        // triplerait la taille d'un dépôt qui peut compter des centaines
        // d'images.
        $nomsPhotos = array();
        $rang = 0;
        foreach ($photos as $relatif => $contenu) {
            $rang++;
            $nom = 'p' . $rang . '.bin';
            if (@file_put_contents($chemin . '/' . $nom, $contenu) !== false) {
                $nomsPhotos[$relatif] = $nom;
            }
        }

        $enveloppe = array(
            'cree' => time(),
            'id_mois' => (int) $id_mois,
            'id_aep' => isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : null,
            'document' => $document,
            'photos' => $nomsPhotos,
        );
        if (@file_put_contents($chemin . '/depot.json', json_encode($enveloppe)) === false) {
            return false;
        }

        self::enregistrerDansSession($jeton);
        return $jeton;
    }

    /**
     * Relit un dépôt.
     *
     * @return array|false array('document', 'photos', 'id_mois') ou false
     */
    public static function reprendre($jeton)
    {
        if (!self::jetonValide($jeton) || !self::appartientALaSession($jeton)) {
            return false;
        }
        $chemin = self::dossier() . '/' . $jeton;
        $brut = @file_get_contents($chemin . '/depot.json');
        if ($brut === false) {
            return false;
        }
        $enveloppe = json_decode($brut, true);
        if (!is_array($enveloppe) || !isset($enveloppe['document'])) {
            return false;
        }
        // Un dépôt ne vaut que pour l'AEP qui l'a créé.
        if (isset($_SESSION['id_aep']) && $enveloppe['id_aep'] != $_SESSION['id_aep']) {
            return false;
        }
        if (time() - (int) $enveloppe['cree'] > self::DUREE_VIE_SECONDES) {
            self::supprimer($jeton);
            return false;
        }

        $photos = array();
        foreach ($enveloppe['photos'] as $relatif => $nom) {
            $contenu = @file_get_contents($chemin . '/' . basename($nom));
            if ($contenu !== false) {
                $photos[$relatif] = $contenu;
            }
        }

        return array(
            'document' => $enveloppe['document'],
            'photos' => $photos,
            'id_mois' => (int) $enveloppe['id_mois'],
        );
    }

    public static function supprimer($jeton)
    {
        if (!self::jetonValide($jeton)) {
            return;
        }
        self::effacerDossier(self::dossier() . '/' . $jeton);
        if (isset($_SESSION['imports_en_attente'][$jeton])) {
            unset($_SESSION['imports_en_attente'][$jeton]);
        }
    }

    // --- Interne -------------------------------------------------------------

    private static function dossier()
    {
        $dossier = __DIR__ . '/imports_temp';
        if (!is_dir($dossier) && !@mkdir($dossier, 0777, true)) {
            return false;
        }
        // Le dossier vit sous la racine web : on coupe toute exécution et tout
        // accès direct, son contenu venant d'un fichier reçu.
        $htaccess = $dossier . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "# Depot d'attente des imports : aucun acces direct.\n"
                . "Order Deny,Allow\nDeny from all\n"
                . "php_flag engine off\n"
            );
        }
        return $dossier;
    }

    private static function jeton()
    {
        if (function_exists('mcrypt_create_iv')) {
            $octets = @mcrypt_create_iv(16, MCRYPT_DEV_URANDOM);
            if ($octets !== false) {
                return bin2hex($octets);
            }
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $octets = @openssl_random_pseudo_bytes(16);
            if ($octets !== false) {
                return bin2hex($octets);
            }
        }
        return sha1(uniqid((string) mt_rand(), true));
    }

    /** Le jeton sert de nom de dossier : rien d'autre que de l'hexadécimal. */
    private static function jetonValide($jeton)
    {
        return is_string($jeton) && preg_match('/^[a-f0-9]{32,40}$/', $jeton) === 1;
    }

    private static function enregistrerDansSession($jeton)
    {
        if (!isset($_SESSION['imports_en_attente']) || !is_array($_SESSION['imports_en_attente'])) {
            $_SESSION['imports_en_attente'] = array();
        }
        $_SESSION['imports_en_attente'][$jeton] = time();
    }

    private static function appartientALaSession($jeton)
    {
        return isset($_SESSION['imports_en_attente'][$jeton]);
    }

    /** Efface les dépôts abandonnés : personne ne revient nettoyer à la main. */
    private static function purger()
    {
        $dossier = self::dossier();
        if ($dossier === false) {
            return;
        }
        $entrees = @scandir($dossier);
        if ($entrees === false) {
            return;
        }
        $limite = time() - self::DUREE_VIE_SECONDES;
        foreach ($entrees as $entree) {
            if ($entree === '.' || $entree === '..' || !self::jetonValide($entree)) {
                continue;
            }
            $depot = $dossier . '/' . $entree . '/depot.json';
            if (!file_exists($depot) || filemtime($depot) < $limite) {
                self::effacerDossier($dossier . '/' . $entree);
            }
        }
    }

    private static function effacerDossier($chemin)
    {
        if (!is_dir($chemin)) {
            return;
        }
        $entrees = @scandir($chemin);
        if ($entrees !== false) {
            foreach ($entrees as $entree) {
                if ($entree === '.' || $entree === '..') {
                    continue;
                }
                @unlink($chemin . '/' . $entree);
            }
        }
        @rmdir($chemin);
    }
}
