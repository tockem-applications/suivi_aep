<?php
include_once 'traitement/aep_traitement.php';
@include_once("donnees/manager.php");
//var_dump($_SESSION);
// Récupérer l'ID de l'AEP depuis l'URL
//var_dump($_SESSION);
if (isset($_GET['aep_id']))
    header("location:index.php?page=aep_dashboard");


$aepId = isset($_SESSION['id_aep']) ? intval($_SESSION['id_aep']) : null;
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
$rendementDistribution = array(
    'labels' => array(),
    'volumes_distribution' => array(),
    'volumes_abonnes' => array(),
    'taux' => array()
);
$tableauMontantsBfBp = array();
$dashboardNbMoisBfBp = isset($_GET['nb_mois_bf_bp']) ? max(1, min(36, (int) $_GET['nb_mois_bf_bp'])) : 12;
$dashboardHasTypeAbone = false;

if ($aepId) {
    // Sécuriser la présence du type de compteur réseau (distribution par défaut)
    try {
        $typeExists = Manager::prepare_query(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'compteur_reseau'
               AND column_name = 'type_compteur'",
            array()
        )->fetch();
        if (!$typeExists || (int) $typeExists['c'] === 0) {
            Manager::prepare_query(
                "ALTER TABLE `compteur_reseau`
                 ADD COLUMN `type_compteur` ENUM('production','distribution','reservoir')
                 NOT NULL DEFAULT 'distribution' AFTER `id_compteur`",
                array()
            );
        }
    } catch (Exception $e) {
        // fallback silencieux
    }

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

    // Graphique rendement de distribution global AEP:
    // taux = somme volumes compteurs distribution réseau / somme volumes compteurs abonnés
    $rowsDistribution = Manager::prepare_query(
        "SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS volume_distribution
         FROM mois_facturation mf
         INNER JOIN constante_reseau c ON c.id = mf.id_constante
         LEFT JOIN indexes i ON i.id_mois_facturation = mf.id
         LEFT JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
         WHERE c.id_aep = ? AND cr.type_compteur = 'distribution'
         GROUP BY mf.mois
         ORDER BY mf.mois ASC",
        array($aepId)
    );
    $mapDistribution = array();
    if ($rowsDistribution) {
        foreach ($rowsDistribution->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapDistribution[$row['mois']] = isset($row['volume_distribution']) ? (float) $row['volume_distribution'] : 0.0;
        }
    }

    $rowsAbonnes = Manager::prepare_query(
        "SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS volume_abonnes
         FROM mois_facturation mf
         INNER JOIN constante_reseau c ON c.id = mf.id_constante
         LEFT JOIN indexes i ON i.id_mois_facturation = mf.id
         LEFT JOIN compteur_abone ca ON ca.id_compteur = i.id_compteur
         WHERE c.id_aep = ?
         GROUP BY mf.mois
         ORDER BY mf.mois ASC",
        array($aepId)
    );
    $mapAbonnes = array();
    if ($rowsAbonnes) {
        foreach ($rowsAbonnes->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapAbonnes[$row['mois']] = isset($row['volume_abonnes']) ? (float) $row['volume_abonnes'] : 0.0;
        }
    }

    $allMonths = array();
    foreach ($mapDistribution as $mois => $v) {
        $allMonths[$mois] = true;
    }
    foreach ($mapAbonnes as $mois => $v) {
        $allMonths[$mois] = true;
    }
    $labelsRend = array_keys($allMonths);
    sort($labelsRend);

    foreach ($labelsRend as $mois) {
        $vd = isset($mapDistribution[$mois]) ? (float) $mapDistribution[$mois] : 0.0;
        $va = isset($mapAbonnes[$mois]) ? (float) $mapAbonnes[$mois] : 0.0;
        $taux = $vd > 0 ? round(($va * 100.0) / $vd, 2) : 0.0;

        $rendementDistribution['labels'][] = getLetterMonth($mois);
        $rendementDistribution['volumes_distribution'][] = $vd;
        $rendementDistribution['volumes_abonnes'][] = $va;
        $rendementDistribution['taux'][] = $taux;
    }

    // Tableau facturé / recouvré par mois, séparé BF (borne fontaine) vs BP (branchement privé)
    try {
        $colType = Manager::prepare_query(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'abone' AND column_name = 'type_abone'",
            array()
        );
        if ($colType) {
            $rowCol = $colType->fetch();
            $dashboardHasTypeAbone = $rowCol && (int) $rowCol['c'] > 0;
        }
    } catch (Exception $e) {
        $dashboardHasTypeAbone = false;
    }

    $limMoisBfBp = max(1, min(36, (int) $dashboardNbMoisBfBp));
    if ($dashboardHasTypeAbone) {
        $sqlBfBp = "
            SELECT * FROM (
                SELECT
                    vaf.mois,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(a.type_abone), ''), 'BP') = 'BF' THEN vaf.montant_total ELSE 0 END) AS facture_bf,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(a.type_abone), ''), 'BP') = 'BF' THEN IFNULL(vaf.montant_verse, 0) ELSE 0 END) AS recouvre_bf,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(a.type_abone), ''), 'BP') <> 'BF' THEN vaf.montant_total ELSE 0 END) AS facture_bp,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(a.type_abone), ''), 'BP') <> 'BF' THEN IFNULL(vaf.montant_verse, 0) ELSE 0 END) AS recouvre_bp
                FROM vue_abones_facturation vaf
                INNER JOIN abone a ON a.id = vaf.id_abone
                INNER JOIN mois_facturation mf ON mf.id = vaf.id_mois
                WHERE vaf.id_aep = ? AND mf.est_mois_base = 0
                GROUP BY vaf.id_mois, vaf.mois
                ORDER BY vaf.mois DESC
                LIMIT " . $limMoisBfBp . "
            ) sub
            ORDER BY sub.mois DESC
        ";
    } else {
        $sqlBfBp = "
            SELECT * FROM (
                SELECT
                    vaf.mois,
                    0 AS facture_bf,
                    0 AS recouvre_bf,
                    SUM(vaf.montant_total) AS facture_bp,
                    SUM(IFNULL(vaf.montant_verse, 0)) AS recouvre_bp
                FROM vue_abones_facturation vaf
                INNER JOIN mois_facturation mf ON mf.id = vaf.id_mois
                WHERE vaf.id_aep = ? AND mf.est_mois_base = 0
                GROUP BY vaf.id_mois, vaf.mois
                ORDER BY vaf.mois DESC
                LIMIT " . $limMoisBfBp . "
            ) sub
            ORDER BY sub.mois DESC
        ";
    }
    try {
        $stmtBfBp = Manager::prepare_query($sqlBfBp, array($aepId));
        if ($stmtBfBp) {
            $tableauMontantsBfBp = $stmtBfBp->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $tableauMontantsBfBp = array();
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
        background-color: #0d6efd;
        color: #fff;
        font-weight: 500;
    }

    .table thead th {
        padding: 12px;
        border-bottom: none;
        color: #fff !important;
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

    /* Tableau BF/BP : bandes de couleur par type de branchement */
    .table-montants-bf-bp tbody tr,
    .table-montants-bf-bp tbody tr:nth-child(even),
    .table-montants-bf-bp tbody tr:hover {
        background-color: transparent;
    }

    .table-montants-bf-bp thead .th-mois {
        background-color: #495057 !important;
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.15);
    }

    .table-montants-bf-bp thead .th-bf {
        background-color: #0f766e !important;
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.15);
    }

    .table-montants-bf-bp thead .th-bp {
        background-color: #5b21b6 !important;
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.15);
    }

    .table-montants-bf-bp tbody .td-mois {
        background-color: #f1f3f5;
    }

    .table-montants-bf-bp tbody tr:nth-child(even) .td-mois {
        background-color: #e9ecef;
    }

    .table-montants-bf-bp tbody .td-bf {
        background-color: #ccfbf1;
    }

    .table-montants-bf-bp tbody tr:nth-child(even) .td-bf {
        background-color: #99f6e4;
    }

    .table-montants-bf-bp tbody .td-bp {
        background-color: #ede9fe;
    }

    .table-montants-bf-bp tbody tr:nth-child(even) .td-bp {
        background-color: #ddd6fe;
    }

    .table-montants-bf-bp tbody tr:hover .td-mois {
        background-color: #dee2e6;
    }

    .table-montants-bf-bp tbody tr:hover .td-bf {
        background-color: #5eead4;
    }

    .table-montants-bf-bp tbody tr:hover .td-bp {
        background-color: #c4b5fd;
    }
