<?php
display_printing_button('', 'Imprimer la liste des entrées/sorties');

function moneyFormatter($montant)
{
    return number_format($montant, 0, ',', ' ');
}

@include_once("../donnees/flux_financier.php");
@include_once("donnees/flux_financier.php");
@include_once("../donnees/categorie_flux_manuel.php");
@include_once("donnees/categorie_flux_manuel.php");

$mois = isset($_POST['mois']) ? $_POST['mois'] : '';
$type = isset($_POST['type']) ? $_POST['type'] : '';
$montant_min = isset($_POST['montant_min']) ? (int) $_POST['montant_min'] : 0;
$id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : 1;

// Récupérer les flux financiers
$res = FluxFinancier::getFinanceData($mois, $type, $montant_min, $id_aep);
$fluxFinanciers = $res->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les versements
$resVersements = FluxFinancier::getAllVersements($id_aep);
$versements = $resVersements->fetchAll(PDO::FETCH_ASSOC);

// Filtrer les versements selon les critères de recherche
$versementsFiltres = array();
foreach ($versements as $versement) {
    $moisMatch = empty($mois) || strpos($versement['mois'], $mois) !== false;
    $typeMatch = empty($type) || $versement['type'] === $type;
    $montantMatch = $versement['prix'] >= $montant_min;

    if ($moisMatch && $typeMatch && $montantMatch) {
        $versementsFiltres[] = $versement;
    }
}

// Combiner les flux financiers et les versements
$tousFlux = array_merge($fluxFinanciers, $versementsFiltres);

