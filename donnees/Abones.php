<?php

//require_once("manager.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/compteur.php");
@include_once("donnees/compteur.php");




class Abones extends Manager
{

    //public $id;
    public $nom;
    public $numero_compteur;
    public $numero_telephone;
    public $numero_compte_anticipation;
    public $id_reseau;
    public $etat;
    public $rang;
    public $derniers_index;
    public $compteur;
    public $type_compteur;

    /**
     * @throws Exception
     */
    public static function getRecouvrementData($id_compteur, $id_aep){
        return self::prepare_query("
        select f.id id_facture, m.mois, montant_verse, id.id as id,  nouvel_index, ancien_index, penalite, SUM(i.montant) as impaye ,
               prix_tva, prix_entretient_compteur, prix_metre_cube_eau, SUM(impe.montant) as impaye2
            from abone a
            inner join facture f on a.id = f.id_abone
            left join indexes id on f.id_indexes = id.id
            inner join mois_facturation m on id.id_mois_facturation = m.id
            left join impaye impe on impe.id_facture = f.id
            left join impaye i on i.id_facture in (select f2.id from facture f2 
                                                       left join indexes id2 on f2.id_indexes = id2.id 
                                                        inner join mois_facturation m2 on id2.id_mois_facturation=m2.id 
                                                    where m2.mois < m.mois and id_abone = a.id)
            inner join constante_reseau c on m.id_constante = c.id
            where id.id_compteur = ? and c.id_aep=? 
            group by m.id
            order by mois desc;
        ", array($id_compteur, $id_aep));
        "select *
            from abone a
            inner join facture f on a.id = f.id_abone
            inner join indexes id on f.id_indexes = id.id
            inner join mois_facturation m on id.id_mois_facturation = m.id
            left join impaye i on i.id_facture in (select f2.id from facture f2 inner join indexes id2 on f2.id_indexes = id2.id inner join mois_facturation m2 on id2.id_mois_facturation=m2.id where m2.mois < m.mois and id_abone = a.id)
            inner join constante_reseau c on m.id_constante = c.id
            where id.id_compteur = 1 order by mois desc;";
    }


    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }
    function getDonnee()
    {
        return array(
            'nom' => $this->nom
            ,
//            'numero_compteur' => $this->numero_compteur
//            ,
            'numero_telephone' => $this->numero_telephone
            ,
            'numero_compte_anticipation' => $this->numero_compte_anticipation
            ,
            'etat' => $this->etat
            ,
            'rang' => $this->rang
            ,
            'id_reseau' => $this->id_reseau
//            ,
//            'type_compteur' => $this->type_compteur
//            ,
//            'derniers_index' => $this->derniers_index
        );
    }

    public function save_abone ()
    {
        try{
            $this->ajouter();
            $this->compteur->ajouter();
            self::prepare_query("insert into compteur_abone(id_abone, id_compteur) values(?, ?);", array($this->id, $this->compteur->id));
        }catch (Exception $e){
            var_dump($e->getMessage());
            return 0;
        }
        return 1;


    }

    function getNomTable()
    {
        return "abone";
    }
    public function __construct($id, $nom, $numero_compteur, $numero_telephone, $numero_compte_anticipation, $etat, $rang, $id_reseau, $derniers_index = 0, $type_compteur = 'distribution')
    {
        $this->id = $id;
        $this->nom = $nom;
        $this->numero_compteur = $numero_compteur;
        $this->numero_telephone = $numero_telephone;
        $this->numero_compte_anticipation = $numero_compte_anticipation;
        $this->etat = $etat;
        $this->rang = $rang;
        $this->id_reseau = $id_reseau;
        $this->derniers_index = $derniers_index;
        $this->type_compteur = $type_compteur;

        $this->compteur = new Compteur('', $numero_compteur , 0.0, $latitude = 0.0, $derniers_index, '' );

    }

