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
    static public $db_user = 'root';
    static public $db_host = 'localhost';
    static public $db_password = '';
    static private $bdd = null;
    public static function connect(){
        if(self::$bdd != null)
            return self::$bdd;
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
    