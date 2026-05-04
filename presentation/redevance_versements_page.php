<?php
/**
 * Page de gestion des versements pour une redevance
 * Permet de faire des versements globaux (sans mois spécifique)
 */

@include_once("../donnees/redevance.php");
@include_once("donnees/redevance.php");
@include_once("../donnees/versements.php");
@include_once("donnees/versements.php");
@include_once("../donnees/mois_facturation.php");
@include_once("donnees/mois_facturation.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$id_redevance = isset($_GET['id_redevance']) ? (int) $_GET['id_redevance'] : 0;

if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné.</div>';
    $redevance = null;
} else if (!$id_redevance) {
    $message = '<div class="alert alert-danger">Redevance non spécifiée.</div>';
    $redevance = null;
} else {
    $redevance = Redevance::getRedevance($id_redevance);
    if (!$redevance || $redevance->id_aep != $aepId) {
        $message = '<div class="alert alert-danger">Redevance introuvable.</div>';
        $redevance = null;
    } else {
        $message = '';
    }
}

// Totaux redevance brute / nette (vente eau) sur tous les mois
$montantTotalEstimatif = 0;
$montantTotalEstimatifPondere = 0;
$montantTotalVerse = 0;
$resteTotalAVerser = 0;
$resteTotalPondere = null;
$donneesMois = array(); // Détail par mois
$ponderationCumulFactureTtc = 0.0;
$ponderationCumulVerse = 0.0;
$ponderationCumulEntretienTtc = 0.0;
$ponderationCumulRecouvreNet = 0.0;

