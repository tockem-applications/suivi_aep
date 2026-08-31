<?php
@include_once("../donnees/categorie_flux_manuel.php");
@include_once("donnees/categorie_flux_manuel.php");
@include_once(__DIR__ . '/../donnees/web_guard.php');

CategorieFluxManuel::ensureOrdreAffichageColumn();

$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$libele_aep = isset($_SESSION['libele_aep']) ? $_SESSION['libele_aep'] : '';
$cfm_form_action = 'index.php?page=categories_flux_manuel';
$cfm_ajax_reorder_url = 'traitement/categorie_flux_ordre_t.php';

function cfm_redirect($query = array())
{
    $base = array('page' => 'categories_flux_manuel');
    $q = array_merge($base, $query);
    $parts = array();
    foreach ($q as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
    }
    header('Location: index.php?' . implode('&', $parts));
    exit;
}

function cfm_activite_short($activite)
{
    if ($activite === 'vente_eau') {
        return 'VE';
    }
    if ($activite === 'branchements') {
        return 'AS';
    }
    return '—';
}

function cfm_fmt_fcfa($n)
{
    return number_format((float) $n, 0, ',', ' ');
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $redirect_cat = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($action === 'create') {
        $categorie = new CategorieFluxManuel();
        $categorie->code_budgetaire = isset($_POST['code_budgetaire']) && !empty($_POST['code_budgetaire']) ? $_POST['code_budgetaire'] : null;
        if (empty($categorie->code_budgetaire)) {
            cfm_redirect(array('error' => 'code_obligatoire'));
        }
        $categorie->nom = $_POST['nom'];
        $categorie->type_flux = $_POST['type_flux'];
        $categorie->description = isset($_POST['description']) ? $_POST['description'] : '';
        $categorie->est_actif = (isset($_POST['est_actif']) && $_POST['est_actif'] == '1') ? 1 : 0;
        $categorie->activite_associee = isset($_POST['activite_associee']) ? $_POST['activite_associee'] : 'autre';
        $ordre_post = isset($_POST['ordre_affichage']) ? trim((string) $_POST['ordre_affichage']) : '';
        $categorie->ordre_affichage = CategorieFluxManuel::resolveOrdreAffichagePourCreation(
            $ordre_post,
            $id_aep,
            $categorie->type_flux
        );
        $categorie->id_aep = $id_aep;
        $categorie->ajouter();
        $new_id = (int) $categorie->id;
        cfm_redirect(array('success' => '1', 'cat' => $new_id > 0 ? $new_id : null));
    }

    if ($action === 'update' || $action === 'update_all_aep') {
        $categorie = new CategorieFluxManuel();
        $categorie->id = (int) $_POST['id'];
        $existant = CategorieFluxManuel::getById($categorie->id);
        $old_code = $existant && isset($existant['code_budgetaire']) ? $existant['code_budgetaire'] : '';
        $old_type = $existant && isset($existant['type_flux']) ? $existant['type_flux'] : '';

        $categorie->code_budgetaire = isset($_POST['code_budgetaire']) && !empty($_POST['code_budgetaire']) ? $_POST['code_budgetaire'] : null;
        if (empty($categorie->code_budgetaire)) {
            cfm_redirect(array('error' => 'code_obligatoire', 'cat' => $categorie->id));
        }
        $categorie->nom = $_POST['nom'];
        $categorie->type_flux = $_POST['type_flux'];
        $categorie->description = isset($_POST['description']) ? $_POST['description'] : '';
        $categorie->est_actif = (isset($_POST['est_actif']) && $_POST['est_actif'] == '1') ? 1 : 0;
        $categorie->activite_associee = isset($_POST['activite_associee']) ? $_POST['activite_associee'] : 'autre';
        $ordre_post = isset($_POST['ordre_affichage']) ? trim((string) $_POST['ordre_affichage']) : '';
        if ($ordre_post !== '' && (int) $ordre_post > 0) {
            $categorie->ordre_affichage = (int) $ordre_post;
        } else {
            $categorie->ordre_affichage = $existant && isset($existant['ordre_affichage'])
                ? (int) $existant['ordre_affichage'] : 0;
        }
        $categorie->id_aep = $id_aep;
        $categorie->update();

        if ($action === 'update_all_aep') {
            $sync = CategorieFluxManuel::updateAllAepsWithSameCode($categorie->id, $old_code, $old_type);
            cfm_redirect(array(
                'success' => 'update_all',
                'cat' => $categorie->id,
                'updated' => (int) $sync['updated'],
            ));
        }
        cfm_redirect(array('success' => '1', 'cat' => $categorie->id));
    }

    if ($action === 'delete') {
        $del_id = (int) $_POST['id'];
        $categorie = new CategorieFluxManuel();
        $categorie->delete($del_id);
        cfm_redirect(array('success' => '1'));
    }

    if ($action === 'duplicate') {
        $ids = isset($_POST['categorie_ids']) && is_array($_POST['categorie_ids'])
            ? $_POST['categorie_ids'] : array();
        if ($id_aep <= 0) {
            cfm_redirect(array('error' => 'no_aep'));
        }
        if (empty($ids)) {
            cfm_redirect(array('error' => 'duplicate_vide'));
        }
        $dup = CategorieFluxManuel::duplicateCategoriesToAep($ids, $id_aep);
        $q = array(
            'success' => 'duplicate_import',
            'created' => (int) $dup['created'],
            'skipped' => (int) $dup['skipped'],
        );
        $redirect_cat = isset($_POST['redirect_cat']) ? (int) $_POST['redirect_cat'] : 0;
        if ($redirect_cat > 0) {
            $q['cat'] = $redirect_cat;
        }
        cfm_redirect($q);
    }

    if ($action === 'duplicate_to_aeps') {
        $src_id = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;
        $aep_ids = isset($_POST['aep_ids']) && is_array($_POST['aep_ids']) ? $_POST['aep_ids'] : array();
        if ($src_id <= 0) {
            cfm_redirect(array('error' => 'duplicate_vide'));
        }
        $dup = CategorieFluxManuel::duplicateCategoryToAeps($src_id, $aep_ids);
        cfm_redirect(array(
            'success' => 'duplicate',
            'cat' => $src_id,
            'created' => (int) $dup['created'],
            'skipped' => (int) $dup['skipped'],
        ));
    }

    if ($action === 'reorder_ordre') {
        $type_flux = isset($_POST['type_flux']) ? $_POST['type_flux'] : '';
        $ids = isset($_POST['categorie_ids']) && is_array($_POST['categorie_ids']) ? $_POST['categorie_ids'] : array();
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $ok = CategorieFluxManuel::reorderOrdreAffichage($ids, $type_flux, $id_aep);
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            $ordres = array();
            if ($ok) {
                $ordre = 10;
                foreach ($ids as $raw_id) {
                    $ordres[(int) $raw_id] = $ordre;
                    $ordre += 10;
                }
            }
            echo json_encode(array('ok' => $ok, 'ordres' => $ordres));
            exit;
        }
        if ($ok) {
            cfm_redirect(array('success' => 'move'));
        }
        cfm_redirect(array('error' => 'move_invalid'));
    }
}

