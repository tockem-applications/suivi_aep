<?php
/**
 * Gestion des pénalités — interface type GCP (Google Cloud Console).
 */
define('PENALITE_DEFAUT_FCFA', 2500);

@include_once(__DIR__ . '/../donnees/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/../donnees/mois_facturation.php');
@include_once('donnees/mois_facturation.php');
@include_once(__DIR__ . '/../donnees/facture.php');
@include_once('donnees/facture.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$libeleAep = isset($_SESSION['libele_aep']) ? $_SESSION['libele_aep'] : '';

if (!$aepId) {
    echo '<div class="container py-5"><div class="alert alert-warning">Sélectionnez un AEP pour gérer les pénalités.</div>'
        . '<a href="?page=aep_dashboard" class="btn btn-primary btn-sm mt-2">Tableau de bord</a></div>';
    exit;
}

$id_mois = isset($_GET['id_mois']) ? (int) $_GET['id_mois'] : 0;
$filtre_reseau = isset($_GET['reseau_id']) ? (int) $_GET['reseau_id'] : 0;
$filtre_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$tab = isset($_GET['tab']) ? preg_replace('/[^a-z_]/', '', $_GET['tab']) : 'overview';
if (!in_array($tab, array('overview', 'liste', 'actives'), true)) {
    $tab = 'overview';
}

function pn_mois_label($mois)
{
    return function_exists('getLetterMonth') ? getLetterMonth($mois) : $mois;
}

function pn_fmt_fcfa($n)
{
    return number_format((float) $n, 0, ',', ' ');
}

function pn_query_suffix($id_mois, $tab, $reseau_id, $q)
{
    $p = array('page=penalites', 'tab=' . urlencode($tab));
    if ($id_mois > 0) {
        $p[] = 'id_mois=' . (int) $id_mois;
    }
    if ($reseau_id > 0) {
        $p[] = 'reseau_id=' . (int) $reseau_id;
    }
    if ($q !== '') {
        $p[] = 'q=' . urlencode($q);
    }
    return implode('&', $p);
}

// Flash messages
$flash = array('type' => '', 'text' => '');
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'penalite_applied') {
        $flash = array('type' => 'success', 'text' => 'Pénalité enregistrée avec succès.');
    } elseif ($_GET['success'] === 'penalite_removed') {
        $flash = array('type' => 'success', 'text' => 'Pénalité retirée avec succès.');
    } elseif ($_GET['success'] === 'bulk_applied') {
        $n = isset($_GET['n']) ? (int) $_GET['n'] : 0;
        $flash = array('type' => 'success', 'text' => 'Pénalité appliquée à ' . $n . ' abonné(s).');
    }
}
if (isset($_GET['error'])) {
    $msg = isset($_GET['message']) ? urldecode($_GET['message']) : 'Une erreur est survenue.';
    $flash = array('type' => 'danger', 'text' => $msg);
}

// KPI & stats par mois
$stats_mois = Manager::prepare_query(
    "SELECT v.id_mois, v.mois,
            SUM(v.penalite) AS total_penalite,
            COUNT(CASE WHEN v.penalite > 0 THEN 1 END) AS nb_avec_penalite,
            COUNT(*) AS nb_factures
     FROM vue_abones_facturation v
     WHERE v.id_aep = ?
     GROUP BY v.id_mois, v.mois
     ORDER BY v.mois DESC
     LIMIT 36",
    array($aepId)
)->fetchAll(PDO::FETCH_ASSOC);

$total_global_penalite = 0.0;
$total_global_abonnes_penalite = 0;
$nb_mois_avec_penalite = 0;
foreach ($stats_mois as $s) {
    $total_global_penalite += (float) $s['total_penalite'];
    $total_global_abonnes_penalite += (int) $s['nb_avec_penalite'];
    if ((int) $s['nb_avec_penalite'] > 0) {
        $nb_mois_avec_penalite++;
    }
}

$candidatsReq = Manager::prepare_query(
    "SELECT COUNT(*) AS c FROM vue_abones_facturation v
     WHERE v.id_aep = ? AND COALESCE(v.montant_restant, 0) > 0 AND COALESCE(v.penalite, 0) = 0",
    array($aepId)
);
$candidats_penalite = $candidatsReq ? (int) $candidatsReq->fetchColumn() : 0;

