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

// Calculer le montant total estimatif de la redevance sur tous les mois
$montantTotalEstimatif = 0;
$montantTotalVerse = 0;
$resteTotalAVerser = 0;
$donneesMois = array(); // Détail par mois

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

            // Pour les versements, on ne peut pas lier à un mois de facturation spécifique
            $verseMois = 0;

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

            // Récupérer le montant versé pour ce mois spécifique
            $resultMois = Manager::prepare_query(
                "SELECT COALESCE(SUM(montant), 0) as verse_mois 
                 FROM versements 
                 WHERE id_redevance = ? AND id_mois_facturation = ?",
                array($id_redevance, $mois['id'])
            )->fetch();

            $verseMois = $resultMois ? (float) $resultMois['verse_mois'] : 0;

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

            $donneesMois[] = array(
                'mois' => $mois,
                'montant_estimatif' => $montant_estimatif,
                'montant_verse' => $verseMois,
                'reste_a_verser' => max(0, $montant_estimatif - $verseMois),
                'quantite' => $quantite,
                'montant_total_facture' => $montant_total_facture
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

        <!-- Résumé des versements -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6>Montant total estimatif</h6>
                        <h4><?php echo number_format($montantTotalEstimatif, 0, ',', ' '); ?> FCFA</h4>
                        <small>Sur tous les mois depuis
                            <?php echo function_exists('getLetterMonth') && $redevance->mois_debut ? getLetterMonth($redevance->mois_debut) : $redevance->mois_debut; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Montant total versé</h6>
                        <h4><?php echo number_format($montantTotalVerse, 0, ',', ' '); ?> FCFA</h4>
                        <small><?php echo $montantTotalEstimatif > 0 ? number_format(($montantTotalVerse / $montantTotalEstimatif) * 100, 1) : 0; ?>%
                            du total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6>Reste à verser</h6>
                        <h4><?php echo number_format($resteTotalAVerser, 0, ',', ' '); ?> FCFA</h4>
                        <small>Maximum autorisé</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Détail par mois -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-calendar-month"></i> Détail par mois</h5>
            </div>
            <div class="card-body">
                <?php if (count($donneesMois) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Mois</th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <th>Eau vendue (m³)</th>
                                        <th>Montant facturé (FCFA)</th>
                                    <?php else: ?>
                                        <th>Nombre de branchements</th>
                                        <th>Montant facturé (FCFA)</th>
                                    <?php endif; ?>
                                    <th>Montant estimatif (FCFA)</th>
                                    <th>Montant versé (FCFA)</th>
                                    <th>Reste à verser (FCFA)</th>
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
                                                    <?php echo number_format($data['quantite'], 0, ',', ' '); ?> m³
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo number_format($data['montant_total_facture'], 0, ',', ' '); ?>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <th>Total</th>
                                    <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                        <?php
                                        $totalEauVendue = 0;
                                        $totalFacture = 0;
                                        foreach ($donneesMois as $data) {
                                            $totalEauVendue += $data['quantite'];
                                            $totalFacture += $data['montant_total_facture'];
                                        }
                                        ?>
                                        <th><strong><?php echo number_format($totalEauVendue, 0, ',', ' '); ?> m³</strong></th>
                                        <th><strong><?php echo number_format($totalFacture, 0, ',', ' '); ?> FCFA</strong></th>
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
                                        <th><strong><?php echo number_format($totalFacture, 0, ',', ' '); ?> FCFA</strong></th>
                                    <?php endif; ?>
                                    <th><strong><?php echo number_format($montantTotalEstimatif, 0, ',', ' '); ?> FCFA</strong>
                                    </th>
                                    <th><strong><?php echo number_format($montantTotalVerse, 0, ',', ' '); ?> FCFA</strong></th>
                                    <th><strong><?php echo number_format($resteTotalAVerser, 0, ',', ' '); ?> FCFA</strong></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> <strong>Comment le montant estimatif est calculé :</strong>
                        <ul class="mb-0 mt-2">
                            <?php if ($redevance->base_calcul == 'vente_eau'): ?>
                                <li><strong>Base de calcul :</strong> Vente d'eau consommée (m³)</li>
                                <?php if ($redevance->type_calcul == 'pourcentage'): ?>
                                    <li><strong>Type de calcul :</strong> <?php echo $redevance->pourcentage; ?>% du montant total
                                        facturé pour chaque mois</li>
                                    <li><strong>Formule :</strong> (Montant total facturé du mois) ×
                                        <?php echo $redevance->pourcentage; ?>%
                                    </li>
                                    <li><strong>Exemple :</strong> Si le montant facturé est de 1 000 000 FCFA, la redevance = 1 000 000
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
                                        facturé pour chaque mois</li>
                                    <li><strong>Formule :</strong> (Montant total facturé du mois) ×
                                        <?php echo $redevance->pourcentage; ?>%
                                    </li>
                                    <li><strong>Exemple :</strong> Si le montant facturé est de 1 000 000 FCFA, la redevance = 1 000 000
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
                        Le montant total versé ne peut pas dépasser le montant estimatif total de
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