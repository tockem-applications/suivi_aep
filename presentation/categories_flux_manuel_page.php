<?php
@include_once("../donnees/categorie_flux_manuel.php");
@include_once("donnees/categorie_flux_manuel.php");

CategorieFluxManuel::ensureOrdreAffichageColumn();

// Gérer les actions CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
        
        if ($action === 'create') {
            $categorie = new CategorieFluxManuel();
            $categorie->code_budgetaire = isset($_POST['code_budgetaire']) && !empty($_POST['code_budgetaire']) ? $_POST['code_budgetaire'] : null;
            if (empty($categorie->code_budgetaire)) {
                header('Location: ?page=categories_flux_manuel&error=code_obligatoire');
                exit;
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
            $categorie->id_aep = $id_aep; // NULL pour global
            $categorie->ajouter();
            header('Location: ?page=categories_flux_manuel&success=1');
            exit;
        } elseif ($action === 'update') {
            $categorie = new CategorieFluxManuel();
            $categorie->id = $_POST['id'];
            $categorie->code_budgetaire = isset($_POST['code_budgetaire']) && !empty($_POST['code_budgetaire']) ? $_POST['code_budgetaire'] : null;
            if (empty($categorie->code_budgetaire)) {
                header('Location: ?page=categories_flux_manuel&error=code_obligatoire');
                exit;
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
                $existant = CategorieFluxManuel::getById($categorie->id);
                $categorie->ordre_affichage = $existant && isset($existant['ordre_affichage'])
                    ? (int) $existant['ordre_affichage'] : 0;
            }
            $categorie->id_aep = $id_aep;
            $categorie->update();
            header('Location: ?page=categories_flux_manuel&success=1');
            exit;
        } elseif ($action === 'delete') {
            $categorie = new CategorieFluxManuel();
            $categorie->delete($_POST['id']);
            header('Location: ?page=categories_flux_manuel&success=1');
            exit;
        } elseif ($action === 'duplicate') {
            $ids = isset($_POST['categorie_ids']) && is_array($_POST['categorie_ids'])
                ? $_POST['categorie_ids'] : array();
            if ($id_aep <= 0) {
                header('Location: index.php?page=categories_flux_manuel&error=no_aep');
                exit;
            }
            if (empty($ids)) {
                header('Location: index.php?page=categories_flux_manuel&error=duplicate_vide');
                exit;
            }
            $dup = CategorieFluxManuel::duplicateCategoriesToAep($ids, $id_aep);
            header(
                'Location: index.php?page=categories_flux_manuel&success=duplicate'
                . '&created=' . (int) $dup['created']
                . '&skipped=' . (int) $dup['skipped']
                . '&errors=' . (int) $dup['errors']
            );
            exit;
        } elseif ($action === 'reorder_ordre') {
            $type_flux = isset($_POST['type_flux']) ? $_POST['type_flux'] : '';
            $ids = isset($_POST['categorie_ids']) && is_array($_POST['categorie_ids'])
                ? $_POST['categorie_ids'] : array();
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
            header(
                'Location: index.php?page=categories_flux_manuel&'
                . ($ok ? 'success=move' : 'error=move_invalid')
            );
            exit;
        }
    }
}

// Récupérer toutes les catégories
$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$libele_aep = isset($_SESSION['libele_aep']) ? $_SESSION['libele_aep'] : '';
$res = CategorieFluxManuel::getAll(null, $id_aep ? $id_aep : null);
$categories = $res->fetchAll(PDO::FETCH_ASSOC);

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

