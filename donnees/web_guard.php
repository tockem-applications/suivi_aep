<?php

/**
 * Garde web : licence, authentification, CSRF.
 */
class WebGuard
{
    /** @var string */
    private static $redirectPrefix = '';

    /** @var bool */
    private static $coreLoaded = false;

    /**
     * @param string $prefix ex. ../ ou ../../
     */
    public static function setRedirectPrefix($prefix)
    {
        self::$redirectPrefix = (string) $prefix;
    }

    /**
     * Requête HTTP directe sur un script du dossier traitement/.
     * @return bool
     */
    public static function isTraitementScriptRequest()
    {
        if (php_sapi_name() === 'cli') {
            return false;
        }
        $script = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
        if ($script === false) {
            return false;
        }
        $needle = DIRECTORY_SEPARATOR . 'traitement' . DIRECTORY_SEPARATOR;
        return strpos($script, $needle) !== false;
    }

    /**
     * @deprecated utiliser isTraitementScriptRequest()
     * @return bool
     */
    public static function isDirectHttpRequest()
    {
        return self::isTraitementScriptRequest();
    }

    public static function loadCore()
    {
        if (self::$coreLoaded) {
            return;
        }
        @include_once __DIR__ . '/manager.php';
        self::$coreLoaded = true;
    }

    /**
     * @param bool $jsonResponse
     */
    public static function enforceLicence($jsonResponse = false)
    {
        self::loadCore();
        if (!function_exists('app_licence')) {
            self::deny('Module licence indisponible.', $jsonResponse, 'licence');
            return;
        }
        if (!app_licence()->isAccesAutorise()) {
            self::deny('Licence requise.', $jsonResponse, 'licence');
        }
    }

    /**
     * @param bool $jsonResponse
     */
    public static function enforceAuth($jsonResponse = false)
    {
        self::loadCore();
        if (empty($_SESSION['user_id'])) {
            self::deny('Authentification requise.', $jsonResponse, 'login');
        }
    }

    /**
     * @param bool $jsonResponse
     */
    public static function enforceLicenceAndAuth($jsonResponse = false)
    {
        self::enforceLicence($jsonResponse);
        self::enforceAuth($jsonResponse);
    }

    /**
     * @param string $message
     * @param bool $jsonResponse
     * @param string $page login|licence
     */
    private static function deny($message, $jsonResponse, $page)
    {
        if ($jsonResponse) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
            }
            echo json_encode(array('ok' => false, 'error' => $message));
            exit;
        }
        $target = self::$redirectPrefix . 'index.php?page=' . $page;
        if ($page === 'login') {
            $target .= '&error=access_denied';
        }
        if (!headers_sent()) {
            header('Location: ' . $target);
        }
        exit;
    }
}

class Csrf
{
    const SESSION_KEY = '_csrf_token';

    /**
     * @return string
     */
    public static function token()
    {
        WebGuard::loadCore();
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = self::generateToken();
        }
        return (string) $_SESSION[self::SESSION_KEY];
    }

    /**
     * @return string
     */
    public static function generateToken()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(32);
            if ($bytes !== false) {
                return bin2hex($bytes);
            }
        }
        return sha1(uniqid((string) mt_rand(), true));
    }

    /**
     * @return string
     */
    public static function hiddenField()
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * @param string|null $token
     * @return bool
     */
    public static function validate($token = null)
    {
        WebGuard::loadCore();
        if ($token === null) {
            $token = isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : '';
        }
        if ($token === '' || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        return self::equals($token, (string) $_SESSION[self::SESSION_KEY]);
    }

    /**
     * @param string $message
     * @param bool $jsonResponse
     */
    public static function requireValid($message = 'Jeton CSRF invalide.', $jsonResponse = false)
    {
        if (self::validate()) {
            return;
        }
        if ($jsonResponse) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
            }
            echo json_encode(array('ok' => false, 'error' => $message));
            exit;
        }
        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
        }
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        exit;
    }

    /**
     * @param string $a
     * @param string $b
     * @return bool
     */
    private static function equals($a, $b)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        return $a === $b;
    }
}
