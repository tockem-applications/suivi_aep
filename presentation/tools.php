<?php
// getLetterMonth() est défini dans donnees/manager.php (chargé par index.php)

function display_modal($id_modal, $traitement)
{
    ?>
    <!-- Modal de Confirmation de Suppression -->
    <div class="modal fade" id="sortir_locataire_modal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmation de l'arret de contrat</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <!--                    <a href="?traitement=appartement_t.php&sortir_locataire=true&id_locataire=-->
                    <?php //echo $id_locataire;
                        ?><!--&id_appartement=--><?php //echo htmlspecialchars($id_appartement);
                            ?><!--"-->
                    <!--                       class="btn btn-danger" id="confirmDelete">sortir</a>-->
                </div>
            </div>
        </div>
    </div>
    <?php
}

function display_delete_modal($titre, $body, $traitement, $id_modal = 'deleteModalLabel')
{
    ?>
    <!-- Modal de Confirmation de Suppression -->
    <div class="modal fade" id="<?php echo $id_modal; ?>" tabindex="-1" role="dialog"
        aria-labelledby="<?php echo $id_modal; ?>" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel"><?php echo $titre ?></h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div><?php echo $body ?></div>
                    <p class="text-danger">Attenton, cette action sera irreversible !</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <a href="<?php echo $traitement; ?>" class="btn btn-danger" id="confirmDelete">Suprimer</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}