$activite_labels = array(
    'branchements' => 'Branchements',
    'vente_eau' => 'Vente d\'eau',
    'autre' => 'Autre',
);
$type_flux_labels = array(
    'recette' => 'Recette',
    'charge' => 'Dépense',
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

$cfm_form_action = 'index.php?page=categories_flux_manuel';
$cfm_ajax_reorder_url = 'traitement/categorie_flux_ordre_t.php';

function cfm_render_category_row($cat, $type_flux_labels, $activite_labels)
{
    $cat_id = (int) $cat['id'];
    $ordre = (int) (isset($cat['ordre_affichage']) ? $cat['ordre_affichage'] : 0);
    $activite = isset($cat['activite_associee']) ? $cat['activite_associee'] : 'autre';
    ?>
    <tr class="cfm-sort-row" data-id="<?php echo $cat_id; ?>">
        <td class="cfm-col-drag text-center">
            <span class="cfm-drag-handle" title="Glisser pour réordonner" aria-label="Glisser pour réordonner">
                <i class="bi bi-grip-vertical"></i>
            </span>
        </td>
        <td class="text-center cfm-col-ordre">
            <span class="cfm-ordre-num"><?php echo $ordre; ?></span>
        </td>
        <td><?php echo htmlspecialchars($cat['nom']); ?></td>
        <td>
            <span class="badge <?php echo $cat['type_flux'] === 'recette' ? 'bg-success' : 'bg-danger'; ?>">
                <?php echo htmlspecialchars(isset($type_flux_labels[$cat['type_flux']]) ? $type_flux_labels[$cat['type_flux']] : $cat['type_flux']); ?>
            </span>
        </td>
        <td>
            <?php if (!empty($cat['code_budgetaire'])): ?>
                <span class="badge bg-info"><?php echo htmlspecialchars($cat['code_budgetaire']); ?></span>
            <?php else: ?>
                <span class="text-muted">-</span>
            <?php endif; ?>
        </td>
        <td>
            <span class="badge bg-secondary"><?php echo htmlspecialchars(isset($activite_labels[$activite]) ? $activite_labels[$activite] : $activite); ?></span>
        </td>
        <td><?php echo htmlspecialchars(isset($cat['description']) ? $cat['description'] : ''); ?></td>
        <td>
            <span class="badge <?php echo $cat['est_actif'] ? 'bg-success' : 'bg-secondary'; ?>"
                data-bs-toggle="tooltip" data-bs-placement="top"
                title="<?php echo $cat['est_actif'] ? 'Cette catégorie est active et peut être utilisée' : 'Cette catégorie est inactive et ne sera pas proposée lors de la création de flux'; ?>">
                <?php echo $cat['est_actif'] ? 'Actif' : 'Inactif'; ?>
            </span>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-warning me-2"
                onclick="editCategorie(<?php echo htmlspecialchars(json_encode($cat)); ?>)"
                data-bs-toggle="tooltip" data-bs-placement="top" title="Modifier">
                <i class="fas fa-edit me-1"></i>Modifier
            </button>
            <button type="button" class="btn btn-sm btn-danger"
                onclick="confirmDelete(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['nom'])); ?>')"
                data-bs-toggle="tooltip" data-bs-placement="top" title="Supprimer">
                <i class="fas fa-trash me-1"></i>Supprimer
            </button>
        </td>
    </tr>
    <?php
}
?>

<style>
    .cfm-page { max-width: 1200px; }
    .cfm-page .cfm-card {
        border: 1px solid #e8eaed;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(60, 64, 67, 0.08);
    }
    .cfm-page .cfm-card .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
    }
    .cfm-page .cfm-card .table td.cfm-col-ordre {
        padding-top: 0.35rem;
        padding-bottom: 0.35rem;
        white-space: nowrap;
    }
    .cfm-ordre-num {
        font-size: 0.75rem;
        color: #5f6368;
    }
    .cfm-page .cfm-card .table td.cfm-col-drag {
        padding: 0.35rem 0.25rem;
        width: 1.75rem;
    }
    .cfm-drag-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #9aa0a6;
        cursor: grab;
        font-size: 1rem;
        padding: 0.15rem;
        border-radius: 3px;
        user-select: none;
        -webkit-user-select: none;
    }
    .cfm-drag-handle:hover {
        color: #1a73e8;
        background: #e8f0fe;
    }
    .cfm-drag-handle:active {
        cursor: grabbing;
    }
    tr.cfm-sort-row.cfm-row-dragging {
        opacity: 0.55;
        background: #e8f0fe !important;
    }
    tr.cfm-sort-row.cfm-row-drag-over td {
        border-top: 2px solid #1a73e8;
    }
    tr.cfm-sort-group-header td {
        font-size: 0.8rem;
        padding: 0.4rem 0.75rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    .cfm-sort-saving {
        font-size: 0.75rem;
        color: #1a73e8;
        margin-left: 0.5rem;
        display: none;
    }
    .cfm-sort-saving.is-visible {
        display: inline;
    }
    #duplicateModal .modal-dialog.cfm-dup-dialog {
        max-width: 920px;
        width: calc(100% - 2rem);
    }
    #duplicateModal.modal .modal-body.cfm-dup-scroll {
        max-height: min(58vh, 520px);
        overflow-y: auto;
    }
    #duplicateModal .modal-footer {
        background: #f8f9fa;
        border-top: 1px solid #dee2e6;
    }
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
    #categorieModal .cfm-modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.85;
    }
    #categorieModal .cfm-modal-body {
        padding: 1.5rem;
        background: #f8fafc;
    }
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
    #categorieModal .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
    }
    #categorieModal .form-control,
    #categorieModal .form-select {
        border-radius: 8px;
        border-color: #cbd5e1;
    }
    #categorieModal .form-control:focus,
    #categorieModal .form-select:focus {
        border-color: #1a73e8;
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.15);
    }
    #categorieModal .cfm-type-pills .btn {
        border-radius: 8px;
        font-weight: 500;
    }
    #categorieModal .cfm-modal-footer {
        background: #fff;
        border-top: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
    }
