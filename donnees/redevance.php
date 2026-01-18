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
    public $base_calcul; // 'vente_eau' ou 'branchements'
    public $type_calcul; // 'pourcentage' ou 'montant_fixe'
    public $montant_par_m3; // Montant fixe par m³ si type_calcul = 'montant_fixe'
    public $est_sortie; // Toujours 1 (toutes les redevances sont des sorties)
    public $type; // 'sortie' (pour compatibilité)
    public $mois_debut; // Mois de début d'application

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        $donnees = array(
            'libele' => $this->libele,
            'pourcentage' => $this->pourcentage,
            'description' => $this->description,
            'id_aep' => $this->id_aep
        );
        
        // Ajouter les nouveaux champs s'ils existent
        if (isset($this->base_calcul)) {
            $donnees['base_calcul'] = $this->base_calcul;
        }
        if (isset($this->type_calcul)) {
            $donnees['type_calcul'] = $this->type_calcul;
        }
        if (isset($this->montant_par_m3)) {
            $donnees['montant_par_m3'] = $this->montant_par_m3;
        }
        if (isset($this->est_sortie)) {
            $donnees['est_sortie'] = $this->est_sortie ? 1 : 0;
        }
        if (isset($this->type)) {
            $donnees['type'] = $this->type;
        }
        if (isset($this->mois_debut)) {
            $donnees['mois_debut'] = $this->mois_debut;
        }
        
        return $donnees;
    }

    function getNomTable()
    {
        return "redevance";
    }

    public function __construct($id = 0, $libele = '', $pourcentage = 0, $description = '', $id_aep = 0, 
                                $base_calcul = 'vente_eau', $type_calcul = 'pourcentage', 
                                $montant_par_m3 = null, $est_sortie = true, $type = 'sortie', $mois_debut = null)
    {
        $this->id = $id;
        $this->libele = $libele;
        $this->pourcentage = $pourcentage;
        $this->description = $description;
        $this->id_aep = $id_aep;
        $this->base_calcul = $base_calcul;
        $this->type_calcul = $type_calcul;
        $this->montant_par_m3 = $montant_par_m3;
        $this->est_sortie = $est_sortie;
        $this->type = $type;
        $this->mois_debut = $mois_debut ? $mois_debut : date('Y-m');
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
                isset($data['pourcentage']) ? $data['pourcentage'] : 0,
                isset($data['description']) ? $data['description'] : '',
                $data['id_aep'],
                isset($data['base_calcul']) ? $data['base_calcul'] : 'vente_eau',
                isset($data['type_calcul']) ? $data['type_calcul'] : 'pourcentage',
                isset($data['montant_par_m3']) ? $data['montant_par_m3'] : null,
                isset($data['est_sortie']) ? (bool)$data['est_sortie'] : true,
                isset($data['type']) ? $data['type'] : 'sortie',
                isset($data['mois_debut']) ? $data['mois_debut'] : null
            );
        }
        return null;
    }

    /**
     * Calcule le montant estimatif maximum d'une redevance pour un mois donné
     * @param int $id_redevance ID de la redevance
     * @param int $id_mois_facturation ID du mois de facturation
     * @return float Montant estimatif maximum
     */
    public static function calculerMontantEstimatif($id_redevance, $id_mois_facturation)
    {
        // Récupérer les informations de la redevance
        $redevance = self::getRedevance($id_redevance);
        if (!$redevance) {
            return 0;
        }

        // Récupérer les informations du mois
        $mois = Manager::prepare_query(
            "SELECT m.*, c.id_aep, c.prix_metre_cube_eau 
             FROM mois_facturation m 
             INNER JOIN constante_reseau c ON m.id_constante = c.id 
             WHERE m.id = ?",
            array($id_mois_facturation)
        )->fetch();

        if (!$mois || $mois['id_aep'] != $redevance->id_aep) {
            return 0;
        }

        // Vérifier que le mois est >= mois_debut
        if ($redevance->mois_debut && $mois['mois'] < $redevance->mois_debut) {
            return 0;
        }

        $montant = 0;

        if ($redevance->base_calcul == 'vente_eau') {
            // Calcul basé sur la vente d'eau consommée
            // Utiliser la vue vue_abones_facturation pour obtenir montant_conso (montant facturé pour la vente d'eau)
            $stats = Manager::prepare_query(
                "SELECT 
                    SUM(v.consommation) as total_conso,
                    SUM(v.montant_conso) as montant_total_facture
                 FROM vue_abones_facturation v
                 INNER JOIN abone a ON v.id_abone = a.id
                 INNER JOIN reseau r ON a.id_reseau = r.id
                 WHERE v.id_mois_facturation = ? AND r.id_aep = ?",
                array($id_mois_facturation, $redevance->id_aep)
            )->fetch();

            if ($stats && $stats['total_conso'] > 0) {
                if ($redevance->type_calcul == 'pourcentage') {
                    // Pourcentage du montant total facturé (montant_conso)
                    $montant_total_facture = $stats['montant_total_facture'] ? (float)$stats['montant_total_facture'] : 0;
                    if ($montant_total_facture > 0) {
                        $montant = ($montant_total_facture * $redevance->pourcentage) / 100;
                    }
                } else if ($redevance->type_calcul == 'montant_fixe' && $redevance->montant_par_m3) {
                    // Montant fixe par m³
                    $montant = $stats['total_conso'] * $redevance->montant_par_m3;
                }
            }
        } else if ($redevance->base_calcul == 'branchements') {
            // Pour les branchements, on n'utilise pas les mois de facturation
            // Cette méthode ne devrait pas être appelée pour les branchements
            // car le calcul se fait directement dans la page de versements
            // On retourne 0 pour éviter les erreurs
            $montant = 0;
        }

        return round($montant, 2);
    }

    /**
     * Récupère le montant déjà versé pour une redevance et un mois
     * @param int $id_redevance ID de la redevance
     * @param int $id_mois_facturation ID du mois de facturation
     * @return float Montant déjà versé
     */
    public static function getMontantDejaVerse($id_redevance, $id_mois_facturation)
    {
        $result = Manager::prepare_query(
            "SELECT SUM(montant) as total_verse 
             FROM versements 
             WHERE id_redevance = ? AND id_mois_facturation = ?",
            array($id_redevance, $id_mois_facturation)
        )->fetch();

        return $result && $result['total_verse'] ? (float)$result['total_verse'] : 0;
    }

    /**
     * Récupère le reste à verser pour une redevance et un mois
     * @param int $id_redevance ID de la redevance
     * @param int $id_mois_facturation ID du mois de facturation
     * @return float Reste à verser
     */
    public static function getResteAVerser($id_redevance, $id_mois_facturation)
    {
        $montant_estimatif = self::calculerMontantEstimatif($id_redevance, $id_mois_facturation);
        $montant_verse = self::getMontantDejaVerse($id_redevance, $id_mois_facturation);
        return max(0, $montant_estimatif - $montant_verse);
    }
}