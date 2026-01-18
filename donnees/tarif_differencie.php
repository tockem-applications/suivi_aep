<?php
/**
 * Classe pour gérer les tarifs différenciés
 * Les tarifs différenciés permettent d'appliquer des tarifs différents selon la consommation
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class TarifDifferencie extends Manager
{
    public $id;
    public $id_constante_reseau;
    public $prix_metre_cube_eau;
    public $prix_entretient_compteur;
    public $prix_tva;
    public $min_consommation;
    public $max_consommation;
    public $date_creation;
    public $description;

    public function __construct(
        $id = 0,
        $id_constante_reseau = 0,
        $prix_metre_cube_eau = 0,
        $prix_entretient_compteur = 0,
        $prix_tva = 0,
        $min_consommation = 0,
        $max_consommation = null,
        $date_creation = null,
        $description = ''
    ) {
        $this->id = (int) $id;
        $this->id_constante_reseau = (int) $id_constante_reseau;
        $this->prix_metre_cube_eau = (int) $prix_metre_cube_eau;
        $this->prix_entretient_compteur = (int) $prix_entretient_compteur;
        $this->prix_tva = (float) $prix_tva;
        $this->min_consommation = (float) $min_consommation;
        $this->max_consommation = $max_consommation !== null ? (float) $max_consommation : null;
        $this->date_creation = $date_creation ? $date_creation : date('Y-m-d');
        $this->description = $description;
    }

    function getNomTable()
    {
        return "tarif_differencie";
    }

    function getDonnee()
    {
        return array(
            'id_constante_reseau' => $this->id_constante_reseau,
            'prix_metre_cube_eau' => $this->prix_metre_cube_eau,
            'prix_entretient_compteur' => $this->prix_entretient_compteur,
            'prix_tva' => $this->prix_tva,
            'min_consommation' => $this->min_consommation,
            'max_consommation' => $this->max_consommation,
            'date_creation' => $this->date_creation,
            'description' => $this->description
        );
    }

    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    /**
     * Récupère tous les tarifs différenciés pour une constante_reseau
     */
    public static function getTarifsByConstante($id_constante_reseau)
    {
        return self::prepare_query(
            "SELECT * FROM tarif_differencie WHERE id_constante_reseau = ? ORDER BY min_consommation ASC",
            array($id_constante_reseau)
        )->fetchAll();
    }

    /**
     * Vérifie si les intervalles de consommation se chevauchent
     * Les intervalles sont définis comme [min; max[ (max non inclus)
     * Exemple : [5; 10[ et [10; 15[ ne se chevauchent PAS car 10 n'est pas inclus dans le premier
     * 
     * Deux intervalles [a; b[ et [c; d[ se chevauchent si :
     * - a < d ET c < b (il y a une intersection)
     */
    public static function checkOverlap($id_constante_reseau, $min_consommation, $max_consommation, $exclude_id = null)
    {
        $params = array($id_constante_reseau);
        $query = "
            SELECT COUNT(*) as count 
            FROM tarif_differencie 
            WHERE id_constante_reseau = ?
        ";
        
        // Logique de chevauchement pour intervalles [min; max[ :
        // Deux intervalles [a; b[ et [c; d[ se chevauchent si : a < d ET c < b
        // Si max est NULL, l'intervalle est [min; +∞[
        
        if ($max_consommation !== null) {
            // Nouvel intervalle : [min_consommation; max_consommation[
            // Intervalle existant : [min_consommation_existant; max_consommation_existant[
            // Ils se chevauchent si : min_consommation < max_consommation_existant ET min_consommation_existant < max_consommation
            $query .= " AND (
                (min_consommation < ? AND (max_consommation IS NULL OR max_consommation > ?))
            )";
            $params[] = $max_consommation; // max du nouvel intervalle (non inclus) - condition 1: min_existant < max_nouvel
            $params[] = $min_consommation; // min du nouvel intervalle (inclus) - condition 2: min_nouvel < max_existant
        } else {
            // Nouvel intervalle : [min_consommation; +∞[
            // Il chevauche un intervalle existant si : min_consommation < max_consommation_existant
            // OU si l'intervalle existant est aussi [x; +∞[ et min_consommation < x
            $query .= " AND (
                (max_consommation IS NULL)
                OR (max_consommation > ?)
            )";
            $params[] = $min_consommation;
        }

        if ($exclude_id !== null) {
            $query .= " AND id != ?";
            $params[] = $exclude_id;
        }

        $result = self::prepare_query($query, $params)->fetch();
        return $result['count'] > 0;
    }

    /**
     * Vérifie si la constante_reseau a des mois de facturation associés
     */
    public static function hasMoisFacturation($id_constante_reseau)
    {
        $result = self::prepare_query(
            "SELECT COUNT(*) as count FROM mois_facturation WHERE id_constante = ?",
            array($id_constante_reseau)
        )->fetch();
        return $result['count'] > 0;
    }
}
