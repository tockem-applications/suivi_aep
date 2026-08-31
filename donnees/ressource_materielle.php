<?php

@include_once("manager.php");

class RessourceMaterielle extends Manager
{
    public $aep_id;
    public $libelle;
    public $categorie;
    public $reference;
    public $quantite_totale;
    public $quantite_disponible;
    public $unite;
    public $cout_unitaire;
    public $statut;
    public $actif;

    public function __construct($id = 0, $aep_id = 0, $libelle = '', $categorie = '', $reference = '', $quantite_totale = 0, $quantite_disponible = 0, $unite = 'u', $cout_unitaire = 0, $statut = 'disponible', $actif = 1)
    {
        parent::__construct();
        $this->id = $id;
        $this->aep_id = $aep_id;
        $this->libelle = $libelle;
        $this->categorie = $categorie;
        $this->reference = $reference;
        $this->quantite_totale = $quantite_totale;
        $this->quantite_disponible = $quantite_disponible;
        $this->unite = $unite;
        $this->cout_unitaire = $cout_unitaire;
        $this->statut = $statut;
        $this->actif = $actif;
    }

    public function getDonnee()
    {
        return array(
            'aep_id' => $this->aep_id,
            'libelle' => $this->libelle,
            'categorie' => $this->categorie,
            'reference' => $this->reference,
            'quantite_totale' => $this->quantite_totale,
            'quantite_disponible' => $this->quantite_disponible,
            'unite' => $this->unite,
            'cout_unitaire' => $this->cout_unitaire,
            'statut' => $this->statut,
            'actif' => $this->actif
        );
    }

    public function getNomTable()
    {
        return 'ressources_materielles';
    }

    public function getconstraint()
    {
        return array('column' => 'id', 'value' => $this->id);
    }

    public static function getAllByAep($aep_id)
    {
        return self::prepare_query('SELECT * FROM ressources_materielles WHERE aep_id = ? ORDER BY libelle ASC', array($aep_id));
    }

    public static function getOneById($id)
    {
        return self::getOne($id, 'ressources_materielles');
    }
}


