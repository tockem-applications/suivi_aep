<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");
@include_once("../donnees/categorie_flux_manuel.php");
@include_once("donnees/categorie_flux_manuel.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'AEP actuel
$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
if (!$id_aep) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Aucun AEP sélectionné.</div></div>';
    exit;
}

// Paramètres de période
$mois_debut = isset($_GET['mois_debut']) ? $_GET['mois_debut'] : date('Y-m', strtotime('-12 months'));
$mois_fin = isset($_GET['mois_fin']) ? $_GET['mois_fin'] : date('Y-m');

$bd = Connexion::connect();

// Fonction pour récupérer les détails des catégories de dépenses par mois
function getDetailsCategoriesDepenses($id_aep, $mois, $bd)
{
    $details = array();
    
    // 1. Récupérer les dépenses par catégorie manuelle
    $query_categories = "
        SELECT 
            COALESCE(cfm.id, 0) as id,
            COALESCE(cfm.code_budgetaire, '') as code_budgetaire,
            COALESCE(cfm.nom, 'Sans catégorie') as nom_categorie,
            COALESCE(SUM(ff.prix), 0) as montant
        FROM flux_financier ff
        LEFT JOIN categorie_flux_manuel cfm ON ff.id_categorie_flux_manuel = cfm.id
        WHERE ff.id_aep = ?
        AND ff.type = 'sortie'
        AND (cfm.activite_associee = 'vente_eau' OR (cfm.activite_associee IS NULL AND cfm.id IS NULL))
        AND (
            (ff.mois IS NOT NULL AND ff.mois != '' AND ff.mois = ?) OR
            (ff.mois IS NULL OR ff.mois = '' AND DATE_FORMAT(ff.date, '%Y-%m') = ?)
        )
        GROUP BY COALESCE(cfm.id, 0), cfm.code_budgetaire, cfm.nom
        HAVING montant > 0
        ORDER BY montant DESC
    ";
    $stmt_categories = $bd->prepare($query_categories);
    $stmt_categories->execute(array($id_aep, $mois, $mois));
    while ($row = $stmt_categories->fetch(PDO::FETCH_ASSOC)) {
        $details[] = array(
            'type' => 'categorie',
            'code_budgetaire' => $row['code_budgetaire'],
            'nom' => $row['nom_categorie'],
            'montant' => floatval($row['montant'])
        );
    }
    
    // 2. Récupérer les redevances sur vente d'eau par redevance
    $query_redevances_detail = "
        SELECT 
            r.id,
            COALESCE(r.libele, 'Redevance') as libelle,
            COALESCE(SUM(v.montant), 0) as montant
        FROM versements v
        INNER JOIN redevance r ON v.id_redevance = r.id
        LEFT JOIN mois_facturation mf ON v.id_mois_facturation = mf.id
        WHERE r.id_aep = ?
        AND (r.base_calcul = 'vente_eau' OR r.base_calcul IS NULL OR r.base_calcul = '')
        AND (
            (mf.mois IS NOT NULL AND mf.mois = ?) OR
            (mf.mois IS NULL AND DATE_FORMAT(v.date_versement, '%Y-%m') = ?)
        )
        GROUP BY r.id, r.libele
        HAVING montant > 0
        ORDER BY montant DESC
    ";
    $stmt_redevances_detail = $bd->prepare($query_redevances_detail);
    $stmt_redevances_detail->execute(array($id_aep, $mois, $mois));
    while ($row = $stmt_redevances_detail->fetch(PDO::FETCH_ASSOC)) {
        $details[] = array(
            'type' => 'redevance',
            'code_budgetaire' => '',
            'nom' => 'Redevance: ' . $row['libelle'],
            'montant' => floatval($row['montant'])
        );
    }
    
    return $details;
}

