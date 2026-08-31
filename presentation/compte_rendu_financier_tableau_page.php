<?php
@include_once("../donnees/config_compte_rendu_financier.php");
@include_once("donnees/config_compte_rendu_financier.php");
@include_once("../donnees/categorie_flux_manuel.php");
@include_once("donnees/categorie_flux_manuel.php");
@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");

// Récupérer les paramètres
$type_periode = isset($_GET['type_periode']) ? $_GET['type_periode'] : 'annee';
$annee = isset($_GET['annee']) ? $_GET['annee'] : date('Y');
$mois_debut = isset($_GET['mois_debut']) ? $_GET['mois_debut'] : date('Y-m', strtotime('-6 months'));
$mois_fin = isset($_GET['mois_fin']) ? $_GET['mois_fin'] : date('Y-m');
$id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : 1;

// Générer les données selon le type de période
if ($type_periode === 'mois') {
    $donnees = generateCompteRenduTableauMois($id_aep, $mois_debut, $mois_fin);
} else {
    $donnees = generateCompteRenduTableau($id_aep, $annee);
}

function generateCompteRenduTableau($id_aep, $annee)
{
    @include_once("../donnees/connexion.php");
    @include_once("donnees/connexion.php");
    $bd = Connexion::connect();

    // Récupérer les libellés, codes budgétaires et activités associées configurés
    $config_recouvrements = ConfigCompteRenduFinancier::getConfig('recouvrements', $id_aep);
    $config_branchements = ConfigCompteRenduFinancier::getConfig('branchements', $id_aep);
    $config_redevances = ConfigCompteRenduFinancier::getConfig('redevances', $id_aep);

    $libelle_recouvrements = $config_recouvrements ? $config_recouvrements['libelle'] : 'Recouvrements';
    $libelle_branchements = $config_branchements ? $config_branchements['libelle'] : 'Branchements';
    $libelle_redevances = $config_redevances ? $config_redevances['libelle'] : 'Redevances';

    $code_budgetaire_recouvrements = $config_recouvrements && !empty($config_recouvrements['code_budgetaire']) ? $config_recouvrements['code_budgetaire'] : null;
    $code_budgetaire_branchements = $config_branchements && !empty($config_branchements['code_budgetaire']) ? $config_branchements['code_budgetaire'] : null;
    $code_budgetaire_redevances = $config_redevances && !empty($config_redevances['code_budgetaire']) ? $config_redevances['code_budgetaire'] : null;

    $activite_recouvrements = $config_recouvrements && !empty($config_recouvrements['activite_associee']) ? $config_recouvrements['activite_associee'] : 'vente_eau';
    $activite_branchements = $config_branchements && !empty($config_branchements['activite_associee']) ? $config_branchements['activite_associee'] : 'branchements';
    $activite_redevances = $config_redevances && !empty($config_redevances['activite_associee']) ? $config_redevances['activite_associee'] : 'autre';

    // Initialiser les données par mois
    $mois_data = array();
    for ($m = 1; $m <= 12; $m++) {
        $mois_key = sprintf('%04d-%02d', $annee, $m);
        $mois_data[$mois_key] = array(
            'recouvrements' => 0,
            'branchements' => 0,
            'redevances_branchements' => 0,
            'redevances_vente_eau' => 0,
            'flux_manuel_charge' => array(),
            'flux_manuel_recette' => array()
        );
    }

    // Récupérer les recouvrements par mois
    $query = "
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
    $stmt = $bd->prepare($query);
    $stmt->execute(array($id_aep, $annee . '-01', $annee . '-12'));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mois_key = $row['mois'];
        if (isset($mois_data[$mois_key])) {
            $mois_data[$mois_key]['recouvrements'] = floatval($row['montant']);
        }
    }

    // Récupérer les branchements par mois
    $query = "
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
    $stmt = $bd->prepare($query);
    $stmt->execute(array($id_aep, $annee . '-01', $annee . '-12'));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mois_key = $row['mois'];
        if (isset($mois_data[$mois_key])) {
            $mois_data[$mois_key]['branchements'] = floatval($row['montant']);
        }
    }

    // Récupérer les redevances par mois, séparées par base_calcul
    // Note: Si base_calcul est NULL ou vide, on considère 'vente_eau' par défaut (comme dans la table)
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
        AND (r.base_calcul = 'vente_eau' OR r.base_calcul IS NULL OR r.base_calcul = '')
        AND v.montant > 0
        AND (
            (mf.mois IS NOT NULL AND mf.mois >= ? AND mf.mois <= ?) OR
            (mf.mois IS NULL AND DATE_FORMAT(v.date_versement, '%Y-%m') >= ? AND DATE_FORMAT(v.date_versement, '%Y-%m') <= ?)
        )
        GROUP BY COALESCE(mf.mois, DATE_FORMAT(v.date_versement, '%Y-%m'))
    ";

    // Récupérer les redevances sur branchements
    $stmt = $bd->prepare($query_redevances_branchements);
    $stmt->execute(array($id_aep, $annee . '-01', $annee . '-12', $annee . '-01', $annee . '-12'));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mois_key = $row['mois'];
        if (isset($mois_data[$mois_key])) {
            $mois_data[$mois_key]['redevances_branchements'] = floatval($row['montant']);
        }
    }

    // Récupérer les redevances sur vente d'eau
    $stmt = $bd->prepare($query_redevances_vente_eau);
    $stmt->execute(array($id_aep, $annee . '-01', $annee . '-12', $annee . '-01', $annee . '-12'));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mois_key = $row['mois'];
        if (isset($mois_data[$mois_key])) {
            $mois_data[$mois_key]['redevances_vente_eau'] = floatval($row['montant']);
        }
    }

    // Récupérer les flux manuels par code budgétaire
    $query = "
        SELECT 
            DATE_FORMAT(ff.date, '%Y-%m') as mois,
            COALESCE(cfm.nom, '') as categorie,
            COALESCE(cfm.code_budgetaire, '') as code_budgetaire,
            COALESCE(cfm.activite_associee, 'autre') as activite_associee,
            COALESCE(cfm.id, 0) as id_categorie,
            ff.type,
            SUM(ff.prix) as montant
        FROM flux_financier ff
        LEFT JOIN categorie_flux_manuel cfm ON ff.id_categorie_flux_manuel = cfm.id
        WHERE ff.id_aep = ?
        AND DATE_FORMAT(ff.date, '%Y-%m') >= ? AND DATE_FORMAT(ff.date, '%Y-%m') <= ?
        GROUP BY DATE_FORMAT(ff.date, '%Y-%m'), COALESCE(cfm.code_budgetaire, ''), COALESCE(cfm.id, 0), ff.type
    ";
    $stmt = $bd->prepare($query);
    $stmt->execute(array($id_aep, $annee . '-01', $annee . '-12'));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mois_key = $row['mois'];
        if (isset($mois_data[$mois_key])) {
            // Utiliser le code budgétaire comme clé si disponible, sinon le nom de la catégorie
            $cle = !empty($row['code_budgetaire']) ? $row['code_budgetaire'] :
                (!empty($row['categorie']) ? $row['categorie'] : 'Autres ' . ($row['type'] === 'entree' ? 'recettes' : 'charges'));
            // Les types dans la base sont 'entree' et 'sortie'
            if ($row['type'] === 'entree') {
                if (!isset($mois_data[$mois_key]['flux_manuel_recette'][$cle])) {
                    $mois_data[$mois_key]['flux_manuel_recette'][$cle] = array('montant' => 0, 'activite' => $row['activite_associee']);
                }
                $mois_data[$mois_key]['flux_manuel_recette'][$cle]['montant'] += floatval($row['montant']);
            } elseif ($row['type'] === 'sortie') {
                // Les sorties sont des charges
                if (!isset($mois_data[$mois_key]['flux_manuel_charge'][$cle])) {
                    $mois_data[$mois_key]['flux_manuel_charge'][$cle] = array('montant' => 0, 'activite' => $row['activite_associee']);
                }
                $mois_data[$mois_key]['flux_manuel_charge'][$cle]['montant'] += floatval($row['montant']);
            }
        }
    }

    // Debug temporaire - à supprimer après vérification
    // error_log("DEBUG mois_data redevances: " . print_r(array_column($mois_data, 'redevances'), true));
    // error_log("DEBUG mois_data flux_manuel_charge: " . print_r(array_map(function($m) { return $m['flux_manuel_charge']; }, $mois_data), true));

    // Construire les lignes du tableau
    $lignes = array();

    // RECETTES
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_recouvrements ? $code_budgetaire_recouvrements : '',
        'nom_categorie' => $libelle_recouvrements,
        'type' => 'recette',
        'mois' => array(),
        'cumule' => 0,
        'activite' => $activite_recouvrements
    );
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_branchements ? $code_budgetaire_branchements : '',
        'nom_categorie' => $libelle_branchements,
        'type' => 'recette',
        'mois' => array(),
        'cumule' => 0,
        'activite' => $activite_branchements
    );

    // Ajouter les recettes des flux manuels (regroupées par code budgétaire)
    $categories_recettes = array();
    foreach ($mois_data as $mois_key => $data) {
        foreach ($data['flux_manuel_recette'] as $cle => $info) {
            if (!isset($categories_recettes[$cle])) {
                // Récupérer le nom de la catégorie depuis la base (filtrer par id_aep)
                $query_cat = "SELECT nom, code_budgetaire, activite_associee FROM categorie_flux_manuel WHERE (code_budgetaire = ? OR nom = ?) AND (id_aep = ? OR id_aep IS NULL) LIMIT 1";
                $stmt_cat = $bd->prepare($query_cat);
                $stmt_cat->execute(array($cle, $cle, $id_aep));
                $cat_info = $stmt_cat->fetch(PDO::FETCH_ASSOC);
                // Stocker avec la clé originale ($cle) pour la correspondance
                $categories_recettes[$cle] = array(
                    'cle_origine' => $cle,
                    'code_budgetaire' => $cat_info ? ($cat_info['code_budgetaire'] ?: '') : '',
                    'nom_categorie' => $cat_info ? $cat_info['nom'] : $cle,
                    'activite' => $cat_info ? $cat_info['activite_associee'] : 'autre'
                );
            }
        }
    }
    foreach ($categories_recettes as $cle => $cat_info) {
        $lignes[] = array(
            'cle_origine' => $cle,
            'code_budgetaire' => $cat_info['code_budgetaire'],
            'nom_categorie' => $cat_info['nom_categorie'],
            'type' => 'recette',
            'mois' => array(),
            'cumule' => 0,
            'activite' => $cat_info['activite']
        );
    }

    // Total recettes
    $lignes[] = array(
        'code_budgetaire' => '',
        'nom_categorie' => 'Total recettes',
        'type' => 'total_recette',
        'mois' => array(),
        'cumule' => 0
    );

    // DEPENSES - Redevances sur branchements
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_redevances ? $code_budgetaire_redevances : '',
        'nom_categorie' => $libelle_redevances . ' (Branchements)',
        'type' => 'charge',
        'mois' => array(),
        'cumule' => 0,
        'activite' => 'branchements',
        'base_calcul' => 'branchements'
    );

    // DEPENSES - Redevances sur vente d'eau
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_redevances ? $code_budgetaire_redevances : '',
        'nom_categorie' => $libelle_redevances . ' (Vente d\'eau)',
        'type' => 'charge',
        'mois' => array(),
        'cumule' => 0,
        'activite' => 'vente_eau',
        'base_calcul' => 'vente_eau'
    );

    // Ajouter les charges des flux manuels (regroupées par code budgétaire)
    $categories_charges = array();
    foreach ($mois_data as $mois_key => $data) {
        foreach ($data['flux_manuel_charge'] as $cle => $info) {
            if (!isset($categories_charges[$cle])) {
                // Récupérer le nom de la catégorie depuis la base (filtrer par id_aep)
                $query_cat = "SELECT nom, code_budgetaire, activite_associee FROM categorie_flux_manuel WHERE (code_budgetaire = ? OR nom = ?) AND (id_aep = ? OR id_aep IS NULL) LIMIT 1";
                $stmt_cat = $bd->prepare($query_cat);
                $stmt_cat->execute(array($cle, $cle, $id_aep));
                $cat_info = $stmt_cat->fetch(PDO::FETCH_ASSOC);
                // Stocker avec la clé originale ($cle) pour la correspondance
                $categories_charges[$cle] = array(
                    'cle_origine' => $cle,
                    'code_budgetaire' => $cat_info ? ($cat_info['code_budgetaire'] ?: '') : '',
                    'nom_categorie' => $cat_info ? $cat_info['nom'] : $cle,
                    'activite' => $cat_info ? $cat_info['activite_associee'] : 'autre'
                );
            }
        }
    }
    foreach ($categories_charges as $cle => $cat_info) {
        $lignes[] = array(
            'cle_origine' => $cle,
            'code_budgetaire' => $cat_info['code_budgetaire'],
            'nom_categorie' => $cat_info['nom_categorie'],
            'type' => 'charge',
            'mois' => array(),
            'cumule' => 0,
            'activite' => $cat_info['activite']
        );
    }

    // Total dépenses
    $lignes[] = array(
        'code_budgetaire' => '',
        'nom_categorie' => 'Total dépenses',
        'type' => 'total_charge',
        'mois' => array(),
        'cumule' => 0
    );

    // Résultats
    $lignes[] = array(
        'code_budgetaire' => '',
        'nom_categorie' => 'Résultats',
        'type' => 'resultat',
        'mois' => array(),
        'cumule' => 0
    );

    // LIGNES DE TRÉSORERIE
    $lignes[] = array(
        'code_budgetaire' => '',
        'nom_categorie' => 'Trésorerie des branchements',
        'type' => 'tresorerie_branchements',
        'mois' => array(),
        'cumule' => 0
    );
    $lignes[] = array(
        'code_budgetaire' => '',
        'nom_categorie' => 'Trésorerie de la vente d\'eau',
        'type' => 'tresorerie_vente_eau',
        'mois' => array(),
        'cumule' => 0
    );
    $lignes[] = array(
        'code_budgetaire' => '',
        'nom_categorie' => 'Trésorerie',
        'type' => 'tresorerie',
        'mois' => array(),
        'cumule' => 0
    );

    // Remplir les données par mois pour les lignes individuelles
    foreach ($lignes as &$ligne) {
        if (in_array($ligne['type'], array('total_recette', 'total_charge', 'resultat'))) {
            continue; // On calculera ces lignes après
        }

        $cumule = 0;
        for ($m = 1; $m <= 12; $m++) {
            $mois_key = sprintf('%04d-%02d', $annee, $m);
            $montant = 0;

            // Identifier la ligne par code budgétaire ou nom
            $code_ligne = isset($ligne['code_budgetaire']) ? $ligne['code_budgetaire'] : '';
            $nom_ligne = isset($ligne['nom_categorie']) ? $ligne['nom_categorie'] : '';
            $base_calcul = isset($ligne['base_calcul']) ? $ligne['base_calcul'] : null;

            // Récupérer la clé originale si disponible (pour les flux manuels)
            $cle_origine = isset($ligne['cle_origine']) ? $ligne['cle_origine'] : '';

            if ($nom_ligne === $libelle_recouvrements || $code_ligne === $code_budgetaire_recouvrements) {
                $montant = isset($mois_data[$mois_key]['recouvrements']) ? $mois_data[$mois_key]['recouvrements'] : 0;
            } elseif ($nom_ligne === $libelle_branchements || $code_ligne === $code_budgetaire_branchements) {
                $montant = isset($mois_data[$mois_key]['branchements']) ? $mois_data[$mois_key]['branchements'] : 0;
            } elseif (strpos($nom_ligne, $libelle_redevances) !== false && $base_calcul === 'branchements') {
                $montant = isset($mois_data[$mois_key]['redevances_branchements']) ? $mois_data[$mois_key]['redevances_branchements'] : 0;
            } elseif (strpos($nom_ligne, $libelle_redevances) !== false && $base_calcul === 'vente_eau') {
                $montant = isset($mois_data[$mois_key]['redevances_vente_eau']) ? $mois_data[$mois_key]['redevances_vente_eau'] : 0;
            } elseif ($ligne['type'] === 'recette') {
                // Chercher par clé originale, code budgétaire ou nom
                $montant = 0;
                foreach ($mois_data[$mois_key]['flux_manuel_recette'] as $cle => $info) {
                    if ($cle === $cle_origine || ($cle_origine === '' && ($cle === $code_ligne || $cle === $nom_ligne))) {
                        $montant = $info['montant'];
                        break;
                    }
                }
            } elseif ($ligne['type'] === 'charge') {
                // Chercher par clé originale, code budgétaire ou nom
                $montant = 0;
                foreach ($mois_data[$mois_key]['flux_manuel_charge'] as $cle => $info) {
                    if ($cle === $cle_origine || ($cle_origine === '' && ($cle === $code_ligne || $cle === $nom_ligne))) {
                        $montant = $info['montant'];
                        break;
                    }
                }
            }

            $ligne['mois'][$mois_key] = $montant;
            $cumule += $montant;
        }
        $ligne['cumule'] = $cumule;
    }

    // Calculer les totaux, résultats et trésoreries
    foreach ($lignes as &$ligne) {
        if (in_array($ligne['type'], array('total_recette', 'total_charge', 'resultat', 'tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie'))) {
            $cumule = 0;
            for ($m = 1; $m <= 12; $m++) {
                $mois_key = sprintf('%04d-%02d', $annee, $m);
                $montant = 0;

                if ($ligne['type'] === 'total_recette') {
                    // Somme de toutes les recettes du mois
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                            $montant += $l['mois'][$mois_key];
                        }
                    }
                } elseif ($ligne['type'] === 'total_charge') {
                    // Somme de toutes les charges du mois
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                            $montant += $l['mois'][$mois_key];
                        }
                    }
                } elseif ($ligne['type'] === 'resultat') {
                    // Résultat = total recettes - total dépenses
                    $total_rec = 0;
                    $total_charge = 0;
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'total_recette' && isset($l['mois'][$mois_key])) {
                            $total_rec = $l['mois'][$mois_key];
                        }
                        if ($l['type'] === 'total_charge' && isset($l['mois'][$mois_key])) {
                            $total_charge = $l['mois'][$mois_key];
                        }
                    }
                    $montant = $total_rec - $total_charge;
                } elseif ($ligne['type'] === 'tresorerie_branchements') {
                    // Trésorerie branchements = recettes branchements (automatiques + manuelles associées) - charges associées (y compris redevances avec base_calcul='branchements')
                    $recettes_branchements = 0;
                    $charges_branchements = 0;
                    foreach ($lignes as $l) {
                        // Pour les redevances, utiliser base_calcul, sinon utiliser activite
                        $is_branchements = false;
                        if (isset($l['base_calcul']) && $l['base_calcul'] === 'branchements') {
                            $is_branchements = true;
                        } elseif (isset($l['activite']) && $l['activite'] === 'branchements') {
                            $is_branchements = true;
                        }

                        if ($is_branchements) {
                            if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                                $recettes_branchements += $l['mois'][$mois_key];
                            } elseif ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                                // Inclut les redevances avec base_calcul='branchements' et toutes les autres charges associées
                                $charges_branchements += $l['mois'][$mois_key];
                            }
                        }
                    }
                    $montant = $recettes_branchements - $charges_branchements;
                } elseif ($ligne['type'] === 'tresorerie_vente_eau') {
                    // Trésorerie vente eau = recettes recouvrements (automatiques + manuelles associées) - charges associées (y compris redevances avec base_calcul='vente_eau')
                    $recettes_vente_eau = 0;
                    $charges_vente_eau = 0;
                    foreach ($lignes as $l) {
                        // Pour les redevances, utiliser base_calcul, sinon utiliser activite
                        $is_vente_eau = false;
                        if (isset($l['base_calcul']) && $l['base_calcul'] === 'vente_eau') {
                            $is_vente_eau = true;
                        } elseif (isset($l['activite']) && $l['activite'] === 'vente_eau') {
                            $is_vente_eau = true;
                        }

                        if ($is_vente_eau) {
                            if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                                $recettes_vente_eau += $l['mois'][$mois_key];
                            } elseif ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                                // Inclut les redevances avec base_calcul='vente_eau' et toutes les autres charges associées
                                $charges_vente_eau += $l['mois'][$mois_key];
                            }
                        }
                    }
                    $montant = $recettes_vente_eau - $charges_vente_eau;
                } elseif ($ligne['type'] === 'tresorerie') {
                    // Trésorerie globale = somme des deux trésoreries + catégories "autre"
                    $tresorerie_branchements = 0;
                    $tresorerie_vente_eau = 0;
                    $autre_recettes = 0;
                    $autre_charges = 0;
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'tresorerie_branchements' && isset($l['mois'][$mois_key])) {
                            $tresorerie_branchements = $l['mois'][$mois_key];
                        } elseif ($l['type'] === 'tresorerie_vente_eau' && isset($l['mois'][$mois_key])) {
                            $tresorerie_vente_eau = $l['mois'][$mois_key];
                        } elseif (isset($l['activite']) && $l['activite'] === 'autre') {
                            if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                                $autre_recettes += $l['mois'][$mois_key];
                            } elseif ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                                $autre_charges += $l['mois'][$mois_key];
                            }
                        }
                    }
                    $montant = $tresorerie_branchements + $tresorerie_vente_eau + $autre_recettes - $autre_charges;
                }

                $ligne['mois'][$mois_key] = $montant;
                $cumule += $montant;
            }
            $ligne['cumule'] = $cumule;
        }
    }

    // Debug temporaire - à supprimer après vérification
    // error_log("DEBUG lignes charges: " . print_r(array_filter($lignes, function($l) { return $l['type'] === 'charge'; }), true));

    return array(
        'annee' => $annee,
        'lignes' => $lignes
    );
}

