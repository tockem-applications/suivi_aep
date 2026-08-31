<?php

@include_once("manager.php");

class Intervention extends Manager
{
    public $aep_id;
    public $titre;
    public $type;
    public $description;
    public $localisation;
    public $date_debut_prevue;
    public $date_fin_prevue;
    public $date_debut_reelle;
    public $date_fin_reelle;
    public $statut;
    public $cout_estime;
    public $cout_reel;
    public $created_by;

    public function __construct($id = 0, $aep_id = 0, $titre = '', $type = '', $description = '', $localisation = '', $date_debut_prevue = null, $date_fin_prevue = null, $date_debut_reelle = null, $date_fin_reelle = null, $statut = 'planifiee', $cout_estime = 0, $cout_reel = 0, $created_by = null)
    {
        parent::__construct();
        $this->id = $id;
        $this->aep_id = $aep_id;
        $this->titre = $titre;
        $this->type = $type;
        $this->description = $description;
        $this->localisation = $localisation;
        $this->date_debut_prevue = $date_debut_prevue;
        $this->date_fin_prevue = $date_fin_prevue;
        $this->date_debut_reelle = $date_debut_reelle;
        $this->date_fin_reelle = $date_fin_reelle;
        $this->statut = $statut;
        $this->cout_estime = $cout_estime;
        $this->cout_reel = $cout_reel;
        $this->created_by = $created_by;
    }

    public function getDonnee()
    {
        return array(
            'aep_id' => $this->aep_id,
            'titre' => $this->titre,
            'type' => $this->type,
            'description' => $this->description,
            'localisation' => $this->localisation,
            'date_debut_prevue' => $this->date_debut_prevue,
            'date_fin_prevue' => $this->date_fin_prevue,
            'date_debut_reelle' => $this->date_debut_reelle,
            'date_fin_reelle' => $this->date_fin_reelle,
            'statut' => $this->statut,
            'cout_estime' => $this->cout_estime,
            'cout_reel' => $this->cout_reel,
            'created_by' => $this->created_by
        );
    }

    public function getNomTable()
    {
        return 'interventions';
    }

    public function getconstraint()
    {
        return array('column' => 'id', 'value' => $this->id);
    }

    public static function getAllByAep($aep_id)
    {
        return self::prepare_query('SELECT * FROM interventions WHERE aep_id = ? ORDER BY created_at DESC', array($aep_id));
    }

    public static function getOneById($id)
    {
        return self::getOne($id, 'interventions');
    }
}


