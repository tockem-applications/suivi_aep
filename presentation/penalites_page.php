<?php
/**
 * Page Gestion des pénalités
 * Tableau de bord, liste des pénalités par mois, appliquer / retirer une pénalité.
 */
define('PENALITE_DEFAUT_FCFA', 2500);

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/mois_facturation.php");
@include_once("donnees/mois_facturation.php");
@include_once("../donnees/facture.php");
@include_once("donnees/facture.php");
@include_once("../traitement/mois_facturation_t.php");
@include_once("traitement/mois_facturation_t.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}
$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
if (!$aepId) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Aucun AEP sélectionné.</div></div>';
    exit;
}

$id_mois = isset($_GET['id_mois']) ? (int) $_GET['id_mois'] : 0;

// Messages
$message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'penalite_applied')
        $message = '<div class="alert alert-success">Pénalité appliquée avec succès.</div>';
    if ($_GET['success'] === 'penalite_removed')
        $message = '<div class="alert alert-success">Pénalité retirée avec succès.</div>';
}
if (isset($_GET['error'])) {
    $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Erreur.';
    $message = '<div class="alert alert-danger">' . $msg . '</div>';
}

// Tableau de bord : totaux par mois (pénalités)
$stats_mois = Manager::prepare_query(
    "SELECT v.id_mois, v.mois,
            SUM(v.penalite) as total_penalite,
            COUNT(CASE WHEN v.penalite > 0 THEN 1 END) as nb_avec_penalite,
            COUNT(*) as nb_factures
     FROM vue_abones_facturation v
     WHERE v.id_aep = ?
     GROUP BY v.id_mois, v.mois
     ORDER BY v.mois DESC
     LIMIT 24",
    array($aepId)
)->fetchAll();

$total_global_penalite = 0;
$total_global_abonnes_penalite = 0;
foreach ($stats_mois as $s) {
    $total_global_penalite += (float) $s['total_penalite'];
    $total_global_abonnes_penalite += (int) $s['nb_avec_penalite'];
}

// Liste des pénalités (pour le mois sélectionné ou tous les mois)
$params_list = array($aepId);
$sql_list = "SELECT v.id, v.id_compteur, v.id_mois, v.mois, v.nom_abone, v.penalite, v.id_abone, v.id_reseau, r.nom as nom_reseau
             FROM vue_abones_facturation v
             INNER JOIN reseau r ON r.id = v.id_reseau
             WHERE v.id_aep = ?";
if ($id_mois > 0) {
    $sql_list .= " AND v.id_mois = ?";
    $params_list[] = $id_mois;
}
$sql_list .= " ORDER BY v.mois DESC, v.penalite DESC, v.nom_abone";
$liste_penalites = Manager::prepare_query($sql_list, $params_list)->fetchAll();

// Toutes les pénalités (tous mois) pour les sous-tableaux par mois
$liste_penalites_tous = Manager::prepare_query(
    "SELECT v.id, v.id_compteur, v.id_mois, v.mois, v.nom_abone, v.penalite, v.id_abone, v.id_reseau, r.nom as nom_reseau
     FROM vue_abones_facturation v
     INNER JOIN reseau r ON r.id = v.id_reseau
     WHERE v.id_aep = ?
     ORDER BY v.mois DESC, v.penalite DESC, v.nom_abone",
    array($aepId)
)->fetchAll();
$penalites_par_mois = array();
foreach ($liste_penalites_tous as $p) {
    $id_m = (int) $p['id_mois'];
    if (!isset($penalites_par_mois[$id_m])) {
        $penalites_par_mois[$id_m] = array();
    }
    if ((float) $p['penalite'] > 0) {
        $penalites_par_mois[$id_m][] = $p;
    }
}