// Fonction pour calculer les données du graphique
function getDonneesGraphiqueCoutRecouvrement($id_aep, $mois_debut, $mois_fin, $bd)
{
    $donnees = array();

    // Récupérer tous les mois dans la période
    $query_mois = "
        SELECT DISTINCT mf.mois
        FROM mois_facturation mf
        INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
        WHERE cr.id_aep = ? 
        AND mf.mois >= ? 
        AND mf.mois <= ?
        AND mf.est_mois_base = 0
        ORDER BY mf.mois ASC
    ";
    $stmt_mois = $bd->prepare($query_mois);
    $stmt_mois->execute(array($id_aep, $mois_debut, $mois_fin));
    $mois_list = array();
    while ($row = $stmt_mois->fetch(PDO::FETCH_ASSOC)) {
        $mois_list[] = $row['mois'];
    }

    foreach ($mois_list as $mois) {
        // 1. Récupérer toutes les dépenses liées aux recouvrements (charges avec activite_associee = 'vente_eau')
        // Somme de toutes les transactions de type 'sortie' dont la catégorie a activite_associee = 'vente_eau'
        $query_depenses = "
            SELECT 
                COALESCE(SUM(ff.prix), 0) as total_depenses
            FROM flux_financier ff
            LEFT JOIN categorie_flux_manuel cfm ON ff.id_categorie_flux_manuel = cfm.id
            WHERE ff.id_aep = ?
            AND ff.type = 'sortie'
            AND (cfm.activite_associee = 'vente_eau' OR (cfm.activite_associee IS NULL AND cfm.id IS NULL))
            AND (
                (ff.mois IS NOT NULL AND ff.mois != '' AND ff.mois = ?) OR
                (ff.mois IS NULL OR ff.mois = '' AND DATE_FORMAT(ff.date, '%Y-%m') = ?)
            )
        ";
        $stmt_depenses = $bd->prepare($query_depenses);
        $stmt_depenses->execute(array($id_aep, $mois, $mois));
        $row_depenses = $stmt_depenses->fetch(PDO::FETCH_ASSOC);
        $total_depenses = floatval($row_depenses['total_depenses']);

        // Ajouter les redevances sur vente d'eau pour ce mois
        $query_redevances = "
            SELECT 
                COALESCE(SUM(v.montant), 0) as total_redevances
            FROM versements v
            INNER JOIN redevance r ON v.id_redevance = r.id
            LEFT JOIN mois_facturation mf ON v.id_mois_facturation = mf.id
            WHERE r.id_aep = ?
            AND (r.base_calcul = 'vente_eau' OR r.base_calcul IS NULL OR r.base_calcul = '')
            AND (
                (mf.mois IS NOT NULL AND mf.mois = ?) OR
                (mf.mois IS NULL AND DATE_FORMAT(v.date_versement, '%Y-%m') = ?)
            )
        ";
        $stmt_redevances = $bd->prepare($query_redevances);
        $stmt_redevances->execute(array($id_aep, $mois, $mois));
        $row_redevances = $stmt_redevances->fetch(PDO::FETCH_ASSOC);
        $total_depenses += floatval($row_redevances['total_redevances']);
        
        // Récupérer les détails des catégories de dépenses
        $details_categories = getDetailsCategoriesDepenses($id_aep, $mois, $bd);

        // 2. Récupérer la consommation totale (m³) pour ce mois
        $query_conso = "
            SELECT 
                SUM(i.nouvel_index - i.ancien_index) as total_conso
            FROM indexes i
            INNER JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
            INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
            WHERE cr.id_aep = ?
            AND mf.mois = ?
            AND mf.est_mois_base = 0
        ";
        $stmt_conso = $bd->prepare($query_conso);
        $stmt_conso->execute(array($id_aep, $mois));
        $row_conso = $stmt_conso->fetch(PDO::FETCH_ASSOC);
        $total_conso = floatval($row_conso['total_conso']);

        // 3. Calculer le prix moyen du m³ pour ce mois (en tenant compte des tarifs différenciés)
        $query_prix_moyen = "
            SELECT 
                i.id,
                i.nouvel_index - i.ancien_index as conso,
                COALESCE(td.prix_metre_cube_eau, cr.prix_metre_cube_eau) as prix_m3
            FROM indexes i
            INNER JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
            INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
            LEFT JOIN tarif_differencie td ON i.id_tarif_differencie = td.id
            WHERE cr.id_aep = ?
            AND mf.mois = ?
            AND mf.est_mois_base = 0
            AND (i.nouvel_index - i.ancien_index) > 0
        ";
        $stmt_prix = $bd->prepare($query_prix_moyen);
        $stmt_prix->execute(array($id_aep, $mois));

        $somme_prix_conso = 0;
        $somme_conso = 0;
        $prix_moyen = 0;

        while ($row_prix = $stmt_prix->fetch(PDO::FETCH_ASSOC)) {
            $conso_i = floatval($row_prix['conso']);
            $prix_m3_i = floatval($row_prix['prix_m3']);
            $somme_prix_conso += $prix_m3_i * $conso_i;
            $somme_conso += $conso_i;
        }

        if ($somme_conso > 0) {
            $prix_moyen = $somme_prix_conso / $somme_conso;
        } else {
            // Si pas de consommation, utiliser le prix standard du mois
            $query_prix_standard = "
                SELECT prix_metre_cube_eau
                FROM constante_reseau cr
                INNER JOIN mois_facturation mf ON mf.id_constante = cr.id
                WHERE cr.id_aep = ?
                AND mf.mois = ?
                LIMIT 1
            ";
            $stmt_prix_standard = $bd->prepare($query_prix_standard);
            $stmt_prix_standard->execute(array($id_aep, $mois));
            $row_prix_standard = $stmt_prix_standard->fetch(PDO::FETCH_ASSOC);
            $prix_moyen = $row_prix_standard ? floatval($row_prix_standard['prix_metre_cube_eau']) : 0;
        }

        // 4. Calculer le coût par m³ (dépenses / consommation)
        $cout_par_m3 = $total_conso > 0 ? ($total_depenses / $total_conso) : 0;

        $donnees[] = array(
            'mois' => $mois,
            'total_depenses' => $total_depenses,
            'total_conso' => $total_conso,
            'cout_par_m3' => $cout_par_m3,
            'prix_moyen_m3' => $prix_moyen,
            'details_categories' => $details_categories
        );
    }

    return $donnees;
}