function generateCompteRenduTableauMois($id_aep, $mois_debut, $mois_fin)
{
    // Utiliser la fonction existante getCompteRenduFinancier qui gère déjà les périodes en mois
    $compte_rendu = ConfigCompteRenduFinancier::getCompteRenduFinancier($id_aep, $mois_debut, $mois_fin);

    // Générer la liste des mois dans la période
    $mois_list = array();
    $date_debut = new DateTime($mois_debut . '-01');
    $date_fin = new DateTime($mois_fin . '-01');
    $date_fin->modify('last day of this month');

    $current = clone $date_debut;
    while ($current <= $date_fin) {
        $mois_key = $current->format('Y-m');
        $mois_list[] = $mois_key;
        $current->modify('+1 month');
    }

    // Récupérer les libellés, codes budgétaires et activités associées configurés
    $config_recouvrements = ConfigCompteRenduFinancier::getConfig('recouvrements', $id_aep);
    $config_branchements = ConfigCompteRenduFinancier::getConfig('branchements', $id_aep);
    $config_redevances = ConfigCompteRenduFinancier::getConfig('redevances', $id_aep);

    $libelle_recouvrements = $config_recouvrements ? $config_recouvrements['libelle'] : 'Recouvrements';
    $libelle_branchements = $config_branchements ? $config_branchements['libelle'] : 'Branchements';
    $libelle_redevances = $config_redevances ? $config_redevances['libelle'] : 'Redevances';

    $code_budgetaire_recouvrements = $config_recouvrements && !empty($config_recouvrements['code_budgetaire']) ? $config_recouvrements['code_budgetaire'] : null;
    $code_budgetaire_branchements = $config_branchements && !empty($config_branchements['code_budgetaire']) ? $config_branchements['code_budgetaire'] : null;
    $code_budgetaire_redevances = $config_redevances && !empty($config_redevances['code_budgetaire']) ? $config_redevances['code_budgetaire'] : null;

    $activite_recouvrements = $config_recouvrements && !empty($config_recouvrements['activite_associee']) ? $config_recouvrements['activite_associee'] : 'vente_eau';
    $activite_branchements = $config_branchements && !empty($config_branchements['activite_associee']) ? $config_branchements['activite_associee'] : 'branchements';
    $activite_redevances = $config_redevances && !empty($config_redevances['activite_associee']) ? $config_redevances['activite_associee'] : 'autre';

    // Construire les lignes à partir des données du compte rendu
    @include_once("../donnees/connexion.php");
    @include_once("donnees/connexion.php");
    $bd = Connexion::connect();

    $lignes = array();

    // RECETTES
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_recouvrements ? $code_budgetaire_recouvrements : '',
        'nom_categorie' => $libelle_recouvrements,
        'type' => 'recette',
        'mois' => array(),
        'cumule' => 0,
        'activite' => $activite_recouvrements
    );
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_branchements ? $code_budgetaire_branchements : '',
        'nom_categorie' => $libelle_branchements,
        'type' => 'recette',
        'mois' => array(),
        'cumule' => 0,
        'activite' => $activite_branchements
    );

    // Ajouter les recettes des flux manuels
    $categories_recettes = array();
    foreach ($compte_rendu as $mois_data) {
        foreach ($mois_data['recettes'] as $libelle => $montant) {
            // Exclure les recouvrements et branchements (vérifier par libellé ET code budgétaire)
            $is_recouvrement = ($libelle === $libelle_recouvrements || $libelle === $code_budgetaire_recouvrements);
            $is_branchement = ($libelle === $libelle_branchements || $libelle === $code_budgetaire_branchements);

            if (!isset($categories_recettes[$libelle]) && !$is_recouvrement && !$is_branchement) {
                // Récupérer le nom de la catégorie depuis la base (filtrer par id_aep)
                $query_cat = "SELECT nom, code_budgetaire, activite_associee FROM categorie_flux_manuel WHERE (code_budgetaire = ? OR nom = ?) AND (id_aep = ? OR id_aep IS NULL) LIMIT 1";
                $stmt_cat = $bd->prepare($query_cat);
                $stmt_cat->execute(array($libelle, $libelle, $id_aep));
                $cat_info = $stmt_cat->fetch(PDO::FETCH_ASSOC);
                $categories_recettes[$libelle] = array(
                    'cle_origine' => $libelle,
                    'code_budgetaire' => $cat_info ? ($cat_info['code_budgetaire'] ?: '') : '',
                    'nom_categorie' => $cat_info ? $cat_info['nom'] : $libelle,
                    'activite' => $cat_info ? $cat_info['activite_associee'] : 'autre'
                );
            }
        }
    }
    foreach ($categories_recettes as $libelle => $cat_info) {
        $lignes[] = array(
            'cle_origine' => $libelle,
            'code_budgetaire' => $cat_info['code_budgetaire'],
            'nom_categorie' => $cat_info['nom_categorie'],
            'type' => 'recette',
            'mois' => array(),
            'cumule' => 0,
            'activite' => $cat_info['activite']
        );
    }

    // Total recettes
    $lignes[] = array('code_budgetaire' => '', 'nom_categorie' => 'Total recettes', 'type' => 'total_recette', 'mois' => array(), 'cumule' => 0);

    // DEPENSES - Redevances sur branchements
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_redevances ? $code_budgetaire_redevances : '',
        'nom_categorie' => $libelle_redevances . ' (Branchements)',
        'type' => 'charge',
        'mois' => array(),
        'cumule' => 0,
        'activite' => 'branchements',
        'base_calcul' => 'branchements'
    );

    // DEPENSES - Redevances sur vente d'eau
    $lignes[] = array(
        'code_budgetaire' => $code_budgetaire_redevances ? $code_budgetaire_redevances : '',
        'nom_categorie' => $libelle_redevances . ' (Vente d\'eau)',
        'type' => 'charge',
        'mois' => array(),
        'cumule' => 0,
        'activite' => 'vente_eau',
        'base_calcul' => 'vente_eau'
    );

    // Ajouter les charges des flux manuels
    $categories_charges = array();
    foreach ($compte_rendu as $mois_data) {
        foreach ($mois_data['charges'] as $libelle => $data) {
            // Gérer l'ancien format (montant direct) et le nouveau format (array)
            if (is_array($data)) {
                $nom_charge = isset($data['nom']) ? $data['nom'] : $libelle;
            } else {
                $nom_charge = $libelle;
            }

            // Ignorer les redevances (elles sont déjà ajoutées séparément) - vérifier par libellé ET code budgétaire
            $is_redevance = (strpos($nom_charge, $libelle_redevances) !== false) ||
                ($code_budgetaire_redevances && strpos($libelle, $code_budgetaire_redevances) !== false);

            if (!$is_redevance && !isset($categories_charges[$libelle])) {
                // Récupérer le nom de la catégorie depuis la base (filtrer par id_aep)
                $query_cat = "SELECT nom, code_budgetaire, activite_associee FROM categorie_flux_manuel WHERE (code_budgetaire = ? OR nom = ?) AND (id_aep = ? OR id_aep IS NULL) LIMIT 1";
                $stmt_cat = $bd->prepare($query_cat);
                $stmt_cat->execute(array($libelle, $libelle, $id_aep));
                $cat_info = $stmt_cat->fetch(PDO::FETCH_ASSOC);

                // Si c'est une redevance (déjà gérée séparément), on l'ignore
                if ($cat_info && strpos($cat_info['nom'], $libelle_redevances) !== false) {
                    continue;
                }

                $categories_charges[$libelle] = array(
                    'cle_origine' => $libelle,
                    'code_budgetaire' => $cat_info ? ($cat_info['code_budgetaire'] ?: '') : '',
                    'nom_categorie' => $cat_info ? $cat_info['nom'] : (is_array($data) ? $nom_charge : $libelle),
                    'activite' => $cat_info ? $cat_info['activite_associee'] : 'autre'
                );
            }
        }
    }
    foreach ($categories_charges as $libelle => $cat_info) {
        $lignes[] = array(
            'cle_origine' => $libelle,
            'code_budgetaire' => $cat_info['code_budgetaire'],
            'nom_categorie' => $cat_info['nom_categorie'],
            'type' => 'charge',
            'mois' => array(),
            'cumule' => 0,
            'activite' => $cat_info['activite']
        );
    }

    // Total dépenses
    $lignes[] = array('code_budgetaire' => '', 'nom_categorie' => 'Total dépenses', 'type' => 'total_charge', 'mois' => array(), 'cumule' => 0);

    // Résultats
    $lignes[] = array('code_budgetaire' => '', 'nom_categorie' => 'Résultats', 'type' => 'resultat', 'mois' => array(), 'cumule' => 0);

    // LIGNES DE TRÉSORERIE
    $lignes[] = array('code_budgetaire' => '', 'nom_categorie' => 'Trésorerie des branchements', 'type' => 'tresorerie_branchements', 'mois' => array(), 'cumule' => 0);
    $lignes[] = array('code_budgetaire' => '', 'nom_categorie' => 'Trésorerie de la vente d\'eau', 'type' => 'tresorerie_vente_eau', 'mois' => array(), 'cumule' => 0);
    $lignes[] = array('code_budgetaire' => '', 'nom_categorie' => 'Trésorerie', 'type' => 'tresorerie', 'mois' => array(), 'cumule' => 0);

    // Remplir les données par mois
    foreach ($lignes as &$ligne) {
        if (in_array($ligne['type'], array('total_recette', 'total_charge', 'resultat'))) {
            continue;
        }

        $cumule = 0;
        foreach ($mois_list as $mois_key) {
            $montant = 0;

            if (isset($compte_rendu[$mois_key])) {
                $mois_data = $compte_rendu[$mois_key];

                // Identifier la ligne par clé originale, code budgétaire ou nom
                $cle_origine = isset($ligne['cle_origine']) ? $ligne['cle_origine'] : '';
                $code_ligne = isset($ligne['code_budgetaire']) ? $ligne['code_budgetaire'] : '';
                $nom_ligne = isset($ligne['nom_categorie']) ? $ligne['nom_categorie'] : '';
                $base_calcul = isset($ligne['base_calcul']) ? $ligne['base_calcul'] : null;

                if ($nom_ligne === $libelle_recouvrements || $code_ligne === $code_budgetaire_recouvrements) {
                    // Pour recouvrements, utiliser le code budgétaire comme clé si disponible
                    $cle_recouvrements = $code_budgetaire_recouvrements ? $code_budgetaire_recouvrements : $libelle_recouvrements;
                    $montant = isset($mois_data['recettes'][$cle_recouvrements]) ? $mois_data['recettes'][$cle_recouvrements] : 0;
                } elseif ($nom_ligne === $libelle_branchements || $code_ligne === $code_budgetaire_branchements) {
                    // Pour branchements, utiliser le code budgétaire comme clé si disponible
                    $cle_branchements = $code_budgetaire_branchements ? $code_budgetaire_branchements : $libelle_branchements;
                    $montant = isset($mois_data['recettes'][$cle_branchements]) ? $mois_data['recettes'][$cle_branchements] : 0;
                } elseif (strpos($nom_ligne, $libelle_redevances) !== false && $base_calcul === 'branchements') {
                    // Redevances sur branchements
                    $cle = ($code_budgetaire_redevances ? $code_budgetaire_redevances : $libelle_redevances) . '_branchements';
                    $montant = isset($mois_data['charges'][$cle]) ? (is_array($mois_data['charges'][$cle]) ? $mois_data['charges'][$cle]['montant'] : $mois_data['charges'][$cle]) : 0;
                } elseif (strpos($nom_ligne, $libelle_redevances) !== false && $base_calcul === 'vente_eau') {
                    // Redevances sur vente d'eau
                    $cle = ($code_budgetaire_redevances ? $code_budgetaire_redevances : $libelle_redevances) . '_vente_eau';
                    $montant = isset($mois_data['charges'][$cle]) ? (is_array($mois_data['charges'][$cle]) ? $mois_data['charges'][$cle]['montant'] : $mois_data['charges'][$cle]) : 0;
                } elseif ($ligne['type'] === 'recette') {
                    // Chercher par clé originale d'abord, puis code budgétaire ou nom
                    $montant = 0;
                    if ($cle_origine !== '' && isset($mois_data['recettes'][$cle_origine])) {
                        $montant = $mois_data['recettes'][$cle_origine];
                    } elseif ($code_ligne !== '' && isset($mois_data['recettes'][$code_ligne])) {
                        $montant = $mois_data['recettes'][$code_ligne];
                    } elseif (isset($mois_data['recettes'][$nom_ligne])) {
                        $montant = $mois_data['recettes'][$nom_ligne];
                    }
                } elseif ($ligne['type'] === 'charge') {
                    // Chercher par clé originale d'abord, puis code budgétaire ou nom
                    $montant = 0;
                    if ($cle_origine !== '' && isset($mois_data['charges'][$cle_origine])) {
                        $montant = $mois_data['charges'][$cle_origine];
                    } elseif ($code_ligne !== '' && isset($mois_data['charges'][$code_ligne])) {
                        $montant = $mois_data['charges'][$code_ligne];
                    } elseif (isset($mois_data['charges'][$nom_ligne])) {
                        $montant = $mois_data['charges'][$nom_ligne];
                    }
                }
            }

            // S'assurer que $montant est un nombre
            if (is_array($montant)) {
                $montant = isset($montant['montant']) ? floatval($montant['montant']) : 0;
            } else {
                $montant = floatval($montant);
            }

            $ligne['mois'][$mois_key] = $montant;
            $cumule += $montant;
        }
        $ligne['cumule'] = $cumule;
    }

    // Calculer les totaux, résultats et trésoreries
    foreach ($lignes as &$ligne) {
        if (in_array($ligne['type'], array('total_recette', 'total_charge', 'resultat', 'tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie'))) {
            $cumule = 0;
            foreach ($mois_list as $mois_key) {
                $montant = 0;

                if ($ligne['type'] === 'total_recette') {
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                            $montant += $l['mois'][$mois_key];
                        }
                    }
                } elseif ($ligne['type'] === 'total_charge') {
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                            $montant += $l['mois'][$mois_key];
                        }
                    }
                } elseif ($ligne['type'] === 'resultat') {
                    $total_rec = 0;
                    $total_charge = 0;
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'total_recette' && isset($l['mois'][$mois_key])) {
                            $total_rec = $l['mois'][$mois_key];
                        }
                        if ($l['type'] === 'total_charge' && isset($l['mois'][$mois_key])) {
                            $total_charge = $l['mois'][$mois_key];
                        }
                    }
                    $montant = $total_rec - $total_charge;
                } elseif ($ligne['type'] === 'tresorerie_branchements') {
                    // Trésorerie branchements = recettes branchements (automatiques + manuelles associées) - charges associées (y compris redevances avec base_calcul='branchements')
                    $recettes_branchements = 0;
                    $charges_branchements = 0;
                    foreach ($lignes as $l) {
                        // Pour les redevances, utiliser base_calcul, sinon utiliser activite
                        $is_branchements = false;
                        if (isset($l['base_calcul']) && $l['base_calcul'] === 'branchements') {
                            $is_branchements = true;
                        } elseif (isset($l['activite']) && $l['activite'] === 'branchements') {
                            $is_branchements = true;
                        }

                        if ($is_branchements) {
                            if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                                $recettes_branchements += $l['mois'][$mois_key];
                            } elseif ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                                // Inclut les redevances avec base_calcul='branchements' et toutes les autres charges associées
                                $charges_branchements += $l['mois'][$mois_key];
                            }
                        }
                    }
                    $montant = $recettes_branchements - $charges_branchements;
                } elseif ($ligne['type'] === 'tresorerie_vente_eau') {
                    // Trésorerie vente eau = recettes recouvrements (automatiques + manuelles associées) - charges associées (y compris redevances avec base_calcul='vente_eau')
                    $recettes_vente_eau = 0;
                    $charges_vente_eau = 0;
                    foreach ($lignes as $l) {
                        // Pour les redevances, utiliser base_calcul, sinon utiliser activite
                        $is_vente_eau = false;
                        if (isset($l['base_calcul']) && $l['base_calcul'] === 'vente_eau') {
                            $is_vente_eau = true;
                        } elseif (isset($l['activite']) && $l['activite'] === 'vente_eau') {
                            $is_vente_eau = true;
                        }

                        if ($is_vente_eau) {
                            if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                                $recettes_vente_eau += $l['mois'][$mois_key];
                            } elseif ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                                // Inclut les redevances avec base_calcul='vente_eau' et toutes les autres charges associées
                                $charges_vente_eau += $l['mois'][$mois_key];
                            }
                        }
                    }
                    $montant = $recettes_vente_eau - $charges_vente_eau;
                } elseif ($ligne['type'] === 'tresorerie') {
                    // Trésorerie globale = somme des deux trésoreries + catégories "autre"
                    $tresorerie_branchements = 0;
                    $tresorerie_vente_eau = 0;
                    $autre_recettes = 0;
                    $autre_charges = 0;
                    foreach ($lignes as $l) {
                        if ($l['type'] === 'tresorerie_branchements' && isset($l['mois'][$mois_key])) {
                            $tresorerie_branchements = $l['mois'][$mois_key];
                        } elseif ($l['type'] === 'tresorerie_vente_eau' && isset($l['mois'][$mois_key])) {
                            $tresorerie_vente_eau = $l['mois'][$mois_key];
                        } elseif (isset($l['activite']) && $l['activite'] === 'autre') {
                            if ($l['type'] === 'recette' && isset($l['mois'][$mois_key])) {
                                $autre_recettes += $l['mois'][$mois_key];
                            } elseif ($l['type'] === 'charge' && isset($l['mois'][$mois_key])) {
                                $autre_charges += $l['mois'][$mois_key];
                            }
                        }
                    }
                    $montant = $tresorerie_branchements + $tresorerie_vente_eau + $autre_recettes - $autre_charges;
                }

                $ligne['mois'][$mois_key] = $montant;
                $cumule += $montant;
            }
            $ligne['cumule'] = $cumule;
        }
    }

    return array(
        'mois_list' => $mois_list,
        'lignes' => $lignes
    );
}