if ($redevance && $aepId) {
    if ($redevance->base_calcul == 'branchements') {
        // Pour les branchements, utiliser uniquement les mois uniques de branchement_abonne
        // Calculer tout en une seule requête selon la structure fournie
        $mois_debut = $redevance->mois_debut ? $redevance->mois_debut : '1900-01';

        $moisBranchements = Manager::prepare_query(
            "SELECT 
                b.mois, 
                SUM(IFNULL(b.versement_fcfa, 0)) as total_facture, 
                COUNT(b.mois) as nombre_branchement,
                COALESCE(
                    CASE WHEN re.type_calcul = 'montant_fixe' 
                         THEN COUNT(b.mois) * IFNULL(re.montant_par_m3, 0)
                         ELSE NULL
                    END,
                    CASE WHEN re.type_calcul = 'pourcentage' 
                         THEN SUM(IFNULL(b.versement_fcfa, 0)) * IFNULL(re.pourcentage, 0) / 100
                         ELSE NULL
                    END,
                    0
                ) as montant_estimatif
             FROM branchement_abonne b
             INNER JOIN abone a ON b.id_abone = a.id
             INNER JOIN reseau r ON a.id_reseau = r.id
             INNER JOIN redevance re ON b.mois >= re.mois_debut AND re.id = ?
             WHERE b.mois >= ? AND r.id_aep = ?
             GROUP BY b.mois, re.type_calcul, re.montant_par_m3, re.pourcentage
             ORDER BY b.mois DESC",
            array(
                $id_redevance,
                $mois_debut,
                $redevance->id_aep
            )
        )->fetchAll();

        // Calculer le détail par mois de branchement
        foreach ($moisBranchements as $moisBranchement) {
            $moisBranchementStr = $moisBranchement['mois'];

            // Toutes les valeurs sont déjà calculées dans la requête
            $quantite = isset($moisBranchement['nombre_branchement']) ? (int) $moisBranchement['nombre_branchement'] : 0;
            $montant_total_facture = isset($moisBranchement['total_facture']) ? (float) $moisBranchement['total_facture'] : 0;
            $montant_estimatif = isset($moisBranchement['montant_estimatif']) ? (float) $moisBranchement['montant_estimatif'] : 0;

            $montantTotalEstimatif += $montant_estimatif;

            // Montant versé affiché : somme des versements dont la date (année-mois) = mois de la ligne (comme l'historique global, sans exiger id_mois_facturation)
            $resultVerseBr = Manager::prepare_query(
                "SELECT COALESCE(SUM(montant), 0) AS verse_mois
                 FROM versements
                 WHERE id_redevance = ?
                   AND DATE_FORMAT(date_versement, '%Y-%m') = ?",
                array($id_redevance, $moisBranchementStr)
            )->fetch();
            $verseMois = $resultVerseBr ? (float) $resultVerseBr['verse_mois'] : 0.0;

            $donneesMois[] = array(
                'mois' => array('mois' => $moisBranchementStr, 'id' => null),
                'montant_estimatif' => $montant_estimatif,
                'montant_verse' => $verseMois,
                'reste_a_verser' => max(0, $montant_estimatif - $verseMois),
                'quantite' => $quantite,
                'montant_total_facture' => $montant_total_facture
            );
        }
    } else {
        // Pour vente_eau, utiliser les mois de facturation comme avant
        $moisFacturation = Manager::prepare_query(
            "SELECT m.*, c.prix_metre_cube_eau 
             FROM mois_facturation m 
             INNER JOIN constante_reseau c ON m.id_constante = c.id 
             WHERE c.id_aep = ? AND m.mois >= ?
             ORDER BY m.mois DESC",
            array($aepId, $redevance->mois_debut ? $redevance->mois_debut : '1900-01')
        )->fetchAll();

        // Calculer le détail par mois
        foreach ($moisFacturation as $mois) {
            $montant_estimatif = Redevance::calculerMontantEstimatif($id_redevance, $mois['id']);
            $montantTotalEstimatif += $montant_estimatif;

            // Montant versé par ligne : même mois calendaire (YYYY-MM) que le mois de facturation, d'après date_versement (inclut les versements « globaux » sans id_mois_facturation)
            $resultMois = Manager::prepare_query(
                "SELECT COALESCE(SUM(montant), 0) AS verse_mois
                 FROM versements
                 WHERE id_redevance = ?
                   AND DATE_FORMAT(date_versement, '%Y-%m') = ?",
                array($id_redevance, $mois['mois'])
            )->fetch();

            $verseMois = $resultMois ? (float) $resultMois['verse_mois'] : 0.0;

            // Récupérer les données de base pour le calcul (m³)
            $stats = Manager::prepare_query(
                "SELECT 
                    SUM(v.consommation) as total_conso,
                    SUM(v.montant_conso) as montant_total_facture
                 FROM vue_abones_facturation v
                 INNER JOIN abone a ON v.id_abone = a.id
                 INNER JOIN reseau r ON a.id_reseau = r.id
                 WHERE v.id_mois_facturation = ? AND r.id_aep = ?",
                array($mois['id'], $redevance->id_aep)
            )->fetch();

            $quantite = $stats ? (float) $stats['total_conso'] : 0;
            $montant_total_facture = $stats ? (float) $stats['montant_total_facture'] : 0;

            $stRec = Redevance::getStatFacturationRecouvrementMois((int) $mois['id'], $redevance->id_aep);
            $tauxRec = $stRec['taux'];
            $montantPondere = $montant_estimatif * $tauxRec;
            $montantTotalEstimatifPondere += $montantPondere;
            $ponderationCumulFactureTtc += $stRec['facture_ttc'];
            $ponderationCumulVerse += $stRec['verse'];
            $ponderationCumulEntretienTtc += $stRec['entretien_ttc'];
            $ponderationCumulRecouvreNet += $stRec['recouvre_net'];

            $donneesMois[] = array(
                'mois' => $mois,
                'montant_estimatif' => $montant_estimatif,
                'montant_verse' => $verseMois,
                'reste_a_verser' => max(0, $montant_estimatif - $verseMois),
                'quantite' => $quantite,
                'montant_total_facture' => $montant_total_facture,
                'taux_recouvrement' => $tauxRec,
                'montant_estimatif_pondere' => $montantPondere,
                'reste_a_verser_pondere' => max(0, $montantPondere - $verseMois),
                'facture_ttc_mois' => $stRec['facture_ttc'],
                'recouvre_net_mois' => $stRec['recouvre_net'],
            );
        }
    }

    // Récupérer le montant total déjà versé (versements globaux + versements par mois)
    $result = Manager::prepare_query(
        "SELECT COALESCE(SUM(montant), 0) as total_verse 
         FROM versements 
         WHERE id_redevance = ?",
        array($id_redevance)
    )->fetch();

    $montantTotalVerse = $result ? (float) $result['total_verse'] : 0;
    $resteTotalAVerser = max(0, $montantTotalEstimatif - $montantTotalVerse);
    if ($redevance->base_calcul !== 'branchements') {
        $resteTotalPondere = max(0, $montantTotalEstimatifPondere - $montantTotalVerse);
    }
}