$donnees_graphique = getDonneesGraphiqueCoutRecouvrement($id_aep, $mois_debut, $mois_fin, $bd);

// Préparer les données pour Chart.js
$chart_labels = array();
$chart_cout_par_m3 = array();
$chart_prix_moyen_m3 = array();

foreach ($donnees_graphique as $data) {
    $chart_labels[] = date('M Y', strtotime($data['mois'] . '-01'));
    $chart_cout_par_m3[] = round($data['cout_par_m3'], 2);
    $chart_prix_moyen_m3[] = round($data['prix_moyen_m3'], 2);
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold">
            <i class="fas fa-chart-line me-2"></i>Analyse Financière
        </h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Sélecteurs de mois de début et fin -->
            <form method="get" action="" class="d-inline">
                <input type="hidden" name="page" value="analyse_financiere">
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
        </div>
    </div>

    <!-- Premier graphique : Coût par m³ vs Prix moyen du m³ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-chart-area me-2"></i>
                Coût des dépenses liées aux recouvrements par m³ vs Prix moyen du m³
            </h5>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal"
                data-bs-target="#detailsGraphiqueModal">
                <i class="fas fa-info-circle me-1"></i> Voir les détails
            </button>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Ce graphique compare le coût moyen des dépenses liées aux recouvrements par m³ d'eau consommée
                avec le prix moyen du m³ d'eau facturé. Le coût est calculé en divisant la somme de toutes les
                dépenses liées aux recouvrements (charges avec activité "vente d'eau" + redevances sur vente d'eau)
                par le nombre total de m³ consommés pour chaque mois.
            </p>
            <canvas id="coutRecouvrementChart" style="max-height: 400px;"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js -->
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script> -->

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('coutRecouvrementChart');
        if (!ctx) return;

        const chartData = {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Coût des dépenses par m³ (FCFA)',
                    data: <?php echo json_encode($chart_cout_par_m3); ?>,
                    borderColor: 'rgb(220, 53, 69)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'y'
                },
                {
                    label: 'Prix moyen du m³ (FCFA)',
                    data: <?php echo json_encode($chart_prix_moyen_m3); ?>,
                    borderColor: 'rgb(40, 167, 69)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'y'
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
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
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
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
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

<!-- Modal pour afficher les détails du graphique -->
<div class="modal fade" id="detailsGraphiqueModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-table me-2"></i>
                    Détails du graphique : Coût des dépenses par m³ vs Prix moyen du m³
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <style>
                    .mois-row:hover {
                        background-color: #f8f9fa !important;
                    }
                    .mois-row td {
                        user-select: none;
                    }
                </style>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Mois</th>
                                <th class="text-end">Dépenses totales<br><small class="text-muted">(FCFA)</small></th>
                                <th class="text-end">Consommation totale<br><small class="text-muted">(m³)</small></th>
                                <th class="text-end">Coût par m³<br><small class="text-muted">(FCFA)</small></th>
                                <th class="text-end">Prix moyen du m³<br><small class="text-muted">(FCFA)</small></th>
                                <th class="text-end">Écart<br><small class="text-muted">(FCFA)</small></th>
                                <th class="text-center">Ratio<br><small class="text-muted">(%)</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_depenses_global = 0;
                            $total_conso_global = 0;
                            $mois_index = 0;
                            foreach ($donnees_graphique as $data):
                                $total_depenses_global += $data['total_depenses'];
                                $total_conso_global += $data['total_conso'];
                                $ecart = $data['cout_par_m3'] - $data['prix_moyen_m3'];
                                $ratio = $data['prix_moyen_m3'] > 0 ? (($data['cout_par_m3'] / $data['prix_moyen_m3']) * 100) : 0;
                                $class_ecart = $ecart > 0 ? 'text-danger' : ($ecart < 0 ? 'text-success' : 'text-muted');
                                $class_ratio = $ratio > 100 ? 'text-danger' : ($ratio < 100 ? 'text-success' : 'text-muted');
                                $has_details = !empty($data['details_categories']);
                                $mois_id = 'mois_' . $mois_index;
                                $mois_index++;
                                ?>
                                <tr class="mois-row" data-mois-id="<?php echo $mois_id; ?>" style="cursor: pointer;" onclick="toggleDetails('<?php echo $mois_id; ?>')">
                                    <td class="text-center">
                                        <?php if ($has_details): ?>
                                            <i class="fas fa-chevron-down" id="icon_<?php echo $mois_id; ?>"></i>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo date('M Y', strtotime($data['mois'] . '-01')); ?></strong></td>
                                    <td class="text-end"><?php echo number_format($data['total_depenses'], 0, ',', ' '); ?>
                                    </td>
                                    <td class="text-end"><?php echo number_format($data['total_conso'], 2, ',', ' '); ?>
                                    </td>
                                    <td class="text-end"><strong
                                            class="text-danger"><?php echo number_format($data['cout_par_m3'], 2, ',', ' '); ?></strong>
                                    </td>
                                    <td class="text-end"><strong
                                            class="text-success"><?php echo number_format($data['prix_moyen_m3'], 2, ',', ' '); ?></strong>
                                    </td>
                                    <td class="text-end">
                                        <span class="<?php echo $class_ecart; ?>">
                                            <?php echo $ecart > 0 ? '+' : ''; ?>
                                            <?php echo number_format($ecart, 2, ',', ' '); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge <?php echo $ratio > 100 ? 'bg-danger' : ($ratio < 100 ? 'bg-success' : 'bg-secondary'); ?>">
                                            <?php echo number_format($ratio, 1, ',', ' '); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($has_details): ?>
                                <tr id="details_<?php echo $mois_id; ?>" class="details-row" style="display: none;">
                                    <td colspan="8" class="p-0">
                                        <div class="p-3 bg-light">
                                            <h6 class="mb-3 text-primary">
                                                <i class="fas fa-list me-2"></i>Détail des catégories de dépenses
                                            </h6>
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="table-secondary">
                                                    <tr>
                                                        <th style="width: 120px;">Code budgétaire</th>
                                                        <th>Catégorie</th>
                                                        <th class="text-end" style="width: 150px;">Montant (FCFA)</th>
                                                        <th class="text-center" style="width: 100px;">% du total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    foreach ($data['details_categories'] as $detail):
                                                        $pourcentage = $data['total_depenses'] > 0 ? (($detail['montant'] / $data['total_depenses']) * 100) : 0;
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <?php if (!empty($detail['code_budgetaire'])): ?>
                                                                    <span class="badge bg-info"><?php echo htmlspecialchars($detail['code_budgetaire']); ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($detail['nom']); ?></td>
                                                            <td class="text-end">
                                                                <strong><?php echo number_format($detail['montant'], 0, ',', ' '); ?></strong>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge bg-secondary">
                                                                    <?php echo number_format($pourcentage, 1, ',', ' '); ?>%
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-secondary fw-bold">
                                                    <tr>
                                                        <td colspan="2"><strong>Total</strong></td>
                                                        <td class="text-end">
                                                            <strong><?php echo number_format($data['total_depenses'], 0, ',', ' '); ?></strong>
                                                        </td>
                                                        <td class="text-center">
                                                            <strong>100%</strong>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td></td>
                                <td><strong>Total / Moyenne</strong></td>
                                <td class="text-end"><?php echo number_format($total_depenses_global, 0, ',', ' '); ?>
                                </td>
                                <td class="text-end"><?php echo number_format($total_conso_global, 2, ',', ' '); ?></td>
                                <td class="text-end">
                                    <?php
                                    $cout_moyen = $total_conso_global > 0 ? ($total_depenses_global / $total_conso_global) : 0;
                                    echo number_format($cout_moyen, 2, ',', ' ');
                                    ?>
                                </td>
                                <td class="text-end">
                                    <?php
                                    $prix_moyen_global = 0;
                                    if (count($donnees_graphique) > 0) {
                                        $somme_prix = 0;
                                        foreach ($donnees_graphique as $data) {
                                            $somme_prix += $data['prix_moyen_m3'];
                                        }
                                        $prix_moyen_global = $somme_prix / count($donnees_graphique);
                                    }
                                    echo number_format($prix_moyen_global, 2, ',', ' ');
                                    ?>
                                </td>
                                <td class="text-end">
                                    <?php
                                    $ecart_moyen = $cout_moyen - $prix_moyen_global;
                                    $class_ecart_moyen = $ecart_moyen > 0 ? 'text-danger' : ($ecart_moyen < 0 ? 'text-success' : 'text-muted');
                                    ?>
                                    <span class="<?php echo $class_ecart_moyen; ?>">
                                        <?php echo $ecart_moyen > 0 ? '+' : ''; ?><?php echo number_format($ecart_moyen, 2, ',', ' '); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $ratio_moyen = $prix_moyen_global > 0 ? (($cout_moyen / $prix_moyen_global) * 100) : 0;
                                    ?>
                                    <span
                                        class="badge <?php echo $ratio_moyen > 100 ? 'bg-danger' : ($ratio_moyen < 100 ? 'bg-success' : 'bg-secondary'); ?>">
                                        <?php echo number_format($ratio_moyen, 1, ',', ' '); ?>%
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-3">
                    <h6 class="text-muted">Légende :</h6>
                    <ul class="list-unstyled small">
                        <li><strong>Dépenses totales :</strong> Somme de toutes les dépenses liées aux recouvrements
                            (charges avec activité "vente d'eau" + redevances sur vente d'eau)</li>
                        <li><strong>Consommation totale :</strong> Volume total d'eau consommé en m³</li>
                        <li><strong>Coût par m³ :</strong> Dépenses totales ÷ Consommation totale</li>
                        <li><strong>Prix moyen du m³ :</strong> Prix moyen pondéré du m³ facturé (tenant compte des
                            tarifs différenciés si présents)</li>
                        <li><strong>Écart :</strong> Différence entre le coût par m³ et le prix moyen du m³ (positif =
                            coût supérieur au prix, négatif = coût inférieur au prix)</li>
                        <li><strong>Ratio :</strong> Pourcentage du coût par rapport au prix moyen (100% = égalité,
                            >100% = coût supérieur, <100%=coût inférieur)</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleDetails(moisId) {
        const detailsRow = document.getElementById('details_' + moisId);
        const icon = document.getElementById('icon_' + moisId);
        
        if (detailsRow && icon) {
            if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                detailsRow.style.display = '';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                detailsRow.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }
    }
</script>