<?php
/**
 * Configuration base de données : variables d'environnement (Docker),
 * fichier optionnel config.local.php (WAMP), puis valeurs par défaut.
 */
function db_config_is_docker()
{
    return file_exists('/.dockerenv');
}

/**
 * Hôte PDO : "localhost" sous Linux utilise le socket Unix, pas le service Docker "db".
 * @param string $host
 * @return string
 */
function db_config_pdo_host($host)
{
    $host = trim((string) $host);
    if ($host === 'localhost' && db_config_is_docker()) {
        $env = getenv('DB_HOST');
        if ($env !== false && $env !== '') {
            return $env;
        }
        return 'db';
    }
    if ($host === 'localhost') {
        return '127.0.0.1';
    }
    return $host;
}

function db_config($key, $default = '')
{
    static $local = null;

    $envMap = array(
        'db_host'     => 'DB_HOST',
        'db_name'     => 'DB_NAME',
        'db_user'     => 'DB_USER',
        'db_password' => 'DB_PASSWORD',
        'db_port'     => 'DB_PORT',
    );

    if (isset($envMap[$key])) {
        $envKey = $envMap[$key];
        $env = getenv($envKey);
        if ($env !== false && $env !== '') {
            return $env;
        }
        if (isset($_ENV[$envKey]) && $_ENV[$envKey] !== '') {
            return $_ENV[$envKey];
        }
        if (isset($_SERVER[$envKey]) && $_SERVER[$envKey] !== '') {
            return $_SERVER[$envKey];
        }
    }

    // Dans Docker, ne pas appliquer config.local.php WAMP (souvent localhost)
    if (db_config_is_docker() && strpos($key, 'db_') === 0) {
        $dockerDefaults = array(
            'db_host'     => 'db',
            'db_name'     => 'suivi_aep_fokoue',
            'db_user'     => 'suivi',
            'db_password' => 'suivi',
            'db_port'     => '3306',
        );
        if (isset($dockerDefaults[$key])) {
            return $dockerDefaults[$key];
        }
    }

    if ($local === null) {
        $localFile = __DIR__ . '/config.local.php';
        if (file_exists($localFile)) {
            $loaded = include $localFile;
            $local = is_array($loaded) ? $loaded : array();
        } else {
            $local = array();
        }
    }

    if (isset($local[$key]) && $local[$key] !== '') {
        return $local[$key];
    }

    return $default;
}

function db_config_array()
{
    $host = db_config('db_host', db_config_is_docker() ? 'db' : 'localhost');
    return array(
        'db_host'     => db_config_pdo_host($host),
        'db_name'     => db_config('db_name', 'suivi_aep_fokoue'),
        'db_user'     => db_config('db_user', db_config_is_docker() ? 'suivi' : 'root'),
        'db_password' => db_config('db_password', db_config_is_docker() ? 'suivi' : ''),
        'db_port'     => db_config('db_port', '3306'),
    );
}