// Récupérer l'historique des versements
$versements = array();
if ($redevance) {
    $versements = Manager::prepare_query(
        "SELECT v.*, m.mois as mois_facturation
         FROM versements v
         LEFT JOIN mois_facturation m ON v.id_mois_facturation = m.id
         WHERE v.id_redevance = ?
         ORDER BY v.date_versement DESC, v.id DESC
         LIMIT 50",
        array($id_redevance)
    )->fetchAll();
}

$tauxImpliciteGlobalRevVer = null;
if ($redevance && $redevance->base_calcul !== 'branchements' && $montantTotalEstimatif > 0) {
    $tauxImpliciteGlobalRevVer = min(1.0, max(0.0, $montantTotalEstimatifPondere / $montantTotalEstimatif));
}

// Lignes pour export CSV (détail par mois) — mêmes données que le tableau
$redevanceDetailMoisCsvRows = array();
if ($redevance && !empty($donneesMois)) {
    foreach ($donneesMois as $data) {
        $moisLabel = function_exists('getLetterMonth') ? getLetterMonth($data['mois']['mois']) : (string) $data['mois']['mois'];
        if ($redevance->base_calcul == 'vente_eau') {
            $redevanceDetailMoisCsvRows[] = array(
                'Mois' => $moisLabel,
                'Eau vendue' => round((float) $data['quantite'], 2),
                'Montant consommé' => round((float) $data['montant_total_facture'], 2),
                'Facturation Totale' => round((float) (isset($data['facture_ttc_mois']) ? $data['facture_ttc_mois'] : 0), 2),
                'Taux recouvrement' => round((isset($data['taux_recouvrement']) ? (float) $data['taux_recouvrement'] : 0) * 100, 2),
                'Redevance brute' => round((float) $data['montant_estimatif'], 2),
                'Redevance nette' => round((float) (isset($data['montant_estimatif_pondere']) ? $data['montant_estimatif_pondere'] : 0), 2),
                'Montant versé' => round((float) $data['montant_verse'], 2),
                'Reste brut' => round((float) $data['reste_a_verser'], 2),
                'Reste net' => round((float) (isset($data['reste_a_verser_pondere']) ? $data['reste_a_verser_pondere'] : 0), 2),
            );
        } else {
            $redevanceDetailMoisCsvRows[] = array(
                'Mois' => $moisLabel,
                'Nombre de branchements' => (int) $data['quantite'],
                'Montant consommé' => round((float) $data['montant_total_facture'], 2),
                'Redevance brute' => round((float) $data['montant_estimatif'], 2),
                'Montant versé' => round((float) $data['montant_verse'], 2),
                'Reste brut' => round((float) $data['reste_a_verser'], 2),
            );
        }
    }
}

