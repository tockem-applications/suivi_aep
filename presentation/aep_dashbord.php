<?php
include_once 'traitement/aep_traitement.php';
@include_once("donnees/manager.php");
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

// Nouvelles métriques pour KPI
$kpis = array(
    'abonnes' => 0,
    'mois_courant' => '',
    'montant_facture_mois' => 0,
    'montant_recouvre_mois' => 0,
    'taux_recouvrement_mois' => 0,
    'conso_mois' => 0,
    'impayes_total' => 0
);
$tops = array(
    'abonnes_impayes' => array(),
    'reseaux_conso' => array()
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

    // KPI: mois courant (dernier mois existant)
    $resMois = Manager::prepare_query(
        "SELECT mf.id, mf.mois, mf.est_actif FROM mois_facturation mf INNER JOIN constante_reseau c ON c.id = mf.id_constante WHERE c.id_aep = ? ORDER BY mf.mois DESC LIMIT 1",
        array($aepId)
    );
    $lastMoisId = 0;
    $lastMoisLabel = '';
    if ($resMois) {
        $row = $resMois->fetch();
        if ($row) {
            $lastMoisId = (int) $row['id'];
            $lastMoisLabel = getLetterMonth($row['mois']);
        }
    }

    // KPI: montants et conso du mois courant
    if ($lastMoisId > 0) {
        $resAgg = Manager::prepare_query(
            "SELECT SUM(vaf.montant_total) mt, SUM(vaf.montant_verse) mv, SUM(vaf.montant_restant) mr, SUM(vaf.consommation) cs FROM vue_abones_facturation vaf WHERE vaf.id_mois = ? AND vaf.id_aep = ?",
            array($lastMoisId, $aepId)
        );
        if ($resAgg) {
            $r = $resAgg->fetch();
            $kpis['montant_facture_mois'] = isset($r['mt']) ? (float) $r['mt'] : 0;
            $kpis['montant_recouvre_mois'] = isset($r['mv']) ? (float) $r['mv'] : 0;
            $kpis['conso_mois'] = isset($r['cs']) ? (float) $r['cs'] : 0;
            $kpis['taux_recouvrement_mois'] = $kpis['montant_facture_mois'] > 0 ? round(($kpis['montant_recouvre_mois'] * 100.0) / $kpis['montant_facture_mois']) : 0;
        }
    }
    $kpis['mois_courant'] = $lastMoisLabel;
    $kpis['abonnes'] = $data['abones_count'];
    $kpis['impayes_total'] = $data['impaye_total'];

    // TOP: abonnés avec plus gros restants (mois courant)
    if ($lastMoisId > 0) {
        $resTopAb = Manager::prepare_query(
            "SELECT vaf.id_abone, a.nom as nom, vaf.montant_restant 
             FROM vue_abones_facturation vaf 
             INNER JOIN abone a ON a.id = vaf.id_abone 
             WHERE vaf.id_mois = ? AND vaf.id_aep = ? 
             ORDER BY vaf.montant_restant DESC LIMIT 5",
            array($lastMoisId, $aepId)
        );
        $tops['abonnes_impayes'] = $resTopAb ? $resTopAb->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    // TOP: réseaux par consommation (mois courant)
    if ($lastMoisId > 0) {
        $resTopRes = Manager::prepare_query(
            "SELECT r.nom, SUM(vaf.consommation) as conso FROM vue_abones_facturation vaf INNER JOIN abone a ON a.id = vaf.id_abone INNER JOIN reseau r ON r.id = a.id_reseau WHERE vaf.id_mois = ? AND vaf.id_aep = ? GROUP BY r.id ORDER BY conso DESC LIMIT 5",
            array($lastMoisId, $aepId)
        );
        $tops['reseaux_conso'] = $resTopRes ? $resTopRes->fetchAll(PDO::FETCH_ASSOC) : array();
    }
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

    .kpi {
        border-left: 5px solid #2c3e50;
    }
</style>
<div class="container-fluid">
    <h1 class="display-4 fw-bold text-dark mb-4 pt-3">Tableau de bord AEP <?php echo $data['libele'] ?> </h1>

    <!-- Ligne KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Abonnés</div>
                <div class="fs-4 fw-bold"><?php echo $kpis['abonnes']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Mois courant</div>
                <div class="fs-6 fw-bold">
                    <?php echo $kpis['mois_courant'] ? htmlspecialchars($kpis['mois_courant']) : '—'; ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Facturé (mois)</div>
                <div class="fs-5 fw-bold"><?php echo number_format($kpis['montant_facture_mois'], 0, ',', ' '); ?> FCFA
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Recouvré (mois)</div>
                <div class="fs-5 fw-bold text-success">
                    <?php echo number_format($kpis['montant_recouvre_mois'], 0, ',', ' '); ?> FCFA
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Taux recouvrement</div>
                <div class="fs-4 fw-bold"><?php echo $kpis['taux_recouvrement_mois']; ?>%</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Impayés totaux</div>
                <div class="fs-5 fw-bold text-danger"><?php echo number_format($kpis['impayes_total'], 0, ',', ' '); ?>
                    FCFA</div>
            </div>
        </div>
    </div>

    <!-- Graphiques principaux -->
    <div class="row g-4">
        <!-- Évolution des consommations -->
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
                                labels: <?php echo json_encode(array_map(function ($item) {
                                    return $item['date'];
                                }, $data['index_history'])); ?>,
                                datasets: [{
                                    label: 'Consommation',
                                    data: <?php echo json_encode(array_map(function ($item) {
                                        return $item['value'];
                                    }, $data['index_history'])); ?>,
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    fill: false,
                                    tension: 0.1
                                }]
                            },
                            options: { responsive: true, scales: { y: { beginAtZero: true } } }
                        });
                    </script>
                </div>
            </div>
        </div>

        <!-- Évolution des recouvrements -->
        <div class="col col-md-12 col-lg-6">
            <div class="card shadow-sm p-4">
                <h2 class="h4 fw-semibold text-dark mb-3">Évolution des recouvrements</h2>
                <div class="chart-container">
                    <canvas id="montants-chart"></canvas>
                    <script>
                        <?php
                        $montantsParMois = $data['montants_par_mois'];
                        $backgroundColorsFacture = array();
                        $backgroundColorsRecouvre = array();
                        foreach ($montantsParMois as $item) {
                            $montantFacture = isset($item['montant_facture']) ? floatval($item['montant_facture']) : 0;
                            $montantRecouvre = isset($item['montant_recouvre']) ? floatval($item['montant_recouvre']) : 0;
                            if (abs($montantFacture - $montantRecouvre) > 0.01) {
                                $backgroundColorsFacture[] = 'rgba(255, 99, 132, 0.9)';
                                $backgroundColorsRecouvre[] = 'rgba(50, 205, 50, 0.9)';
                            } else {
                                $backgroundColorsFacture[] = 'rgba(255, 0, 0, 0.7)';
                                $backgroundColorsRecouvre[] = 'rgba(0, 128, 0, 0.7)';
                            }
                        }
                        ?>
                        var ctx2 = document.getElementById('montants-chart').getContext('2d');
                        new Chart(ctx2, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode(array_map(function ($item) {
                                    return $item['date'];
                                }, $data['montants_par_mois'])); ?>,
                                datasets: [
                                    {
                                        label: 'Montant facturé', data: <?php echo json_encode(array_map(function ($item) {
                                            return $item['montant_facture'] ? $item['montant_facture'] : 0;
                                        }, $data['montants_par_mois'])); ?>, backgroundColor: <?php echo json_encode($backgroundColorsFacture); ?>, borderColor: 'rgba(255, 0, 0, 1)', borderWidth: 1
                                    },
                                    {
                                        label: 'Montant recouvré', data: <?php echo json_encode(array_map(function ($item) {
                                            return $item['montant_recouvre'] ? $item['montant_recouvre'] : 0;
                                        }, $data['montants_par_mois'])); ?>, backgroundColor: <?php echo json_encode($backgroundColorsRecouvre); ?>, borderColor: 'rgba(0, 128, 0, 1)', borderWidth: 1
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                scales: { y: { beginAtZero: true, title: { display: true, text: 'Montant (FCFA)' } }, x: { title: { display: true, text: 'Mois' } } },
                                plugins: { tooltip: { mode: 'index', intersect: false, callbacks: { label: function (context) { var label = context.dataset.label || ''; if (label) { label += ': '; } if (context.parsed.y !== null) { try { label += new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(context.parsed.y); } catch (e) { label += context.parsed.y + ' FCFA'; } } return label; } } } }
                            }
                        });
                    </script>
                </div>
            </div>
        </div>

        <!-- Répartition recouvrement (mois courant) -->
        <div class="col col-md-12 col-lg-4">
            <div class="card shadow-sm p-4">
                <h2 class="h5 fw-semibold text-dark mb-3">Répartition recouvrement (mois courant)</h2>
                <div class="chart-container" style="height: 260px;">
                    <canvas id="donut-recouvrement"></canvas>
                    <script>
                        var ctx3 = document.getElementById('donut-recouvrement').getContext('2d');
                        new Chart(ctx3, {
                            type: 'doughnut',
                            data: {
                                labels: ['Recouvré', 'Restant'],
                                datasets: [{
                                    data: [<?php echo $kpis['montant_recouvre_mois']; ?>, <?php echo max(0, $kpis['montant_facture_mois'] - $kpis['montant_recouvre_mois']); ?>],
                                    backgroundColor: ['rgba(39, 174, 96, 0.85)', 'rgba(231, 76, 60, 0.85)']
                                }]
                            },
                            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                        });
                    </script>
                </div>
            </div>
        </div>

        <!-- Top abonnés impayés -->
        <div class="col col-md-12 col-lg-4">
            <div class="card shadow-sm p-4">
                <h2 class="h5 fw-semibold text-dark mb-3">Top abonnés impayés (mois)</h2>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Abonné</th>
                                <th class="text-end">Reste (FCFA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tops['abonnes_impayes'])):
                                foreach ($tops['abonnes_impayes'] as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(isset($row['nom']) ? $row['nom'] : ''); ?></td>
                                        <td class="text-end text-danger fw-bold">
                                            <?php echo number_format(isset($row['montant_restant']) ? $row['montant_restant'] : 0, 0, ',', ' '); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="2" class="text-muted">Aucune donnée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top réseaux par consommation -->
        <div class="col col-md-12 col-lg-4">
            <div class="card shadow-sm p-4">
                <h2 class="h5 fw-semibold text-dark mb-3">Top réseaux par consommation (mois)</h2>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Réseau</th>
                                <th class="text-end">Conso (m³)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tops['reseaux_conso'])):
                                foreach ($tops['reseaux_conso'] as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(isset($row['nom']) ? $row['nom'] : ''); ?></td>
                                        <td class="text-end fw-bold">
                                            <?php echo number_format(isset($row['conso']) ? $row['conso'] : 0, 2, ',', ' '); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="2" class="text-muted">Aucune donnée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                    <a href="?page=reseaux" class="text-primary text-decoration-underline text-sm">
                        Voir détails <i class="fas fa-arrow-right link-icon"></i>
                    </a>
                </p>
                <ul class="list-group list-group-flush mt-2">
                    <?php foreach ($data['reseaux'] as $reseau): ?>
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
                    <p><strong>Total des entrées :</strong> <span
                            class="text-success fw-medium"><?php echo $data['flux_financiers']['entrees']; ?>
                            FCFA</span></p>
                    <p><strong>Total des sorties :</strong> <span
                            class="text-danger fw-medium"><?php echo $data['flux_financiers']['sorties']; ?> FCFA</span>
                    </p>
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
                            <?php foreach ($data['redevances'] as $redevance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($redevance['libele']); ?></td>
                                    <td><?php echo htmlspecialchars($redevance['pourcentage']); ?>%</td>
                                    <td><?php echo htmlspecialchars($redevance['type']); ?></td>
                                    <td><?php echo htmlspecialchars($redevance['mois_debut'] ? $redevance['mois_debut'] : 'N/A'); ?>
                                    </td>
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

        <!-- Impayés -->
<!--        <div class="col col-md-12">-->
<!--            <div class="card shadow-sm p-4">-->
<!--                <h2 class="h4 fw-semibold text-dark mb-3">Impayés</h2>-->
<!--                <div class="table-responsive">-->
<!--                    <table class="table table-bordered">-->
<!--                        <thead class="table-light">-->
<!--                            <tr>-->
<!--                                <th scope="col">Facture ID</th>-->
<!--                                <th scope="col">Montant</th>-->
<!--                                <th scope="col">Règlement</th>-->
<!--                            </tr>-->
<!--                        </thead>-->
<!--                        <tbody>-->
<!--                            --><?php //foreach ($data['impayes'] as $impaye): ?>
<!--                                <tr>-->
<!--                                    <td>Facture #--><?php //echo htmlspecialchars($impaye['id_facture']); ?><!--</td>-->
<!--                                    <td>--><?php //echo htmlspecialchars($impaye['montant']); ?><!-- FCFA</td>-->
<!--                                    <td>--><?php //echo htmlspecialchars($impaye['date_reglement'] ? $impaye['date_reglement'] : 'Non réglé'); ?>
<!--                                    </td>-->
<!--                                </tr>-->
<!--                            --><?php //endforeach; ?>
<!--                        </tbody>-->
<!--                    </table>-->
<!--                </div>-->
<!--                <a href="?list=recouvrement&insolvable=1"-->
<!--                    class="text-primary text-decoration-underline text-sm mt-3 d-inline-block">-->
<!--                    Voir tous les impayés <i class="fas fa-arrow-right link-icon"></i>-->
<!--                </a>-->
<!--            </div>-->
<!--        </div>-->
    </div>
</div>
<!-- Bootstrap JS -->