$res = CategorieFluxManuel::getAll(null, $id_aep ? $id_aep : null);
$categories = $res->fetchAll(PDO::FETCH_ASSOC);

$type_flux_labels = array(
    'recette' => 'Recette',
    'charge' => 'Dépense',
);
$activite_labels = array(
    'branchements' => 'Branchements (AS)',
    'vente_eau' => 'Vente d\'eau (VE)',
    'autre' => 'Autre',
);

$categories_by_type = array('charge' => array(), 'recette' => array());
foreach ($categories as $cat) {
    $t = isset($cat['type_flux']) ? $cat['type_flux'] : 'charge';
    if (!isset($categories_by_type[$t])) {
        $categories_by_type[$t] = array();
    }
    $categories_by_type[$t][] = $cat;
}
foreach ($categories_by_type as $t => $list) {
    usort($categories_by_type[$t], function ($a, $b) {
        $oa = (int) (isset($a['ordre_affichage']) ? $a['ordre_affichage'] : 0);
        $ob = (int) (isset($b['ordre_affichage']) ? $b['ordre_affichage'] : 0);
        if ($oa !== $ob) {
            return $oa - $ob;
        }
        return strcmp($a['nom'], $b['nom']);
    });
}

$cfm_sort_groups = array(
    'charge' => array('label' => 'Dépenses', 'badge' => 'bg-danger'),
    'recette' => array('label' => 'Recettes', 'badge' => 'bg-success'),
);

$codes_existants = array();
foreach ($categories as $cat) {
    if (!empty($cat['code_budgetaire'])) {
        $codes_existants[$cat['type_flux'] . '|' . $cat['code_budgetaire']] = true;
    }
}

$autres_aeps = array();
$categories_par_aep = array();
if ($id_aep > 0) {
    $autres_aeps = CategorieFluxManuel::getAutresAeps($id_aep);
    foreach ($autres_aeps as $aep_row) {
        $categories_par_aep[(int) $aep_row['id']] = CategorieFluxManuel::getByAepStrict((int) $aep_row['id']);
    }
}

$selected_id = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$cfm_can_import = ($id_aep > 0 && !empty($autres_aeps));
$detail = null;
if ($selected_id > 0) {
    $detail = CategorieFluxManuel::getDetail($selected_id, $id_aep);
    if (!$detail) {
        $selected_id = 0;
    }
}

