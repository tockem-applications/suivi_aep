<?php

//require_once("manager.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

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
    public $type_compteur;

    public static function getRecouvrementData($id_abone){
        return self::prepare_query("
        select m.mois, montant_verse, f.id as id,  nouvel_index, ancien_index, penalite, 0 as impaye, prix_tva, prix_entretient_compteur, prix_metre_cube_eau
            from abone a
            inner join facture f on a.id = f.id_abone
            inner join mois_facturation m on f.id_mois_facturation = m.id
            #left join impaye i on i.id_facture in (select f2.id from facture f2 inner join mois_facturation m2 on f2.id_mois_facturation=m2.id where m2.mois < m.mois and id_abone = a.id)
            inner join constante_reseau c on m.id_constante = c.id
            where a.id = ? order by mois desc;
        ", array($id_abone,));
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
            'numero_compteur' => $this->numero_compteur
            ,
            'numero_telephone' => $this->numero_telephone
            ,
            'numero_compte_anticipation' => $this->numero_compte_anticipation
            ,
            'etat' => $this->etat
            ,
            'rang' => $this->rang
            ,
            'id_reseau' => $this->id_reseau
            ,
            'type_compteur' => $this->type_compteur
            ,
            'derniers_index' => $this->derniers_index
        );
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
    }

    public static function getSimpleAbone($type_compteur = '', $id_aep=0)
    {
        return self::prepare_query("select a.id id, a.nom nom, r.nom reseau, etat, derniers_index,  numero_telephone, numero_compteur, type_compteur 
                    from abone a, reseau r 
                    where a.id_reseau = r.id and type_compteur like concat('%', ?) and r.id_aep=?
                    order by type_compteur desc, id"
            , array($type_compteur, $id_aep));
    }

    public static function getLastmonthIndex($id_aep)
    {
        $res = self::prepare_query("
                            select a.id,  a.nom as libele, numero_compteur as numero, numero_telephone as numero_abone, 
                                   derniers_index as ancien_index, 0.0 as nouvel_index,
                                    r.nom as reseau, 0.0 as latitude, 0.0 as longitude, Date_format(now(), '%d/%m/%y') as date_releve 
                            from abone a
                                 inner join reseau r on a.id_reseau = r.id 
                            where r.id_aep=? and a.etat='actif';", array($id_aep));
        $res = $res->fetchAll(PDO::FETCH_ASSOC);
        $tab = array();
        foreach ($res as $row) {
            $tab[] = array(
                'id' => (int)$row['id']
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
        $res = false;
        if (is_float($index))
            $res = self::query("update abone set derniers_index=$index where id=$id");
        return $res;
    }
    public static function getAllAboneInfoByid($id)
    {
        $res = false;
        if (is_int($id))
            $res = self::query("
            select a.nom, count(f.id) duree, r.nom reseau, numero_telephone, r.id id_reseau, sum(nouvel_index - ancien_index)
                    consommation, derniers_index, sum(i.montant) as impaye,  numero_compteur, sum(montant_verse) montant_verse, 
                    max(date_paiement)date_paiement, a.etat , type_compteur
                from abone a
                     inner join facture f on a.id = f.id_abone
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
            $res =  self::query("delete from abone where id=$id_delete");
            return $res == false? false: true;
        }
        return false;
    }


    public static function updateSingleValue($id_abone, $key, $value ){
        try{
            if(self::$bd == null)
                self::$bd = Connexion::connect();
            $req = self::$bd->prepare("update abone set $key=? where id=?");
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
