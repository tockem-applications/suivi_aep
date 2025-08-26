

<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Aep extends Manager
{

    public $libele;
    public $date;
    public $fichier_facture;
    public $description;
    public $numero_compte;
    public $nom_banque;

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
            'numero_compte'=> $this->numero_compte,
            'nom_banque'=> $this->nom_banque,
            'description' => $this->description
        );
    }

    function getNomTable()
    {
        return "aep";
    }

    public function __construct($id, $libele, $fichier_facture, $date, $description, $nom_banque, $numero_compte)
    {
        $this->id = $id;
        $this->libele = $libele;
        $this->date = $date;
        $this->description = $description;
        $this->nom_banque = $nom_banque;
        $this->numero_compte = $numero_compte;
        $this->fichier_facture = $fichier_facture;
    }
}