function moneyFormatter($montant)
{
    return number_format($montant, 0, ',', ' ');
}

$mois_noms = array(
    '',
    'Janvier',
    'Février',
    'Mars',
    'Avril',
    'Mai',
    'Juin',
    'Juillet',
    'Août',
    'Septembre',
    'Octobre',
    'Novembre',
    'Décembre'
);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold">
            Compte d'Exploitation
            <?php if ($type_periode === 'annee'): ?>
                - Année <?php echo $annee; ?>
            <?php else: ?>
                - Période <?php echo date('m/Y', strtotime($mois_debut . '-01')); ?> à
                <?php echo date('m/Y', strtotime($mois_fin . '-01')); ?>
            <?php endif; ?>
        </h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="?page=config_compte_rendu" class="btn btn-outline-primary" data-bs-toggle="tooltip"
                data-bs-placement="top" title="Configurer les libellés des catégories">
                <i class="fas fa-cog me-2"></i>Configurer
            </a>

            <!-- Sélecteur de type de période -->
            <form method="get" action="" class="d-inline">
                <input type="hidden" name="page" value="compte_rendu_tableau">
                <select name="type_periode" class="form-select d-inline-block" style="width: auto;"
                    onchange="this.form.submit()">
                    <option value="annee" <?php echo $type_periode === 'annee' ? 'selected' : ''; ?>>Par Année</option>
                    <option value="mois" <?php echo $type_periode === 'mois' ? 'selected' : ''; ?>>Par Période (mois)
                    </option>
                </select>
            </form>

            <?php if ($type_periode === 'annee'): ?>
                <!-- Sélecteur d'année -->
                <form method="get" action="" class="d-inline">
                    <input type="hidden" name="page" value="compte_rendu_tableau">
                    <input type="hidden" name="type_periode" value="annee">
                    <select name="annee" class="form-select d-inline-block" style="width: auto;"
                        onchange="this.form.submit()">
                        <?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): ?>
                            <option value="<?php echo $a; ?>" <?php echo $a == $annee ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </form>
            <?php else: ?>
                <!-- Sélecteurs de mois de début et fin -->
                <form method="get" action="" class="d-inline">
                    <input type="hidden" name="page" value="compte_rendu_tableau">
                    <input type="hidden" name="type_periode" value="mois">
                    <div class="d-inline-flex align-items-center gap-2">
                        <label for="mois_debut_select" class="form-label mb-0">Mois début:</label>
                        <input type="month" id="mois_debut_select" name="mois_debut" class="form-control d-inline-block"
                            style="width: auto;" value="<?php echo htmlspecialchars($mois_debut); ?>"
                            onchange="this.form.submit()">
                        <label for="mois_fin_select" class="form-label mb-0">Mois fin:</label>
                        <input type="month" id="mois_fin_select" name="mois_fin" class="form-control d-inline-block"
                            style="width: auto;" value="<?php echo htmlspecialchars($mois_fin); ?>"
                            onchange="this.form.submit()">
                    </div>
                </form>
            <?php endif; ?>

            <?php
            // Préparer les données pour l'export
            $mois_noms = array(
                '',
                'Janvier',
                'Février',
                'Mars',
                'Avril',
                'Mai',
                'Juin',
                'Juillet',
                'Août',
                'Septembre',
                'Octobre',
                'Novembre',
                'Décembre'
            );

            $data_export = array();
            foreach ($donnees['lignes'] as $ligne) {
                $code_budgetaire = isset($ligne['code_budgetaire']) && !empty($ligne['code_budgetaire']) ? $ligne['code_budgetaire'] : '-';
                $nom_categorie = isset($ligne['nom_categorie']) ? $ligne['nom_categorie'] : (isset($ligne['libelle']) ? $ligne['libelle'] : '-');
                $row = array('Code budgétaire' => $code_budgetaire, 'Nom de la catégorie' => $nom_categorie);
                if ($type_periode === 'annee') {
                    for ($m = 1; $m <= 12; $m++) {
                        $mois_key = sprintf('%04d-%02d', $annee, $m);
                        $montant = isset($ligne['mois'][$mois_key]) ? $ligne['mois'][$mois_key] : 0;
                        $row[$mois_noms[$m]] = number_format($montant, 0, ',', ' ');
                    }
                } else {
                    foreach ($donnees['mois_list'] as $mois_key) {
                        $montant = isset($ligne['mois'][$mois_key]) ? $ligne['mois'][$mois_key] : 0;
                        $row[date('M Y', strtotime($mois_key . '-01'))] = number_format($montant, 0, ',', ' ');
                    }
                }
                $row['Cumulé'] = number_format($ligne['cumule'], 0, ',', ' ');
                $data_export[] = $row;
            }

            @include_once("../donnees/manager.php");
            @include_once("donnees/manager.php");
            $export_filename = ($type_periode === 'annee') ? 'compte_rendu_financier_' . $annee . '.csv' : 'compte_rendu_financier_' . $mois_debut . '_a_' . $mois_fin . '.csv';
            $export_tooltip = ($type_periode === 'annee') ? "Export du compte d'exploitation " . $annee : "Export du compte d'exploitation de " . date('m/Y', strtotime($mois_debut . '-01')) . " à " . date('m/Y', strtotime($mois_fin . '-01'));
            create_csv_exportation_button($data_export, $export_filename, $export_tooltip);
            ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive" id="tableau_compte_rendu">
                <table class="table table-bordered table-sm mb-0" style="font-size: 0.85rem;">
                    <thead class="table-dark">
                        <tr>
                            <th
                                style="min-width: 120px; max-width: 120px; width: 120px; position: sticky; left: 0; background: #212529; z-index: 10; box-shadow: 2px 0 5px rgba(0,0,0,0.2); padding: 0.5rem;">
                                Code budgétaire</th>
                            <th
                                style="min-width: 180px; max-width: 180px; width: 180px; position: sticky; left: 120px; background: #212529; z-index: 10; box-shadow: 2px 0 5px rgba(0,0,0,0.2); padding: 0.5rem;">
                                Nom de la catégorie</th>
                            <?php if ($type_periode === 'annee'): ?>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <th class="text-center" style="min-width: 80px; padding: 0.5rem;">
                                        <?php echo substr($mois_noms[$m], 0, 4); ?>
                                    </th>
                                <?php endfor; ?>
                            <?php else: ?>
                                <?php foreach ($donnees['mois_list'] as $mois_key): ?>
                                    <th class="text-center" style="min-width: 80px; padding: 0.5rem;">
                                        <?php echo date('M Y', strtotime($mois_key . '-01')); ?>
                                    </th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <th class="text-center bg-primary" style="min-width: 100px; padding: 0.5rem;">Cumulé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $in_recettes = true;
                        $first_row = true;
                        foreach ($donnees['lignes'] as $ligne):
                            // Calculer le nombre de colonnes
                            $nb_colonnes = ($type_periode === 'annee') ? 12 : count($donnees['mois_list']);
                            $colspan = $nb_colonnes + 3; // +2 pour code budgétaire et nom catégorie, +1 pour cumulé
                        
                            // Afficher l'en-tête RECETTES au début
                            if ($first_row && $ligne['type'] === 'recette') {
                                echo '<tr><td colspan="' . $colspan . '" class="bg-light fw-bold" style="position: relative; z-index: 9;">RECETTES</td></tr>';
                                $first_row = false;
                            }
                            // Afficher l'en-tête DÉPENSES avant la première charge ou total_charge
                            if (($ligne['type'] === 'charge' || $ligne['type'] === 'total_charge') && $in_recettes) {
                                $in_recettes = false;
                                echo '<tr><td colspan="' . $colspan . '" class="bg-light fw-bold" style="position: relative; z-index: 9;">DÉPENSES</td></tr>';
                            }
                            // Afficher l'en-tête TRÉSORERIE avant les lignes de trésorerie
                            if (($ligne['type'] === 'tresorerie_branchements' || $ligne['type'] === 'tresorerie_vente_eau' || $ligne['type'] === 'tresorerie') && !isset($in_tresorerie)) {
                                $in_tresorerie = true;
                                echo '<tr><td colspan="' . $colspan . '" class="bg-light fw-bold" style="position: relative; z-index: 9;">TRÉSORERIE</td></tr>';
                            }

                            // Déterminer la classe et la couleur de fond pour la colonne catégorie
                            $row_class = '';
                            $td_bg_color = 'white'; // Valeur par défaut
                            if ($ligne['type'] === 'total_recette' || $ligne['type'] === 'total_charge') {
                                $row_class = 'table-info fw-bold';
                                $td_bg_color = '#d1ecf1';
                            } elseif ($ligne['type'] === 'resultat') {
                                $row_class = 'table-warning fw-bold';
                                $td_bg_color = '#fff3cd';
                            } elseif (in_array($ligne['type'], array('tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie'))) {
                                $row_class = 'table-success fw-bold';
                                $td_bg_color = '#d4edda';
                            }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td style="position: sticky; left: 0; background-color: <?php echo $td_bg_color; ?> !important; z-index: 5; box-shadow: 2px 0 5px rgba(0,0,0,0.1); min-width: 120px; max-width: 120px; width: 120px; padding: 0.5rem;"
                                    class="fw-bold">
                                    <?php echo htmlspecialchars(isset($ligne['code_budgetaire']) && !empty($ligne['code_budgetaire']) ? $ligne['code_budgetaire'] : '-'); ?>
                                </td>
                                <td style="position: sticky; left: 120px; background-color: <?php echo $td_bg_color; ?> !important; z-index: 5; box-shadow: 2px 0 5px rgba(0,0,0,0.1); min-width: 180px; max-width: 180px; width: 180px; padding: 0.5rem;"
                                    class="fw-bold">
                                    <?php echo htmlspecialchars(isset($ligne['nom_categorie']) ? $ligne['nom_categorie'] : (isset($ligne['libelle']) ? $ligne['libelle'] : '-')); ?>
                                </td>
                                <?php
                                if ($type_periode === 'annee') {
                                    for ($m = 1; $m <= 12; $m++):
                                        $mois_key = sprintf('%04d-%02d', $annee, $m);
                                        $montant = isset($ligne['mois'][$mois_key]) ? $ligne['mois'][$mois_key] : 0;
                                        $text_class = '';
                                        if (in_array($ligne['type'], array('resultat', 'tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie')) && $montant < 0) {
                                            $text_class = 'text-danger';
                                        } elseif (in_array($ligne['type'], array('resultat', 'tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie')) && $montant > 0) {
                                            $text_class = 'text-success';
                                        }
                                        ?>
                                        <td class="text-end <?php echo $text_class; ?>" style="padding: 0.5rem;">
                                            <?php echo moneyFormatter($montant); ?>
                                        </td>
                                        <?php
                                    endfor;
                                } else {
                                    // Pour les périodes en mois
                                    foreach ($donnees['mois_list'] as $mois_key):
                                        $montant = isset($ligne['mois'][$mois_key]) ? $ligne['mois'][$mois_key] : 0;
                                        $text_class = '';
                                        if (in_array($ligne['type'], array('resultat', 'tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie')) && $montant < 0) {
                                            $text_class = 'text-danger';
                                        } elseif (in_array($ligne['type'], array('resultat', 'tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie')) && $montant > 0) {
                                            $text_class = 'text-success';
                                        }
                                        ?>
                                        <td class="text-end <?php echo $text_class; ?>" style="padding: 0.5rem;">
                                            <?php echo moneyFormatter($montant); ?>
                                        </td>
                                        <?php
                                    endforeach;
                                }
                                ?>
                                <td class="text-end bg-primary text-white fw-bold <?php echo in_array($ligne['type'], array('resultat', 'tresorerie_branchements', 'tresorerie_vente_eau', 'tresorerie')) ? ($ligne['cumule'] < 0 ? 'text-danger' : 'text-success') : ''; ?>"
                                    style="padding: 0.5rem;">
                                    <?php echo moneyFormatter($ligne['cumule']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Note :</strong> Les montants sont exprimés en FCFA. Les résultats négatifs indiquent une perte pour le
        mois concerné.
    </div>

    <!-- Graphique en courbes -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Évolution financière</h5>
        </div>
        <div class="card-body">
            <canvas id="compteExploitationChart" style="max-height: 400px;"></canvas>
        </div>
    </div>
</div>

<style>
    /* Correction du scroll horizontal - la colonne catégorie reste fixe et masque le contenu */
    #tableau_compte_rendu {
        position: relative;
        overflow-x: auto;
        overflow-y: visible;
    }

    #tableau_compte_rendu table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    /* Colonne catégorie fixe avec fond opaque pour masquer le contenu qui passe en dessous */
    #tableau_compte_rendu th:first-child,
    #tableau_compte_rendu td:first-child {
        position: sticky;
        left: 0;
        z-index: 10;
        background-color: inherit;
        background-clip: padding-box;
        min-width: 150px;
        max-width: 150px;
        width: 150px;
    }

    /* En-tête de la colonne catégorie */
    #tableau_compte_rendu thead th:first-child {
        z-index: 11;
        background-color: #212529 !important;
        color: white;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
    }

    /* Corps du tableau - colonne catégorie avec fond opaque */
    #tableau_compte_rendu tbody tr {
        background-color: white;
    }

    #tableau_compte_rendu tbody td:first-child {
        background-color: white !important;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    }

    /* Lignes avec classes spéciales */
    #tableau_compte_rendu tbody tr.table-info td:first-child {
        background-color: #d1ecf1 !important;
    }

    #tableau_compte_rendu tbody tr.table-warning td:first-child {
        background-color: #fff3cd !important;
    }

    #tableau_compte_rendu tbody tr.table-success td:first-child {
        background-color: #d1e7dd !important;
    }

    #tableau_compte_rendu tbody tr.table-danger td:first-child {
        background-color: #f8d7da !important;
    }

    /* Lignes d'en-tête de section */
    #tableau_compte_rendu tbody tr td.bg-light {
        position: relative;
        z-index: 9;
    }

    /* Assurer que le contenu qui passe en dessous est vraiment masqué */
    #tableau_compte_rendu tbody td:not(:first-child) {
        position: relative;
        z-index: 1;
    }

    @media print {

        .btn,
        form,
        .alert {
            display: none !important;
        }

        .table {
            font-size: 0.8rem !important;
        }

        #tableau_compte_rendu {
            overflow: visible !important;
        }

        #tableau_compte_rendu th:first-child,
        #tableau_compte_rendu td:first-child {
            position: static !important;
        }

        #compteExploitationChart {
            display: none !important;
        }
    }