</style>

<div class="container mt-4 cfm-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="text-primary fw-bold mb-0">Gestion des Catégories de Flux Manuels</h2>
            <?php if ($id_aep > 0 && $libele_aep !== ''): ?>
                <p class="text-muted small mb-0">AEP : <strong><?php echo htmlspecialchars($libele_aep, ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($id_aep > 0 && !empty($autres_aeps)): ?>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#duplicateModal">
                    <i class="fas fa-copy me-2"></i>Dupliquer depuis d'autres AEP
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categorieModal">
                <i class="fas fa-plus me-2"></i>Nouvelle catégorie
            </button>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Opération effectuée avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] === 'code_obligatoire'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Le code budgétaire est obligatoire !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'no_aep'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Sélectionnez un AEP avant de dupliquer des catégories.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate_vide'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Aucune catégorie sélectionnée pour la duplication.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'move'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Ordre d'affichage mis à jour.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'move_limite'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Déplacement impossible : la catégorie est déjà en première ou dernière position pour son type.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'duplicate'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Duplication terminée :
            <strong><?php echo (int) (isset($_GET['created']) ? $_GET['created'] : 0); ?></strong> catégorie(s) ajoutée(s),
            <strong><?php echo (int) (isset($_GET['skipped']) ? $_GET['skipped'] : 0); ?></strong> ignorée(s) (déjà présentes ou source invalide).
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card cfm-card">
        <div class="card-body">
            <p class="small text-muted mb-3">
                <i class="bi bi-sort-numeric-down me-1"></i>
                L'<strong>ordre d'affichage</strong> définit la position dans les comptes d'exploitation.
                Utilisez la poignée <i class="bi bi-grip-vertical"></i> pour glisser-déposer les lignes (dépenses puis recettes).
            </p>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width:1.75rem" title="Glisser-déposer"></th>
                            <th class="text-center" style="width:3rem">Ordre</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Code budgétaire</th>
                            <th>Activité associée</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <?php foreach ($cfm_sort_groups as $type_key => $group_info):
                        $group_cats = isset($categories_by_type[$type_key]) ? $categories_by_type[$type_key] : array();
                        if (empty($group_cats)) {
                            continue;
                        }
                        ?>
                        <tbody class="cfm-sort-group" id="cfm-sort-<?php echo htmlspecialchars($type_key, ENT_QUOTES, 'UTF-8'); ?>"
                            data-type-flux="<?php echo htmlspecialchars($type_key, ENT_QUOTES, 'UTF-8'); ?>">
                            <tr class="cfm-sort-group-header">
                                <td colspan="9">
                                    <span class="badge <?php echo htmlspecialchars($group_info['badge'], ENT_QUOTES, 'UTF-8'); ?> me-2">
                                        <?php echo htmlspecialchars($group_info['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    Glisser les lignes pour réordonner
                                    <span class="cfm-sort-saving" id="cfm-sort-saving-<?php echo htmlspecialchars($type_key, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>Enregistrement…
                                    </span>
                                </td>
                            </tr>
                            <?php foreach ($group_cats as $cat):
                                cfm_render_category_row($cat, $type_flux_labels, $activite_labels);
                            endforeach; ?>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour créer/modifier une catégorie -->
<div class="modal fade" id="categorieModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" id="categorieForm">
                <div class="modal-header cfm-modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="modalTitle">Nouvelle catégorie</h5>
                        <p class="small mb-0 opacity-75">Flux manuel · compte d'exploitation</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body cfm-modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="formId">

                    <div class="cfm-field-card">
                        <h6><i class="bi bi-tag me-1"></i> Identification</h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" required
                                    placeholder="Ex. Maintenance réseau">
                            </div>
                            <div class="col-md-4">
                                <label for="code_budgetaire" class="form-label">Code budgétaire <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_budgetaire" name="code_budgetaire"
                                    maxlength="10" placeholder="A001" required>
                            </div>
                        </div>
                    </div>

                    <div class="cfm-field-card">
                        <h6><i class="bi bi-sliders me-1"></i> Paramètres</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="type_flux" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type_flux" name="type_flux" required>
                                    <option value="charge" selected>Dépense</option>
                                    <option value="recette">Recette</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="ordre_affichage" class="form-label">Ordre d'affichage</label>
                                <input type="number" class="form-control" id="ordre_affichage" name="ordre_affichage"
                                    min="1" step="1" placeholder="Vide = dernière position">
                            </div>
                            <div class="col-md-4">
                                <label for="activite_associee" class="form-label">Activité</label>
                                <select class="form-select" id="activite_associee" name="activite_associee" required>
                                    <option value="branchements">Branchements</option>
                                    <option value="vente_eau">Vente d'eau</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input type="checkbox" class="form-check-input" id="est_actif" name="est_actif" value="1" checked>
                            <label class="form-check-label" for="est_actif">Catégorie active (proposée à la saisie des flux)</label>
                        </div>
                    </div>

                    <div class="cfm-field-card mb-0">
                        <h6><i class="bi bi-card-text me-1"></i> Description</h6>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Optionnel — précision pour les utilisateurs"></textarea>
                    </div>
                </div>
                <div class="modal-footer cfm-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal duplication depuis d'autres AEP -->
<div class="modal fade" id="duplicateModal" tabindex="-1" aria-labelledby="duplicateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable cfm-dup-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title" id="duplicateModalLabel">
                    <i class="fas fa-copy me-2"></i>Dupliquer des catégories vers
                    <?php echo $libele_aep !== '' ? htmlspecialchars($libele_aep, ENT_QUOTES, 'UTF-8') : 'cet AEP'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="post" action="<?php echo htmlspecialchars($cfm_form_action, ENT_QUOTES, 'UTF-8'); ?>" id="duplicateForm">
                <input type="hidden" name="action" value="duplicate">
                <div class="modal-body cfm-dup-scroll">
                    <p class="text-muted small">
                        Cochez les catégories à copier depuis les autres AEP. Les doublons (même code budgétaire et type)
                        déjà présents sur votre AEP sont désactivés.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dupSelectAll">
                            Tout cocher (disponibles)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dupSelectNone">
                            Tout décocher
                        </button>
                    </div>
                    <div class="accordion" id="dupAepAccordion">
                        <?php
                        $acc_idx = 0;
                        foreach ($autres_aeps as $aep_row):
                            $aid = (int) $aep_row['id'];
                            $cats_aep = isset($categories_par_aep[$aid]) ? $categories_par_aep[$aid] : array();
                            $acc_idx++;
                            $acc_id = 'dupAep' . $aid;
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
                                    data-bs-parent="#dupAepAccordion">
                                    <div class="accordion-body p-0">
                                        <?php if (empty($cats_aep)): ?>
                                            <p class="text-muted small p-3 mb-0">Aucune catégorie propre à cet AEP.</p>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-2 p-2 border-bottom bg-light">
                                                <?php $collapse_id = 'collapse' . $acc_id; ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary dup-select-aep-all"
                                                    data-dup-group="<?php echo htmlspecialchars($collapse_id, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="bi bi-check2-square me-1"></i>Tout sélectionner
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary dup-deselect-aep-all"
                                                    data-dup-group="<?php echo htmlspecialchars($collapse_id, ENT_QUOTES, 'UTF-8'); ?>">
                                                    Tout décocher
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width:2.5rem"></th>
                                                            <th class="text-center">Ordre</th>
                                                            <th>Nom</th>
                                                            <th>Type</th>
                                                            <th>Code</th>
                                                            <th>Activité</th>
                                                            <th>Statut</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cats_aep as $src_cat):
                                                            $dup_key = $src_cat['type_flux'] . '|' . $src_cat['code_budgetaire'];
                                                            $deja_presente = isset($codes_existants[$dup_key]);
                                                            ?>
                                                            <tr class="<?php echo $deja_presente ? 'table-secondary' : ''; ?>">
                                                                <td>
                                                                    <input type="checkbox" class="form-check-input dup-cat-cb"
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
                                                                        <?php
                                                                        echo htmlspecialchars(
                                                                            isset($type_flux_labels[$src_cat['type_flux']]) ? $type_flux_labels[$src_cat['type_flux']] : $src_cat['type_flux'],
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        );
                                                                        ?>
                                                                    </span>
                                                                </td>
                                                                <td><code><?php echo htmlspecialchars($src_cat['code_budgetaire'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                                                <td class="small">
                                                                    <?php
                                                                    $act = isset($src_cat['activite_associee']) ? $src_cat['activite_associee'] : 'autre';
                                                                    echo htmlspecialchars(isset($activite_labels[$act]) ? $activite_labels[$act] : $act, ENT_QUOTES, 'UTF-8');
                                                                    ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($deja_presente): ?>
                                                                        <span class="badge bg-warning text-dark">Déjà sur cet AEP</span>
                                                                    <?php elseif ($src_cat['est_actif']): ?>
                                                                        <span class="badge bg-success">Actif</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">Inactif</span>
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
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" id="dupSubmitBtn" form="duplicateForm">
                    <i class="fas fa-copy me-1"></i>Dupliquer la sélection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmer la suppression
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer la catégorie <strong id="deleteCategorieNom"></strong> ?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Attention :</strong> Cette action est irréversible. Les flux financiers associés à cette catégorie ne seront pas supprimés, mais perdront leur catégorie.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Annuler
                </button>
                <form method="post" action="" id="deleteForm" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteCategorieId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var cfmFormAction = <?php echo json_encode($cfm_form_action); ?>;
var cfmAjaxReorderUrl = <?php echo json_encode($cfm_ajax_reorder_url); ?>;

function cfmGetSortRows(tbody) {
    return [].slice.call(tbody.querySelectorAll('tr.cfm-sort-row'));
}

function cfmUpdateOrdreUi(tbody, ordres) {
    var rows = cfmGetSortRows(tbody);
    rows.forEach(function (row, idx) {
        var id = row.getAttribute('data-id');
        var numEl = row.querySelector('.cfm-ordre-num');
        if (numEl) {
            if (ordres && ordres[id] !== undefined) {
                numEl.textContent = ordres[id];
            } else {
                numEl.textContent = (idx + 1) * 10;
            }
        }
    });
}

function cfmSaveSortOrder(tbody, typeFlux, savingEl) {
    var ids = [];
    cfmGetSortRows(tbody).forEach(function (row) {
        ids.push(row.getAttribute('data-id'));
    });
    if (savingEl) {
        savingEl.classList.add('is-visible');
    }

    var formData = new FormData();
    formData.append('action', 'reorder_ordre');
    formData.append('type_flux', typeFlux);
    ids.forEach(function (id) {
        formData.append('categorie_ids[]', id);
    });

    fetch(cfmAjaxReorderUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(function (response) {
            return response.text().then(function (text) {
                return { okHttp: response.ok, text: text };
            });
        })
        .then(function (res) {
            if (savingEl) {
                savingEl.classList.remove('is-visible');
            }
            var data = null;
            try {
                data = JSON.parse(res.text);
            } catch (e) {
                var match = res.text.match(/\{[\s\S]*\}/);
                if (match) {
                    try {
                        data = JSON.parse(match[0]);
                    } catch (e2) {
                        data = null;
                    }
                }
            }
            if (data && data.ok) {
                cfmUpdateOrdreUi(tbody, data.ordres || null);
            } else if (res.okHttp) {
                cfmUpdateOrdreUi(tbody, null);
            } else {
                alert('Impossible d\'enregistrer le nouvel ordre.');
            }
        })
        .catch(function () {
            if (savingEl) {
                savingEl.classList.remove('is-visible');
            }
            cfmUpdateOrdreUi(tbody, null);
        });
}

function initCfmDragDrop() {
    var draggedRow = null;

    [].slice.call(document.querySelectorAll('.cfm-sort-group')).forEach(function (tbody) {
        var typeFlux = tbody.getAttribute('data-type-flux');
        var savingEl = document.getElementById('cfm-sort-saving-' + typeFlux);

        cfmUpdateOrdreUi(tbody, null);

        cfmGetSortRows(tbody).forEach(function (row) {
            var handle = row.querySelector('.cfm-drag-handle');
            if (!handle) {
                return;
            }

            handle.setAttribute('draggable', 'true');

            handle.addEventListener('dragstart', function (e) {
                draggedRow = row;
                row.classList.add('cfm-row-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
            });

            row.addEventListener('dragend', function () {
                row.classList.remove('cfm-row-dragging');
                cfmGetSortRows(tbody).forEach(function (r) {
                    r.classList.remove('cfm-row-drag-over');
                });
                draggedRow = null;
            });

            row.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!draggedRow || draggedRow === row) {
                    return;
                }
                e.dataTransfer.dropEffect = 'move';
                cfmGetSortRows(tbody).forEach(function (r) {
                    r.classList.remove('cfm-row-drag-over');
                });
                row.classList.add('cfm-row-drag-over');
            });

            row.addEventListener('dragleave', function () {
                row.classList.remove('cfm-row-drag-over');
            });

            row.addEventListener('drop', function (e) {
                e.preventDefault();
                row.classList.remove('cfm-row-drag-over');
                if (!draggedRow || draggedRow === row) {
                    return;
                }

                var all = cfmGetSortRows(tbody);
                var fromIdx = all.indexOf(draggedRow);
                var toIdx = all.indexOf(row);
                if (fromIdx < 0 || toIdx < 0) {
                    return;
                }

                if (fromIdx < toIdx) {
                    row.parentNode.insertBefore(draggedRow, row.nextSibling);
                } else {
                    row.parentNode.insertBefore(draggedRow, row);
                }

                cfmUpdateOrdreUi(tbody, null);
                cfmSaveSortOrder(tbody, typeFlux, savingEl);
            });
        });
    });
}

function editCategorie(categorie) {
    document.getElementById('modalTitle').textContent = 'Modifier la catégorie';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = categorie.id;
    document.getElementById('code_budgetaire').value = categorie.code_budgetaire || '';
    document.getElementById('type_flux').value = categorie.type_flux;
    document.getElementById('activite_associee').value = categorie.activite_associee || 'autre';
    document.getElementById('nom').value = categorie.nom;
    document.getElementById('description').value = categorie.description || '';
    document.getElementById('ordre_affichage').value = categorie.ordre_affichage !== undefined ? categorie.ordre_affichage : 0;
    document.getElementById('est_actif').checked = (categorie.est_actif == 1 || categorie.est_actif == '1');

    var modal = new bootstrap.Modal(document.getElementById('categorieModal'));
    modal.show();
}

document.getElementById('categorieModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Nouvelle catégorie';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('code_budgetaire').value = '';
    document.getElementById('type_flux').value = 'charge';
    document.getElementById('activite_associee').value = 'autre';
    document.getElementById('nom').value = '';
    document.getElementById('description').value = '';
    document.getElementById('est_actif').checked = true;
    document.getElementById('ordre_affichage').value = '';
});

// Fonction pour confirmer la suppression
function confirmDelete(id, nom) {
    document.getElementById('deleteCategorieId').value = id;
    document.getElementById('deleteCategorieNom').textContent = nom;
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Initialiser les tooltips Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    initCfmDragDrop();

    var dupSelectAll = document.getElementById('dupSelectAll');
    var dupSelectNone = document.getElementById('dupSelectNone');
    var dupForm = document.getElementById('duplicateForm');

    function getDupCheckboxes() {
        return document.querySelectorAll('#duplicateModal .dup-cat-cb:not(:disabled)');
    }

    if (dupSelectAll) {
        dupSelectAll.addEventListener('click', function () {
            getDupCheckboxes().forEach(function (cb) { cb.checked = true; });
        });
    }
    if (dupSelectNone) {
        dupSelectNone.addEventListener('click', function () {
            document.querySelectorAll('#duplicateModal .dup-cat-cb').forEach(function (cb) { cb.checked = false; });
        });
    }

    var dupModal = document.getElementById('duplicateModal');
    if (dupModal) {
        dupModal.addEventListener('click', function (e) {
            var btnAll = e.target.closest('.dup-select-aep-all');
            var btnNone = e.target.closest('.dup-deselect-aep-all');
            if (!btnAll && !btnNone) {
                return;
            }
            var groupId = (btnAll || btnNone).getAttribute('data-dup-group');
            var panel = groupId ? document.getElementById(groupId) : null;
            if (!panel) {
                return;
            }
            panel.querySelectorAll('.dup-cat-cb').forEach(function (cb) {
                if (cb.disabled) {
                    return;
                }
                cb.checked = !!btnAll;
            });
        });
    }
    if (dupForm) {
        dupForm.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('#duplicateModal .dup-cat-cb:checked:not(:disabled)');
            if (checked.length === 0) {
                e.preventDefault();
                alert('Veuillez cocher au moins une catégorie à dupliquer.');
                return false;
            }
            var btn = document.getElementById('dupSubmitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Duplication…';
            }
        });
    }
});
</script>