function genererGraphiques($dataArray, $options = array())
{
    $embed = !empty($options['embed']);
    $prefix = isset($options['prefix']) ? preg_replace('/[^a-z0-9_]/i', '', $options['prefix']) : 'chart';
    if ($prefix === '') {
        $prefix = 'chart';
    }
    // Initialiser des tableaux pour stocker les données graphiques
    $mois = array();
    $consommation = array();
    $nombreFactures = array();
    $montantFacture = array();
    $montantRecouvert = array();
    $tauxRecouvrement = array();
    $reverse_data_array = array_reverse($dataArray);
    // Traiter le tableau d'entrée — afficher les mois en lettres (ex. Janvier 2025)
    foreach ($reverse_data_array as $entry) {
        $monthVal = isset($entry['month']) ? $entry['month'] : '';
        $mois[] = (function_exists('getLetterMonth') && preg_match('/^\d{4}-\d{2}$/', $monthVal))
            ? getLetterMonth($monthVal)
            : $monthVal;
        $d = isset($entry['data']) && is_array($entry['data']) ? $entry['data'] : array();
        $consommation[] = floatval(isset($d['consommation']) ? $d['consommation'] : 0);
        $nombreFactures[] = intval(isset($d['nombre_factures']) ? $d['nombre_factures'] : (isset($d['nombre de factures']) ? $d['nombre de factures'] : 0));
        $montantFacture[] = floatval(isset($d['montant_facture']) ? $d['montant_facture'] : (isset($d['montant facturé']) ? $d['montant facturé'] : 0));
        $montantRecouvert[] = floatval(isset($d['montant_recouvert']) ? $d['montant_recouvert'] : (isset($d['montant recouvert']) ? $d['montant recouvert'] : 0));
        $tauxRecouv = isset($d['taux_recouvrement']) ? $d['taux_recouvrement'] : (isset($d['Taux de recouvrement']) ? $d['Taux de recouvrement'] : null);
        $tauxRecouvrement[] = ($tauxRecouv === '-' || $tauxRecouv === null || $tauxRecouv === '') ? 0 : floatval($tauxRecouv);
    }

    // Convertir les données en JSON pour les utiliser dans JavaScript
    $moisJSON = json_encode($mois);
    $consommationJSON = json_encode($consommation);
    $nombreFacturesJSON = json_encode($nombreFactures);
    $montantFactureJSON = json_encode($montantFacture);
    $montantRecouvertJSON = json_encode($montantRecouvert);
    $tauxRecouvrementJSON = json_encode($tauxRecouvrement);

    $maxTauxVal = 0.0;
    foreach ($tauxRecouvrement as $t) {
        if ($t > $maxTauxVal) {
            $maxTauxVal = $t;
        }
    }
    // Échelle Y : au moins 100 %, ou 15 % au-dessus du max si recouvrement > 100 % (paiements antérieurs, etc.)
    $tauxYMax = (int) max(100, ceil($maxTauxVal * 1.15));
    $tauxYScale = "max: {$tauxYMax},\n                                ";

    $c1 = $prefix . '_chart1';
    $c2 = $prefix . '_chart2';
    $c3 = $prefix . '_chart3';
    $c4 = $prefix . '_chart4';
    $chartHeight = isset($options['chart_height']) ? (int) $options['chart_height'] : ($embed ? 200 : 300);
    $showLegend = !empty($options['legend']);
    $deferInit = !empty($options['defer_init']);
    $includeScript = !isset($options['include_script']) || $options['include_script'];
    $isModal = !empty($options['modal']);
    $chartMaintain = ($embed || $isModal) ? 'false' : 'true';
    $legendDisplay = $showLegend ? 'true' : 'false';
    $titleSize = $isModal ? 14 : 12;

    if ($includeScript) {
        echo "<script src=\"https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js\"></script>\n";
    }

    if ($embed || $isModal) {
        $panelClass = $isModal ? 'rs-charts-panel rs-charts-panel-modal' : 'rs-charts-panel';
        echo "
<div class=\"{$panelClass}\">
            <div class=\"rs-charts-grid\">
<div class=\"rs-chart-cell\"><div class=\"rs-chart-canvas-wrap\" style=\"height:{$chartHeight}px\"><canvas id=\"{$c1}\"></canvas></div></div>
                <div class=\"rs-chart-cell\"><div class=\"rs-chart-canvas-wrap\" style=\"height:{$chartHeight}px\"><canvas id=\"{$c2}\"></canvas></div></div>
                <div class=\"rs-chart-cell\"><div class=\"rs-chart-canvas-wrap\" style=\"height:{$chartHeight}px\"><canvas id=\"{$c3}\"></canvas></div></div>
                <div class=\"rs-chart-cell\">
<div class=\"rs-chart-canvas-wrap\" style=\"height:{$chartHeight}px\"><canvas id=\"{$c4}\"></canvas></div></div>
            </div>
        </div>";
    } else {
        echo "
        <div class='card'>
            <h2 class='h2 d-flex justify-content-center mb-4'>Tableau de bord</h2>
            <div class='card-body row'>
                <div class='mt-3 col-12 col-md-6'><canvas id='{$c1}' style='max-height:300px'></canvas></div>
                <div class='mt-3 col-12 col-md-6'><canvas id='{$c2}' style='max-height:300px'></canvas></div>
                <div class='mt-3 col-12 col-md-6'><canvas id='{$c3}' style='max-height:300px'></canvas></div>
                <div class='mt-3 col-12 col-md-6'><canvas id='{$c4}' style='max-height:300px'></canvas></div>
            </div>
        </div>";
    }

    $initBody = "
            window._rsChartRegistry = window._rsChartRegistry || {};
            var labels = $moisJSON;
            var charts = [];
            function mk(id, cfg) {
                var el = document.getElementById(id);
                if (!el) return null;
                var existing = Chart.getChart(el);
                if (existing) existing.destroy();
                return new Chart(el.getContext('2d'), cfg);
            }
            var c1 = mk('{$c1}', {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Montant facturé', data: $montantFactureJSON, borderColor: 'rgba(26, 115, 232, 1)', backgroundColor: 'rgba(26, 115, 232, 0.08)', tension: 0.35, borderWidth: 2, pointRadius: 3 },
                        { label: 'Montant recouvré', data: $montantRecouvertJSON, borderColor: 'rgba(52, 168, 83, 1)', backgroundColor: 'rgba(52, 168, 83, 0.08)', tension: 0.35, borderWidth: 2, pointRadius: 3 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: {$chartMaintain},
                    plugins: {
                        title: { display: true, text: 'Facturation / Recouvrement', font: { size: {$titleSize}, weight: '500' } },
                        legend: { display: {$legendDisplay}, position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
                    },
                    scales: { y: { beginAtZero: true, ticks: { font: { size: 11 } } }, x: { ticks: { font: { size: 11 }, maxRotation: 45 } } }
                }
            });
            if (c1) charts.push(c1);
            var c2 = mk('{$c2}', {
                type: 'line',
                data: { labels: labels, datasets: [{ label: 'Taux (%)', data: $tauxRecouvrementJSON, borderColor: 'rgba(234, 67, 53, 1)', backgroundColor: 'rgba(234, 67, 53, 0.1)', tension: 0.35, borderWidth: 2, fill: true, pointRadius: 3, clip: false }] },
                options: {
                    responsive: true, maintainAspectRatio: {$chartMaintain},
                    plugins: { title: { display: true, text: 'Taux de recouvrement', font: { size: {$titleSize}, weight: '500' } }, legend: { display: false } },
                    scales: { y: { beginAtZero: true, {$tauxYScale}ticks: { font: { size: 11 }, callback: function(v) { return v + '%'; } } }, x: { ticks: { font: { size: 11 }, maxRotation: 45 } } }
                }
            });
            if (c2) charts.push(c2);
            var c3 = mk('{$c3}', {
                type: 'line',
                data: { labels: labels, datasets: [{ label: 'Factures', data: $nombreFacturesJSON, borderColor: 'rgba(103, 58, 183, 1)', backgroundColor: 'rgba(103, 58, 183, 0.1)', tension: 0.35, borderWidth: 2, fill: true, pointRadius: 3 }] },
                options: {
                    responsive: true, maintainAspectRatio: {$chartMaintain},
                    plugins: { title: { display: true, text: 'Nombre de factures', font: { size: {$titleSize}, weight: '500' } }, legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { font: { size: 11 } } }, x: { ticks: { font: { size: 11 }, maxRotation: 45 } } }
                }
            });
            if (c3) charts.push(c3);
            var c4 = mk('{$c4}', {
                type: 'line',
                data: { labels: labels, datasets: [{ label: 'm³', data: $consommationJSON, borderColor: 'rgba(251, 140, 0, 1)', backgroundColor: 'rgba(251, 140, 0, 0.1)', tension: 0.35, borderWidth: 2, fill: true, pointRadius: 3 }] },
                options: {
                    responsive: true, maintainAspectRatio: {$chartMaintain},
                    plugins: { title: { display: true, text: 'Consommation', font: { size: {$titleSize}, weight: '500' } }, legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { font: { size: 11 }, callback: function(v) { return v + ' m³'; } } }, x: { ticks: { font: { size: 11 }, maxRotation: 45 } } }
                }
            });
            if (c4) charts.push(c4);
            window._rsChartRegistry['{$prefix}'] = charts;
    ";

    if ($deferInit) {
        echo "<script type=\"text/javascript\">window.rsInitCharts_{$prefix} = function() { {$initBody} };</script>";
    } else {
        echo "<script type=\"text/javascript\">(function() { {$initBody} })();</script>";
    }
}
