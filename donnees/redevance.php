<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Redevance extends Manager
{
    public $id;
    public $libele;
    public $pourcentage;
    public $description;
    public $id_aep;

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'libele' => $this->libele,
            'pourcentage' => $this->pourcentage,
            'description' => $this->description,
            'id_aep' => $this->id_aep
        );
    }

    function getNomTable()
    {
        return "redevance";
    }

    public function __construct($id, $libele, $pourcentage, $description, $id_aep)
    {
        $this->id = $id;
        $this->libele = $libele;
        $this->pourcentage = $pourcentage;
        $this->description = $description;
        $this->id_aep = $id_aep;
    }

    // Méthode pour récupérer une redevance par ID
    public static function getRedevance($id)
    {
        $query = Manager::prepare_query(
            "SELECT * FROM redevance WHERE id = ?",
            array($id)
        );
        $data = $query->fetch();
        if ($data) {
            return new Redevance(
                $data['id'],
                $data['libele'],
                $data['pourcentage'],
                $data['description'],
                $data['id_aep']
            );
        }
        return null;
    }
}