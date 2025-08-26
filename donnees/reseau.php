<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Reseau extends Manager
{

    //public $id;
    public $nom;
    public $id_aep;
    public $abreviation;
    public $date_creation;
    public $description_reseau;

    public static function getAllByIdReseau($id_aep)
    {
        return self::prepare_query("SELECT * FROM reseau WHERE id_aep = ?", array($id_aep));
    }

    public  function saveReseau($compteur)
    {
        try {
            $this->ajouter();
            if($compteur instanceof Compteur){
                $compteur->save_compteur_reseau($this->id);

            }
            return true;
        }catch (Exception $e){
            echo $e->getMessage();
            throw $e;
        }
        return false;
    }


    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array('nom' => $this->nom,
            'abreviation' => $this->abreviation,
            'date_creation' => $this->date_creation,
            'description_reseau' => $this->description_reseau,
            'id_aep' => $this->id_aep
        );
    }

    function getNomTable()
    {
        return "reseau";
    }

    public function deleteReseau()
    {
        try {
            $res = self::prepare_query("select id_compteur from compteur_reseau where id_reseau=?", array($this->id));
            $res->fetchAll();
            self::prepare_query("DELETE FROM compteur_reseau WHERE id_reseau = ?", array($this->id));
            self::prepare_query("DELETE FROM abone WHERE id_reseau = ?", array($this->id));
            foreach ($res as $ligne) {
                $id_compteur = $ligne['id_compteur'];
                self::prepare_query("delete from compteur where id =?", array($id_compteur));
            }
            self::prepare_query("DELETE FROM reseau WHERE id = ?", array($this->id));
            return true;
        }catch (Exception $e){
            echo $e->getMessage();
        }
        return false;
    }

    public function __construct($id, $nom, $abreviation, $date_creation, $description_reseau, $id_aep = 1)
    {
        $this->id = $id;
        $this->nom = $nom;
        $this->id_aep = $id_aep;
        $this->abreviation = $abreviation;
        $this->date_creation = $date_creation;
        $this->description_reseau = $description_reseau;

    }
}