<?php


function connexion(){
    try{
        $bdd = new PDO('mysql:host=mysql-126762-0.cloudclusters.net;dbname=immo', 'root', '');
//        $bdd = new PDO('mysql:host=localhost;dbname=rapelle', 'root', '');
        return $bdd;
    }
    catch (Exception $e){
        die('Erreur : ' . $e->getMessage());
    }
       
}

class Connexion{
    static public $db_name = 'suivi_aep_fokoue';
    static private $db_user = 'root';
    static private $db_host = 'localhost';
    static private $db_password = '';
    public static function connect(){
        try{
            $bdd = new PDO("mysql:host=".self::$db_host.";dbname=".self::$db_name, self::$db_user, self::$db_password, array(PDO::ATTR_PERSISTENT=>true));
//            $bdd = new PDO('mysql:host=mysql-126762-0.cloudclusters.net;port=10015;dbname=immo', 'admin', 'vJ69WFrJ');
            $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO:: ERRMODE_EXCEPTION);
            return $bdd;
        }
        catch (Exception $e){
            die('Erreur : ' . $e->getMessage());
        }
    }
    public static function connectXml(){
        try{
            //$bd = simplexml_load_file('../donnees/bd.tld');
            $bd = simplexml_load_file('donnees/bd.tld');
            return $bd;
        }
        catch (Exception $e){
            die('Erreur : ' . $e->getMessage());
        }
    }
}


Connexion::connect();
    