// Trier par date décroissante
usort($tousFlux, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculer les totaux
$somme_algebrique = 0;
$somme_sortie = 0;
$somme_entree = 0;
$nb_sorties = 0;
$nb_entrees = 0;

foreach ($tousFlux as $flux) {
    if ($flux['type'] == 'sortie') {
        $somme_sortie += (int) $flux['prix'];
        $nb_sorties++;
    } else {
        $somme_entree += (int) $flux['prix'];
        $nb_entrees++;
    }
}
$somme_algebrique = $somme_entree - $somme_sortie;

// Préparer les catégories pour les modals
$res_cat_recette = CategorieFluxManuel::getAllActives('recette', $id_aep);
$categories_recette = $res_cat_recette->fetchAll(PDO::FETCH_ASSOC);
$res_cat_charge = CategorieFluxManuel::getAllActives('charge', $id_aep);
$categories_charge = $res_cat_charge->fetchAll(PDO::FETCH_ASSOC);

$activite_labels = array(
    'branchements' => 'Branchements',
    'vente_eau' => 'Vente d\'eau',
    'autre' => 'Autre'
);
?>

<style>
.flux-table tbody td, .flux-table thead th {
    padding: 0.25rem 0.5rem !important;
    line-height: 1.2 !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    max-height: 7mm !important;
}
.flux-table tbody tr {
    height: 7mm !important;
    max-height: 7mm !important;
}
.flux-table .text-truncate-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.flux-table tfoot td {
    padding: 0.35rem 0.5rem !important;
}
</style>

<div class="container-fluid mt-4" id="a_imprimer">
    <!-- Cartes de bilan -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Recettes</h6>
                            <h4 class="mb-0"><?php echo moneyFormatter($somme_entree); ?> F</h4>
                        </div>
                        <i class="bi bi-arrow-down-circle fs-1 opacity-50"></i>
                    </div>
                    <small class="opacity-75"><?php echo $nb_entrees; ?> opération(s)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Dépenses</h6>
                            <h4 class="mb-0"><?php echo moneyFormatter($somme_sortie); ?> F</h4>
                        </div>
                        <i class="bi bi-arrow-up-circle fs-1 opacity-50"></i>
                    </div>
                    <small class="opacity-75"><?php echo $nb_sorties; ?> opération(s)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?php echo $somme_algebrique >= 0 ? 'bg-primary' : 'bg-warning'; ?> text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Solde</h6>
                            <h4 class="mb-0"><?php echo moneyFormatter($somme_algebrique); ?> F</h4>
                        </div>
                        <i class="bi bi-wallet2 fs-1 opacity-50"></i>
                    </div>
                    <small class="opacity-75"><?php echo $somme_algebrique >= 0 ? 'Excédent' : 'Déficit'; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Opérations</h6>
                            <h4 class="mb-0"><?php echo count($tousFlux); ?></h4>
                        </div>
                        <i class="bi bi-list-ul fs-1 opacity-50"></i>
                    </div>
                    <small class="opacity-75">mouvements enregistrés</small>
                </div>
            </div>
        </div>
    </div>
    <h2 class="mb-4">Flux Financiers</h2>

    <!-- Section des Flux -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-arrow-left-right"></i> Entrées / Sorties
                <span class="badge bg-secondary ms-2"><?php echo count($tousFlux); ?></span>
            </h4>
            <div class="d-flex gap-2 align-items-center">
                <?php
                create_csv_exportation_button(
                    $tousFlux,
                    'flux_financier-' . $_SESSION["libele_aep"] . '-' . $mois . '.csv',
                    "Exporter les données financières au format CSV"
                );
                ?>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createFluxModal">
                    <i class="bi bi-plus-circle"></i> Nouveau Flux
                </button>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card-body border-bottom">
            <form action="?page=transaction" method="post" class="row g-3">
                <div class="col-md-3">
                    <label for="mois" class="form-label">Période (Mois)</label>
                    <input type="month" name="mois" id="mois" class="form-control"
                        value="<?php echo isset($_POST['mois']) ? htmlspecialchars($_POST['mois']) : ''; ?>">
                </div>

                <div class="col-md-2">
                    <label for="type" class="form-label">Type</label>
                    <select name="type" id="type_filter" class="form-control">
                        <option value="">Tous les types</option>
                        <option value="entree" <?php echo isset($_POST['type']) && $_POST['type'] === 'entree' ? 'selected' : ''; ?>>Recettes</option>
                        <option value="sortie" <?php echo isset($_POST['type']) && $_POST['type'] === 'sortie' ? 'selected' : ''; ?>>Dépenses</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="montant_min" class="form-label">Montant min. (FCFA)</label>
                    <input type="number" name="montant_min" id="montant_min" class="form-control" placeholder="0"
                        value="<?php echo isset($_POST['montant_min']) ? htmlspecialchars($_POST['montant_min']) : ''; ?>">
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                    <a href="?page=transaction" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Réinitialiser
                    </a>
                </div>
            </form>

            <!-- Résumé des filtres actifs -->
            <?php
            $filtresActifs = array();
            if (!empty($mois)) {
                $filtresActifs[] = 'Période: ' . htmlspecialchars($mois);
            }
            if (!empty($type)) {
                $filtresActifs[] = 'Type: ' . ($type === 'entree' ? 'Recettes' : 'Dépenses');
            }
            if ($montant_min > 0) {
                $filtresActifs[] = 'Montant min: ' . moneyFormatter($montant_min) . ' FCFA';
            }
            if (!empty($filtresActifs)) {
                ?>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Filtres actifs :
                        <?php echo implode(', ', $filtresActifs); ?>
                        | <?php echo count($tousFlux); ?> flux trouvé(s)
                    </small>
                </div>
                <?php
            }
            ?>
        </div>

        <div class="card-body">
            <?php if (count($tousFlux) > 0): ?>
                <div class="table-responsive">
                    <table class="table_searching table table-striped table-hover flux-table mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Mois</th>
                                <th>Libellé</th>
                                <th>Catégorie</th>
                                <th>Montant</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tousFlux as $flux):
                                $no_actions = isset($flux['actions']);
                                $is_entree = $flux['type'] === 'entree';
                                $row_class = $is_entree ? 'table-success' : 'table-danger';
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo date('d/m/Y', strtotime($flux['date'])); ?></td>
                                    <td><?php echo htmlspecialchars(getLetterMonth($flux['mois'])); ?></td>
                                    <td class="text-truncate-cell" title="<?php echo htmlspecialchars($flux['libele'] . (!empty($flux['description']) ? ' - ' . $flux['description'] : '')); ?>">
                                        <?php echo htmlspecialchars($flux['libele']); ?>
                                    </td>
                                    <td class="text-truncate-cell" title="<?php echo htmlspecialchars(isset($flux['categorie_nom']) ? $flux['categorie_nom'] : '-'); ?>">
                                        <?php echo !empty($flux['categorie_nom']) ? htmlspecialchars($flux['categorie_nom']) : '-'; ?>
                                    </td>
                                    <td class="<?php echo $is_entree ? 'text-success' : 'text-danger'; ?>">
                                        <strong><?php echo moneyFormatter($flux['prix']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $is_entree ? 'bg-success' : 'bg-danger'; ?>"><?php echo $is_entree ? 'Recette' : 'Dépense'; ?></span>
                                    </td>
                                    <td>
                                        <?php if (!$no_actions): ?>
                                            <button class="btn btn-sm btn-outline-primary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editFluxModal<?php echo $flux['id']; ?>" title="Modifier"><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-sm btn-outline-danger py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteFluxModal<?php echo $flux['id']; ?>" title="Supprimer"><i class="bi bi-trash"></i></button>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Paiement</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total Recettes :</strong></td>
                                <td><strong class="text-success"><?php echo moneyFormatter($somme_entree); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total Dépenses :</strong></td>
                                <td><strong class="text-danger"><?php echo moneyFormatter($somme_sortie); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr class="table-dark">
                                <td colspan="4" class="text-end"><strong>SOLDE :</strong></td>
                                <td><strong><?php echo moneyFormatter($somme_algebrique); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Aucun flux financier trouvé</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de création -->
<div class="modal fade" id="createFluxModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Flux Financier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createFluxForm" action="traitement/flux_financier_t.php?ajout=true" method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="mois_create" class="form-label">Mois *</label>
                                <input type="month" class="form-control" id="mois_create" name="mois" 
                                       value="<?php echo date('Y-m'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="libele" class="form-label">Libellé *</label>
                                <input type="text" class="form-control" id="libele" name="libele" 
                                       placeholder="Ex: Achat de matériel" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prix" class="form-label">Montant (FCFA) *</label>
                                <input type="number" class="form-control" id="prix" name="prix" 
                                       placeholder="Ex: 50000" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type *</label>
                                <select class="form-control" id="type" name="type" required onchange="updateCategories(this.value)">
                                    <option value="sortie" selected>Dépense (sortie)</option>
                                    <option value="entree">Recette (entrée)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_categorie" class="form-label">Catégorie</label>
                                <select class="form-control" id="id_categorie" name="id_categorie">
                                    <option value="">Aucune catégorie</option>
                                    <?php foreach ($categories_recette as $cat): 
                                        $code = !empty($cat['code_budgetaire']) ? $cat['code_budgetaire'] . ': ' : '';
                                    ?>
                                        <option value="<?php echo $cat['id']; ?>" data-type="recette">
                                            <?php echo $code . htmlspecialchars($cat['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php foreach ($categories_charge as $cat): 
                                        $code = !empty($cat['code_budgetaire']) ? $cat['code_budgetaire'] . ': ' : '';
                                    ?>
                                        <option value="<?php echo $cat['id']; ?>" data-type="charge">
                                            <?php echo $code . htmlspecialchars($cat['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Détails supplémentaires (optionnel)"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="createFluxForm" class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modals de modification et suppression -->
<?php foreach ($tousFlux as $flux): ?>
    <?php if (!isset($flux['actions'])): ?>
        <!-- Modal de modification -->
        <div class="modal fade" id="editFluxModal<?php echo $flux['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier le Flux</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editFluxForm<?php echo $flux['id']; ?>" 
                              action="traitement/flux_financier_t.php?update=true&id_updates_flux=<?php echo $flux['id']; ?>" 
                              method="post">
                            <input type="hidden" name="id" value="<?php echo $flux['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Date *</label>
                                        <input type="date" class="form-control" name="date" 
                                               value="<?php echo htmlspecialchars($flux['date']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Mois *</label>
                                        <input type="month" class="form-control" name="mois" 
                                               value="<?php echo htmlspecialchars($flux['mois']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Libellé *</label>
                                        <input type="text" class="form-control" name="libele" 
                                               value="<?php echo htmlspecialchars($flux['libele']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Montant (FCFA) *</label>
                                        <input type="number" class="form-control" name="prix" 
                                               value="<?php echo htmlspecialchars($flux['prix']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Type *</label>
                                        <select class="form-control" name="type" id="type_<?php echo $flux['id']; ?>" required 
                                                onchange="updateCategoriesEdit('<?php echo $flux['id']; ?>', this.value)">
                                            <option value="entree" <?php echo $flux['type'] === 'entree' ? 'selected' : ''; ?>>Recette</option>
                                            <option value="sortie" <?php echo $flux['type'] === 'sortie' ? 'selected' : ''; ?>>Dépense</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Catégorie</label>
                                        <select class="form-control" name="id_categorie" id="id_categorie_<?php echo $flux['id']; ?>">
                                            <option value="">Aucune catégorie</option>
                                            <?php 
                                            $current_cat = isset($flux['id_categorie_flux_manuel']) ? $flux['id_categorie_flux_manuel'] : null;
                                            foreach ($categories_recette as $cat): 
                                                $selected = ($current_cat == $cat['id']) ? 'selected' : '';
                                                $code = !empty($cat['code_budgetaire']) ? $cat['code_budgetaire'] . ': ' : '';
                                            ?>
                                                <option value="<?php echo $cat['id']; ?>" data-type="recette" <?php echo $selected; ?>>
                                                    <?php echo $code . htmlspecialchars($cat['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <?php foreach ($categories_charge as $cat): 
                                                $selected = ($current_cat == $cat['id']) ? 'selected' : '';
                                                $code = !empty($cat['code_budgetaire']) ? $cat['code_budgetaire'] . ': ' : '';
                                            ?>
                                                <option value="<?php echo $cat['id']; ?>" data-type="charge" <?php echo $selected; ?>>
                                                    <?php echo $code . htmlspecialchars($cat['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($flux['description']); ?></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" form="editFluxForm<?php echo $flux['id']; ?>" class="btn btn-primary">Enregistrer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de suppression -->
        <div class="modal fade" id="deleteFluxModal<?php echo $flux['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Supprimer le Flux</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Voulez-vous vraiment supprimer ce flux ?</p>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>Libellé</strong></td>
                                <td><?php echo htmlspecialchars($flux['libele']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Montant</strong></td>
                                <td><?php echo moneyFormatter($flux['prix']); ?> FCFA</td>
                            </tr>
                            <tr>
                                <td><strong>Date</strong></td>
                                <td><?php echo date('d/m/Y', strtotime($flux['date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Type</strong></td>
                                <td>
                                    <span class="badge <?php echo $flux['type'] === 'entree' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $flux['type'] === 'entree' ? 'Recette' : 'Dépense'; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <a href="traitement/flux_financier_t.php?delete=true&id_flux=<?php echo $flux['id']; ?>" 
                           class="btn btn-danger">Confirmer la suppression</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
function updateCategories(type) {
    var select = document.getElementById('id_categorie');
    if (!select) return;
    
    var type_flux = type === 'entree' ? 'recette' : 'charge';
    var options = select.querySelectorAll('option[data-type]');
    
    options.forEach(function(option) {
        option.style.display = option.getAttribute('data-type') === type_flux ? 'block' : 'none';
    });
    
    var currentOption = select.options[select.selectedIndex];
    if (currentOption && currentOption.getAttribute('data-type') && currentOption.getAttribute('data-type') !== type_flux) {
        select.value = '';
    }
}

function updateCategoriesEdit(fluxId, type) {
    var select = document.getElementById('id_categorie_' + fluxId);
    if (!select) return;
    
    var type_flux = type === 'entree' ? 'recette' : 'charge';
    var options = select.querySelectorAll('option[data-type]');
    
    options.forEach(function(option) {
        option.style.display = option.getAttribute('data-type') === type_flux ? 'block' : 'none';
    });
    
    var currentValue = select.value;
    var currentOption = select.querySelector('option[value="' + currentValue + '"]');
    if (currentOption && currentOption.getAttribute('data-type') && currentOption.getAttribute('data-type') !== type_flux && currentValue !== '') {
        select.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var typeSelect = document.getElementById('type');
    if (typeSelect) {
        updateCategories(typeSelect.value);
    }
});
</script>
