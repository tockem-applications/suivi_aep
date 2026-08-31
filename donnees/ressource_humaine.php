<?php

@include_once("manager.php");

class RessourceHumaine extends Manager
{
    public $aep_id;
    public $nom;
    public $fonction;
    public $competences;
    public $telephone;
    public $statut;
    public $cout_horaire;
    public $actif;

    public function __construct($id = 0, $aep_id = 0, $nom = '', $fonction = '', $competences = '', $telephone = '', $statut = 'disponible', $cout_horaire = 0, $actif = 1)
    {
        parent::__construct();
        $this->id = $id;
        $this->aep_id = $aep_id;
        $this->nom = $nom;
        $this->fonction = $fonction;
        $this->competences = $competences;
        $this->telephone = $telephone;
        $this->statut = $statut;
        $this->cout_horaire = $cout_horaire;
        $this->actif = $actif;
    }

    public function getDonnee()
    {
        return array(
            'aep_id' => $this->aep_id,
            'nom' => $this->nom,
            'fonction' => $this->fonction,
            'competences' => $this->competences,
            'telephone' => $this->telephone,
            'statut' => $this->statut,
            'cout_horaire' => $this->cout_horaire,
            'actif' => $this->actif
        );
    }

    public function getNomTable()
    {
        return 'ressources_humaines';
    }

    public function getconstraint()
    {
        return array('column' => 'id', 'value' => $this->id);
    }

    public static function getAllByAep($aep_id)
    {
        return self::prepare_query('SELECT * FROM ressources_humaines WHERE aep_id = ? ORDER BY nom ASC', array($aep_id));
    }

    public static function getOneById($id)
    {
        return self::getOne($id, 'ressources_humaines');
    }
}


