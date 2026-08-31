<?php
@include_once("../donnees/config_compte_rendu_financier.php");
@include_once("donnees/config_compte_rendu_financier.php");

// Récupérer les paramètres de période
$mois_debut = isset($_GET['mois_debut']) ? $_GET['mois_debut'] : date('Y-m', strtotime('-6 months'));
$mois_fin = isset($_GET['mois_fin']) ? $_GET['mois_fin'] : date('Y-m');
$id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : 1;

// Générer le compte rendu financier
$compte_rendu = ConfigCompteRenduFinancier::getCompteRenduFinancier($id_aep, $mois_debut, $mois_fin);

function moneyFormatter($montant)
{
    return number_format($montant, 0, ',', ' ');
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold">Compte Rendu Financier</h2>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Imprimer
        </button>
    </div>

    <!-- Onglets -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <ul class="nav nav-tabs" id="compteRenduTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="resume-tab" data-bs-toggle="tab" data-bs-target="#resume" type="button" role="tab" aria-controls="resume" aria-selected="true">
                        <i class="fas fa-chart-line me-2"></i><strong>Résumé par mois</strong>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="false">
                        <i class="fas fa-list-alt me-2"></i><strong>Bilan détaillé par catégorie</strong>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="compteRenduTabContent">
        <!-- Onglet Résumé -->
        <div class="tab-pane fade show active" id="resume" role="tabpanel">

    <!-- Formulaire de filtrage -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <input type="hidden" name="page" value="compte_rendu_financier">
                <div class="col-md-4">
                    <label for="mois_debut" class="form-label">Mois de début</label>
                    <input type="month" class="form-control" id="mois_debut" name="mois_debut" 
                           value="<?php echo htmlspecialchars($mois_debut); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="mois_fin" class="form-label">Mois de fin</label>
                    <input type="month" class="form-control" id="mois_fin" name="mois_fin" 
                           value="<?php echo htmlspecialchars($mois_fin); ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Résumé global -->
    <?php
    $total_recettes_global = 0;
    $total_charges_global = 0;
    foreach ($compte_rendu as $mois_data) {
        $total_recettes_global += $mois_data['total_recettes'];
        $total_charges_global += $mois_data['total_charges'];
    }
    $solde_global = $total_recettes_global - $total_charges_global;
    ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Recettes</h5>
                    <h3 class="mb-0"><?php echo moneyFormatter($total_recettes_global); ?> FCFA</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Charges</h5>
                    <h3 class="mb-0"><?php echo moneyFormatter($total_charges_global); ?> FCFA</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card <?php echo $solde_global >= 0 ? 'bg-primary' : 'bg-warning'; ?> text-white">
                <div class="card-body">
                    <h5 class="card-title">Solde Global</h5>
                    <h3 class="mb-0"><?php echo moneyFormatter($solde_global); ?> FCFA</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Détail par mois -->
    <?php if (empty($compte_rendu)): ?>
        <div class="alert alert-info">
            Aucune donnée financière trouvée pour la période sélectionnée.
        </div>
    <?php else: ?>
        <?php foreach ($compte_rendu as $mois_data): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo date('F Y', strtotime($mois_data['mois'] . '-01')); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Recettes -->
                        <div class="col-md-6">
                            <h6 class="text-success fw-bold mb-3">
                                <i class="fas fa-arrow-up me-2"></i>Recettes
                            </h6>
                            <?php if (empty($mois_data['recettes'])): ?>
                                <p class="text-muted">Aucune recette</p>
                            <?php else: ?>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Catégorie</th>
                                            <th class="text-end">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mois_data['recettes'] as $libelle => $data): ?>
                                            <?php 
                                            // Gérer l'ancien format (montant direct) et le nouveau format (array)
                                            if (is_array($data)) {
                                                $code_budgetaire = isset($data['code_budgetaire']) ? $data['code_budgetaire'] : '';
                                                $nom = isset($data['nom']) ? $data['nom'] : $libelle;
                                                $montant = isset($data['montant']) ? $data['montant'] : 0;
                                            } else {
                                                $code_budgetaire = '';
                                                $nom = $libelle;
                                                $montant = $data;
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($code_budgetaire)): ?>
                                                        <span class="badge bg-info me-2"><?php echo htmlspecialchars($code_budgetaire); ?></span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($nom); ?>
                                                </td>
                                                <td class="text-end text-success">
                                                    <?php echo moneyFormatter($montant); ?> FCFA
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-success fw-bold">
                                            <td>Total Recettes</td>
                                            <td class="text-end">
                                                <?php echo moneyFormatter($mois_data['total_recettes']); ?> FCFA
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- Charges -->
                        <div class="col-md-6">
                            <h6 class="text-danger fw-bold mb-3">
                                <i class="fas fa-arrow-down me-2"></i>Charges
                            </h6>
                            <?php if (empty($mois_data['charges'])): ?>
                                <p class="text-muted">Aucune charge</p>
                            <?php else: ?>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Catégorie</th>
                                            <th class="text-end">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mois_data['charges'] as $libelle => $data): ?>
                                            <?php 
                                            // Gérer l'ancien format (montant direct) et le nouveau format (array)
                                            if (is_array($data)) {
                                                $code_budgetaire = isset($data['code_budgetaire']) ? $data['code_budgetaire'] : '';
                                                $nom = isset($data['nom']) ? $data['nom'] : $libelle;
                                                $montant = isset($data['montant']) ? $data['montant'] : 0;
                                            } else {
                                                $code_budgetaire = '';
                                                $nom = $libelle;
                                                $montant = $data;
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($code_budgetaire)): ?>
                                                        <span class="badge bg-info me-2"><?php echo htmlspecialchars($code_budgetaire); ?></span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($nom); ?>
                                                </td>
                                                <td class="text-end text-danger">
                                                    <?php echo moneyFormatter($montant); ?> FCFA
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-danger fw-bold">
                                            <td>Total Charges</td>
                                            <td class="text-end">
                                                <?php echo moneyFormatter($mois_data['total_charges']); ?> FCFA
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Solde du mois -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="alert <?php echo $mois_data['solde'] >= 0 ? 'alert-success' : 'alert-warning'; ?> mb-0">
                                <strong>Solde du mois :</strong>
                                <span class="float-end">
                                    <?php echo moneyFormatter($mois_data['solde']); ?> FCFA
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
        </div>

        <!-- Onglet Bilan détaillé par catégorie -->
        <div class="tab-pane fade" id="details" role="tabpanel">
            <?php
            // Organiser les données par catégorie
            $categories_data = array();
            foreach ($compte_rendu as $mois_data) {
                // Traiter les recettes
                foreach ($mois_data['recettes'] as $libelle => $data) {
                    // Gérer l'ancien format (montant direct) et le nouveau format (array)
                    if (is_array($data)) {
                        $code_budgetaire = isset($data['code_budgetaire']) ? $data['code_budgetaire'] : '';
                        $nom = isset($data['nom']) ? $data['nom'] : $libelle;
                        $montant = isset($data['montant']) ? $data['montant'] : 0;
                    } else {
                        $code_budgetaire = '';
                        $nom = $libelle;
                        $montant = $data;
                    }
                    
                    if (!isset($categories_data[$libelle])) {
                        $categories_data[$libelle] = array(
                            'type' => 'recette',
                            'code_budgetaire' => $code_budgetaire,
                            'nom' => $nom,
                            'mois' => array(),
                            'total' => 0
                        );
                    }
                    $categories_data[$libelle]['mois'][$mois_data['mois']] = $montant;
                    $categories_data[$libelle]['total'] += $montant;
                }
                // Traiter les charges
                foreach ($mois_data['charges'] as $libelle => $data) {
                    // Gérer l'ancien format (montant direct) et le nouveau format (array)
                    if (is_array($data)) {
                        $code_budgetaire = isset($data['code_budgetaire']) ? $data['code_budgetaire'] : '';
                        $nom = isset($data['nom']) ? $data['nom'] : $libelle;
                        $montant = isset($data['montant']) ? $data['montant'] : 0;
                    } else {
                        $code_budgetaire = '';
                        $nom = $libelle;
                        $montant = $data;
                    }
                    
                    if (!isset($categories_data[$libelle])) {
                        $categories_data[$libelle] = array(
                            'type' => 'charge',
                            'code_budgetaire' => $code_budgetaire,
                            'nom' => $nom,
                            'mois' => array(),
                            'total' => 0
                        );
                    }
                    $categories_data[$libelle]['mois'][$mois_data['mois']] = $montant;
                    $categories_data[$libelle]['total'] += $montant;
                }
            }
            
            // Séparer recettes et charges
            $recettes_categories = array();
            $charges_categories = array();
            foreach ($categories_data as $libelle => $data) {
                if ($data['type'] === 'recette') {
                    $recettes_categories[$libelle] = $data;
                } else {
                    $charges_categories[$libelle] = $data;
                }
            }
            ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="text-success fw-bold mb-3">
                        <i class="fas fa-arrow-up me-2"></i>Recettes par catégorie
                    </h5>
                    <?php if (empty($recettes_categories)): ?>
                        <p class="text-muted">Aucune recette</p>
                    <?php else: ?>
                        <?php foreach ($recettes_categories as $libelle => $data): ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <?php if (!empty($data['code_budgetaire'])): ?>
                                            <span class="badge bg-light text-dark me-2"><?php echo htmlspecialchars($data['code_budgetaire']); ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars(isset($data['nom']) ? $data['nom'] : $libelle); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Mois</th>
                                                <th class="text-end">Montant</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Trier les mois
                                            krsort($data['mois']);
                                            foreach ($data['mois'] as $mois => $montant): 
                                            ?>
                                                <tr>
                                                    <td><?php echo date('F Y', strtotime($mois . '-01')); ?></td>
                                                    <td class="text-end text-success">
                                                        <?php echo moneyFormatter($montant); ?> FCFA
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-success fw-bold">
                                                <td>Total</td>
                                                <td class="text-end">
                                                    <?php echo moneyFormatter($data['total']); ?> FCFA
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <h5 class="text-danger fw-bold mb-3">
                        <i class="fas fa-arrow-down me-2"></i>Charges par catégorie
                    </h5>
                    <?php if (empty($charges_categories)): ?>
                        <p class="text-muted">Aucune charge</p>
                    <?php else: ?>
                        <?php foreach ($charges_categories as $libelle => $data): ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">
                                        <?php if (!empty($data['code_budgetaire'])): ?>
                                            <span class="badge bg-light text-dark me-2"><?php echo htmlspecialchars($data['code_budgetaire']); ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars(isset($data['nom']) ? $data['nom'] : $libelle); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Mois</th>
                                                <th class="text-end">Montant</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Trier les mois
                                            krsort($data['mois']);
                                            foreach ($data['mois'] as $mois => $montant): 
                                            ?>
                                                <tr>
                                                    <td><?php echo date('F Y', strtotime($mois . '-01')); ?></td>
                                                    <td class="text-end text-danger">
                                                        <?php echo moneyFormatter($montant); ?> FCFA
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-danger fw-bold">
                                                <td>Total</td>
                                                <td class="text-end">
                                                    <?php echo moneyFormatter($data['total']); ?> FCFA
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Style des onglets pour meilleure visibilité */
.nav-tabs {
    border-bottom: 2px solid #dee2e6 !important;
    margin-bottom: 0 !important;
}

.nav-tabs .nav-link {
    color: #495057 !important;
    border-radius: 0 !important;
    margin-right: 0 !important;
    padding: 1rem 1.5rem !important;
    font-weight: 500 !important;
    border: none !important;
    border-bottom: 3px solid transparent !important;
    transition: all 0.3s ease !important;
    background-color: transparent !important;
}

.nav-tabs .nav-link:hover {
    background-color: #f8f9fa !important;
    color: #0d6efd !important;
    border-bottom-color: #0d6efd !important;
}

.nav-tabs .nav-link.active {
    color: #0d6efd !important;
    background-color: #fff !important;
    border-bottom-color: #0d6efd !important;
    font-weight: 600 !important;
}

.nav-tabs .nav-link i {
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

.tab-content {
    background-color: #fff;
    padding: 0;
    border: none;
}

.tab-pane {
    padding-top: 1rem;
}

@media print {
    .btn, form, .card-header, .nav-tabs {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
    .tab-content > .tab-pane {
        display: block !important;
    }
}
</style>