function cfm_render_sidebar_row($cat, $selected_id, $activite_labels)
{
    $cat_id = (int) $cat['id'];
    $ordre = (int) (isset($cat['ordre_affichage']) ? $cat['ordre_affichage'] : 0);
    $activite = isset($cat['activite_associee']) ? $cat['activite_associee'] : 'autre';
    $active = ($selected_id === $cat_id) ? ' cfm-nav-active' : '';
    $href = 'index.php?page=categories_flux_manuel&cat=' . $cat_id;
    ?>
    <tr class="cfm-sort-row<?php echo $active; ?>" data-id="<?php echo $cat_id; ?>">
        <td class="cfm-col-drag text-center">
            <span class="cfm-drag-handle" title="Glisser pour réordonner" onclick="event.preventDefault(); event.stopPropagation();">
                <i class="bi bi-grip-vertical"></i>
            </span>
        </td>
        <td class="text-center text-muted small cfm-col-ordre">
            <span class="cfm-ordre-num"><?php echo $ordre; ?></span>
        </td>
        <td class="cfm-nav-link-cell">
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="cfm-nav-link stretched-link">
                <?php echo htmlspecialchars($cat['nom'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </td>
        <td class="text-center">
            <?php if (!empty($cat['code_budgetaire'])): ?>
                <code class="small"><?php echo htmlspecialchars($cat['code_budgetaire'], ENT_QUOTES, 'UTF-8'); ?></code>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <span class="badge <?php echo $cat['type_flux'] === 'recette' ? 'bg-success' : 'bg-danger'; ?> cfm-badge-type">
                <?php echo $cat['type_flux'] === 'recette' ? 'R' : 'D'; ?>
            </span>
        </td>
        <td class="text-center">
            <span class="badge bg-light text-dark border cfm-badge-act" title="<?php echo htmlspecialchars(isset($activite_labels[$activite]) ? $activite_labels[$activite] : $activite, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(cfm_activite_short($activite), ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </td>
    </tr>
    <?php
}

function cfm_render_detail($detail, $type_flux_labels, $activite_labels, $id_aep)
{
    $cat = $detail['categorie'];
    $bilan = $detail['bilan'];
    $transactions = $detail['transactions'];
    $nb_meme_code = (int) $detail['nb_meme_code'];
    $cat_id = (int) $cat['id'];
    $activite = isset($cat['activite_associee']) ? $cat['activite_associee'] : 'autre';
    $cat_json = htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="cfm-detail">
        <div class="cfm-detail-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h2 class="h4 mb-0"><?php echo htmlspecialchars($cat['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <span class="badge <?php echo $cat['type_flux'] === 'recette' ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo htmlspecialchars(isset($type_flux_labels[$cat['type_flux']]) ? $type_flux_labels[$cat['type_flux']] : $cat['type_flux'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php if ($cat['est_actif']): ?>
                        <span class="badge bg-light text-primary border">Actif</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactif</span>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mb-0">
                    Code <strong><?php echo htmlspecialchars($cat['code_budgetaire'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    · Ordre <?php echo (int) (isset($cat['ordre_affichage']) ? $cat['ordre_affichage'] : 0); ?>
                    · <?php echo htmlspecialchars(isset($activite_labels[$activite]) ? $activite_labels[$activite] : $activite, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="editCategorie(<?php echo $cat_json; ?>)">
                    <i class="fas fa-edit me-1"></i>Modifier
                </button>
                <?php if ($id_aep > 0): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#duplicateAepModal">
                        <i class="fas fa-copy me-1"></i>Dupliquer
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-danger btn-sm"
                    onclick="confirmDelete(<?php echo $cat_id; ?>, '<?php echo htmlspecialchars(addslashes($cat['nom']), ENT_QUOTES, 'UTF-8'); ?>')">
                    <i class="fas fa-trash me-1"></i>Supprimer
                </button>
            </div>
        </div>

        <?php if (!empty($cat['description'])): ?>
            <div class="cfm-detail-card mb-3">
                <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8')); ?></p>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="cfm-stat-card cfm-stat-entree">
                    <div class="cfm-stat-label">Entrées</div>
                    <div class="cfm-stat-value"><?php echo cfm_fmt_fcfa($bilan['total_entrees']); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="cfm-stat-card cfm-stat-sortie">
                    <div class="cfm-stat-label">Sorties</div>
                    <div class="cfm-stat-value"><?php echo cfm_fmt_fcfa($bilan['total_sorties']); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="cfm-stat-card cfm-stat-solde">
                    <div class="cfm-stat-label">Solde</div>
                    <div class="cfm-stat-value"><?php echo cfm_fmt_fcfa($bilan['solde']); ?></div>
                    <div class="cfm-stat-meta"><?php echo (int) $bilan['nb_operations']; ?> opération(s)</div>
                </div>
            </div>
        </div>

        <?php if (!empty($bilan['par_mois'])): ?>
            <div class="cfm-detail-card mb-4">
                <h3 class="h6 mb-3">Bilan par mois</h3>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mois</th>
                                <th class="text-end">Entrées</th>
                                <th class="text-end">Sorties</th>
                                <th class="text-end">Solde</th>
                                <th class="text-center">Nb</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bilan['par_mois'] as $pm): ?>
                                <?php
                                $e = (float) $pm['entrees'];
                                $s = (float) $pm['sorties'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pm['mois'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end text-success"><?php echo cfm_fmt_fcfa($e); ?></td>
                                    <td class="text-end text-danger"><?php echo cfm_fmt_fcfa($s); ?></td>
                                    <td class="text-end fw-semibold"><?php echo cfm_fmt_fcfa($e - $s); ?></td>
                                    <td class="text-center text-muted"><?php echo (int) $pm['nb']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="cfm-detail-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Transactions rattachées</h3>
                <a href="index.php?page=transaction" class="btn btn-sm btn-link">Voir toutes les transactions</a>
            </div>
            <?php if (empty($transactions)): ?>
                <p class="text-muted small mb-0">Aucune transaction pour cette catégorie.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Mois</th>
                                <th>Libellé</th>
                                <th>Type</th>
                                <th class="text-end">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td class="text-nowrap small"><?php echo htmlspecialchars($tx['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($tx['mois'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($tx['libele'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($tx['type'] === 'entree'): ?>
                                            <span class="badge bg-success">Entrée</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Sortie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-semibold"><?php echo cfm_fmt_fcfa($tx['prix']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>

<style>
    .cfm-app {
        display: flex;
        gap: 0;
        min-height: calc(100vh - 120px);
        margin: -0.5rem -0.75rem 0;
        background: #f1f3f4;
    }
    .cfm-sidebar {
        width: 380px;
        min-width: 300px;
        max-width: 42vw;
        background: #fff;
        border-right: 1px solid #e0e0e0;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }
    .cfm-sidebar-head {
        padding: 1rem 1rem 0.75rem;
        border-bottom: 1px solid #e8eaed;
    }
    .cfm-sidebar-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem 0;
    }
    .cfm-sidebar table {
        font-size: 0.78rem;
        margin-bottom: 0;
    }
    .cfm-sidebar thead th {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
        color: #5f6368;
        border-bottom: 1px solid #e8eaed;
        background: #fafafa;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .cfm-nav-link-cell { position: relative; max-width: 9rem; }
    .cfm-nav-link {
        color: #202124;
        text-decoration: none;
        font-weight: 500;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    tr.cfm-sort-row.cfm-nav-active,
    tr.cfm-sort-row:hover { background: #e8f0fe; }
    tr.cfm-sort-row.cfm-nav-active .cfm-nav-link { color: #1a73e8; }
    .cfm-col-drag { width: 1.5rem; padding: 0.25rem !important; }
    .cfm-col-ordre { width: 2.25rem; }
    .cfm-badge-type, .cfm-badge-act { font-size: 0.65rem; }
    .cfm-drag-handle {
        color: #9aa0a6;
        cursor: grab;
        font-size: 0.95rem;
    }
    .cfm-drag-handle:hover { color: #1a73e8; }
    tr.cfm-sort-row.cfm-row-dragging { opacity: 0.55; background: #e8f0fe !important; }
    tr.cfm-sort-row.cfm-row-drag-over td { border-top: 2px solid #1a73e8; }
    .cfm-sort-group-label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.5rem 1rem 0.25rem;
        color: #5f6368;
    }
    .cfm-main {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem 2rem;
        min-width: 0;
    }
    .cfm-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 320px;
        color: #5f6368;
        text-align: center;
    }
    .cfm-empty i { font-size: 3rem; opacity: 0.35; margin-bottom: 1rem; }
    .cfm-detail-card {
        background: #fff;
        border: 1px solid #e8eaed;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 1px 2px rgba(60, 64, 67, 0.06);
    }
    .cfm-stat-card {
        background: #fff;
        border: 1px solid #e8eaed;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        height: 100%;
    }
    .cfm-stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: #5f6368; }
    .cfm-stat-value { font-size: 1.35rem; font-weight: 700; margin-top: 0.25rem; }
    .cfm-stat-meta { font-size: 0.75rem; color: #80868b; margin-top: 0.25rem; }
    .cfm-stat-entree .cfm-stat-value { color: #137333; }
    .cfm-stat-sortie .cfm-stat-value { color: #c5221f; }
    .cfm-stat-solde .cfm-stat-value { color: #1a73e8; }
    .cfm-sort-saving { font-size: 0.7rem; color: #1a73e8; display: none; }
    .cfm-sort-saving.is-visible { display: inline; }
    #categorieModal .modal-content {
        border: none;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
    }
    #categorieModal .cfm-modal-header {
        background: linear-gradient(135deg, #1a73e8 0%, #1557b0 100%);
        color: #fff;
        padding: 1.25rem 1.5rem;
        border: none;
    }
    #categorieModal .cfm-modal-header .btn-close { filter: brightness(0) invert(1); opacity: 0.85; }
    #categorieModal .cfm-modal-body { padding: 1.5rem; background: #f8fafc; }
    #categorieModal .cfm-field-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem 1.15rem;
        margin-bottom: 1rem;
    }
    #categorieModal .cfm-field-card h6 {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin-bottom: 0.75rem;
        font-weight: 600;
    }
    #duplicateAepModal .dup-aep-row.disabled { opacity: 0.55; background: #f8f9fa; }
    #categorieModal .modal-dialog.cfm-dup-dialog {
        max-width: 920px;
        width: calc(100% - 2rem);
    }
    #categorieModal .cfm-dup-scroll {
        max-height: min(58vh, 520px);
        overflow-y: auto;
    }
    .cfm-choice-card {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
        width: 100%;
        padding: 1.25rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        text-align: left;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .cfm-choice-card:hover {
        border-color: #1a73e8;
        box-shadow: 0 4px 12px rgba(26, 115, 232, 0.12);
    }
    .cfm-choice-card i { font-size: 1.5rem; color: #1a73e8; }
    .cfm-choice-card.cfm-choice-dup i { color: #5f6368; }
    .cfm-choice-card.cfm-choice-dup:hover { border-color: #5f6368; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    @media (max-width: 991px) {
        .cfm-app { flex-direction: column; min-height: auto; }
        .cfm-sidebar { width: 100%; max-width: none; max-height: 45vh; border-right: none; border-bottom: 1px solid #e0e0e0; }
    }
</style>

<div class="cfm-app">
    <aside class="cfm-sidebar">
        <div class="cfm-sidebar-head">
            <h1 class="h6 fw-bold text-primary mb-1">Catégories flux</h1>
            <?php if ($id_aep > 0 && $libele_aep !== ''): ?>
                <p class="text-muted mb-2" style="font-size:0.75rem"><?php echo htmlspecialchars($libele_aep, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-sm w-100" id="btnNouvelleCategorie">
                <i class="fas fa-plus me-1"></i>Nouvelle catégorie
            </button>
        </div>
        <div class="cfm-sidebar-scroll">
            <?php foreach ($cfm_sort_groups as $type_key => $group_info):
                $group_cats = isset($categories_by_type[$type_key]) ? $categories_by_type[$type_key] : array();
                if (empty($group_cats)) {
                    continue;
                }
                ?>
                <div class="cfm-sort-group-label">
                    <span class="badge <?php echo htmlspecialchars($group_info['badge'], ENT_QUOTES, 'UTF-8'); ?> me-1">
                        <?php echo htmlspecialchars($group_info['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="cfm-sort-saving" id="cfm-sort-saving-<?php echo htmlspecialchars($type_key, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="spinner-border spinner-border-sm"></span>
                    </span>
                </div>
                <table class="table table-borderless table-sm mb-2">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="text-center">#</th>
                            <th>Nom</th>
                            <th class="text-center">Code</th>
                            <th class="text-center">T</th>
                            <th class="text-center" title="VE = vente eau, AS = abonnement service">Act.</th>
                        </tr>
                    </thead>
                    <tbody class="cfm-sort-group" id="cfm-sort-<?php echo htmlspecialchars($type_key, ENT_QUOTES, 'UTF-8'); ?>"
                        data-type-flux="<?php echo htmlspecialchars($type_key, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php foreach ($group_cats as $cat):
                            cfm_render_sidebar_row($cat, $selected_id, $activite_labels);
                        endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
                <p class="text-muted small px-3">Aucune catégorie. Créez-en une.</p>
            <?php endif; ?>
        </div>
    </aside>

    <main class="cfm-main">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                <?php
                if ($_GET['success'] === 'duplicate_import') {
                    echo 'Import depuis d\'autres AEP : <strong>' . (int) (isset($_GET['created']) ? $_GET['created'] : 0) . '</strong> catégorie(s) ajoutée(s), '
                        . (int) (isset($_GET['skipped']) ? $_GET['skipped'] : 0) . ' ignorée(s) (déjà présentes).';
                } elseif ($_GET['success'] === 'duplicate') {
                    echo 'Duplication vers d\'autres AEP : <strong>' . (int) (isset($_GET['created']) ? $_GET['created'] : 0) . '</strong> créée(s), '
                        . (int) (isset($_GET['skipped']) ? $_GET['skipped'] : 0) . ' ignorée(s).';
                } elseif ($_GET['success'] === 'update_all') {
                    echo 'Modification appliquée sur <strong>' . (int) (isset($_GET['updated']) ? $_GET['updated'] : 0) . '</strong> catégorie(s) (même code budgétaire).';
                } elseif ($_GET['success'] === 'move') {
                    echo 'Ordre d\'affichage mis à jour.';
                } else {
                    echo 'Opération effectuée avec succès.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'code_obligatoire'): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2">Le code budgétaire est obligatoire.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'no_aep'): ?>
            <div class="alert alert-warning alert-dismissible fade show py-2">Sélectionnez un AEP en session pour importer des catégories.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate_vide'): ?>
            <div class="alert alert-warning alert-dismissible fade show py-2">Aucune catégorie ou AEP sélectionné(e).<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if ($detail): ?>
            <?php cfm_render_detail($detail, $type_flux_labels, $activite_labels, $id_aep); ?>
        <?php else: ?>
            <div class="cfm-empty">
                <i class="bi bi-folder2-open"></i>
                <p class="mb-1 fw-semibold">Sélectionnez une catégorie</p>
                <p class="small mb-0">Choisissez une ligne dans le menu à gauche pour afficher le détail,<br>le bilan financier et les transactions.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal nouvelle catégorie : choix créer / dupliquer, ou modification -->
<div class="modal fade" id="categorieModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" id="categorieModalDialog">
        <div class="modal-content">
            <div class="modal-header cfm-modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="modalTitle">Nouvelle catégorie</h5>
                    <p class="small mb-0 opacity-75" id="modalSubtitle">Que souhaitez-vous faire ?</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <?php if ($cfm_can_import): ?>
            <!-- Étape choix -->
            <div id="cfmPaneChoice" class="modal-body cfm-modal-body">
                <p class="text-muted small mb-3">Créez une catégorie vide ou importez des catégories depuis les autres réseaux AEP.</p>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <button type="button" class="cfm-choice-card" id="cfmBtnChooseCreate">
                            <i class="bi bi-plus-circle"></i>
                            <strong>Créer une catégorie</strong>
                            <span class="small text-muted">Saisie manuelle du nom, code, type…</span>
                        </button>
                    </div>
                    <div class="col-sm-6">
                        <button type="button" class="cfm-choice-card cfm-choice-dup" id="cfmBtnChooseDuplicate">
                            <i class="bi bi-copy"></i>
                            <strong>Dupliquer depuis d'autres AEP</strong>
                            <span class="small text-muted">Copier des catégories existantes vers <?php echo $libele_aep !== '' ? htmlspecialchars($libele_aep, ENT_QUOTES, 'UTF-8') : 'cet AEP'; ?></span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Étape création / modification -->
            <div id="cfmPaneCreate" class="d-none">
                <form method="post" action="<?php echo htmlspecialchars($cfm_form_action, ENT_QUOTES, 'UTF-8'); ?>" id="categorieForm">
                    <div class="modal-body cfm-modal-body">
                        <button type="button" class="btn btn-link btn-sm text-secondary px-0 mb-2" id="cfmBackToChoice">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </button>
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="formId">
                    <div class="cfm-field-card">
                        <h6>Identification</h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                            <div class="col-md-4">
                                <label for="code_budgetaire" class="form-label">Code budgétaire <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_budgetaire" name="code_budgetaire" maxlength="10" required>
                            </div>
                        </div>
                    </div>
                    <div class="cfm-field-card">
                        <h6>Paramètres</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="type_flux" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type_flux" name="type_flux" required>
                                    <option value="charge">Dépense</option>
                                    <option value="recette">Recette</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="ordre_affichage" class="form-label">Ordre</label>
                                <input type="number" class="form-control" id="ordre_affichage" name="ordre_affichage" min="1" placeholder="Auto">
                            </div>
                            <div class="col-md-4">
                                <label for="activite_associee" class="form-label">Activité</label>
                                <select class="form-select" id="activite_associee" name="activite_associee" required>
                                    <option value="branchements">AS — Abonnement service</option>
                                    <option value="vente_eau">VE — Vente d'eau</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input type="checkbox" class="form-check-input" id="est_actif" name="est_actif" value="1" checked>
                            <label class="form-check-label" for="est_actif">Catégorie active</label>
                        </div>
                    </div>
                    <div class="cfm-field-card mb-0">
                        <h6>Description</h6>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-outline-primary px-3" id="btnUpdateAllAep" name="submit_mode" value="all" style="display:none;">
                            <i class="bi bi-globe2 me-1"></i>Modifier dans tous les AEP
                        </button>
                        <button type="submit" class="btn btn-primary px-4" id="btnSaveCategorie">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($cfm_can_import): ?>
            <!-- Étape duplication depuis autres AEP -->
            <div id="cfmPaneDuplicate" class="d-none">
                <form method="post" action="<?php echo htmlspecialchars($cfm_form_action, ENT_QUOTES, 'UTF-8'); ?>" id="duplicateFromForm">
                    <input type="hidden" name="action" value="duplicate">
                    <?php if ($selected_id > 0): ?>
                        <input type="hidden" name="redirect_cat" value="<?php echo (int) $selected_id; ?>">
                    <?php endif; ?>
                    <div class="modal-body cfm-modal-body cfm-dup-scroll">
                        <button type="button" class="btn btn-link btn-sm text-secondary px-0 mb-2" id="cfmBackFromDuplicate">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </button>
                    <p class="text-muted small">
                        Cochez les catégories à copier depuis les autres AEP.
                        Les doublons (même code budgétaire et type) déjà présents sur votre AEP sont grisés.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dupFromSelectAll">
                            Tout cocher (disponibles)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dupFromSelectNone">
                            Tout décocher
                        </button>
                    </div>
                    <div class="accordion" id="dupFromAepAccordion">
                        <?php
                        $acc_idx = 0;
                        foreach ($autres_aeps as $aep_row):
                            $aid = (int) $aep_row['id'];
                            $cats_aep = isset($categories_par_aep[$aid]) ? $categories_par_aep[$aid] : array();
                            $acc_idx++;
                            $acc_id = 'dupFromAep' . $aid;
                            ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $acc_id; ?>">
                                    <button class="accordion-button <?php echo $acc_idx > 1 ? 'collapsed' : ''; ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $acc_id; ?>"
                                        aria-expanded="<?php echo $acc_idx === 1 ? 'true' : 'false'; ?>">
                                        <?php echo htmlspecialchars($aep_row['libele'], ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="badge bg-secondary ms-2"><?php echo count($cats_aep); ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $acc_id; ?>"
                                    class="accordion-collapse collapse <?php echo $acc_idx === 1 ? 'show' : ''; ?>"
                                    data-bs-parent="#dupFromAepAccordion">
                                    <div class="accordion-body p-0">
                                        <?php if (empty($cats_aep)): ?>
                                            <p class="text-muted small p-3 mb-0">Aucune catégorie sur cet AEP.</p>
                                        <?php else: ?>
                                            <?php $collapse_id = 'collapse' . $acc_id; ?>
                                            <div class="d-flex flex-wrap gap-2 p-2 border-bottom bg-light">
                                                <button type="button" class="btn btn-sm btn-outline-primary dup-from-select-aep-all"
                                                    data-dup-group="<?php echo htmlspecialchars($collapse_id, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="bi bi-check2-square me-1"></i>Tout sélectionner
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary dup-from-deselect-aep-all"
                                                    data-dup-group="<?php echo htmlspecialchars($collapse_id, ENT_QUOTES, 'UTF-8'); ?>">
                                                    Tout décocher
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width:2.5rem"></th>
                                                            <th class="text-center">#</th>
                                                            <th>Nom</th>
                                                            <th>Type</th>
                                                            <th>Code</th>
                                                            <th>Act.</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cats_aep as $src_cat):
                                                            $dup_key = $src_cat['type_flux'] . '|' . $src_cat['code_budgetaire'];
                                                            $deja_presente = isset($codes_existants[$dup_key]);
                                                            $act = isset($src_cat['activite_associee']) ? $src_cat['activite_associee'] : 'autre';
                                                            ?>
                                                            <tr class="<?php echo $deja_presente ? 'table-secondary' : ''; ?>">
                                                                <td>
                                                                    <input type="checkbox" class="form-check-input dup-from-cat-cb"
                                                                        name="categorie_ids[]"
                                                                        value="<?php echo (int) $src_cat['id']; ?>"
                                                                        <?php echo $deja_presente ? 'disabled' : ''; ?>>
                                                                </td>
                                                                <td class="text-center text-muted small">
                                                                    <?php echo (int) (isset($src_cat['ordre_affichage']) ? $src_cat['ordre_affichage'] : 0); ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($src_cat['nom'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td>
                                                                    <span class="badge <?php echo $src_cat['type_flux'] === 'recette' ? 'bg-success' : 'bg-danger'; ?>">
                                                                        <?php echo $src_cat['type_flux'] === 'recette' ? 'R' : 'D'; ?>
                                                                    </span>
                                                                </td>
                                                                <td><code class="small"><?php echo htmlspecialchars($src_cat['code_budgetaire'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(cfm_activite_short($act), ENT_QUOTES, 'UTF-8'); ?></span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="dupFromSubmitBtn">
                            <i class="fas fa-copy me-1"></i>Dupliquer la sélection
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($cfm_can_import): ?>
            <div id="cfmFooterChoice" class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($detail && $id_aep > 0): ?>
<div class="modal fade" id="duplicateAepModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-copy me-2"></i>Dupliquer vers d'autres AEP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo htmlspecialchars($cfm_form_action, ENT_QUOTES, 'UTF-8'); ?>" id="duplicateAepForm">
                <input type="hidden" name="action" value="duplicate_to_aeps">
                <input type="hidden" name="categorie_id" value="<?php echo (int) $detail['categorie']['id']; ?>">
                <div class="modal-body">
                    <p class="text-muted small">
                        Cochez les AEP qui recevront une copie de cette catégorie.
                        Les AEP qui ont déjà le code <strong><?php echo htmlspecialchars($detail['categorie']['code_budgetaire'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        sont grisés.
                    </p>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dupAepSelectAll">Tout cocher</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dupAepSelectNone">Tout décocher</button>
                    </div>
                    <div class="list-group list-group-flush border rounded">
                        <?php foreach ($detail['duplicate_aeps'] as $aep_row): ?>
                            <label class="list-group-item dup-aep-row d-flex align-items-center gap-2 mb-0 <?php echo $aep_row['deja_presente'] ? 'disabled' : ''; ?>">
                                <input type="checkbox" class="form-check-input dup-aep-cb" name="aep_ids[]"
                                    value="<?php echo (int) $aep_row['id']; ?>"
                                    <?php echo $aep_row['deja_presente'] ? 'disabled' : ''; ?>>
                                <span class="flex-grow-1"><?php echo htmlspecialchars($aep_row['libele'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($aep_row['deja_presente']): ?>
                                    <span class="badge bg-secondary">Code déjà présent</span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="dupAepSubmitBtn">
                        <i class="fas fa-copy me-1"></i>Dupliquer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Supprimer la catégorie <strong id="deleteCategorieNom"></strong> ?</p>
                <p class="small text-muted mb-0">Les flux associés ne seront pas supprimés mais perdront leur catégorie.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="post" action="<?php echo htmlspecialchars($cfm_form_action, ENT_QUOTES, 'UTF-8'); ?>" id="deleteForm" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteCategorieId">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var cfmAjaxReorderUrl = <?php echo json_encode($cfm_ajax_reorder_url); ?>;
var cfmCsrfToken = <?php echo json_encode(Csrf::token()); ?>;
var cfmNbMemeCode = <?php echo $detail ? (int) $detail['nb_meme_code'] : 0; ?>;
var cfmCanImport = <?php echo $cfm_can_import ? 'true' : 'false'; ?>;
var cfmModalMode = 'new';

function cfmShowPane(pane) {
    var choice = document.getElementById('cfmPaneChoice');
    var create = document.getElementById('cfmPaneCreate');
    var dup = document.getElementById('cfmPaneDuplicate');
    var footerChoice = document.getElementById('cfmFooterChoice');
    var dialog = document.getElementById('categorieModalDialog');
    var backCreate = document.getElementById('cfmBackToChoice');
    var backDup = document.getElementById('cfmBackFromDuplicate');

    if (choice) choice.classList.add('d-none');
    if (create) create.classList.add('d-none');
    if (dup) dup.classList.add('d-none');
    if (footerChoice) footerChoice.classList.add('d-none');

    if (pane === 'choice' && choice) {
        choice.classList.remove('d-none');
        if (footerChoice) footerChoice.classList.remove('d-none');
        document.getElementById('modalTitle').textContent = 'Nouvelle catégorie';
        document.getElementById('modalSubtitle').textContent = 'Que souhaitez-vous faire ?';
        if (dialog) {
            dialog.classList.remove('cfm-dup-dialog', 'modal-dialog-scrollable');
            dialog.classList.add('modal-lg', 'modal-dialog-centered');
        }
    } else if (pane === 'create' && create) {
        create.classList.remove('d-none');
        if (cfmModalMode === 'edit') {
            document.getElementById('modalTitle').textContent = 'Modifier la catégorie';
            document.getElementById('modalSubtitle').textContent = 'Flux manuel · compte d\'exploitation';
            if (backCreate) backCreate.style.display = 'none';
        } else {
            document.getElementById('modalTitle').textContent = 'Créer une catégorie';
            document.getElementById('modalSubtitle').textContent = 'Saisie manuelle';
            if (backCreate) backCreate.style.display = cfmCanImport ? '' : 'none';
        }
        if (dialog) {
            dialog.classList.remove('cfm-dup-dialog', 'modal-dialog-scrollable');
            dialog.classList.add('modal-lg', 'modal-dialog-centered');
        }
    } else if (pane === 'duplicate' && dup) {
        dup.classList.remove('d-none');
        document.getElementById('modalTitle').textContent = 'Dupliquer depuis d\'autres AEP';
        document.getElementById('modalSubtitle').textContent = 'Vers votre réseau actuel';
        if (dialog) {
            dialog.classList.add('cfm-dup-dialog', 'modal-dialog-scrollable');
            dialog.classList.remove('modal-lg');
        }
    }
}

function cfmResetCreateForm() {
    document.getElementById('modalTitle').textContent = 'Nouvelle catégorie';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('btnUpdateAllAep').style.display = 'none';
    ['code_budgetaire', 'nom', 'description', 'ordre_affichage'].forEach(function (id) {
        document.getElementById(id).value = '';
    });
    document.getElementById('type_flux').value = 'charge';
    document.getElementById('activite_associee').value = 'autre';
    document.getElementById('est_actif').checked = true;
}

function cfmOpenNewModal() {
    cfmModalMode = 'new';
    cfmResetCreateForm();
    if (cfmCanImport) {
        cfmShowPane('choice');
    } else {
        cfmShowPane('create');
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('categorieModal')).show();
}

function cfmGetSortRows(tbody) {
    return [].slice.call(tbody.querySelectorAll('tr.cfm-sort-row'));
}

function cfmUpdateOrdreUi(tbody, ordres) {
    cfmGetSortRows(tbody).forEach(function (row, idx) {
        var id = row.getAttribute('data-id');
        var numEl = row.querySelector('.cfm-ordre-num');
        if (numEl) {
            numEl.textContent = (ordres && ordres[id] !== undefined) ? ordres[id] : (idx + 1) * 10;
        }
    });
}

function cfmSaveSortOrder(tbody, typeFlux, savingEl) {
    var ids = [];
    cfmGetSortRows(tbody).forEach(function (row) { ids.push(row.getAttribute('data-id')); });
    if (savingEl) savingEl.classList.add('is-visible');
    var formData = new FormData();
    formData.append('action', 'reorder_ordre');
    formData.append('type_flux', typeFlux);
    formData.append('_csrf', cfmCsrfToken);
    ids.forEach(function (id) { formData.append('categorie_ids[]', id); });
    fetch(cfmAjaxReorderUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            if (savingEl) savingEl.classList.remove('is-visible');
            var data = null;
            try { data = JSON.parse(text); } catch (e) {
                var m = text.match(/\{[\s\S]*\}/);
                if (m) try { data = JSON.parse(m[0]); } catch (e2) {}
            }
            if (data && data.ok) cfmUpdateOrdreUi(tbody, data.ordres);
            else cfmUpdateOrdreUi(tbody, null);
        })
        .catch(function () {
            if (savingEl) savingEl.classList.remove('is-visible');
        });
}

function initCfmDragDrop() {
    var draggedRow = null;
    [].slice.call(document.querySelectorAll('.cfm-sort-group')).forEach(function (tbody) {
        var typeFlux = tbody.getAttribute('data-type-flux');
        var savingEl = document.getElementById('cfm-sort-saving-' + typeFlux);
        cfmGetSortRows(tbody).forEach(function (row) {
            var handle = row.querySelector('.cfm-drag-handle');
            if (!handle) return;
            handle.setAttribute('draggable', 'true');
            handle.addEventListener('dragstart', function (e) {
                draggedRow = row;
                row.classList.add('cfm-row-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragend', function () {
                row.classList.remove('cfm-row-dragging');
                cfmGetSortRows(tbody).forEach(function (r) { r.classList.remove('cfm-row-drag-over'); });
                draggedRow = null;
            });
            row.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!draggedRow || draggedRow === row) return;
                cfmGetSortRows(tbody).forEach(function (r) { r.classList.remove('cfm-row-drag-over'); });
                row.classList.add('cfm-row-drag-over');
            });
            row.addEventListener('drop', function (e) {
                e.preventDefault();
                row.classList.remove('cfm-row-drag-over');
                if (!draggedRow || draggedRow === row) return;
                var all = cfmGetSortRows(tbody);
                var fromIdx = all.indexOf(draggedRow);
                var toIdx = all.indexOf(row);
                if (fromIdx < toIdx) row.parentNode.insertBefore(draggedRow, row.nextSibling);
                else row.parentNode.insertBefore(draggedRow, row);
                cfmUpdateOrdreUi(tbody, null);
                cfmSaveSortOrder(tbody, typeFlux, savingEl);
            });
        });
    });
}

function editCategorie(categorie) {
    cfmModalMode = 'edit';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = categorie.id;
    document.getElementById('code_budgetaire').value = categorie.code_budgetaire || '';
    document.getElementById('type_flux').value = categorie.type_flux;
    document.getElementById('activite_associee').value = categorie.activite_associee || 'autre';
    document.getElementById('nom').value = categorie.nom;
    document.getElementById('description').value = categorie.description || '';
    document.getElementById('ordre_affichage').value = categorie.ordre_affichage !== undefined ? categorie.ordre_affichage : '';
    document.getElementById('est_actif').checked = (categorie.est_actif == 1 || categorie.est_actif == '1');
    var btnAll = document.getElementById('btnUpdateAllAep');
    if (btnAll) {
        btnAll.style.display = (cfmNbMemeCode > 1) ? 'inline-block' : 'none';
    }
    cfmShowPane('create');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('categorieModal')).show();
}

document.getElementById('categorieForm').addEventListener('submit', function (e) {
    var submitter = e.submitter;
    if (submitter && submitter.id === 'btnUpdateAllAep') {
        document.getElementById('formAction').value = 'update_all_aep';
        if (!confirm('Appliquer ces modifications à toutes les catégories ayant le même code budgétaire sur tous les AEP ?')) {
            e.preventDefault();
            document.getElementById('formAction').value = 'update';
        }
    }
});

document.getElementById('categorieModal').addEventListener('hidden.bs.modal', function () {
    cfmModalMode = 'new';
    cfmResetCreateForm();
    if (cfmCanImport) {
        cfmShowPane('choice');
    } else {
        cfmShowPane('create');
    }
});

function confirmDelete(id, nom) {
    document.getElementById('deleteCategorieId').value = id;
    document.getElementById('deleteCategorieNom').textContent = nom;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.addEventListener('DOMContentLoaded', function () {
    initCfmDragDrop();

    var btnNew = document.getElementById('btnNouvelleCategorie');
    if (btnNew) {
        btnNew.addEventListener('click', cfmOpenNewModal);
    }
    var btnChooseCreate = document.getElementById('cfmBtnChooseCreate');
    if (btnChooseCreate) {
        btnChooseCreate.addEventListener('click', function () {
            cfmResetCreateForm();
            cfmShowPane('create');
        });
    }
    var btnChooseDup = document.getElementById('cfmBtnChooseDuplicate');
    if (btnChooseDup) {
        btnChooseDup.addEventListener('click', function () {
            cfmShowPane('duplicate');
        });
    }
    var backCreate = document.getElementById('cfmBackToChoice');
    if (backCreate) {
        backCreate.addEventListener('click', function () {
            cfmShowPane('choice');
        });
    }
    var backDup = document.getElementById('cfmBackFromDuplicate');
    if (backDup) {
        backDup.addEventListener('click', function () {
            cfmShowPane('choice');
        });
    }

    var dupSelectAll = document.getElementById('dupFromSelectAll');
    if (dupSelectAll) {
        dupSelectAll.addEventListener('click', function () {
            document.querySelectorAll('#cfmPaneDuplicate .dup-from-cat-cb:not(:disabled)').forEach(function (cb) {
                cb.checked = true;
            });
        });
    }
    var dupSelectNone = document.getElementById('dupFromSelectNone');
    if (dupSelectNone) {
        dupSelectNone.addEventListener('click', function () {
            document.querySelectorAll('#cfmPaneDuplicate .dup-from-cat-cb').forEach(function (cb) {
                cb.checked = false;
            });
        });
    }
    var catModal = document.getElementById('categorieModal');
    if (catModal) {
        catModal.addEventListener('click', function (e) {
            var btnAll = e.target.closest('.dup-from-select-aep-all');
            var btnNone = e.target.closest('.dup-from-deselect-aep-all');
            if (!btnAll && !btnNone) return;
            var groupId = (btnAll || btnNone).getAttribute('data-dup-group');
            var panel = groupId ? document.getElementById(groupId) : null;
            if (!panel) return;
            panel.querySelectorAll('.dup-from-cat-cb').forEach(function (cb) {
                if (cb.disabled) return;
                cb.checked = !!btnAll;
            });
        });
    }
    var dupFromForm = document.getElementById('duplicateFromForm');
    if (dupFromForm) {
        dupFromForm.addEventListener('submit', function (e) {
            if (!document.querySelectorAll('#cfmPaneDuplicate .dup-from-cat-cb:checked:not(:disabled)').length) {
                e.preventDefault();
                alert('Veuillez cocher au moins une catégorie à dupliquer.');
                return false;
            }
            var btn = document.getElementById('dupFromSubmitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Duplication…';
            }
        });
    }

    if (cfmCanImport) {
        cfmShowPane('choice');
    } else {
        cfmShowPane('create');
    }

    var dupForm = document.getElementById('duplicateAepForm');
    if (dupForm) {
        document.getElementById('dupAepSelectAll').addEventListener('click', function () {
            document.querySelectorAll('.dup-aep-cb:not(:disabled)').forEach(function (cb) { cb.checked = true; });
        });
        document.getElementById('dupAepSelectNone').addEventListener('click', function () {
            document.querySelectorAll('.dup-aep-cb').forEach(function (cb) { cb.checked = false; });
        });
        dupForm.addEventListener('submit', function (e) {
            if (!document.querySelectorAll('.dup-aep-cb:checked:not(:disabled)').length) {
                e.preventDefault();
                alert('Cochez au moins un AEP.');
            }
        });
    }
});
</script>