    public static function getSimpleAbone($id_aep=0)
    {
        return self::prepare_query("select a.id id, a.nom nom, r.nom reseau, etat, derniers_index,  
                            numero_telephone, numero_compteur, 'distribution' as type_compteur 
                    from abone a
                         inner join reseau r on a.id_reseau = r.id
                        inner join compteur_abone c_ab on c_ab.id_abone = a.id
                        inner join compteur c on c.id = c_ab.id_compteur
                    where r.id_aep=?
                    order by id"
            , array($id_aep));
    }
    public static function getSimpleCompteurReseau($id_aep=0)
    {
        return self::prepare_query("select r.id id, r.nom nom, r.nom reseau, 'actif' etat, derniers_index,  
                            '00000000' numero_telephone,  numero_compteur, 'production' as type_compteur 
                    from  reseau r
                        inner join compteur_reseau c_r on c_r.id_reseau = r.id
                        inner join compteur c on c.id = c_r.id_compteur
                    where r.id_aep=?
                    order by id"
            , array($id_aep));
    }

    public static function getLastmonthIndex($id_aep)
    {
        //recuperation des compteurs d'abonés
        $res = self::prepare_query("
                            select a.id, co.id as id_compteur,  a.nom as libele, numero_compteur as numero, numero_telephone as numero_abone, 
                                   derniers_index as ancien_index, 0.0 as nouvel_index,
                                    r.nom as reseau, 0.0 as latitude, 0.0 as longitude, Date_format(now(), '%d/%m/%y') as date_releve 
                            from abone a
                                inner join compteur_abone c_ab on c_ab.id_abone = a.id
                                inner join compteur co on co.id = c_ab.id_compteur
                                 inner join reseau r on a.id_reseau = r.id 
                            where r.id_aep=? and a.etat='actif';", array($id_aep));
        //recuperation des compteurs de secteur
        $res2 = self::prepare_query("
                            select r.id, co.id as id_compteur, concat('compteur ', r.nom ) as libele, numero_compteur as numero, '0000000000' as numero_abone, 
                                   derniers_index as ancien_index, 0.0 as nouvel_index,
                                    r.nom as reseau, 0.0 as latitude, 0.0 as longitude, Date_format(now(), '%d/%m/%y') as date_releve 
                            from reseau r
                                inner join compteur_reseau c_r on c_r.id_reseau = r.id
                                inner join compteur co on co.id = c_r.id_compteur
                            where r.id_aep=? ;", array($id_aep));
//        $res = $res->fetchAll(PDO::FETCH_ASSOC);
        //recuperation des compteurs de l'aep
        $res3 = self::prepare_query("
                            select a.id, co.id as id_compteur,  a.nom as libele, numero_compteur as numero, numero_telephone as numero_abone, 
                                   derniers_index as ancien_index, 0.0 as nouvel_index,
                                    r.nom as reseau, 0.0 as latitude, 0.0 as longitude, Date_format(now(), '%d/%m/%y') as date_releve 
                            from abone a
                                inner join compteur_abone c_ab on c_ab.id_abone = a.id
                                inner join compteur co on co.id = c_ab.id_compteur
                                 inner join reseau r on a.id_reseau = r.id 
                            where r.id_aep=? and a.etat='actif';", array($id_aep));


        $tab = array();
        $tab = self::addResponseToTabAsJson($res, $tab);
        $tab = self::addResponseToTabAsJson($res2, $tab);
//        $tab = self::addResponseToTabAsJson($res3, $tab);
//        exit();
        return $tab;

    }


    public static function addResponseToTabAsJson($response, $tab){
//        var_dump($response);
        $res = $response->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $row) {
            $tab[] = array(
                'id' => (int)$row['id']
            ,
                'id_compteur' => $row['id_compteur']
            ,
                'libele' => $row['libele']
            ,
                'numero' => $row['numero']
            ,
                'numero_abone' => $row['numero_abone']
            ,
                'ancien_index' => (float)$row['ancien_index']
            ,
                'nouvel_index' => (float)$row['nouvel_index']
            ,
                'reseau' => $row['reseau']
            ,
                'latitude' => (float)$row['latitude']
            ,
                'longitude' => (float)$row['longitude']
            ,
                'date_releve' => $row['date_releve']
            );
        }
        return $tab;
    }

    public static function writeToFile($fileName, $content)
    {
        return file_put_contents($fileName, $content);
    }

    public static function updateIndex($id, $index)
    {
        var_dump(44);
        $res = false;
        if (is_float($index))
        $res = self::query("update abone a
                            inner join compteur_abone c_ab on c_ab.id_abone = a.id
                            inner join compteur co on co.id = c_ab.id_compteur    
                            set derniers_index=$index where a.id=$id");
        return $res;
    }
    public static function updateIndexByCompteur_id($id_compteur, $index)
    {
        var_dump(44);
        $res = false;
        if (is_float($index))
        $res = self::prepare_query("update compteur    
                            set derniers_index=? where id=?", array($index, $id_compteur));
        return $res;
    }
    public static function getAllAboneInfoByid($id)
    {
        $res = false;
        if (is_int($id))
            $res = self::query("
            select a.nom, c.id as id_compteur, count(f.id) duree, r.nom reseau, numero_telephone, r.id id_reseau, sum(nouvel_index - ancien_index)
                    consommation, derniers_index, sum(i.montant) as impaye,  numero_compteur, sum(montant_verse) montant_verse, 
                    max(date_paiement)date_paiement, a.etat , 'distribution' as type_compteur
                from abone a
                    inner join compteur_abone c_ab on c_ab.id_abone = a.id
                    inner join compteur c on c.id = c_ab.id_compteur
                     left join facture f on a.id = f.id_abone
                    left join indexes id on f.id_indexes = id.id
                     left join impaye i on i.id_facture = f.id 
                     inner join reseau r on r.id = a.id_reseau 
                where a.id=$id;
            ");
        return $res;
    }

    public static function getAboneBy($id_reseau, $nom, $numero){
        echo "select * from abone where id_reseau=$id_reseau and nom='$nom' and numero_telephone='$numero' limit 1";
        $res = self::query("select * from abone where id_reseau=$id_reseau and nom='$nom' and numero_telephone='$numero' limit 1");
        $data = $res->fetchAll();
        var_dump($data);
        if(count($data) == 0)
            return null;
        return $data[0];
    }

    public function getAboneIdBy(){
        $res = Abones::getAboneBy($this->id_reseau, $this->nom, $this->numero_telephone);
        if($res != null)
            $this->id = $res['id'];
    }

    public static function deleteAbone($id_delete)
    {
        if(is_int($id_delete)) {
            $res =  self::prepare_query("
                
                DELETE FROM compteur_abone 
                WHERE id_abone = ?;

                -- Supprimer le compteur associé (si nécessaire)
                DELETE FROM compteur 
                WHERE id IN (SELECT id_compteur FROM compteur_abone WHERE id_abone = ?);
                
                
                -- Supprimer l'abonné
                DELETE FROM abone 
                WHERE id = ?;

        ", array($id_delete, $id_delete, $id_delete));//delete from abone where id=$id_delete
            return $res == false? false: true;
        }
        return false;
    }


    public static function updateSingleValue($id_abone, $key, $value ){
        try{
            if(self::$bd == null)
                self::$bd = Connexion::connect();
            $req = self::$bd->prepare("update abone a
                        inner join compteur_abone c_ab on c_ab.id_abone = a.id
                        inner join compteur c on c.id = c_ab.id_compteur
                        set $key=? where a.id=?");
            $req->execute(array( $value, $id_abone));
            return $req;
        }catch(Exception $error){
            return $error;
        }
        //rue;
    }

    public static function telecharger($fileName)
    {
        $file = $fileName;
        $baseName = basename($file);
        // Vérifiez si le fichier existe
        if (file_exists($file)) {
            // Définir les en-têtes pour le téléchargement
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));

            // Lire le fichier et le transmettre au navigateur
            readfile($file);

            exit;
        } else {
            echo "Le fichier n'existe pas.";
        }
    }
}
/*
$abone = new Abones(0, "Tsafack", "Erick", "654190514", 'Sercive', 21);
//var_dump($abone);
echo 'Erick Tsafack <br>';
//$abone->ajouterXml2();
$abone->ajouter();
var_dump($abone);
$abone->etat = 'actif';
$abone->update();*/