</style>
<div class="container-fluid">
    <h1 class="display-4 fw-bold text-dark mb-4 pt-3">Tableau de bord AEP <?php echo $data['libele'] ?> </h1>

    <!-- Ligne KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Abonnés</div>
                <div class="fs-5 fw-bold"><?php echo $kpis['abonnes']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card kpi p-3">
                <div class="small text-muted">Mois courant</div>
                <div class="fs-5 fw-bold">
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
                <div class="fs-5 fw-bold"><?php echo $kpis['taux_recouvrement_mois']; ?>%</div>
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
                                    return getLetterMonth(isset($item['date']) ? $item['date'] : '');
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
                                    return getLetterMonth(isset($item['date']) ? $item['date'] : '');
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

        <!-- Rendement distribution global -->
        <div class="col col-md-12 col-lg-8">
            <div class="card shadow-sm p-4">
                <h2 class="h5 fw-semibold text-dark mb-3">Rendement de distribution (global AEP)</h2>
                <div class="small text-muted mb-2">
                    Taux = Somme volumes compteurs abonnés / Somme volumes compteurs réseau de type distribution.
                </div>
                <div class="chart-container">
                    <canvas id="rendement-distribution-chart"></canvas>
                    <script>
                        var ctxR = document.getElementById('rendement-distribution-chart').getContext('2d');
                        new Chart(ctxR, {
                            data: {
                                labels: <?php echo json_encode($rendementDistribution['labels']); ?>,
                                datasets: [
                                    {
                                        type: 'bar',
                                        label: 'Volume distribution (m³)',
                                        data: <?php echo json_encode($rendementDistribution['volumes_distribution']); ?>,
                                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1
                                    },
                                    {
                                        type: 'bar',
                                        label: 'Volume abonnés (m³)',
                                        data: <?php echo json_encode($rendementDistribution['volumes_abonnes']); ?>,
                                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        borderWidth: 1
                                    },
                                    {
                                        type: 'line',
                                        label: 'Rendement distribution (%)',
                                        data: <?php echo json_encode($rendementDistribution['taux']); ?>,
                                        yAxisID: 'y1',
                                        borderColor: 'rgba(255, 99, 132, 1)',
                                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                        borderWidth: 2,
                                        tension: 0.2
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                interaction: { mode: 'index', intersect: false },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: { display: true, text: 'Volume (m³)' }
                                    },
                                    y1: {
                                        beginAtZero: true,
                                        position: 'right',
                                        title: { display: true, text: 'Rendement (%)' },
                                        grid: { drawOnChartArea: false }
                                    }
                                }
                            }
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>

    <?php if ($aepId): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card shadow-sm p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 fw-semibold text-dark mb-1">Facturation et recouvrement par type de branchement</h2>
                            <p class="small text-muted mb-0">
                                <strong>BF</strong> : bornes fontaines (<code>type_abone = BF</code>) —
                                <strong>BP</strong> : branchements privés (tout abonné qui n’est pas BF).
                            </p>
                        </div>
                        <form method="get" action="" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="page" value="aep_dashboard">
                            <label class="small text-muted mb-0" for="nb_mois_bf_bp">Mois affichés</label>
                            <select name="nb_mois_bf_bp" id="nb_mois_bf_bp" class="form-select form-select-sm" style="width: auto;"
                                onchange="this.form.submit()">
                                <?php
                                $choixNbMois = array(6, 12, 18, 24, 36);
                                if (!in_array($dashboardNbMoisBfBp, $choixNbMois, true)) {
                                    $choixNbMois[] = $dashboardNbMoisBfBp;
                                    sort($choixNbMois, SORT_NUMERIC);
                                }
                                foreach ($choixNbMois as $n):
                                    ?>
                                    <option value="<?php echo (int) $n; ?>" <?php echo $dashboardNbMoisBfBp === (int) $n ? 'selected' : ''; ?>>
                                        <?php echo (int) $n; ?> mois
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <?php if (!$dashboardHasTypeAbone): ?>
                        <div class="alert alert-warning py-2 small">
                            La colonne <code>abone.type_abone</code> est absente : les montants sont regroupés en <strong>BP</strong> uniquement (BF à 0).
                            Exécutez la migration bornes fontaines pour activer la répartition BF/BP.
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-montants-bf-bp align-middle mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle th-mois">Mois</th>
                                    <th colspan="2" class="text-center th-bf">Borne fontaine (BF)</th>
                                    <th colspan="2" class="text-center th-bp">Branchement privé (BP)</th>
                                </tr>
                                <tr>
                                    <th class="text-end th-bf">Facturé (FCFA)</th>
                                    <th class="text-end th-bf">Recouvré (FCFA)</th>
                                    <th class="text-end th-bp">Facturé (FCFA)</th>
                                    <th class="text-end th-bp">Recouvré (FCFA)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($tableauMontantsBfBp)): ?>
                                    <?php foreach ($tableauMontantsBfBp as $ligne): ?>
                                        <tr>
                                            <td class="td-mois"><?php echo htmlspecialchars(getLetterMonth(isset($ligne['mois']) ? $ligne['mois'] : '')); ?></td>
                                            <td class="text-end td-bf"><?php echo number_format((float) (isset($ligne['facture_bf']) ? $ligne['facture_bf'] : 0), 0, ',', ' '); ?></td>
                                            <td class="text-end text-success td-bf"><?php echo number_format((float) (isset($ligne['recouvre_bf']) ? $ligne['recouvre_bf'] : 0), 0, ',', ' '); ?></td>
                                            <td class="text-end td-bp"><?php echo number_format((float) (isset($ligne['facture_bp']) ? $ligne['facture_bp'] : 0), 0, ',', ' '); ?></td>
                                            <td class="text-end text-success td-bp"><?php echo number_format((float) (isset($ligne['recouvre_bp']) ? $ligne['recouvre_bp'] : 0), 0, ',', ' '); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4 bg-light">Aucune donnée sur la période.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
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