</style>

<?php
// Préparer les données pour le graphique
$chart_labels = array();
$chart_data_recettes = array();
$chart_data_depenses = array();
$chart_data_tresorerie_branchements = array();
$chart_data_tresorerie_vente_eau = array();
$chart_data_tresorerie = array();

// Extraire les données des lignes importantes
$total_recettes = null;
$total_depenses = null;
$tresorerie_branchements = null;
$tresorerie_vente_eau = null;
$tresorerie = null;

foreach ($donnees['lignes'] as $ligne) {
    if ($ligne['type'] === 'total_recette') {
        $total_recettes = $ligne;
    } elseif ($ligne['type'] === 'total_charge') {
        $total_depenses = $ligne;
    } elseif ($ligne['type'] === 'tresorerie_branchements') {
        $tresorerie_branchements = $ligne;
    } elseif ($ligne['type'] === 'tresorerie_vente_eau') {
        $tresorerie_vente_eau = $ligne;
    } elseif ($ligne['type'] === 'tresorerie') {
        $tresorerie = $ligne;
    }
}

// Construire les labels et les données
if ($type_periode === 'annee') {
    for ($m = 1; $m <= 12; $m++) {
        $mois_key = sprintf('%04d-%02d', $annee, $m);
        $chart_labels[] = $mois_noms[$m];

        $chart_data_recettes[] = $total_recettes && isset($total_recettes['mois'][$mois_key]) ? floatval($total_recettes['mois'][$mois_key]) : 0;
        $chart_data_depenses[] = $total_depenses && isset($total_depenses['mois'][$mois_key]) ? floatval($total_depenses['mois'][$mois_key]) : 0;
        $chart_data_tresorerie_branchements[] = $tresorerie_branchements && isset($tresorerie_branchements['mois'][$mois_key]) ? floatval($tresorerie_branchements['mois'][$mois_key]) : 0;
        $chart_data_tresorerie_vente_eau[] = $tresorerie_vente_eau && isset($tresorerie_vente_eau['mois'][$mois_key]) ? floatval($tresorerie_vente_eau['mois'][$mois_key]) : 0;
        $chart_data_tresorerie[] = $tresorerie && isset($tresorerie['mois'][$mois_key]) ? floatval($tresorerie['mois'][$mois_key]) : 0;
    }
} else {
    foreach ($donnees['mois_list'] as $mois_key) {
        $chart_labels[] = date('M Y', strtotime($mois_key . '-01'));

        $chart_data_recettes[] = $total_recettes && isset($total_recettes['mois'][$mois_key]) ? floatval($total_recettes['mois'][$mois_key]) : 0;
        $chart_data_depenses[] = $total_depenses && isset($total_depenses['mois'][$mois_key]) ? floatval($total_depenses['mois'][$mois_key]) : 0;
        $chart_data_tresorerie_branchements[] = $tresorerie_branchements && isset($tresorerie_branchements['mois'][$mois_key]) ? floatval($tresorerie_branchements['mois'][$mois_key]) : 0;
        $chart_data_tresorerie_vente_eau[] = $tresorerie_vente_eau && isset($tresorerie_vente_eau['mois'][$mois_key]) ? floatval($tresorerie_vente_eau['mois'][$mois_key]) : 0;
        $chart_data_tresorerie[] = $tresorerie && isset($tresorerie['mois'][$mois_key]) ? floatval($tresorerie['mois'][$mois_key]) : 0;
    }
}
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('compteExploitationChart');
        if (!ctx) return;

        const chartData = {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Total Recettes',
                    data: <?php echo json_encode($chart_data_recettes); ?>,
                    borderColor: 'rgb(40, 167, 69)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Total Dépenses',
                    data: <?php echo json_encode($chart_data_depenses); ?>,
                    borderColor: 'rgb(220, 53, 69)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Trésorerie Branchements',
                    data: <?php echo json_encode($chart_data_tresorerie_branchements); ?>,
                    borderColor: 'rgb(0, 123, 255)',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Trésorerie Vente d\'eau',
                    data: <?php echo json_encode($chart_data_tresorerie_vente_eau); ?>,
                    borderColor: 'rgb(255, 193, 7)',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Trésorerie',
                    data: <?php echo json_encode($chart_data_tresorerie); ?>,
                    borderColor: 'rgb(108, 117, 125)',
                    backgroundColor: 'rgba(108, 117, 125, 0.1)',
                    tension: 0.4,
                    fill: false,
                    borderWidth: 2
                }
            ]
        };

        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat('fr-FR', {
                                    style: 'decimal',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(context.parsed.y) + ' FCFA';
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return new Intl.NumberFormat('fr-FR', {
                                    style: 'decimal',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        },
                        title: {
                            display: true,
                            text: 'Montant (FCFA)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Période'
                        }
                    }
                }
            }
        });
    });
</script>