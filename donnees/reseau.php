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