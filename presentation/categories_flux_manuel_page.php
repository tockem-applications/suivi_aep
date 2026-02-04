<?php
@include_once("../donnees/categorie_flux_manuel.php");
@include_once("donnees/categorie_flux_manuel.php");

// Gérer les actions CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : null;
        
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
            $categorie->id_aep = $id_aep;
            $categorie->update();
            header('Location: ?page=categories_flux_manuel&success=1');
            exit;
        } elseif ($action === 'delete') {
            $categorie = new CategorieFluxManuel();
            $categorie->delete($_POST['id']);
            header('Location: ?page=categories_flux_manuel&success=1');
            exit;
        }
    }
}

// Récupérer toutes les catégories
$id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : null;
$res = CategorieFluxManuel::getAll(null, $id_aep);
$categories = $res->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold">Gestion des Catégories de Flux Manuels</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categorieModal">
            <i class="fas fa-plus me-2"></i>Nouvelle catégorie
        </button>
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

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Code budgétaire</th>
                            <th>Activité associée</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cat['nom']); ?></td>
                                <td>
                                    <span class="badge <?php echo $cat['type_flux'] === 'recette' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo htmlspecialchars($cat['type_flux']); ?>
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
                                    <?php 
                                    $activite_labels = array(
                                        'branchements' => 'Branchements',
                                        'vente_eau' => 'Vente d\'eau',
                                        'autre' => 'Autre'
                                    );
                                    $activite = isset($cat['activite_associee']) ? $cat['activite_associee'] : 'autre';
                                    ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($activite_labels[$activite]); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(isset($cat['description']) ? $cat['description'] : ''); ?></td>
                                <td>
                                    <span class="badge <?php echo $cat['est_actif'] ? 'bg-success' : 'bg-secondary'; ?>" 
                                          data-bs-toggle="tooltip" 
                                          data-bs-placement="top" 
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour créer/modifier une catégorie -->
<div class="modal fade" id="categorieModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvelle catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="code_budgetaire" class="form-label">Code budgétaire <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="code_budgetaire" name="code_budgetaire" 
                                   maxlength="10" placeholder="Ex: A001, BUD-001" required>
                            <small class="form-text text-muted">Code budgétaire pour le regroupement (max 10 caractères)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="type_flux" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_flux" name="type_flux" required>
                                <option value="recette">Recette</option>
                                <option value="charge">Charge</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="activite_associee" class="form-label">Activité associée <span class="text-danger">*</span></label>
                            <select class="form-select" id="activite_associee" name="activite_associee" required>
                                <option value="branchements">Branchements</option>
                                <option value="vente_eau">Vente d'eau</option>
                                <option value="autre">Autre</option>
                            </select>
                            <small class="form-text text-muted">Indique l'activité associée</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="est_actif" name="est_actif" value="1" checked>
                                <label class="form-check-label" for="est_actif">
                                    Catégorie active
                                </label>
                            </div>
                            <small class="form-text text-muted">Une catégorie active peut être utilisée lors de la création de flux financiers</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
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
function editCategorie(categorie) {
    document.getElementById('modalTitle').textContent = 'Modifier la catégorie';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = categorie.id;
    document.getElementById('code_budgetaire').value = categorie.code_budgetaire || '';
    document.getElementById('type_flux').value = categorie.type_flux;
    document.getElementById('activite_associee').value = categorie.activite_associee || 'autre';
    document.getElementById('nom').value = categorie.nom;
    document.getElementById('description').value = categorie.description || '';
    document.getElementById('est_actif').checked = (categorie.est_actif == 1 || categorie.est_actif == '1');
    
    var modal = new bootstrap.Modal(document.getElementById('categorieModal'));
    modal.show();
}

// Réinitialiser le formulaire quand le modal est fermé
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
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
