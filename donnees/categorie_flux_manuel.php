<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class CategorieFluxManuel extends Manager
{
    public $id;
    public $nom;
    public $type_flux; // 'recette' ou 'charge'
    public $description;
    public $id_aep; // NULL pour global, ou ID spécifique d'un AEP
    public $est_actif;
    public $code_budgetaire; // Code budgétaire de la catégorie
    public $activite_associee; // 'branchements', 'vente_eau', ou 'autre'
    public $date_creation;

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        $donnees = array(
            'nom' => $this->nom,
            'type_flux' => $this->type_flux,
            'description' => $this->description,
            'id_aep' => $this->id_aep,
            'est_actif' => isset($this->est_actif) ? $this->est_actif : 1,
            'code_budgetaire' => isset($this->code_budgetaire) ? $this->code_budgetaire : null,
            'activite_associee' => isset($this->activite_associee) ? $this->activite_associee : 'autre'
        );
        
        // Ajouter date_creation seulement lors de la création
        if (empty($this->id)) {
            $donnees['date_creation'] = date('Y-m-d H:i:s');
        }
        
        return $donnees;
    }

    function getNomTable()
    {
        return "categorie_flux_manuel";
    }

    /**
     * Récupère toutes les catégories actives
     */
    public static function getAllActives($type_flux = null, $id_aep = null)
    {
        $query = "SELECT * FROM categorie_flux_manuel WHERE est_actif = 1";
        $params = array();
        
        if ($type_flux !== null) {
            $query .= " AND type_flux = ?";
            $params[] = $type_flux;
        }
        
        if ($id_aep !== null) {
            $query .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = $id_aep;
        } else {
            $query .= " AND id_aep IS NULL";
        }
        
        $query .= " ORDER BY type_flux, nom";
        
        return self::prepare_query($query, $params);
    }

    /**
     * Récupère toutes les catégories (actives et inactives)
     */
    public static function getAll($type_flux = null, $id_aep = null)
    {
        $query = "SELECT * FROM categorie_flux_manuel WHERE 1=1";
        $params = array();
        
        if ($type_flux !== null) {
            $query .= " AND type_flux = ?";
            $params[] = $type_flux;
        }
        
        if ($id_aep !== null) {
            $query .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = $id_aep;
        } else {
            $query .= " AND id_aep IS NULL";
        }
        
        $query .= " ORDER BY est_actif DESC, type_flux, nom";
        
        return self::prepare_query($query, $params);
    }

    /**
     * Récupère une catégorie par son ID
     */
    public static function getById($id)
    {
        return self::prepare_query("SELECT * FROM categorie_flux_manuel WHERE id = ?", array($id))->fetch();
    }
}
