<?php
include_once 'traitement/aep_traitement.php';
//var_dump($_SESSION);
// Récupérer l'ID de l'AEP depuis l'URL
$aepId = isset($_GET['aep_id']) ? intval($_GET['aep_id']) : null;
$aepIdError = !$aepId;

// Initialiser le modèle
$model = new AepModel();

// Récupérer toutes les données nécessaires
$data = array(
    'id' => $aepId,
    'libele' => '',
    'description' => '',
    'date' => '',
    'numero_compte' => '',
    'nom_banque' => '',
    'reseaux' => array(),
    'abones_count' => 0,
    'facture_total' => 0,
    'impaye_total' => 0,
    'flux_financiers' => array('entrees' => 0, 'sorties' => 0),
    'redevances' => array(),
    'index_history' => array(),
    'montants_par_mois' => array(),
    'recent_factures' => array(),
    'impayes' => array()
);

if ($aepId) {
    $aepInfo = $model->getAepInfo($aepId);
    if ($aepInfo) {
        $data['libele'] = $aepInfo['libele'];
        $data['description'] = $aepInfo['description'];
        $data['date'] = $aepInfo['date'];
        $data['numero_compte'] = $aepInfo['numero_compte'];
        $data['nom_banque'] = $aepInfo['nom_banque'];
    }

    $data['reseaux'] = $model->getReseaux($aepId);
    $data['abones_count'] = $model->getAbonesCount($aepId);
    $data['facture_total'] = $model->getFactureTotal($aepId);
    $data['impaye_total'] = $model->getImpayeTotal($aepId);
    $data['flux_financiers'] = $model->getFluxFinanciers($aepId);
    $data['redevances'] = $model->getRedevances($aepId);
    $data['index_history'] = $model->getIndexHistory($aepId);
    $data['montants_par_mois'] = $model->getMontantsParMois($aepId); // Nouvelle donnée pour le

    // Récupérer les factures (filtrées si un mois est spécifié)
    $mois = isset($_GET['mois']) ? $_GET['mois'] : null;
    $data['recent_factures'] = $model->getRecentFactures($aepId, $mois);
    $data['impayes'] = $model->getImpayes($aepId);
}
?>


