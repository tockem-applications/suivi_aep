<?php

@include_once(__DIR__ . '/licence_crypto.php');

/**
 * Lecture et validation du fichier licence signé (active.lic).
 */
class AppLicence
{
    /** @var AppLicence */
    private static $instance = null;

    /** @var array */
    private $data = array();

    /** @var bool */
    private $loaded = false;

    /** @var bool */
    private $signatureOk = false;

    /** @var string */
    private $sourceFile = '';

    /** @var string */
    private $lastError = '';

    /** Jours avant expiration pour alerte « bientôt » */
    const ALERTE_JOURS = 30;

    /**
     * @return AppLicence
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new AppLicence();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->reload();
    }

    /**
     * @return string
     */
    public static function getActiveLicencePath()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'licence' . DIRECTORY_SEPARATOR . 'active.lic';
    }

    /**
     * Recharge le fichier licence actif.
     */
    public function reload()
    {
        $this->data = array();
        $this->loaded = false;
        $this->signatureOk = false;
        $this->sourceFile = '';
        $this->lastError = '';

        $path = self::getActiveLicencePath();
        if (!is_readable($path)) {
            $this->lastError = 'Fichier licence absent.';
            return;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            $this->lastError = 'Fichier licence vide.';
            return;
        }

        if (!class_exists('LicenceCrypto')) {
            $this->lastError = 'Module cryptographique indisponible.';
            return;
        }

        $result = LicenceCrypto::verifyLicenceContent($raw);
        if (!$result['ok']) {
            $this->lastError = $result['error'];
            $this->sourceFile = $path;
            return;
        }

        $this->data = $result['payload'];
        $this->loaded = true;
        $this->signatureOk = true;
        $this->sourceFile = $path;
    }

    /**
     * @param string $sourcePath
     * @return array success, message
     */
    public function importFromPath($sourcePath)
    {
        if (!is_readable($sourcePath)) {
            return array('success' => false, 'message' => 'Fichier introuvable ou illisible.');
        }

        $raw = @file_get_contents($sourcePath);
        if ($raw === false || trim($raw) === '') {
            return array('success' => false, 'message' => 'Fichier licence vide.');
        }

        if (!class_exists('LicenceCrypto')) {
            return array('success' => false, 'message' => 'Module cryptographique indisponible.');
        }

        $result = LicenceCrypto::verifyLicenceContent($raw);
        if (!$result['ok']) {
            return array('success' => false, 'message' => $result['error']);
        }

        $dest = self::getActiveLicencePath();
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return array('success' => false, 'message' => 'Impossible de créer le dossier licence.');
        }

        if (@file_put_contents($dest, $raw) === false) {
            return array('success' => false, 'message' => 'Impossible d\'enregistrer la licence.');
        }

        $this->reload();
        if (!$this->isAccesAutorise()) {
            return array('success' => false, 'message' => 'Licence enregistrée mais expirée ou invalide.');
        }

        return array('success' => true, 'message' => 'Licence importée avec succès.');
    }

    public function isLoaded()
    {
        return $this->loaded;
    }

    public function isSignatureOk()
    {
        return $this->signatureOk;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getSourceBasename()
    {
        if ($this->sourceFile === '') {
            return '';
        }
        return basename($this->sourceFile);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = '')
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * @return array
     */
    public function getEditeur()
    {
        $e = $this->get('editeur', array());
        return is_array($e) ? $e : array();
    }

    /**
     * @return array
     */
    public function getClient()
    {
        $c = $this->get('client', array());
        return is_array($c) ? $c : array();
    }

    /**
     * @return array
     */
    public function getLogiciel()
    {
        $l = $this->get('logiciel', array());
        return is_array($l) ? $l : array();
    }

    public function getNumeroLicence()
    {
        return trim((string) $this->get('numero_licence', ''));
    }

    public function getTypeLicence()
    {
        return trim((string) $this->get('type_licence', ''));
    }

    public function getDateDebut()
    {
        return trim((string) $this->get('date_debut', ''));
    }

    public function getDateExpiration()
    {
        return trim((string) $this->get('date_expiration', ''));
    }

    public function getNotes()
    {
        return trim((string) $this->get('notes', ''));
    }

    /**
     * @return int|null timestamp fin de journée expiration
     */
    public function getExpirationTimestamp()
    {
        $d = $this->getDateExpiration();
        if ($d === '') {
            return null;
        }
        $ts = strtotime($d . ' 23:59:59');
        return $ts !== false ? (int) $ts : null;
    }

    /**
     * @return int|null jours restants (négatif si expiré)
     */
    public function getJoursRestants()
    {
        $exp = $this->getExpirationTimestamp();
        if ($exp === null) {
            return null;
        }
        $diff = $exp - time();
        return (int) floor($diff / 86400);
    }

    /**
     * ok | expire_bientot | expire | fichier_absent | signature_invalide
     * @return string
     */
    public function getStatut()
    {
        if (!$this->loaded) {
            if ($this->lastError !== '' && strpos($this->lastError, 'Signature') !== false) {
                return 'signature_invalide';
            }
            if ($this->sourceFile !== '' && is_readable($this->sourceFile)) {
                return 'signature_invalide';
            }
            return 'fichier_absent';
        }
        $exp = $this->getExpirationTimestamp();
        if ($exp === null) {
            return 'ok';
        }
        if ($exp < time()) {
            return 'expire';
        }
        $jours = $this->getJoursRestants();
        if ($jours !== null && $jours <= self::ALERTE_JOURS) {
            return 'expire_bientot';
        }
        return 'ok';
    }

    /**
     * Docker local sans extension openssl : autoriser l'app si LICENCE_DEV_MODE=1.
     * @return bool
     */
    private static function isLicenceDevBypass()
    {
        $v = getenv('LICENCE_DEV_MODE');
        if ($v === false || $v === '' || $v === '0') {
            return false;
        }
        return !function_exists('openssl_verify');
    }

    /**
     * Licence chargée, signée et non expirée (expire_bientot autorisé).
     * @return bool
     */
    public function isAccesAutorise()
    {
        if (self::isLicenceDevBypass()) {
            return true;
        }
        if (!$this->loaded || !$this->signatureOk) {
            return false;
        }
        $s = $this->getStatut();
        return ($s === 'ok' || $s === 'expire_bientot');
    }

    public function isValide()
    {
        return $this->isAccesAutorise();
    }

    /**
     * @return string
     */
    public function getStatutLibelle()
    {
        switch ($this->getStatut()) {
            case 'ok':
                return 'Licence active';
            case 'expire_bientot':
                $j = $this->getJoursRestants();
                return 'Licence expire bientôt' . ($j !== null ? ' (' . $j . ' j)' : '');
            case 'expire':
                return 'Licence expirée';
            case 'signature_invalide':
                return 'Licence non reconnue (signature invalide)';
            case 'fichier_absent':
            default:
                return 'Licence absente';
        }
    }

    /**
     * Classe Bootstrap pour badge statut.
     * @return string
     */
    public function getStatutBadgeClass()
    {
        switch ($this->getStatut()) {
            case 'ok':
                return 'bg-success';
            case 'expire_bientot':
                return 'bg-warning text-dark';
            case 'expire':
                return 'bg-danger';
            case 'signature_invalide':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }

    /**
     * Affichage d'une date YYYY-MM-DD en français simple.
     * @param string $ymd
     * @return string
     */
    public static function fmtDateFr($ymd)
    {
        $ymd = trim((string) $ymd);
        if ($ymd === '') {
            return '—';
        }
        $ts = strtotime($ymd);
        if ($ts === false) {
            return htmlspecialchars($ymd, ENT_QUOTES, 'UTF-8');
        }
        return date('d/m/Y', $ts);
    }
}

/**
 * @return AppLicence
 */
function app_licence()
{
    return AppLicence::getInstance();
}