// Gérer les messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'versement_added':
            $message = '<div class="alert alert-success">Versement ajouté avec succès.</div>';
            break;
        case 'versement_deleted':
            $message = '<div class="alert alert-success">Versement supprimé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
    $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <a href="?page=redevance" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour aux redevances
            </a>
        </div>
    </div>

    <?php echo $message; ?>

    <?php if ($redevance): ?>
        <!-- En-tête de la redevance -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="bi bi-cash-coin"></i> Gestion des versements -
                    <?php echo htmlspecialchars($redevance->libele); ?>
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Base de calcul :</strong>
                            <span class="badge bg-info">
                                <?php echo $redevance->base_calcul == 'vente_eau' ? 'Vente d\'eau' : 'Branchements'; ?>
                            </span>
                        </p>
                        <p><strong>Type de calcul :</strong>
                            <span class="badge bg-secondary">
                                <?php echo $redevance->type_calcul == 'pourcentage' ? 'Pourcentage' : 'Montant fixe'; ?>
                            </span>
                        </p>
                        <p><strong>Valeur :</strong>
                            <?php
                            if ($redevance->type_calcul == 'pourcentage') {
                                echo number_format($redevance->pourcentage, 2) . '%';
                            } else {
                                echo number_format($redevance->montant_par_m3, 0, ',', ' ') . ' FCFA';
                                if ($redevance->base_calcul == 'vente_eau') {
                                    echo ' / m³';
                                } else {
                                    echo ' / branchement';
                                }
                            }
                            ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Mois de début :</strong>
                            <?php echo function_exists('getLetterMonth') && $redevance->mois_debut ? getLetterMonth($redevance->mois_debut) : $redevance->mois_debut; ?>
                        </p>
                        <?php if ($redevance->description): ?>
                            <p><strong>Description :</strong> <?php echo htmlspecialchars($redevance->description); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($redevance->base_calcul !== 'branchements' && count($donneesMois) > 0): ?>
            <div class="card border-primary shadow-sm mb-3">
                <div class="card-header bg-primary text-white">
                    <strong>Redevance nette au recouvrement (vente d'eau)</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Par mois : <strong>taux de recouvrement</strong> = montants versés sur factures ÷ facturation totale
                        (ex. facturation totale 10 000 et versé 10 000 → 100 %). La <strong>redevance nette</strong> du mois = redevance brute × ce taux.
                        Les versements de redevance ne peuvent pas dépasser ce plafond. Le « recouvrement net » (versé − entretiens compteur TTC) reste affiché à titre informatif.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="small text-muted">Facturation Totale (cumul)</div>
                            <div class="fs-6 fw-bold"><?php echo number_format($ponderationCumulFactureTtc, 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Versé (factures)</div>
                            <div class="fs-6"><?php echo number_format($ponderationCumulVerse, 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Entretiens compteur TTC</div>
                            <div class="fs-6"><?php echo number_format($ponderationCumulEntretienTtc, 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Recouvrement net</div>
                            <div class="fs-6 fw-bold text-success"><?php echo number_format($ponderationCumulRecouvreNet, 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Redevance brute (cumul)</div>
                            <div class="fs-5"><?php echo number_format($montantTotalEstimatif, 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Redevance nette (plafond cohérent)</div>
                            <div class="fs-5 fw-bold text-primary"><?php echo number_format($montantTotalEstimatifPondere, 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted">Taux implicite (redevance nette ÷ redevance brute)</div>
                            <div class="fs-5"><?php echo $tauxImpliciteGlobalRevVer !== null ? number_format($tauxImpliciteGlobalRevVer * 100, 2, ',', ' ') . ' %' : '—'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Résumé des versements (hauteur égale sur la ligne) -->
        <div class="row mb-3 align-items-stretch">
            <div class="col-md-4 d-flex">
                <div class="card bg-info text-white w-100 h-100 d-flex flex-column">
                    <div class="card-body d-flex flex-column flex-grow-1">
                        <?php if ($redevance->base_calcul !== 'branchements'): ?>
                            <h6>Redevance nette</h6>
                            <h4><?php echo number_format($montantTotalEstimatifPondere, 0, ',', ' '); ?> FCFA</h4>
                            <small class="mt-auto">Plafond cohérent avec le recouvrement (réf. redevance brute :
                                <?php echo number_format($montantTotalEstimatif, 0, ',', ' '); ?> FCFA) — depuis
                                <?php echo function_exists('getLetterMonth') && $redevance->mois_debut ? getLetterMonth($redevance->mois_debut) : $redevance->mois_debut; ?></small>
                        <?php else: ?>
                            <h6>Redevance brute (total)</h6>
                            <h4><?php echo number_format($montantTotalEstimatif, 0, ',', ' '); ?> FCFA</h4>
                            <small class="mt-auto">Sur tous les mois depuis
                                <?php echo function_exists('getLetterMonth') && $redevance->mois_debut ? getLetterMonth($redevance->mois_debut) : $redevance->mois_debut; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4 d-flex">
                <div class="card bg-success text-white w-100 h-100 d-flex flex-column">
                    <div class="card-body d-flex flex-column flex-grow-1">
                        <h6>Montant total versé</h6>
                        <h4><?php echo number_format($montantTotalVerse, 0, ',', ' '); ?> FCFA</h4>
                        <small class="mt-auto"><?php
                        if ($redevance->base_calcul !== 'branchements' && $montantTotalEstimatifPondere > 0) {
                            echo number_format(($montantTotalVerse / $montantTotalEstimatifPondere) * 100, 1);
                        } elseif ($montantTotalEstimatif > 0) {
                            echo number_format(($montantTotalVerse / $montantTotalEstimatif) * 100, 1);
                        } else {
                            echo '0';
                        }
                        ?>%
                            <?php echo $redevance->base_calcul !== 'branchements' ? 'de la redevance nette' : 'de la redevance brute'; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 d-flex">
                <div class="card bg-warning text-white w-100 h-100 d-flex flex-column">
                    <div class="card-body d-flex flex-column flex-grow-1">
                        <?php if ($redevance->base_calcul !== 'branchements' && $resteTotalPondere !== null): ?>
                            <h6>Reste à verser (net)</h6>
                            <h4><?php echo number_format($resteTotalPondere, 0, ',', ' '); ?> FCFA</h4>
                            <small class="mt-auto">Sur base recouvrement réel (réf. reste brut : <?php echo number_format($resteTotalAVerser, 0, ',', ' '); ?> FCFA)</small>
                        <?php else: ?>
                            <h6>Reste à verser</h6>
                            <h4><?php echo number_format($resteTotalAVerser, 0, ',', ' '); ?> FCFA</h4>
                            <small class="mt-auto">Maximum autorisé (redevance brute)</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Détail par mois -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0"><i class="bi bi-calendar-month"></i> Détail par mois</h5>
                <div class="ms-auto">
                    <?php
                    if (!empty($redevanceDetailMoisCsvRows) && function_exists('create_csv_exportation_button')) {
                        $fnCsv = 'redevance_detail_mois_' . (int) $id_redevance . '_' . date('Ymd_His') . '.csv';
                        create_csv_exportation_button(
                            $redevanceDetailMoisCsvRows,
                            $fnCsv,
                            'Télécharger le détail par mois au format CSV'
                        );
                    }
                    ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($donneesMois) > 0): ?>
                    <p class="small text-muted mb-2">Montants en FCFA, eau vendue en m³, taux de recouvrement en % (unités non répétées dans les en-têtes). « Montant consommé » = montant facturé sur la consommation ; « Facturation Totale » = facturation globale du mois.</p>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Mois</th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <th>Eau vendue</th>
                                        <th>Montant consommé</th>
                                        <th>Facturation Totale</th>
                                        <th>Taux recouvrement</th>
                                    <?php else: ?>
                                        <th>Nombre de branchements</th>
                                        <th>Montant consommé</th>
                                    <?php endif; ?>
                                    <th>Redevance brute</th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <th>Redevance nette</th>
                                    <?php endif; ?>
                                    <th>Montant versé</th>
                                    <th>Reste brut</th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <th>Reste net</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donneesMois as $data): ?>
                                    <tr>
                                        <td><strong><?php echo function_exists('getLetterMonth') ? getLetterMonth($data['mois']['mois']) : $data['mois']['mois']; ?></strong>
                                        </td>
                                        <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo number_format($data['quantite'], 0, ',', ' '); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo number_format($data['montant_total_facture'], 0, ',', ' '); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark">
                                                    <?php echo number_format(isset($data['facture_ttc_mois']) ? $data['facture_ttc_mois'] : 0, 0, ',', ' '); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo number_format((isset($data['taux_recouvrement']) ? $data['taux_recouvrement'] : 0) * 100, 1, ',', ' '); ?>
                                                </span>
                                            </td>
                                        <?php else: ?>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo number_format($data['quantite'], 0, ',', ' '); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo number_format($data['montant_total_facture'], 0, ',', ' '); ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo number_format($data['montant_estimatif'], 0, ',', ' '); ?>
                                            </span>
                                        </td>
                                        <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo number_format(isset($data['montant_estimatif_pondere']) ? $data['montant_estimatif_pondere'] : 0, 0, ',', ' '); ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo number_format($data['montant_verse'], 0, ',', ' '); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                class="badge <?php echo $data['reste_a_verser'] > 0 ? 'bg-warning' : 'bg-secondary'; ?>">
                                                <?php echo number_format($data['reste_a_verser'], 0, ',', ' '); ?>
                                            </span>
                                        </td>
                                        <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                            <td>
                                                <span class="badge <?php echo (isset($data['reste_a_verser_pondere']) && $data['reste_a_verser_pondere'] > 0) ? 'bg-warning' : 'bg-secondary'; ?>">
                                                    <?php echo number_format(isset($data['reste_a_verser_pondere']) ? $data['reste_a_verser_pondere'] : 0, 0, ',', ' '); ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <?php $totalVerseMois = 0; $totalRestePondMois = 0; ?>
                                <tr class="table-info">
                                    <th>Total</th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <?php
                                        $totalEauVendue = 0;
                                        $totalFactureConso = 0;
                                        $totalPondere = 0;
                                        $totalRestePondMois = 0;
                                        foreach ($donneesMois as $data) {
                                            $totalEauVendue += $data['quantite'];
                                            $totalFactureConso += $data['montant_total_facture'];
                                            $totalVerseMois += $data['montant_verse'];
                                            $totalPondere += isset($data['montant_estimatif_pondere']) ? $data['montant_estimatif_pondere'] : 0;
                                            $totalRestePondMois += isset($data['reste_a_verser_pondere']) ? $data['reste_a_verser_pondere'] : 0;
                                        }
                                        ?>
                                        <th><strong><?php echo number_format($totalEauVendue, 0, ',', ' '); ?></strong></th>
                                        <th><strong><?php echo number_format($totalFactureConso, 0, ',', ' '); ?></strong></th>
                                        <th><strong><?php echo number_format($ponderationCumulFactureTtc, 0, ',', ' '); ?></strong></th>
                                        <th><strong><?php echo $tauxImpliciteGlobalRevVer !== null ? number_format($tauxImpliciteGlobalRevVer * 100, 2, ',', ' ') : '—'; ?></strong></th>
                                    <?php else: ?>
                                        <?php
                                        $totalBranchements = 0;
                                        $totalFacture = 0;
                                        foreach ($donneesMois as $data) {
                                            $totalBranchements += $data['quantite'];
                                            $totalFacture += $data['montant_total_facture'];
                                        }
                                        ?>
                                        <th><strong><?php echo number_format($totalBranchements, 0, ',', ' '); ?></strong></th>
                                        <th><strong><?php echo number_format($totalFacture, 0, ',', ' '); ?></strong></th>
                                    <?php endif; ?>
                                    <th><strong><?php echo number_format($montantTotalEstimatif, 0, ',', ' '); ?></strong>
                                    </th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <th><strong><?php echo number_format($montantTotalEstimatifPondere, 0, ',', ' '); ?></strong></th>
                                    <?php endif; ?>
                                    <th><strong><?php echo number_format($redevance->base_calcul === 'vente_eau' ? $totalVerseMois : $montantTotalVerse, 0, ',', ' '); ?></strong></th>
                                    <th><strong><?php echo number_format($resteTotalAVerser, 0, ',', ' '); ?></strong></th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <th><strong><?php echo number_format($totalRestePondMois, 0, ',', ' '); ?></strong></th>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> <strong>Comment la redevance brute est calculée :</strong>
                        <ul class="mb-0 mt-2">
                            <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                <li><strong>Base de calcul :</strong> Vente d'eau consommée (m³)</li>
                                <li><strong>Pondération :</strong> la redevance nette et le « reste net » utilisent le taux de recouvrement du mois : montants versés sur factures ÷ facturation totale (100 % si les deux montants sont égaux).</li>
                                <?php if ($redevance->type_calcul == 'pourcentage'): ?>
                                    <li><strong>Type de calcul :</strong> <?php echo $redevance->pourcentage; ?>% du montant total
                                        consommé (facturé sur l'eau) pour chaque mois</li>
                                    <li><strong>Formule :</strong> (Montant consommé du mois) ×
                                        <?php echo $redevance->pourcentage; ?>%
                                    </li>
                                    <li><strong>Exemple :</strong> Si le montant consommé est de 1 000 000 FCFA, la redevance brute = 1 000 000
                                        × <?php echo $redevance->pourcentage; ?>% =
                                        <?php echo number_format(1000000 * $redevance->pourcentage / 100, 0, ',', ' '); ?> FCFA
                                    </li>
                                <?php else: ?>
                                    <li><strong>Type de calcul :</strong> Montant fixe de
                                        <?php echo number_format($redevance->montant_par_m3, 0, ',', ' '); ?> FCFA par m³ consommé
                                    </li>
                                    <li><strong>Formule :</strong> (Total consommation en m³ du mois) ×
                                        <?php echo number_format($redevance->montant_par_m3, 0, ',', ' '); ?> FCFA
                                    </li>
                                    <li><strong>Exemple :</strong> Si 100 m³ sont vendus, la redevance = 100 ×
                                        <?php echo number_format($redevance->montant_par_m3, 0, ',', ' '); ?> =
                                        <?php echo number_format(100 * $redevance->montant_par_m3, 0, ',', ' '); ?> FCFA
                                    </li>
                                <?php endif; ?>
                            <?php else: ?>
                                <li><strong>Base de calcul :</strong> Nombre de branchements actifs</li>
                                <?php if ($redevance->type_calcul == 'pourcentage'): ?>
                                    <li><strong>Type de calcul :</strong> <?php echo $redevance->pourcentage; ?>% du montant total
                                        consommé (versements branchements du mois) pour chaque mois</li>
                                    <li><strong>Formule :</strong> (Montant consommé du mois) ×
                                        <?php echo $redevance->pourcentage; ?>%
                                    </li>
                                    <li><strong>Exemple :</strong> Si le montant consommé est de 1 000 000 FCFA, la redevance brute = 1 000 000
                                        × <?php echo $redevance->pourcentage; ?>% =
                                        <?php echo number_format(1000000 * $redevance->pourcentage / 100, 0, ',', ' '); ?> FCFA
                                    </li>
                                <?php else: ?>
                                    <li><strong>Type de calcul :</strong> Montant fixe de
                                        <?php echo number_format($redevance->montant_par_m3, 0, ',', ' '); ?> FCFA par branchement
                                    </li>
                                    <li><strong>Formule :</strong> (Nombre de branchements actifs du mois) ×
                                        <?php echo number_format($redevance->montant_par_m3, 0, ',', ' '); ?> FCFA
                                    </li>
                                    <li><strong>Exemple :</strong> Si 50 branchements sont actifs, la redevance = 50 ×
                                        <?php echo number_format($redevance->montant_par_m3, 0, ',', ' '); ?> =
                                        <?php echo number_format(50 * $redevance->montant_par_m3, 0, ',', ' '); ?> FCFA
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x fs-1"></i>
                        <p class="mt-2">Aucun mois de facturation disponible pour cette redevance</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulaire de versement -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Ajouter un versement</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="traitement/versement_t.php">
                    <input type="hidden" name="action" value="add_versement">
                    <input type="hidden" name="id_redevance" value="<?php echo $id_redevance; ?>">
                    <input type="hidden" name="id_mois_facturation" value="">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="montant" class="form-label">Montant à verser (FCFA) <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="montant" name="montant" min="0" step="0.01"
                                    max="<?php echo $resteTotalAVerser; ?>" required oninput="updateMaxMessage()">
                                <small class="form-text text-muted">
                                    Maximum: <span
                                        id="max_montant"><?php echo number_format($resteTotalAVerser, 0, ',', ' '); ?></span>
                                    FCFA
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="date_versement" class="form-label">Date de versement <span
                                        class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_versement" name="date_versement"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="bi bi-check-circle"></i> Enregistrer le versement
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Important :</strong> Ce versement sera global
                        (non attribué à un mois spécifique).
                        Le montant total versé ne peut pas dépasser la redevance brute totale de
                        <strong><?php echo number_format($montantTotalEstimatif, 0, ',', ' '); ?> FCFA</strong>.
                    </div>
                </form>
            </div>
        </div>

        <!-- Historique des versements -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historique des versements</h5>
            </div>
            <div class="card-body">
                <?php if (count($versements) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Montant (FCFA)</th>
                                    <th>Mois associé</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($versements as $versement): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($versement['date_versement'])); ?></td>
                                        <td><strong><?php echo number_format($versement['montant'], 0, ',', ' '); ?></strong></td>
                                        <td>
                                            <?php if ($versement['mois_facturation']): ?>
                                                <span
                                                    class="badge bg-info"><?php echo function_exists('getLetterMonth') ? getLetterMonth($versement['mois_facturation']) : $versement['mois_facturation']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Versement global</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                onclick="supprimerVersement(<?php echo $versement['id']; ?>)" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <th>Total</th>
                                    <th><strong><?php echo number_format($montantTotalVerse, 0, ',', ' '); ?> FCFA</strong></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">Aucun versement enregistré</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function updateMaxMessage() {
                const montantInput = document.getElementById('montant');
                const maxMontant = <?php echo $resteTotalAVerser; ?>;
                const montantSaisi = parseFloat(montantInput.value) || 0;

                if (montantSaisi > maxMontant) {
                    montantInput.setCustomValidity('Le montant ne peut pas dépasser ' + new Intl.NumberFormat('fr-FR').format(maxMontant) + ' FCFA');
                } else {
                    montantInput.setCustomValidity('');
                }
            }

            function supprimerVersement(idVersement) {
                if (confirm('Êtes-vous sûr de vouloir supprimer ce versement ?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'traitement/versement_t.php';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_versement';

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = idVersement;

                    form.appendChild(actionInput);
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        </script>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> Redevance introuvable ou non accessible.
        </div>
    <?php endif; ?>
</div>