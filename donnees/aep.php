

<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Aep extends Manager
{

    public $libele;
    public $date;
    public $fichier_facture;
    public $description;

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'libele' => $this->libele,
            'fichier_facture' => $this->fichier_facture,
            'date'=> $this->date,
            'description' => $this->description
        );
    }

    function getNomTable()
    {
        return "aep";
    }

    public function __construct($id, $libele, $fichier_facture, $date, $description)
    {
        $this->id = $id;
        $this->libele = $libele;
        $this->date = $date;
        $this->description = $description;
        $this->fichier_facture = $fichier_facture;
    }
}