$liste_mois = MoisFacturation::getOrderedMonthList($aepId)->fetchAll(PDO::FETCH_ASSOC);
$reseaux = Manager::prepare_query(
    "SELECT id, nom FROM reseau WHERE id_aep = ? ORDER BY nom",
    array($aepId)
)->fetchAll(PDO::FETCH_ASSOC);

// Liste filtrée
$params_list = array($aepId);
$sql_list = "SELECT v.id, v.id_compteur, v.id_mois, v.mois, v.nom_abone, v.penalite,
                    v.id_abone, v.id_reseau, v.montant_restant, v.montant_total, r.nom AS nom_reseau
             FROM vue_abones_facturation v
             INNER JOIN reseau r ON r.id = v.id_reseau
             WHERE v.id_aep = ?";
if ($id_mois > 0) {
    $sql_list .= " AND v.id_mois = ?";
    $params_list[] = $id_mois;
}
if ($filtre_reseau > 0) {
    $sql_list .= " AND v.id_reseau = ?";
    $params_list[] = $filtre_reseau;
}
if ($filtre_q !== '') {
    $sql_list .= " AND (v.nom_abone LIKE ? OR r.nom LIKE ?)";
    $params_list[] = '%' . $filtre_q . '%';
    $params_list[] = '%' . $filtre_q . '%';
}
if ($tab === 'actives') {
    $sql_list .= " AND v.penalite > 0";
}
$sql_list .= " ORDER BY v.mois DESC, v.penalite DESC, v.nom_abone";
$liste_penalites = Manager::prepare_query($sql_list, $params_list)->fetchAll(PDO::FETCH_ASSOC);

$penalites_par_mois = array();
$source_penalites_mois = $liste_penalites;
if ($tab === 'overview') {
    $source_penalites_mois = Manager::prepare_query(
        "SELECT v.id, v.id_compteur, v.id_mois, v.mois, v.nom_abone, v.penalite,
                v.id_abone, v.id_reseau, r.nom AS nom_reseau
         FROM vue_abones_facturation v
         INNER JOIN reseau r ON r.id = v.id_reseau
         WHERE v.id_aep = ? AND v.penalite > 0
         ORDER BY v.mois DESC, v.nom_abone",
        array($aepId)
    )->fetchAll(PDO::FETCH_ASSOC);
}
foreach ($source_penalites_mois as $p) {
    if ((float) $p['penalite'] <= 0) {
        continue;
    }
    $id_m = (int) $p['id_mois'];
    if (!isset($penalites_par_mois[$id_m])) {
        $penalites_par_mois[$id_m] = array();
    }
    $penalites_par_mois[$id_m][] = $p;
}