<style>
    body {
        background-color: #f8f9fa;
        font-family: 'Arial', sans-serif;
        color: #333;
        margin: 0;
        /*padding: 20px;*/
    }
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        background-color: #fff;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
    }
    h1 {
        color: #2c3e50;
        font-weight: 700;
        margin-bottom: 30px;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
    }
    .chart-container {
        position: relative;
        height: 350px;
        width: 100%;
        background-color: #f1f3f5;
        border-radius: 8px;
        padding: 10px;
        box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.05);
    }
    .link-icon {
        margin-left: 8px;
        color: #2980b9;
        transition: transform 0.2s ease;
    }
    a:hover .link-icon {
        transform: translateX(5px);
    }
    .table {
        border-radius: 8px;
        overflow: hidden;
    }
    .table thead {
        background-color: #2c3e50;
        color: #fff;
        font-weight: 500;
    }
    .table thead th {
        padding: 12px;
        border-bottom: none;
    }
    .table tbody tr {
        transition: background-color 0.2s ease;
    }
    .table tbody tr:nth-child(even) {
        background-color: #ecf0f1;
    }
    .table tbody tr:hover {
        background-color: #dfe6e9;
    }
    .table td {
        padding: 12px;
        vertical-align: middle;
    }
    .btn-primary {
        background-color: #2c3e50;
        border-color: #2c3e50;
        border-radius: 6px;
        padding: 8px 18px;
        font-weight: 500;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-primary:hover {
        background-color: #233240;
        transform: scale(1.05);
    }
    .form-select {
        border-radius: 6px;
        padding: 8px 12px;
        border-color: #ccc;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form-select:focus {
        border-color: #2c3e50;
        box-shadow: 0 0 5px rgba(44, 62, 80, 0.3);
    }
    .text-primary {
        color: #2980b9 !important;
    }
    .text-success {
        color: #27ae60 !important;
    }
    .text-danger {
        color: #e74c3c !important;
    }
    .list-group-item {
        border: none;
        padding: 10px 0;
        color: #7f8c8d;
    }
</style>
<div class="container-fluid">
    <h1 class="display-4 fw-bold text-dark mb-4 pt-3">Tableau de bord AEP <?php echo $data['libele']?> </h1>

    <!-- Grille de mise en page -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <!-- Informations de l'AEP -->
<!--        <div class="col">-->
<!--            <div class="card shadow-sm p-4">-->
<!--                <h2 class="h4 fw-semibold text-dark mb-3">Informations de l'AEP</h2>-->
<!--                <div class="d-flex flex-column gap-2">-->
<!--                    <p><strong>Libellé :</strong> <span class="text-primary">--><?php //echo htmlspecialchars($data['libele'] ?: 'N/A'); ?><!--</span></p>-->
<!--                    <p><strong>Description :</strong> <span class="text-muted">--><?php //echo htmlspecialchars($data['description'] ?: 'N/A'); ?><!--</span></p>-->
<!--                    <p><strong>Date :</strong> <span class="text-muted">--><?php //echo htmlspecialchars($data['date'] ?: 'N/A'); ?><!--</span></p>-->
<!--                    <p><strong>Numéro de compte :</strong> <span class="text-muted">--><?php //echo htmlspecialchars($data['numero_compte'] ?: 'N/A'); ?><!--</span></p>-->
<!--                    <p><strong>Nom de la banque :</strong> <span class="text-muted">--><?php //echo htmlspecialchars($data['nom_banque'] ?: 'N/A'); ?><!--</span></p>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->

        <!-- Statistiques -->
<!--        <div class="col">-->
<!--            <div class="card shadow-sm p-4">-->
<!--                <h2 class="h4 fw-semibold text-dark mb-3">Statistiques</h2>-->
<!--                <div class="d-flex flex-column gap-2">-->
<!--                    <p>-->
<!--                        <strong>Abonnés :</strong>-->
<!--                        <span class="text-success fw-medium">--><?php //echo $data['abones_count']; ?><!--</span>-->
<!--                        <a href="abone_list.html?aep_id=--><?php //echo $aepId; ?><!--" class="text-primary text-decoration-underline text-sm">-->
<!--                            Voir détails <i class="fas fa-arrow-right link-icon"></i>-->
<!--                        </a>-->
<!--                    </p>-->
<!--                    <p><strong>Factures totales :</strong> <span class="text-success fw-medium">--><?php //echo $data['facture_total']; ?><!-- FCFA</span></p>-->
<!--                    <p><strong>Impayés totaux :</strong> <span class="text-danger fw-medium">--><?php //echo $data['impaye_total']; ?><!-- FCFA</span></p>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->

        <!-- Graphique des index -->
        <div class="col col-md-12 col-lg-6">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Évolution des consommations</h2>
                <div class="chart-container">
                    <canvas id="index-chart"></canvas>
                    <script>
                        var ctx = document.getElementById('index-chart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode(array_map(function($item) { return $item['date']; }, $data['index_history'])); ?>,
                                datasets: [{
                                    label: 'consommation',
                                    data: <?php echo json_encode(array_map(function($item) { return $item['value']; }, $data['index_history'])); ?>,
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    fill: false,
                                    tension: 0.1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: { y: { beginAtZero: true } }
                            }
                        });
                    </script>
                </div>
            </div>
        </div>

        <!-- Graphique des montants facturés et recouvrés -->
        <div class="col col-md-12 col-lg-6">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Évolution des recouvrements</h2>
                <div class="chart-container">
                    <canvas id="montants-chart"></canvas>
                    <script>
                        // Calcul des mois déséquilibrés et création des tableaux de couleurs dynamiques
                        <?php
                        $montantsParMois = $data['montants_par_mois'];
                        $backgroundColorsFacture = array();
                        $backgroundColorsRecouvre = array();

                        foreach ($montantsParMois as $item) {
                            $montantFacture = isset($item['montant_facture']) ? floatval($item['montant_facture']) : 0;
                            $montantRecouvre = isset($item['montant_recouvre']) ? floatval($item['montant_recouvre']) : 0;

                            // Vérifier si les montants sont différents (déséquilibre)
                            if (abs($montantFacture - $montantRecouvre) > 0.01) { // Tolérance pour les erreurs de flottants
                                // Couleurs plus vives pour les mois déséquilibrés
                                $backgroundColorsFacture[] = 'rgba(255, 99, 132, 0.9)'; // Rouge clair
                                $backgroundColorsRecouvre[] = 'rgba(50, 205, 50, 0.9)'; // Vert clair (limegreen)
                            } else {
                                // Couleurs normales pour les mois équilibrés
                                $backgroundColorsFacture[] = 'rgba(255, 0, 0, 0.7)'; // Rouge
                                $backgroundColorsRecouvre[] = 'rgba(0, 128, 0, 0.7)'; // Vert
                            }
                        }
                        ?>

                        var ctx = document.getElementById('montants-chart').getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode(array_map(function($item) { return $item['date']; }, $data['montants_par_mois'])); ?>,
                                datasets: [
                                    {
                                        label: 'Montant facturé',
                                        data: <?php echo json_encode(array_map(function($item) { return $item['montant_facture'] ?: 0; }, $data['montants_par_mois'])); ?>,
                                        backgroundColor: <?php echo json_encode($backgroundColorsFacture); ?>, // Couleurs dynamiques
                                        borderColor: 'rgba(255, 0, 0, 1)',
                                        borderWidth: 1
                                    },
                                    {
                                        label: 'Montant recouvré',
                                        data: <?php echo json_encode(array_map(function($item) { return $item['montant_recouvre'] ?: 0; }, $data['montants_par_mois'])); ?>,
                                        backgroundColor: <?php echo json_encode($backgroundColorsRecouvre); ?>, // Couleurs dynamiques
                                        borderColor: 'rgba(0, 128, 0, 1)',
                                        borderWidth: 1
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Montant (FCFA)'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Mois'
                                        }
                                    }
                                },
                                plugins: {
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                if (context.parsed.y !== null) {
                                                    label += new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(context.parsed.y);
                                                }
                                                return label;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    </script>
                </div>
            </div>
        </div>

        <!-- Réseaux associés -->
        <div class="col">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Réseaux</h2>
                <p>
                    <strong>Nombre de réseaux :</strong>
                    <span class="text-primary"><?php echo count($data['reseaux']); ?></span>
                    <a href="?page=reseau" class="text-primary text-decoration-underline text-sm">
                        Voir détails <i class="fas fa-arrow-right link-icon"></i>
                    </a>
                </p>
                <ul class="list-group list-group-flush mt-2">
                    <?php foreach ($data['reseaux'] as $reseau) : ?>
                        <li class="list-group-item"><?php echo htmlspecialchars($reseau['nom']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Flux financiers -->
        <div class="col">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Flux financiers</h2>
                <div class="d-flex flex-column gap-2">
                    <p><strong>Total des entrées :</strong> <span class="text-success fw-medium"><?php echo $data['flux_financiers']['entrees']; ?> FCFA</span></p>
                    <p><strong>Total des sorties :</strong> <span class="text-danger fw-medium"><?php echo $data['flux_financiers']['sorties']; ?> FCFA</span></p>
                    <a href="?page=transaction" class="text-primary text-decoration-underline text-sm">
                        Voir détails <i class="fas fa-arrow-right link-icon"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Redevances -->
        <div class="col col-md-12">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Redevances</h2>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Libellé</th>
                            <th scope="col">Pourcentage</th>
                            <th scope="col">Type</th>
                            <th scope="col">Mois de début</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['redevances'] as $redevance) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($redevance['libele']); ?></td>
                                <td><?php echo htmlspecialchars($redevance['pourcentage']); ?>%</td>
                                <td><?php echo htmlspecialchars($redevance['type']); ?></td>
                                <td><?php echo htmlspecialchars($redevance['mois_debut'] ?: 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="?page=redevance" class="text-primary text-decoration-underline text-sm mt-3 d-inline-block">
                    Voir détails <i class="fas fa-arrow-right link-icon"></i>
                </a>
            </div>
        </div>

        <!-- Tableau des factures -->
        <!--<div class="col col-md-12">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Factures récentes</h2>
                <form method="GET" action="dashboard.php" class="d-flex mb-3 gap-2">
                    <input type="hidden" name="aep_id" value="<?php /*echo $aepId; */?>">
                    <select name="mois" class="form-select">
                        <option value="all" <?php /*echo (!$mois || $mois === 'all') ? 'selected' : ''; */?>>Tous les mois</option>
                        <option value="2025-06" <?php /*echo $mois === '2025-06' ? 'selected' : ''; */?>>Juin 2025</option>
                        <option value="2025-05" <?php /*echo $mois === '2025-05' ? 'selected' : ''; */?>>Mai 2025</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Rafraîchir</button>
                </form>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Abonné</th>
                            <th scope="col">Montant</th>
                            <th scope="col">Date</th>
                            <th scope="col">Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php /*foreach ($data['recent_factures'] as $facture) : */?>
                            <tr>
                                <td><?php /*echo htmlspecialchars($facture['abone_nom']); */?></td>
                                <td><?php /*echo htmlspecialchars($facture['montant_verse']); */?> FCFA</td>
                                <td><?php /*echo htmlspecialchars($facture['date_paiement'] ?: 'Non payé'); */?></td>
                                <td><?php /*echo $facture['date_paiement'] ? 'Payé' : 'Impayé'; */?></td>
                            </tr>
                        <?php /*endforeach; */?>
                        </tbody>
                    </table>
                </div>
                <a href="facture_list.html?aep_id=<?php /*echo $aepId; */?>" class="text-primary text-decoration-underline text-sm mt-3 d-inline-block">
                    Voir toutes les factures <i class="fas fa-arrow-right link-icon"></i>
                </a>
            </div>
        </div>-->

        <!-- Tableau des impayés -->
        <div class="col col-md-12">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Impayés</h2>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Facture ID</th>
                            <th scope="col">Montant</th>
                            <th scope="col">Règlement</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['impayes'] as $impaye) : ?>
                            <tr>
                                <td>Facture #<?php echo htmlspecialchars($impaye['id_facture']); ?></td>
                                <td><?php echo htmlspecialchars($impaye['montant']); ?> FCFA</td>
                                <td><?php echo htmlspecialchars($impaye['date_reglement'] ?: 'Non réglé'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="?list=recouvrement&insolvable=1" class="text-primary text-decoration-underline text-sm mt-3 d-inline-block">
                    Voir tous les impayés <i class="fas fa-arrow-right link-icon"></i>
                </a>
            </div>
        </div>
    </div>
</div>
<!-- Bootstrap JS (nécessaire pour certaines fonctionnalités comme les dropdowns) -->
<!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->
<!--</body>-->
<!--</html>-->