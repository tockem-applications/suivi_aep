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
     * Totaux facturation pour un mois (AEP) : taux de recouvrement pour pondérer les redevances.
     * Taux = somme des montants versés sur factures ÷ facturation TTC (0–1, plafonné à 100 %).
     * Recouvrement net = versé − entretiens compteur TTC (indicateur complémentaire, non utilisé pour le taux).
     *
     * @return array{facture_ttc: float, verse: float, entretien_ttc: float, recouvre_net: float, taux: float}
     */
    public static function getStatFacturationRecouvrementMois($id_mois_facturation, $id_aep)
    {
        $id_mois_facturation = (int) $id_mois_facturation;
        $id_aep = (int) $id_aep;
        if ($id_mois_facturation <= 0 || $id_aep <= 0) {
            return array(
                'facture_ttc' => 0.0,
                'verse' => 0.0,
                'entretien_ttc' => 0.0,
                'recouvre_net' => 0.0,
                'taux' => 0.0,
            );
        }
        $row = Manager::prepare_query(
            "SELECT 
                COALESCE(SUM(v.montant_total), 0) AS facture_ttc,
                COALESCE(SUM(v.montant_verse), 0) AS verse,
                COALESCE(SUM(v.prix_entretient_compteur * (1 + v.prix_tva / 100)), 0) AS entretien_ttc
             FROM vue_abones_facturation v
             INNER JOIN abone a ON a.id = v.id_abone
             INNER JOIN reseau r ON r.id = a.id_reseau
             WHERE v.id_mois_facturation = ? AND r.id_aep = ?",
            array($id_mois_facturation, $id_aep)
        )->fetch();

        $facture = $row ? (float) $row['facture_ttc'] : 0.0;
        $verse = $row ? (float) $row['verse'] : 0.0;
        $entretien = $row ? (float) $row['entretien_ttc'] : 0.0;
        $recouvreNet = max(0.0, $verse - $entretien);
        $taux = ($facture > 0.0) ? min(1.0, max(0.0, $verse / $facture)) : 0.0;

        return array(
            'facture_ttc' => $facture,
            'verse' => $verse,
            'entretien_ttc' => $entretien,
            'recouvre_net' => $recouvreNet,
            'taux' => $taux,
        );
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
     * Récupère le montant déjà versé pour une redevance et un mois de facturation.
     * Attribue les versements au mois selon DATE(date_versement) : même année-mois que mois_facturation.mois
     * (aligné sur le tableau « détail par mois », versements globaux inclus).
     *
     * @param int $id_redevance ID de la redevance
     * @param int $id_mois_facturation ID du mois de facturation
     * @return float Montant déjà versé
     */
    public static function getMontantDejaVerse($id_redevance, $id_mois_facturation)
    {
        $id_mois_facturation = (int) $id_mois_facturation;
        if ($id_mois_facturation <= 0) {
            return 0.0;
        }
        $row = Manager::prepare_query(
            "SELECT m.mois FROM mois_facturation m WHERE m.id = ?",
            array($id_mois_facturation)
        )->fetch();
        if (!$row || empty($row['mois'])) {
            return 0.0;
        }
        $result = Manager::prepare_query(
            "SELECT COALESCE(SUM(v.montant), 0) AS total_verse
             FROM versements v
             WHERE v.id_redevance = ?
               AND DATE_FORMAT(v.date_versement, '%Y-%m') = ?",
            array($id_redevance, $row['mois'])
        )->fetch();

        return $result ? (float) $result['total_verse'] : 0.0;
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