function pn_render_penalites_rows($rows, $id_mois_filter, $tab, $reseau_id, $q)
{
    if (empty($rows)) {
        echo '<p class="small text-muted mb-0">Aucun abonné pénalisé ce mois.</p>';
        return;
    }
    ?>
    <table class="table pn-table table-sm mb-0">
        <thead>
            <tr>
                <th>Abonné</th>
                <th>Réseau</th>
                <th class="text-end">Pénalité</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a href="?page=info_abone&amp;id=<?php echo (int) $row['id_abone']; ?>&amp;section=analyse_penalite" class="pn-link-abone">
                            <?php echo htmlspecialchars($row['nom_abone'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </td>
                    <td><span class="pn-badge-reseau badge"><?php echo htmlspecialchars($row['nom_reseau'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td class="text-end"><span class="pn-badge-pen"><?php echo pn_fmt_fcfa($row['penalite']); ?> FCFA</span></td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm pn-btn-text pn-open-modal"
                            data-id-compteur="<?php echo (int) $row['id_compteur']; ?>"
                            data-id-mois="<?php echo (int) $row['id_mois']; ?>"
                            data-nom="<?php echo htmlspecialchars($row['nom_abone'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-mois="<?php echo htmlspecialchars(pn_mois_label($row['mois']), ENT_QUOTES, 'UTF-8'); ?>"
                            data-penalite="<?php echo (int) $row['penalite']; ?>"
                            data-montant="<?php echo (int) $row['penalite']; ?>">Modifier</button>
                        <form method="post" action="traitement/penalite_t.php" class="d-inline" onsubmit="return confirm('Retirer la pénalité ?');">
                            <input type="hidden" name="action" value="cancel_penalite">
                            <input type="hidden" name="id_compteur" value="<?php echo (int) $row['id_compteur']; ?>">
                            <input type="hidden" name="id_mois" value="<?php echo (int) $row['id_mois']; ?>">
                            <input type="hidden" name="tab" value="overview">
                            <button type="submit" class="btn btn-sm pn-btn-text pn-btn-danger-text">Retirer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

$chart_labels = array();
$chart_totals = array();
$chart_counts = array();
foreach (array_reverse($stats_mois) as $s) {
    $chart_labels[] = pn_mois_label($s['mois']);
    $chart_totals[] = (float) $s['total_penalite'];
    $chart_counts[] = (int) $s['nb_avec_penalite'];
}

$pn_suffix = pn_query_suffix($id_mois, $tab, $filtre_reseau, $filtre_q);
?>
<style>
    .pn-shell { background: #f8f9fa; margin: -0.5rem -0.75rem 0; min-height: calc(100vh - 100px); padding: 0 0 2.5rem; }
    .pn-main { max-width: 1400px; margin: 0 auto; padding: 1rem 1.5rem 0; }
    .pn-toprow { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.5rem; }
    .pn-breadcrumb { font-size: 0.8rem; color: #5f6368; margin: 0; }
    .pn-breadcrumb a { color: #1a73e8; text-decoration: none; }
    .pn-breadcrumb a:hover { text-decoration: underline; }
    .pn-page-title { font-size: 1.35rem; font-weight: 400; color: #202124; margin: 0 0 0.15rem; }
    .pn-subtitle { font-size: 0.85rem; color: #5f6368; margin: 0; }
    .pn-kpi { background: #fff; border: 1px solid #dadce0; border-radius: 8px; padding: 1rem 1.1rem; height: 100%; }
    .pn-kpi-label { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; color: #5f6368; margin-bottom: 0.35rem; }
    .pn-kpi-value { font-size: 1.35rem; font-weight: 500; color: #202124; line-height: 1.2; }
    .pn-kpi-value small { font-size: 0.8rem; font-weight: 400; color: #5f6368; }
    .pn-card { background: #fff; border: 1px solid #dadce0; border-radius: 8px; overflow: hidden; }
    .pn-card-header { padding: 0.85rem 1.25rem; border-bottom: 1px solid #e8eaed; font-weight: 500; font-size: 0.92rem; color: #202124; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; }
    .pn-card-body { padding: 1.25rem; }
    .pn-card-body.p-0 { padding: 0; }
    .pn-tabs { display: flex; gap: 0; border-bottom: 1px solid #dadce0; margin-bottom: 1.25rem; background: #fff; border-radius: 8px 8px 0 0; padding: 0 0.5rem; }
    .pn-tab { padding: 0.75rem 1rem; font-size: 0.875rem; color: #5f6368; text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .pn-tab:hover { color: #1a73e8; background: #f8f9fa; }
    .pn-tab.active { color: #1a73e8; border-bottom-color: #1a73e8; font-weight: 500; }
    .pn-table { font-size: 0.875rem; margin-bottom: 0; }
    .pn-table thead th { background: #f8f9fa; color: #5f6368; font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid #e8eaed; padding: 0.65rem 1rem; white-space: nowrap; }
    .pn-table tbody td { padding: 0.65rem 1rem; vertical-align: middle; border-color: #e8eaed; }
    .pn-table tbody tr:hover { background: #f8f9fa; }
    .pn-badge-pen { background: #fce8e6; color: #c5221f; font-weight: 500; font-size: 0.78rem; padding: 0.25rem 0.5rem; border-radius: 4px; }
    .pn-badge-ok { background: #e6f4ea; color: #137333; font-size: 0.78rem; padding: 0.25rem 0.5rem; border-radius: 4px; }
    .pn-badge-reseau { background: #e8f0fe; color: #1967d2; font-size: 0.75rem; font-weight: 500; }
    .pn-btn-primary { background: #1a73e8; border-color: #1a73e8; color: #fff; font-size: 0.8125rem; }
    .pn-btn-primary:hover { background: #1765cc; border-color: #1765cc; color: #fff; }
    .pn-btn-text { color: #1a73e8; font-size: 0.8125rem; padding: 0.25rem 0.5rem; }
    .pn-btn-text:hover { background: #e8f0fe; color: #1557b0; }
    .pn-btn-danger-text { color: #c5221f; }
    .pn-btn-danger-text:hover { background: #fce8e6; color: #a50e0e; }
    .pn-filters { background: #fff; border: 1px solid #dadce0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
    .pn-expand-row { cursor: pointer; }
    .pn-expand-row .bi-chevron-down { transition: transform 0.2s; font-size: 0.75rem; }
    .pn-expand-row.expanded .bi-chevron-down { transform: rotate(180deg); }
    .pn-detail-row td { padding: 0 !important; background: #f8f9fa !important; border-top: none !important; }
    .pn-detail-inner { padding: 0.75rem 1rem 1rem 2.5rem; }
    .pn-empty { text-align: center; padding: 3rem 1.5rem; color: #5f6368; }
    .pn-empty i { font-size: 2.5rem; opacity: 0.35; display: block; margin-bottom: 0.75rem; }
    .pn-chart-wrap { height: 260px; position: relative; }
    .pn-link-abone { color: #1a73e8; text-decoration: none; font-weight: 500; }
    .pn-link-abone:hover { text-decoration: underline; }
    .pn-sticky-bar { position: sticky; top: 0; z-index: 5; background: #fff; border-bottom: 1px solid #e8eaed; padding: 0.5rem 1rem; display: none; }
    .pn-sticky-bar.show { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; }
    @media (max-width: 767.98px) {
        .pn-main { padding: 0.75rem; }
    }
</style>

<div class="pn-shell">
    <div class="pn-main">
        <div class="pn-toprow">
            <nav class="pn-breadcrumb" aria-label="Fil d'Ariane">
                <a href="?page=home">Accueil</a>
                <span class="mx-1">/</span>
                <?php if ($libeleAep !== ''): ?>
                    <a href="?page=aep_dashboard"><?php echo htmlspecialchars($libeleAep, ENT_QUOTES, 'UTF-8'); ?></a>
                    <span class="mx-1">/</span>
                <?php endif; ?>
                <span>Pénalités</span>
            </nav>
            <form method="get" action="index.php" class="d-flex align-items-center gap-2 flex-shrink-0">
                <input type="hidden" name="page" value="penalites">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($filtre_reseau > 0): ?>
                    <input type="hidden" name="reseau_id" value="<?php echo (int) $filtre_reseau; ?>">
                <?php endif; ?>
                <?php if ($filtre_q !== ''): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <label class="form-label small mb-0 text-muted" for="pn_id_mois">Mois</label>
                <select name="id_mois" id="pn_id_mois" class="form-select form-select-sm" style="width: 10rem" onchange="this.form.submit()">
                    <option value="0">Tous les mois</option>
                    <?php foreach ($liste_mois as $m): ?>
                        <option value="<?php echo (int) $m['id']; ?>" <?php echo $id_mois === (int) $m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(pn_mois_label($m['mois']), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        

        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h1 class="pn-page-title">Gestion des pénalités</h1>
                <p class="pn-subtitle">
                    Appliquer, modifier ou retirer les pénalités sur les factures
                    <?php if ($libeleAep !== ''): ?> · <?php echo htmlspecialchars($libeleAep, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="?page=recouvrement" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-cash-stack me-1"></i>Recouvrement
                </a>
                <a href="?page=aep_dashboard" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-grid-1x2 me-1"></i>Tableau de bord
                </a>
            </div>
        </div>

        <?php if ($flash['text'] !== ''): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius:8px">
                <?php echo htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="pn-kpi">
                    <div class="pn-kpi-label">Total pénalités</div>
                    <div class="pn-kpi-value"><?php echo pn_fmt_fcfa($total_global_penalite); ?> <small>FCFA</small></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="pn-kpi">
                    <div class="pn-kpi-label">Abonnés pénalisés</div>
                    <div class="pn-kpi-value"><?php echo (int) $total_global_abonnes_penalite; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="pn-kpi">
                    <div class="pn-kpi-label">Mois avec pénalités</div>
                    <div class="pn-kpi-value"><?php echo (int) $nb_mois_avec_penalite; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="pn-kpi">
                    <div class="pn-kpi-label">Impayés sans pénalité</div>
                    <div class="pn-kpi-value text-warning"><?php echo (int) $candidats_penalite; ?></div>
                    <div class="small text-muted mt-1">Montant par défaut : <?php echo number_format(PENALITE_DEFAUT_FCFA, 0, ',', ' '); ?> FCFA</div>
                </div>
            </div>
        </div>

        <nav class="pn-tabs" aria-label="Sections pénalités">
            <a class="pn-tab<?php echo $tab === 'overview' ? ' active' : ''; ?>" href="?<?php echo pn_query_suffix($id_mois, 'overview', $filtre_reseau, $filtre_q); ?>">Vue d'ensemble</a>
            <a class="pn-tab<?php echo $tab === 'liste' ? ' active' : ''; ?>" href="?<?php echo pn_query_suffix($id_mois, 'liste', $filtre_reseau, $filtre_q); ?>">Toutes les factures</a>
            <a class="pn-tab<?php echo $tab === 'actives' ? ' active' : ''; ?>" href="?<?php echo pn_query_suffix($id_mois, 'actives', $filtre_reseau, $filtre_q); ?>">
                Pénalités actives
                <?php if ($total_global_abonnes_penalite > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-1"><?php echo (int) $total_global_abonnes_penalite; ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <form method="get" action="index.php" class="pn-filters">
            <input type="hidden" name="page" value="penalites">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($id_mois > 0): ?>
                <input type="hidden" name="id_mois" value="<?php echo (int) $id_mois; ?>">
            <?php endif; ?>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1" for="pn_q">Rechercher</label>
                    <input type="search" name="q" id="pn_q" class="form-control form-control-sm"
                        placeholder="Nom abonné ou réseau…" value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1" for="pn_reseau">Réseau</label>
                    <select name="reseau_id" id="pn_reseau" class="form-select form-select-sm">
                        <option value="0">Tous les réseaux</option>
                        <?php foreach ($reseaux as $re): ?>
                            <option value="<?php echo (int) $re['id']; ?>" <?php echo $filtre_reseau === (int) $re['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($re['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-5 d-flex gap-2 justify-content-md-end">
                    <button type="submit" class="btn btn-sm pn-btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filtrer
                    </button>
                    <a href="?page=penalites&amp;tab=<?php echo urlencode($tab); ?>" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
                </div>
            </div>
        </form>

        <?php if ($tab === 'overview'): ?>
                        <div class="row g-3 mb-3">
                <div class="col-12 col-lg-5">
                    <div class="pn-card h-100">
                        <div class="pn-card-header">Évolution des pénalités</div>
                        <div class="pn-card-body">
                            <div class="pn-chart-wrap">
                                <canvas id="pnChartPenalites" aria-label="Graphique des pénalités par mois"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="pn-card h-100">
                                                <div class="pn-card-header">
                            <span>Résumé par mois</span>
                            <span class="small text-muted fw-normal">Cliquer sur une ligne pour le détail</span>
                        </div>
                        <div class="pn-card-body p-0">
                            <div class="table-responsive">
                                <table class="table pn-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Mois</th>
                                            <th class="text-end">Total (FCFA)</th>
                                            <th class="text-center">Pénalisés</th>
                                            <th class="text-center">Factures</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($stats_mois)): ?>
                                            <tr><td colspan="4" class="pn-empty">Aucune donnée de facturation.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($stats_mois as $s):
                                                $id_mois_s = (int) $s['id_mois'];
                                                $abonnes_mois = isset($penalites_par_mois[$id_mois_s]) ? $penalites_par_mois[$id_mois_s] : array();
                                                $has = count($abonnes_mois) > 0;
                                                ?>
                                                <tr class="pn-expand-row<?php echo $has ? '' : ' text-muted'; ?>"
                                                    data-id-mois="<?php echo $id_mois_s; ?>" <?php echo $has ? '' : ' style="cursor:default"'; ?>>
                                                    <td>
                                                        <?php if ($has): ?><i class="bi bi-chevron-down me-1"></i><?php endif; ?>
                                                        <a href="?<?php echo pn_query_suffix($id_mois_s, 'actives', $filtre_reseau, ''); ?>" class="pn-link-abone" onclick="event.stopPropagation()">
                                                            <?php echo htmlspecialchars(pn_mois_label($s['mois']), ENT_QUOTES, 'UTF-8'); ?>
                                                        </a>
                                                    </td>
                                                    <td class="text-end fw-medium"><?php echo pn_fmt_fcfa($s['total_penalite']); ?></td>
                                                    <td class="text-center">
                                                        <?php if ((int) $s['nb_avec_penalite'] > 0): ?>
                                                            <span class="pn-badge-pen"><?php echo (int) $s['nb_avec_penalite']; ?></span>
                                                        <?php else: ?>—<?php endif; ?>
                                                    </td>
                                                    <td class="text-center text-muted"><?php echo (int) $s['nb_factures']; ?></td>
                                                </tr>
                                                <?php if ($has): ?>
                                                    <tr class="pn-detail-row detail-mois-<?php echo $id_mois_s; ?>" style="display:none">
                                                        <td colspan="4">
                                                            <div class="pn-detail-inner">
                                                                <?php pn_render_penalites_rows($abonnes_mois, $id_mois, $tab, $filtre_reseau, $filtre_q); ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'liste' || $tab === 'actives'): ?>
            <div class="pn-card">
                <div class="pn-card-header">
                    <span><?php echo $tab === 'actives' ? 'Pénalités actives' : 'Factures et pénalités'; ?></span>
                    <span class="small text-muted fw-normal"><?php echo count($liste_penalites); ?> ligne(s)</span>
                </div>
                <div id="pnBulkBar" class="pn-sticky-bar">
                    <span class="small"><strong id="pnBulkCount">0</strong> sélectionné(s)</span>
                    <form method="post" action="traitement/penalite_t.php" class="d-flex align-items-center gap-2 m-0" id="pnBulkForm"
                        onsubmit="return confirm('Appliquer la pénalité aux abonnés sélectionnés ?');">
                        <input type="hidden" name="action" value="bulk_apply_penalite">
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id_mois" value="<?php echo (int) $id_mois; ?>">
                        <input type="hidden" name="reseau_id" value="<?php echo (int) $filtre_reseau; ?>">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="number" name="penalite_montant" class="form-control form-control-sm" style="width:6rem"
                            value="<?php echo PENALITE_DEFAUT_FCFA; ?>" min="1" step="100">
                        <span class="small text-muted">FCFA</span>
                        <button type="submit" class="btn btn-sm pn-btn-primary">Appliquer la sélection</button>
                    </form>
                </div>
                <div class="pn-card-body p-0">
                    <?php if (empty($liste_penalites)): ?>
                        <div class="pn-empty">
                            <i class="bi bi-inbox"></i>
                            Aucun résultat pour ces filtres.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table pn-table mb-0" id="pnTableListe">
                                <thead>
                                    <tr>
                                        <th style="width:2.5rem"><input type="checkbox" class="form-check-input" id="pnCheckAll" title="Tout sélectionner"></th>
                                        <th>Mois</th>
                                        <th>Abonné</th>
                                        <th>Réseau</th>
                                        <th class="text-end">Reste à payer</th>
                                        <th class="text-end">Pénalité</th>
                                        <th class="text-end" style="width:11rem">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($liste_penalites as $row):
                                        $has_pen = (float) $row['penalite'] > 0;
                                        $montant_pen = $has_pen ? (int) $row['penalite'] : PENALITE_DEFAUT_FCFA;
                                        ?>
                                        <tr data-id-compteur="<?php echo (int) $row['id_compteur']; ?>"
                                            data-id-mois="<?php echo (int) $row['id_mois']; ?>"
                                            data-id-abone="<?php echo (int) $row['id_abone']; ?>"
                                            data-nom="<?php echo htmlspecialchars($row['nom_abone'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-mois="<?php echo htmlspecialchars(pn_mois_label($row['mois']), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-penalite="<?php echo (int) $row['penalite']; ?>"
                                            data-montant="<?php echo $montant_pen; ?>">
                                            <td>
                                                <?php if (!$has_pen): ?>
                                                    <input type="checkbox" class="form-check-input pn-row-check">
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap text-muted"><?php echo htmlspecialchars(pn_mois_label($row['mois']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <a href="?page=info_abone&amp;id=<?php echo (int) $row['id_abone']; ?>&amp;section=analyse_penalite" class="pn-link-abone">
                                                    <?php echo htmlspecialchars($row['nom_abone'], ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            </td>
                                            <td><span class="pn-badge-reseau badge"><?php echo htmlspecialchars($row['nom_reseau'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td class="text-end <?php echo (float) $row['montant_restant'] > 0 ? 'text-danger' : 'text-muted'; ?>">
                                                <?php echo (float) $row['montant_restant'] > 0 ? pn_fmt_fcfa($row['montant_restant']) : '—'; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($has_pen): ?>
                                                    <span class="pn-badge-pen"><?php echo pn_fmt_fcfa($row['penalite']); ?> FCFA</span>
                                                <?php else: ?>
                                                    <span class="pn-badge-ok">Aucune</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm pn-btn-text pn-open-modal"
                                                    data-action="<?php echo $has_pen ? 'edit' : 'apply'; ?>">
                                                    <i class="bi bi-pencil-square me-1"></i><?php echo $has_pen ? 'Modifier' : 'Appliquer'; ?>
                                                </button>
                                                <?php if ($has_pen): ?>
                                                    <form method="post" action="traitement/penalite_t.php" class="d-inline"
                                                        onsubmit="return confirm('Retirer la pénalité ?');">
                                                        <input type="hidden" name="action" value="cancel_penalite">
                                                        <input type="hidden" name="id_compteur" value="<?php echo (int) $row['id_compteur']; ?>">
                                                        <input type="hidden" name="id_mois" value="<?php echo (int) $row['id_mois']; ?>">
                                                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="reseau_id" value="<?php echo (int) $filtre_reseau; ?>">
                                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="btn btn-sm pn-btn-text pn-btn-danger-text">Retirer</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal unique -->
<div class="modal fade" id="pnModalPenalite" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;border:1px solid #dadce0">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-normal" id="pnModalTitle">Pénalité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="post" action="traitement/penalite_t.php" id="pnModalForm">
                <div class="modal-body pt-2">
                    <input type="hidden" name="action" value="apply_penalite">
                    <input type="hidden" name="id_compteur" id="pnModalCompteur" value="">
                    <input type="hidden" name="id_mois" id="pnModalMois" value="">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="reseau_id" value="<?php echo (int) $filtre_reseau; ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="small text-muted mb-3" id="pnModalSubtitle"></p>
                    <label for="pnModalMontant" class="form-label">Montant (FCFA)</label>
                    <input type="number" class="form-control form-control-lg" name="penalite_montant" id="pnModalMontant"
                        min="0" step="100" value="<?php echo PENALITE_DEFAUT_FCFA; ?>" required>
                    <p class="form-text small mb-0">Saisir 0 pour retirer la pénalité.</p>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-sm pn-btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var chartLabels = <?php echo json_encode($chart_labels); ?>;
    var chartTotals = <?php echo json_encode($chart_totals); ?>;
    var chartCounts = <?php echo json_encode($chart_counts); ?>;

    document.querySelectorAll('.pn-expand-row[data-id-mois]').forEach(function (row) {
        if (row.style.cursor === 'default') return;
        row.addEventListener('click', function () {
            var id = this.getAttribute('data-id-mois');
            var detail = document.querySelector('.detail-mois-' + id);
            if (!detail) return;
            var show = detail.style.display === 'none';
            detail.style.display = show ? 'table-row' : 'none';
            this.classList.toggle('expanded', show);
        });
    });

    var modalEl = document.getElementById('pnModalPenalite');
    var modalForm = document.getElementById('pnModalForm');
    var modalTitle = document.getElementById('pnModalTitle');
    var modalSub = document.getElementById('pnModalSubtitle');
    var modalCompteur = document.getElementById('pnModalCompteur');
    var modalMois = document.getElementById('pnModalMois');
    var modalMontant = document.getElementById('pnModalMontant');
    var bsModal = modalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalEl) : null;

    function openPenaliteModal(data) {
        if (!bsModal) return;
        modalTitle.textContent = data.nom || 'Pénalité';
        modalSub.textContent = (data.mois ? data.mois + ' · ' : '') + (data.action === 'edit' ? 'Modifier le montant' : 'Appliquer une pénalité');
        modalCompteur.value = data.idCompteur || '';
        modalMois.value = data.idMois || '';
        modalMontant.value = data.montant || <?php echo PENALITE_DEFAUT_FCFA; ?>;
        bsModal.show();
    }

    document.querySelectorAll('.pn-open-modal').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var tr = this.closest('tr');
            var d = tr ? {
                idCompteur: tr.getAttribute('data-id-compteur'),
                idMois: tr.getAttribute('data-id-mois'),
                nom: tr.getAttribute('data-nom'),
                mois: tr.getAttribute('data-mois'),
                montant: tr.getAttribute('data-montant'),
                action: this.getAttribute('data-action') || 'edit'
            } : {
                idCompteur: this.getAttribute('data-id-compteur'),
                idMois: this.getAttribute('data-id-mois'),
                nom: this.getAttribute('data-nom'),
                mois: this.getAttribute('data-mois'),
                montant: this.getAttribute('data-penalite') || this.getAttribute('data-montant'),
                action: 'edit'
            };
            openPenaliteModal(d);
        });
    });

    var bulkBar = document.getElementById('pnBulkBar');
    var bulkForm = document.getElementById('pnBulkForm');
    var bulkCount = document.getElementById('pnBulkCount');
    var checkAll = document.getElementById('pnCheckAll');

    function syncBulk() {
        if (!bulkBar || !bulkForm) return;
        bulkForm.querySelectorAll('input[name="bulk_id_compteur[]"], input[name="bulk_id_mois[]"]').forEach(function (el) { el.remove(); });
        var checks = document.querySelectorAll('.pn-row-check:checked');
        checks.forEach(function (cb) {
            var tr = cb.closest('tr');
            if (!tr) return;
            var ic = document.createElement('input');
            ic.type = 'hidden';
            ic.name = 'bulk_id_compteur[]';
            ic.value = tr.getAttribute('data-id-compteur');
            var im = document.createElement('input');
            im.type = 'hidden';
            im.name = 'bulk_id_mois[]';
            im.value = tr.getAttribute('data-id-mois');
            bulkForm.appendChild(ic);
            bulkForm.appendChild(im);
        });
        if (bulkCount) bulkCount.textContent = checks.length;
        bulkBar.classList.toggle('show', checks.length > 0);
    }

    document.querySelectorAll('.pn-row-check').forEach(function (cb) {
        cb.addEventListener('change', syncBulk);
    });
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.pn-row-check').forEach(function (c) {
                c.checked = checkAll.checked;
            });
            syncBulk();
        });
    }

    var canvas = document.getElementById('pnChartPenalites');
    if (canvas && typeof Chart !== 'undefined' && chartLabels.length) {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Total pénalités (FCFA)',
                        data: chartTotals,
                        backgroundColor: 'rgba(26, 115, 232, 0.65)',
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Abonnés pénalisés',
                        data: chartCounts,
                        type: 'line',
                        borderColor: '#ea4335',
                        backgroundColor: 'rgba(234, 67, 53, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { position: 'left', ticks: { callback: function (v) { return v.toLocaleString('fr-FR'); } } },
                    y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { stepSize: 1 } }
                }
            }
        });
    }
})();
</script>
