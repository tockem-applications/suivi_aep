<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class ConfigCompteRenduFinancier extends Manager
{
    public $id;
    public $code_type; // 'recouvrements', 'branchements', 'redevances'
    public $libelle; // Libellé à afficher
    public $type_flux; // 'recette' ou 'charge'
    public $id_aep; // NULL pour global, ou ID spécifique d'un AEP
    public $code_budgetaire; // Code budgétaire pour les catégories automatiques
    public $activite_associee; // 'branchements', 'vente_eau', ou 'autre'

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        $donnees = array(
            'code_type' => $this->code_type,
            'libelle' => $this->libelle,
            'type_flux' => $this->type_flux,
            'id_aep' => $this->id_aep,
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
        return "config_compte_rendu_financier";
    }

    /**
     * Récupère la configuration pour un type donné et un AEP
     */
    public static function getConfig($code_type, $id_aep = null)
    {
        $query = "SELECT * FROM config_compte_rendu_financier 
                  WHERE code_type = ? AND (id_aep = ? OR id_aep IS NULL)
                  ORDER BY id_aep DESC LIMIT 1";
        return self::prepare_query($query, array($code_type, $id_aep))->fetch();
    }

    /**
     * Récupère toutes les configurations pour un AEP
     */
    public static function getAllConfigs($id_aep = null)
    {
        $query = "SELECT * FROM config_compte_rendu_financier 
                  WHERE id_aep = ? OR id_aep IS NULL
                  ORDER BY type_flux, code_type";
        return self::prepare_query($query, array($id_aep));
    }

    /**
     * Génère le compte rendu financier simplifié par période
     * 
     * @param int $id_aep ID de l'AEP
     * @param string $mois_debut Mois de début (format: YYYY-MM)
     * @param string $mois_fin Mois de fin (format: YYYY-MM)
     * @return array Tableau avec les données du compte rendu
     */
    public static function getCompteRenduFinancier($id_aep, $mois_debut, $mois_fin)
    {
        @include_once("../donnees/connexion.php");
        @include_once("donnees/connexion.php");
        $bd = Connexion::connect();
        
        // Récupérer les libellés et codes budgétaires configurés
        $config_recouvrements = self::getConfig('recouvrements', $id_aep);
        $config_branchements = self::getConfig('branchements', $id_aep);
        $config_redevances = self::getConfig('redevances', $id_aep);
        
        $libelle_recouvrements = $config_recouvrements ? $config_recouvrements['libelle'] : 'Recouvrements';
        $libelle_branchements = $config_branchements ? $config_branchements['libelle'] : 'Branchements';
        $libelle_redevances = $config_redevances ? $config_redevances['libelle'] : 'Redevances';
        
        $code_budgetaire_recouvrements = $config_recouvrements && !empty($config_recouvrements['code_budgetaire']) ? $config_recouvrements['code_budgetaire'] : null;
        $code_budgetaire_branchements = $config_branchements && !empty($config_branchements['code_budgetaire']) ? $config_branchements['code_budgetaire'] : null;
        $code_budgetaire_redevances = $config_redevances && !empty($config_redevances['code_budgetaire']) ? $config_redevances['code_budgetaire'] : null;
        
        // Requête pour récupérer les recouvrements par mois
        $query_recouvrements = "
            SELECT 
                mf.mois,
                SUM(f.montant_verse) as montant
            FROM facture f
            INNER JOIN indexes i ON f.id_indexes = i.id
            INNER JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
            INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
            WHERE cr.id_aep = ? 
            AND f.montant_verse > 0
            AND mf.mois >= ? AND mf.mois <= ?
            GROUP BY mf.mois
        ";
        
        // Requête pour récupérer les branchements par mois
        $query_branchements = "
            SELECT 
                ba.mois,
                SUM(ba.versement_fcfa) as montant
            FROM branchement_abonne ba
            INNER JOIN abone a ON ba.id_abone = a.id
            INNER JOIN reseau r ON a.id_reseau = r.id
            WHERE r.id_aep = ?
            AND ba.versement_fcfa > 0
            AND ba.mois >= ? AND ba.mois <= ?
            GROUP BY ba.mois
        ";
        
        // Requête pour récupérer les versements de redevances par mois, séparées par base_calcul
        $query_redevances_branchements = "
            SELECT 
                COALESCE(mf.mois, DATE_FORMAT(v.date_versement, '%Y-%m')) as mois,
                SUM(v.montant) as montant
            FROM versements v
            INNER JOIN redevance r ON v.id_redevance = r.id
            LEFT JOIN mois_facturation mf ON v.id_mois_facturation = mf.id
            WHERE r.id_aep = ?
            AND r.base_calcul = 'branchements'
            AND v.montant > 0
            AND (
                (mf.mois IS NOT NULL AND mf.mois >= ? AND mf.mois <= ?) OR
                (mf.mois IS NULL AND DATE_FORMAT(v.date_versement, '%Y-%m') >= ? AND DATE_FORMAT(v.date_versement, '%Y-%m') <= ?)
            )
            GROUP BY COALESCE(mf.mois, DATE_FORMAT(v.date_versement, '%Y-%m'))
        ";
        $query_redevances_vente_eau = "
            SELECT 
                COALESCE(mf.mois, DATE_FORMAT(v.date_versement, '%Y-%m')) as mois,
                SUM(v.montant) as montant
            FROM versements v
            INNER JOIN redevance r ON v.id_redevance = r.id
            LEFT JOIN mois_facturation mf ON v.id_mois_facturation = mf.id
            WHERE r.id_aep = ?
            AND r.base_calcul = 'vente_eau'
            AND v.montant > 0
            AND (
                (mf.mois IS NOT NULL AND mf.mois >= ? AND mf.mois <= ?) OR
                (mf.mois IS NULL AND DATE_FORMAT(v.date_versement, '%Y-%m') >= ? AND DATE_FORMAT(v.date_versement, '%Y-%m') <= ?)
            )
            GROUP BY COALESCE(mf.mois, DATE_FORMAT(v.date_versement, '%Y-%m'))
        ";
        
        // Requête pour récupérer les flux manuels par code budgétaire
        $query_flux_manuels = "
            SELECT 
                DATE_FORMAT(ff.date, '%Y-%m') as mois,
                COALESCE(cfm.nom, '') as categorie,
                COALESCE(cfm.code_budgetaire, '') as code_budgetaire,
                ff.type,
                SUM(ff.prix) as montant
            FROM flux_financier ff
            LEFT JOIN categorie_flux_manuel cfm ON ff.id_categorie_flux_manuel = cfm.id
            WHERE ff.id_aep = ?
            AND DATE_FORMAT(ff.date, '%Y-%m') >= ? AND DATE_FORMAT(ff.date, '%Y-%m') <= ?
            GROUP BY DATE_FORMAT(ff.date, '%Y-%m'), COALESCE(cfm.code_budgetaire, ''), COALESCE(cfm.id, 0), ff.type
        ";
        
        // Exécuter les requêtes
        $stmt_recouvrements = $bd->prepare($query_recouvrements);
        $stmt_recouvrements->execute(array($id_aep, $mois_debut, $mois_fin));
        $recouvrements = $stmt_recouvrements->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_branchements = $bd->prepare($query_branchements);
        $stmt_branchements->execute(array($id_aep, $mois_debut, $mois_fin));
        $branchements = $stmt_branchements->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_redevances_branchements = $bd->prepare($query_redevances_branchements);
        $stmt_redevances_branchements->execute(array($id_aep, $mois_debut, $mois_fin, $mois_debut, $mois_fin));
        $redevances_branchements = $stmt_redevances_branchements->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_redevances_vente_eau = $bd->prepare($query_redevances_vente_eau);
        $stmt_redevances_vente_eau->execute(array($id_aep, $mois_debut, $mois_fin, $mois_debut, $mois_fin));
        $redevances_vente_eau = $stmt_redevances_vente_eau->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_flux_manuels = $bd->prepare($query_flux_manuels);
        $stmt_flux_manuels->execute(array($id_aep, $mois_debut, $mois_fin));
        $flux_manuels = $stmt_flux_manuels->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les données par mois
        $resultat = array();
        
        // Traiter les recouvrements (recettes)
        foreach ($recouvrements as $recouvrement) {
            $mois = $recouvrement['mois'];
            if (!isset($resultat[$mois])) {
                $resultat[$mois] = array(
                    'mois' => $mois,
                    'recettes' => array(),
                    'charges' => array(),
                    'total_recettes' => 0,
                    'total_charges' => 0,
                    'solde' => 0
                );
            }
            $montant = floatval($recouvrement['montant']);
            // Utiliser le code budgétaire comme clé si disponible, sinon le libellé
            $cle = $code_budgetaire_recouvrements ? $code_budgetaire_recouvrements : $libelle_recouvrements;
            if (isset($resultat[$mois]['recettes'][$cle])) {
                $resultat[$mois]['recettes'][$cle]['montant'] += $montant;
            } else {
                $resultat[$mois]['recettes'][$cle] = array(
                    'code_budgetaire' => $code_budgetaire_recouvrements ?: '',
                    'nom' => $libelle_recouvrements,
                    'montant' => $montant
                );
            }
            $resultat[$mois]['total_recettes'] += $montant;
        }
        
        // Traiter les branchements (recettes)
        foreach ($branchements as $branchement) {
            $mois = $branchement['mois'];
            if (!isset($resultat[$mois])) {
                $resultat[$mois] = array(
                    'mois' => $mois,
                    'recettes' => array(),
                    'charges' => array(),
                    'total_recettes' => 0,
                    'total_charges' => 0,
                    'solde' => 0
                );
            }
            $montant = floatval($branchement['montant']);
            // Utiliser le code budgétaire comme clé si disponible, sinon le libellé
            $cle = $code_budgetaire_branchements ? $code_budgetaire_branchements : $libelle_branchements;
            if (isset($resultat[$mois]['recettes'][$cle])) {
                $resultat[$mois]['recettes'][$cle]['montant'] += $montant;
            } else {
                $resultat[$mois]['recettes'][$cle] = array(
                    'code_budgetaire' => $code_budgetaire_branchements ?: '',
                    'nom' => $libelle_branchements,
                    'montant' => $montant
                );
            }
            $resultat[$mois]['total_recettes'] += $montant;
        }
        
        // Traiter les redevances sur branchements (charges)
        foreach ($redevances_branchements as $redevance) {
            $mois = $redevance['mois'];
            if (!isset($resultat[$mois])) {
                $resultat[$mois] = array(
                    'mois' => $mois,
                    'recettes' => array(),
                    'charges' => array(),
                    'total_recettes' => 0,
                    'total_charges' => 0,
                    'solde' => 0
                );
            }
            $montant = floatval($redevance['montant']);
            // Utiliser le code budgétaire comme clé si disponible, sinon le libellé avec suffixe
            $cle = ($code_budgetaire_redevances ? $code_budgetaire_redevances : $libelle_redevances) . '_branchements';
            // Ajouter au montant existant si déjà présent
            if (isset($resultat[$mois]['charges'][$cle])) {
                $resultat[$mois]['charges'][$cle]['montant'] += $montant;
            } else {
                $resultat[$mois]['charges'][$cle] = array(
                    'code_budgetaire' => $code_budgetaire_redevances ?: '',
                    'nom' => $libelle_redevances . ' (Branchements)',
                    'montant' => $montant
                );
            }
            $resultat[$mois]['total_charges'] += $montant;
        }
        
        // Traiter les redevances sur vente d'eau (charges)
        foreach ($redevances_vente_eau as $redevance) {
            $mois = $redevance['mois'];
            if (!isset($resultat[$mois])) {
                $resultat[$mois] = array(
                    'mois' => $mois,
                    'recettes' => array(),
                    'charges' => array(),
                    'total_recettes' => 0,
                    'total_charges' => 0,
                    'solde' => 0
                );
            }
            $montant = floatval($redevance['montant']);
            // Utiliser le code budgétaire comme clé si disponible, sinon le libellé avec suffixe
            $cle = ($code_budgetaire_redevances ? $code_budgetaire_redevances : $libelle_redevances) . '_vente_eau';
            // Ajouter au montant existant si déjà présent
            if (isset($resultat[$mois]['charges'][$cle])) {
                $resultat[$mois]['charges'][$cle]['montant'] += $montant;
            } else {
                $resultat[$mois]['charges'][$cle] = array(
                    'code_budgetaire' => $code_budgetaire_redevances ?: '',
                    'nom' => $libelle_redevances . ' (Vente d\'eau)',
                    'montant' => $montant
                );
            }
            $resultat[$mois]['total_charges'] += $montant;
        }
        
        // Traiter les flux manuels (recettes et charges) - regroupés par code budgétaire
        foreach ($flux_manuels as $flux) {
            $mois = $flux['mois'];
            if (!isset($resultat[$mois])) {
                $resultat[$mois] = array(
                    'mois' => $mois,
                    'recettes' => array(),
                    'charges' => array(),
                    'total_recettes' => 0,
                    'total_charges' => 0,
                    'solde' => 0
                );
            }
            $montant = floatval($flux['montant']);
            
            // Utiliser le code budgétaire comme clé si disponible, sinon le nom de la catégorie
            $cle = !empty($flux['code_budgetaire']) ? $flux['code_budgetaire'] : 
                   (!empty($flux['categorie']) ? $flux['categorie'] : 'Autres ' . ($flux['type'] === 'entree' ? 'recettes' : 'charges'));
            
            $code_budgetaire = !empty($flux['code_budgetaire']) ? $flux['code_budgetaire'] : '';
            $nom_categorie = !empty($flux['categorie']) ? $flux['categorie'] : 'Autres ' . ($flux['type'] === 'entree' ? 'recettes' : 'charges');
            
            // Les types dans la base sont 'entree' et 'sortie'
            if ($flux['type'] === 'entree') {
                // Recette
                if (isset($resultat[$mois]['recettes'][$cle])) {
                    $resultat[$mois]['recettes'][$cle]['montant'] += $montant;
                } else {
                    $resultat[$mois]['recettes'][$cle] = array(
                        'code_budgetaire' => $code_budgetaire,
                        'nom' => $nom_categorie,
                        'montant' => $montant
                    );
                }
                $resultat[$mois]['total_recettes'] += $montant;
            } elseif ($flux['type'] === 'sortie') {
                // Charge
                if (isset($resultat[$mois]['charges'][$cle])) {
                    $resultat[$mois]['charges'][$cle]['montant'] += $montant;
                } else {
                    $resultat[$mois]['charges'][$cle] = array(
                        'code_budgetaire' => $code_budgetaire,
                        'nom' => $nom_categorie,
                        'montant' => $montant
                    );
                }
                $resultat[$mois]['total_charges'] += $montant;
            }
        }
        
        // Calculer le solde pour chaque mois
        foreach ($resultat as &$mois_data) {
            $mois_data['solde'] = $mois_data['total_recettes'] - $mois_data['total_charges'];
        }
        
        // Trier par mois décroissant
        krsort($resultat);
        
        return $resultat;
    }
}
