<?php
/**
 * Configuration base de données : variables d'environnement (Docker),
 * fichier optionnel config.local.php (WAMP), puis valeurs par défaut.
 */
function db_config($key, $default = '')
{
    static $local = null;

    $envMap = array(
        'db_host'     => 'DB_HOST',
        'db_name'     => 'DB_NAME',
        'db_user'     => 'DB_USER',
        'db_password' => 'DB_PASSWORD',
    );

    if (isset($envMap[$key])) {
        $env = getenv($envMap[$key]);
        if ($env !== false && $env !== '') {
            return $env;
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
    return array(
        'db_host'     => db_config('db_host', 'localhost'),
        'db_name'     => db_config('db_name', 'suivi_aep_fokoue'),
        'db_user'     => db_config('db_user', 'root'),
        'db_password' => db_config('db_password', ''),
    );
}
