


<?php

//require_once("manager.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class ConstanteReseau extends Manager{

    //public $id;
    public $prix_metre_cube_eau;
    public $prix_entretient_compteur;
    public $prix_tva;
    public $date_creation;
    public $est_actif;
    public $description;
    public $id_aep;


    function getconstraint(){
        return array('value' => $this->id, 'column' => 'id');
    }
    function getDonnee(){
        return array(
            'prix_metre_cube_eau' => $this->prix_metre_cube_eau
            , 'prix_entretient_compteur' => $this->prix_entretient_compteur
            , 'prix_tva' => $this->prix_tva
            , 'date_creation' => $this->date_creation
            , 'est_actif'=> $this->est_actif
            , 'id_aep'=> $this->id_aep
            , 'description' => $this->description);
    }
    function getNomTable(){
        return "constante_reseau";
    }

    function ajouterEtActiver(){
        $res = 0;
        try{
            $this->est_actif = true;
            $this->connecter();
            self::$bd->beginTransaction();
            self::prepare_query("update constante_reseau set est_actif=false where est_actif=true and id_aep=?", array($this->id_aep));
            $res = $this->ajouter();
            if($res)
                self::$bd->commit();
            else
                self::$bd->rollBack();
        }catch(Exception $e){
            self::$bd->rollBack();
            $res = 0;
        }
        return $res;

    }
    public function __construct($id, $prix_metre_cube_eau, $prix_entretient_compteur, $prix_tva,  $date_creation , $est_actif, $description, $id_aep){
        $this->id = $id;
        $this->prix_metre_cube_eau = $prix_metre_cube_eau;
        $this->prix_entretient_compteur = $prix_entretient_compteur;
        $this->est_actif = $est_actif;
        $this->prix_tva = $prix_tva;
        $this->id_aep = $id_aep;
        $this->date_creation = $date_creation;
        $this->description = $description;
    }

    public static function getConstanteActive($id_aep){
        return Manager::prepare_query('select * from constante_reseau where est_actif=true and id_aep=?;' , array($id_aep));
    }
    public static function getIdConstanteActive($id_aep){
        $res = self::getConstanteActive($id_aep);
        $data = $res->fetchAll();
        var_dump( (int)$data[0]['id']);
        if($res->rowCount() == 0){
            return 0;
        }
        return (int)$data[0]['id'];
    }
}