// Liste des mois pour le filtre
$liste_mois = MoisFacturation::getOrderedMonthList($aepId)->fetchAll();
?>
<style>
    .penalites-page {
        background-color: #f8f9fa;
        min-height: 100vh;
        padding-bottom: 2rem;
    }

    .penalites-page .page-title {
        color: #2c3e50;
        font-weight: 700;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.06);
    }

    .penalites-page .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: box-shadow 0.2s ease;
    }

    .penalites-page .card:hover {
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
    }

    .penalites-page .card-header {
        border-radius: 10px 10px 0 0;
        border: none;
        font-weight: 600;
        padding: 0.9rem 1.25rem;
        background-color: #0d6efd !important;
        color: #fff !important;
    }

    .penalites-page .card-header .form-select {
        max-width: 200px;
        border-radius: 6px;
    }

    .penalites-page .kpi-card {
        border-left: 5px solid #e67e22;
        background: #fff;
    }

    .penalites-page .kpi-card.kpi-total {
        border-left-color: #c0392b;
    }

    .penalites-page .kpi-card.kpi-abonnes {
        border-left-color: #2980b9;
    }

    .penalites-page .kpi-card.kpi-info {
        border-left-color: #27ae60;
    }

    .penalites-page .kpi-card .card-body {
        padding: 1.25rem 1.5rem;
    }

    .penalites-page .kpi-card .kpi-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #2c3e50;
        line-height: 1.2;
    }

    .penalites-page .kpi-card .kpi-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #7f8c8d;
        margin-bottom: 0.25rem;
    }

    .penalites-page .table {
        border-radius: 8px;
        overflow: hidden;
    }

    .penalites-page .table thead {
        background-color: #0d6efd;
        color: #fff;
        font-weight: 500;
    }

    .penalites-page .table thead th {
        padding: 12px 1rem;
        border: none;
        font-size: 0.875rem;
        vertical-align: middle;
        color: #fff !important;
    }

    .penalites-page .table tbody td {
        padding: 12px 1rem;
        vertical-align: middle;
    }

    .penalites-page .table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    .penalites-page .table tbody tr:hover {
        background-color: #ecf0f1;
    }

    .penalites-page .table tbody tr .link-mois {
        color: #2980b9;
        font-weight: 500;
        transition: color 0.2s;
    }

    .penalites-page .table tbody tr .link-mois:hover {
        color: #1a5276;
    }

    .penalites-page .badge-penalite {
        font-weight: 600;
        padding: 0.4rem 0.65rem;
        font-size: 0.8rem;
        background-color: #f39c12 !important;
        color: #2c3e50 !important;
    }

    .penalites-page .btn-actions .btn {
        margin: 0 3px;
    }

    .penalites-page .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    }

    .penalites-page .modal-header {
        border-radius: 12px 12px 0 0;
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: #fff;
        padding: 1rem 1.25rem;
    }

    .penalites-page .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.9;
    }

    .penalites-page .empty-state {
        color: #95a5a6;
        padding: 3rem 1rem;
    }

    .penalites-page .row-mois-expand {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .penalites-page .row-mois-expand:hover {
        background-color: rgba(13, 110, 253, 0.08) !important;
    }

    .penalites-page .row-mois-expand .bi-chevron-down {
        transition: transform 0.2s ease;
    }

    .penalites-page .row-mois-expand.expanded .bi-chevron-down {
        transform: rotate(180deg);
    }

    .penalites-page .penalites-detail-row td {
        padding: 0 !important;
        border-top: none !important;
        vertical-align: top;
        background-color: #f8f9fa !important;
    }

    .penalites-page .sub-table-wrap {
        padding: 0.75rem 1rem 1rem 2rem;
    }

    .penalites-page .sub-table-wrap .table {
        margin-bottom: 0;
        font-size: 0.9rem;
    }

    .penalites-page .sub-table-wrap .table thead th {
        background-color: #0b5ed7;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }

    .penalites-page .sub-table-wrap .table tbody td {
        padding: 0.5rem 0.75rem;
    }
</style>

<div class="container penalites-page pt-3 pb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <h1 class="page-title mb-0 display-6">
            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Gestion des pénalités
        </h1>
        <a href="?page=aep_dashboard" class="btn btn-outline-secondary shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Tableau de bord
        </a>
    </div>

    <?php if ($message): ?>
        <div class="mb-4"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card kpi-card kpi-total h-100">
                <div class="card-body">
                    <div class="kpi-label">Total des pénalités</div>
                    <div class="kpi-value"><?php echo number_format($total_global_penalite, 0, ',', ' '); ?> <small
                            class="text-muted fs-6 fw-normal">FCFA</small></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card kpi-card kpi-abonnes h-100">
                <div class="card-body">
                    <div class="kpi-label">Abonnés avec pénalité</div>
                    <div class="kpi-value"><?php echo (int) $total_global_abonnes_penalite; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card kpi-card kpi-info h-100">
                <div class="card-body">
                    <div class="kpi-label">Détail par mois</div>
                    <div class="text-muted small mb-0">Cliquez sur un mois pour afficher les abonnés pénalisés dans un
                        sous-tableau.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Résumé par mois -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-bar-chart-steps me-2"></i>Pénalités par mois
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th class="text-end">Total (FCFA)</th>
                            <th class="text-center">Avec pénalité</th>
                            <th class="text-center">Factures</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_mois as $s):
                            $id_mois_s = (int) $s['id_mois'];
                            $abonnes_mois = isset($penalites_par_mois[$id_mois_s]) ? $penalites_par_mois[$id_mois_s] : array();
                            $has_penalites = count($abonnes_mois) > 0;
                            ?>
                            <tr class="row-mois-expand <?php echo $has_penalites ? '' : 'no-expand'; ?>"
                                data-id-mois="<?php echo $id_mois_s; ?>" <?php echo $has_penalites ? ' title="Cliquer pour afficher les abonnés pénalisés" ' : ''; ?>>
                                <td>
                                    <?php if ($has_penalites): ?>
                                        <i class="bi bi-chevron-down small me-1 opacity-75"></i>
                                    <?php endif; ?>
                                    <span class="<?php echo $has_penalites ? 'link-mois' : ''; ?>">
                                        <?php echo function_exists('getLetterMonth') ? getLetterMonth($s['mois']) : $s['mois']; ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?php echo number_format((float) $s['total_penalite'], 0, ',', ' '); ?>
                                </td>
                                <td class="text-center"><span
                                        class="badge bg-warning text-dark"><?php echo (int) $s['nb_avec_penalite']; ?></span>
                                </td>
                                <td class="text-center text-muted"><?php echo (int) $s['nb_factures']; ?></td>
                            </tr>
                            <?php if ($has_penalites): ?>
                                <tr class="penalites-detail-row detail-mois-<?php echo $id_mois_s; ?>" style="display: none;">
                                    <td colspan="4">
                                        <div class="sub-table-wrap">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Abonné</th>
                                                        <th>Réseau</th>
                                                        <th class="text-end">Pénalité</th>
                                                        <th class="text-center" style="width: 180px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($abonnes_mois as $row): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($row['nom_abone']); ?></strong>
                                                            </td>
                                                            <td><span
                                                                    class="badge bg-secondary"><?php echo htmlspecialchars($row['nom_reseau']); ?></span>
                                                            </td>
                                                            <td class="text-end">
                                                                <span
                                                                    class="badge badge-penalite"><?php echo number_format((float) $row['penalite'], 0, ',', ' '); ?>
                                                                    FCFA</span>
                                                            </td>
                                                            <td class="text-center btn-actions">
                                                                <form method="post"
                                                                    action="traitement/penalite_t.php?id_mois=<?php echo (int) $id_mois; ?>"
                                                                    class="d-inline"
                                                                    onsubmit="return confirm('Retirer la pénalité pour cet abonné ?');">
                                                                    <input type="hidden" name="action" value="cancel_penalite">
                                                                    <input type="hidden" name="id_compteur"
                                                                        value="<?php echo (int) $row['id_compteur']; ?>">
                                                                    <input type="hidden" name="id_mois"
                                                                        value="<?php echo (int) $row['id_mois']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                        title="Retirer"><i class="bi bi-x-lg"></i></button>
                                                                </form>
                                                                <button type="button" class="btn btn-sm btn-primary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#modal_penalite_sub_<?php echo (int) $row['id']; ?>_<?php echo $id_mois_s; ?>"
                                                                    title="<?php echo (float) $row['penalite'] > 0 ? 'Modifier' : 'Appliquer'; ?>">
                                                                    <i
                                                                        class="bi bi-pencil-square me-1"></i><?php echo (float) $row['penalite'] > 0 ? 'Modifier' : 'Appliquer'; ?>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.penalites-page .row-mois-expand').forEach(function (row) {
            if (row.classList.contains('no-expand')) return;
            row.addEventListener('click', function () {
                var idMois = this.getAttribute('data-id-mois');
                var detailRow = document.querySelector('.penalites-page .detail-mois-' + idMois);
                if (!detailRow) return;
                var isHidden = detailRow.style.display === 'none';
                detailRow.style.display = isHidden ? 'table-row' : 'none';
                this.classList.toggle('expanded', isHidden);
            });
        });
    </script>
    <?php foreach ($penalites_par_mois as $id_mois_mod => $abonnes_mod):
        foreach ($abonnes_mod as $row_mod): ?>
            <div class="modal fade"
                id="modal_penalite_sub_<?php echo (int) $row_mod['id']; ?>_<?php echo (int) $id_mois_mod; ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold"><i
                                    class="bi bi-cash-coin me-2"></i><?php echo htmlspecialchars($row_mod['nom_abone']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <form method="post" action="traitement/penalite_t.php?id_mois=<?php echo (int) $id_mois; ?>">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="apply_penalite">
                                <input type="hidden" name="id_compteur" value="<?php echo (int) $row_mod['id_compteur']; ?>">
                                <input type="hidden" name="id_mois" value="<?php echo (int) $row_mod['id_mois']; ?>">
                                <div class="mb-3">
                                    <label
                                        for="penalite_montant_sub_<?php echo (int) $row_mod['id']; ?>_<?php echo (int) $id_mois_mod; ?>"
                                        class="form-label fw-semibold">Montant pénalité (FCFA)</label>
                                    <input type="number" class="form-control form-control-lg"
                                        id="penalite_montant_sub_<?php echo (int) $row_mod['id']; ?>_<?php echo (int) $id_mois_mod; ?>"
                                        name="penalite_montant"
                                        value="<?php echo (int) $row_mod['penalite'] > 0 ? (int) $row_mod['penalite'] : PENALITE_DEFAUT_FCFA; ?>"
                                        min="0" step="1" placeholder="<?php echo PENALITE_DEFAUT_FCFA; ?>">
                                </div>
                                <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Mettre 0 pour retirer la
                                    pénalité.</p>
                            </div>
                            <div class="modal-footer border-top bg-light">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-warning text-dark fw-semibold"><i
                                        class="bi bi-check-lg me-1"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; endforeach; ?>

    <!-- Liste détaillée -->
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                <i class="bi bi-list-check me-2"></i>Liste des pénalités
                <?php if ($id_mois): ?><span class="badge bg-light text-dark ms-2">Mois
                        sélectionné</span><?php else: ?><span class="badge bg-light text-dark ms-2">Tous les
                        mois</span><?php endif; ?>
            </span>
            <form method="get" action="" class="d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="penalites">
                <label class="mb-0 text-white small text-nowrap">Filtrer :</label>
                <select name="id_mois" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Tous les mois</option>
                    <?php foreach ($liste_mois as $m): ?>
                        <option value="<?php echo (int) $m['id']; ?>" <?php echo ($id_mois === (int) $m['id']) ? 'selected' : ''; ?>>
                            <?php echo function_exists('getLetterMonth') ? getLetterMonth($m['mois']) : $m['mois']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th>Abonné</th>
                            <th>Réseau</th>
                            <th class="text-end">Pénalité</th>
                            <th class="text-center" style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($liste_penalites)): ?>
                            <tr>
                                <td colspan="5" class="text-center empty-state">
                                    <i class="bi bi-inbox d-block mb-2" style="font-size: 2.5rem;"></i>
                                    Aucune facture pour ce filtre.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($liste_penalites as $row): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <?php echo function_exists('getLetterMonth') ? getLetterMonth($row['mois']) : $row['mois']; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['nom_abone']); ?></strong></td>
                                    <td><span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($row['nom_reseau']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((float) $row['penalite'] > 0): ?>
                                            <span
                                                class="badge badge-penalite"><?php echo number_format((float) $row['penalite'], 0, ',', ' '); ?>
                                                FCFA</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center btn-actions">
                                        <?php if ((float) $row['penalite'] > 0): ?>
                                            <form method="post"
                                                action="traitement/penalite_t.php?id_mois=<?php echo (int) $id_mois; ?>"
                                                class="d-inline"
                                                onsubmit="return confirm('Retirer la pénalité pour cet abonné ?');">
                                                <input type="hidden" name="action" value="cancel_penalite">
                                                <input type="hidden" name="id_compteur"
                                                    value="<?php echo (int) $row['id_compteur']; ?>">
                                                <input type="hidden" name="id_mois" value="<?php echo (int) $row['id_mois']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer"><i
                                                        class="bi bi-x-lg"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#modal_penalite_<?php echo (int) $row['id']; ?>"
                                            title="<?php echo (float) $row['penalite'] > 0 ? 'Modifier' : 'Appliquer'; ?>">
                                            <i
                                                class="bi bi-pencil-square me-1"></i><?php echo (float) $row['penalite'] > 0 ? 'Modifier' : 'Appliquer'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <div class="modal fade" id="modal_penalite_<?php echo (int) $row['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold"><i
                                                        class="bi bi-cash-coin me-2"></i><?php echo htmlspecialchars($row['nom_abone']); ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Fermer"></button>
                                            </div>
                                            <form method="post"
                                                action="traitement/penalite_t.php?id_mois=<?php echo (int) $id_mois; ?>">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="apply_penalite">
                                                    <input type="hidden" name="id_compteur"
                                                        value="<?php echo (int) $row['id_compteur']; ?>">
                                                    <input type="hidden" name="id_mois"
                                                        value="<?php echo (int) $row['id_mois']; ?>">
                                                    <div class="mb-3">
                                                        <label for="penalite_montant_<?php echo (int) $row['id']; ?>"
                                                            class="form-label fw-semibold">Montant pénalité (FCFA)</label>
                                                        <input type="number" class="form-control form-control-lg"
                                                            id="penalite_montant_<?php echo (int) $row['id']; ?>"
                                                            name="penalite_montant"
                                                            value="<?php echo (int) $row['penalite'] > 0 ? (int) $row['penalite'] : PENALITE_DEFAUT_FCFA; ?>"
                                                            min="0" step="1" placeholder="<?php echo PENALITE_DEFAUT_FCFA; ?>">
                                                    </div>
                                                    <p class="text-muted small mb-0"><i
                                                            class="bi bi-info-circle me-1"></i>Mettre 0 pour retirer la
                                                        pénalité.</p>
                                                </div>
                                                <div class="modal-footer border-top bg-light">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-warning text-dark fw-semibold"><i
                                                            class="bi bi-check-lg me-1"></i>Enregistrer</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>