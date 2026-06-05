<?php

require_once __DIR__ . '/db_config.php';

$dbDefaults = db_config_array();

function connexion()
{
    try {
        $bdd = new PDO('mysql:host=mysql-126762-0.cloudclusters.net;dbname=immo', 'root', '');
        //        $bdd = new PDO('mysql:host=localhost;dbname=rapelle', 'root', '');
        return $bdd;
    } catch (Exception $e) {
        die('Erreur : ' . $e->getMessage());
    }

}

class Connexion
{
    static public $db_name;
    static public $db_user;
    static public $db_host;
    static public $db_password;
    static public $db_port;

    public static function initConfig()
    {
        global $dbDefaults;
        $dbDefaults = db_config_array();
        self::$db_host     = $dbDefaults['db_host'];
        self::$db_name     = $dbDefaults['db_name'];
        self::$db_user     = $dbDefaults['db_user'];
        self::$db_password = $dbDefaults['db_password'];
        self::$db_port     = isset($dbDefaults['db_port']) ? $dbDefaults['db_port'] : '3306';
        if (function_exists('db_config_is_docker') && db_config_is_docker()) {
            if (self::$db_host === 'localhost' || self::$db_host === '127.0.0.1') {
                self::$db_host = 'db';
            }
        }
    }

    public static function resetConnection()
    {
        self::$bdd = null;
    }

    static private $bdd = null;
    public static function connect()
    {
        self::initConfig();
        if (self::$bdd != null) {
            return self::$bdd;
        }
        try {
            $dsn = 'mysql:host=' . self::$db_host
                . ';port=' . (int) self::$db_port
                . ';dbname=' . self::$db_name
                . ';charset=utf8';
            $bdd = new PDO(
                $dsn,
                self::$db_user,
                self::$db_password,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
            //            $bdd = new PDO('mysql:host=mysql-126762-0.cloudclusters.net;port=10015;dbname=immo', 'admin', 'vJ69WFrJ');
            $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $bdd->exec("SET NAMES utf8");
            $bdd->exec("SET CHARACTER SET utf8");
            $bdd->exec("SET COLLATION_CONNECTION = 'utf8_general_ci'");
            return $bdd;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    public static function connectXml()
    {
        try {
            //$bd = simplexml_load_file('../donnees/bd.tld');
            $bd = simplexml_load_file('donnees/bd.tld');
            return $bd;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
}

Connexion::initConfig();
