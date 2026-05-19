<?php
require_once("traitement/reseau_t.php");
require_once("presentation/compteur_component.php");
include_once("facture_component.php");
include_once("tools.php");

/**
 * Calcule les statistiques mensuelles (montants cohérents avec le recouvrement AEP).
 *
 * @return array{rows: array, chart: array, csv: array, totals: array}
 */
function computeStatistiqueReseau($id_reseau, $mois_debut = null, $mois_fin = null)
{
    $id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
    $req = MoisFacturation::getStatsReseauParMois($mois_debut, $mois_fin, $id_aep, $id_reseau);
    $lignes = $req ? $req->fetchAll(PDO::FETCH_ASSOC) : array();

    $rows = array();
    $chart = array();
    $csv = array();
    $tot = array(
        'conso' => 0.0,
        'nombre' => 0,
        'montant_facture' => 0.0,
        'montant_recouvert' => 0.0,
    );

    foreach ($lignes as $ligne) {
        $mois = $ligne['mois'];
        $conso = (float) $ligne['conso'];
        $nombre = (int) $ligne['nombre'];
        $montant_facture = (float) $ligne['montant_facture'];
        $montant_recouvert = (float) $ligne['montant_verse'];
        $conso_moy = $nombre > 0 ? $conso / $nombre : 0.0;
        $taux = $montant_facture > 0 ? round(100.0 * $montant_recouvert / $montant_facture, 1) : null;

        $tot['conso'] += $conso;
        $tot['nombre'] += $nombre;
        $tot['montant_facture'] += $montant_facture;
        $tot['montant_recouvert'] += $montant_recouvert;

        $mois_lib = function_exists('getLetterMonth') ? getLetterMonth($mois) : $mois;

        $rows[] = array(
            'id_mois' => (int) $ligne['id_mois'],
            'mois' => $mois,
            'mois_libelle' => $mois_lib,
            'consommation' => $conso,
            'nombre_factures' => $nombre,
            'montant_facture' => $montant_facture,
            'montant_recouvert' => $montant_recouvert,
            'consommation_moyenne' => $conso_moy,
            'taux_recouvrement' => $taux,
        );

        $chart[] = array(
            'month' => $mois_lib,
            'data' => array(
                'consommation' => $conso,
                'nombre_factures' => $nombre,
                'montant_facture' => $montant_facture,
                'montant_recouvert' => $montant_recouvert,
                'taux_recouvrement' => $taux !== null ? $taux : 0,
            ),
        );

        $csv[] = array(
            'Mois' => $mois_lib,
            'Consommation (m3)' => number_format($conso, 2, ',', ' '),
            'Nombre de factures' => $nombre,
            'Montant facturé (FCFA)' => (int) round($montant_facture),
            'Montant recouvré (FCFA)' => (int) round($montant_recouvert),
            'Consommation moyenne (m3)' => number_format($conso_moy, 2, ',', ' '),
            'Taux de recouvrement (%)' => $taux !== null ? $taux : '',
        );
    }

    $tot['taux_recouvrement'] = $tot['montant_facture'] > 0
        ? round(100.0 * $tot['montant_recouvert'] / $tot['montant_facture'], 1)
        : null;

    return array(
        'rows' => $rows,
        'chart' => $chart,
        'csv' => $csv,
        'totals' => $tot,
    );
}

/** @deprecated Utiliser computeStatistiqueReseau */
function afficherStatistiqueReseau($id_reseau, $mois_debut = null, $mois_fin = null)
{
    return computeStatistiqueReseau($id_reseau, $mois_debut, $mois_fin);
}

function affichergraphiquesReseau($data)
{
    ?>
    <div class="rs-charts-toolbar d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
            data-bs-target="#rsChartsModal" title="Afficher les graphiques en plein écran">
            <i class="bi bi-arrows-fullscreen me-1"></i>Agrandir les graphiques
        </button>
    </div>
    <?php
    genererGraphiques(
        isset($data['chart']) ? $data['chart'] : $data,
        array('embed' => true, 'prefix' => 'reseau', 'chart_height' => 200)
    );
}

function rs_render_charts_modal($data)
{
    $chartData = isset($data['chart']) ? $data['chart'] : $data;
    ?>
    <div class="modal fade" id="rsChartsModal" tabindex="-1" aria-labelledby="rsChartsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title" id="rsChartsModalLabel">
                        <i class="bi bi-bar-chart-line me-2"></i>Graphiques — vue détaillée
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-3">
                    <?php
                    genererGraphiques($chartData, array(
                        'modal' => true,
                        'prefix' => 'reseau_modal',
                        'chart_height' => 360,
                        'legend' => true,
                        'defer_init' => true,
                        'include_script' => false,
                    ));
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function afficherCompteursReseau($id_reseau)
{
    display_compteur_list($id_reseau);
}

function rs_fmt_fcfa($n)
{
    return number_format((float) $n, 0, ',', ' ');
}

function rs_fmt_m3($n)
{
    return number_format((float) $n, 2, ',', ' ');
}

function rs_render_month_cards(array $rows)
{
    if (empty($rows)) {
        echo '<p class="text-muted small text-center py-4 mb-0">Aucune donnée pour la période.</p>';
        return;
    }
    ?>
    <div class="accordion accordion-flush rs-month-accordion" id="rsMoisAccordion">
        <?php foreach ($rows as $i => $r):
            $accId = 'rs-acc-' . (int) $i;
            $expanded = ($i === 0);
            $taux = $r['taux_recouvrement'];
            $tauxClass = $taux === null ? 'secondary' : ($taux >= 80 ? 'success' : ($taux >= 50 ? 'warning' : 'danger'));
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button<?php echo $expanded ? '' : ' collapsed'; ?>" type="button"
                        data-bs-toggle="collapse" data-bs-target="#<?php echo $accId; ?>"
                        aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo $accId; ?>">
                        <span class="rs-acc-mois"><?php echo htmlspecialchars($r['mois_libelle'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="badge text-bg-<?php echo $tauxClass; ?> ms-auto me-2">
                            <?php echo $taux !== null ? $taux . ' %' : '—'; ?>
                        </span>
                    </button>
                </h2>
                <div id="<?php echo $accId; ?>" class="accordion-collapse collapse<?php echo $expanded ? ' show' : ''; ?>"
                    data-bs-parent="#rsMoisAccordion">
                    <div class="accordion-body">
                        <dl class="rs-acc-dl row g-2 mb-0">
                            <div class="col-6">
                                <dt>Consommation</dt>
                                <dd><?php echo rs_fmt_m3($r['consommation']); ?> m³</dd>
                            </div>
                            <div class="col-6">
                                <dt>Factures</dt>
                                <dd><?php echo (int) $r['nombre_factures']; ?></dd>
                            </div>
                            <div class="col-6">
                                <dt>Montant facturé</dt>
                                <dd><?php echo rs_fmt_fcfa($r['montant_facture']); ?> FCFA</dd>
                            </div>
                            <div class="col-6">
                                <dt>Montant recouvré</dt>
                                <dd class="text-success"><?php echo rs_fmt_fcfa($r['montant_recouvert']); ?> FCFA</dd>
                            </div>
                            <div class="col-6">
                                <dt>Conso. moyenne</dt>
                                <dd><?php echo rs_fmt_m3($r['consommation_moyenne']); ?> m³</dd>
                            </div>
                            <div class="col-6">
                                <dt>Taux recouvrement</dt>
                                <dd><?php echo $taux !== null ? $taux . ' %' : '—'; ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
function rs_render_month_table(array $rows, array $tot, array $csv, $csvName)
{
    if (empty($rows)) {
        echo '<p class="text-muted small text-center py-4 mb-0">Aucune donnée pour la période.</p>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table rs-table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th class="text-end">m³</th>
                    <th class="text-center">Fact.</th>
                    <th class="text-end">Facturé</th>
                    <th class="text-end">Recouvré</th>
                    <th class="text-end">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-nowrap"><?php echo htmlspecialchars($r['mois_libelle'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo rs_fmt_m3($r['consommation']); ?></td>
                        <td class="text-center"><?php echo (int) $r['nombre_factures']; ?></td>
                        <td class="text-end"><?php echo rs_fmt_fcfa($r['montant_facture']); ?></td>
                        <td class="text-end text-success"><?php echo rs_fmt_fcfa($r['montant_recouvert']); ?></td>
                        <td class="text-end">
                            <?php if ($r['taux_recouvrement'] !== null): ?>
                                <?php echo htmlspecialchars((string) $r['taux_recouvrement'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (count($rows) > 1): ?>
                <tfoot class="table-light">
                    <tr class="fw-semibold">
                        <td>Total</td>
                        <td class="text-end"><?php echo rs_fmt_m3(isset($tot['conso']) ? $tot['conso'] : 0); ?></td>
                        <td class="text-center"><?php echo (int) (isset($tot['nombre']) ? $tot['nombre'] : 0); ?></td>
                        <td class="text-end"><?php echo rs_fmt_fcfa(isset($tot['montant_facture']) ? $tot['montant_facture'] : 0); ?></td>
                        <td class="text-end text-success"><?php echo rs_fmt_fcfa(isset($tot['montant_recouvert']) ? $tot['montant_recouvert'] : 0); ?></td>
                        <td class="text-end"><?php echo isset($tot['taux_recouvrement']) && $tot['taux_recouvrement'] !== null ? $tot['taux_recouvrement'] : '—'; ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php
}

function afficherPageReseau($id_reseau, $stats, $periodeReseau = null)
{
    $rows = isset($stats['rows']) ? $stats['rows'] : array();
    $csv = isset($stats['csv']) ? $stats['csv'] : array();
    $tot = isset($stats['totals']) ? $stats['totals'] : array();
    $showCompteurs = ($id_reseau != 0 && isset($_GET['compteur']));

    @include_once(__DIR__ . '/periode_selector_component.php');
    @include_once('presentation/periode_selector_component.php');

    $selected_reseau = rs_resolve_selected_reseau($id_reseau);
    $libeleAep = isset($_SESSION['libele_aep']) ? $_SESSION['libele_aep'] : '';
    $isAepGlobal = ($id_reseau === 0 || $id_reseau === '0');
    $csvName = $isAepGlobal
        ? 'aep_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $libeleAep !== '' ? $libeleAep : 'global') . '_stats_mensuelles.csv'
        : 'reseau_' . ($selected_reseau ? preg_replace('/[^a-zA-Z0-9_-]+/', '_', $selected_reseau->nom) : 'reseau') . '_stats_mensuelles.csv';

    $csvExportHtml = '';
    if (!empty($csv) && function_exists('create_csv_exportation_button')) {
        ob_start();
        create_csv_exportation_button(
            $csv,
            $csvName,
            'Exporter le récapitulatif mensuel au format CSV',
            'btn btn-outline-secondary btn-sm',
            'Exporter CSV'
        );
        $csvExportHtml = ob_get_clean();
    }
    ?>
    <style>
        .rs-shell { background: #f8f9fa; margin: -0.5rem -0.75rem 0; min-height: calc(100vh - 100px); padding-bottom: 2rem; }
        .rs-main { max-width: 1400px; margin: 0 auto; padding: 1rem 1.5rem 0; }
        .rs-breadcrumb { font-size: 0.8rem; color: #5f6368; }
        .rs-breadcrumb a { color: #1a73e8; text-decoration: none; }
        .rs-page-title { font-size: 1.35rem; font-weight: 400; color: #202124; margin: 0; }
        .rs-subtitle { font-size: 0.85rem; color: #5f6368; margin: 0; }
        .rs-card { background: #fff; border: 1px solid #dadce0; border-radius: 8px; overflow: hidden; }
        .rs-card-header { padding: 0.85rem 1.25rem; border-bottom: 1px solid #e8eaed; font-weight: 500; font-size: 0.92rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; }
        .rs-card-body { padding: 1.25rem; }
        .rs-kpi { background: #fff; border: 1px solid #dadce0; border-radius: 8px; padding: 1rem; height: 100%; }
        .rs-kpi-label { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; color: #5f6368; margin-bottom: 0.35rem; }
        .rs-kpi-value { font-size: 1.2rem; font-weight: 500; color: #202124; }
        .rs-table { font-size: 0.875rem; margin-bottom: 0; }
        .rs-table thead th { background: #f8f9fa; color: #5f6368; font-weight: 500; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid #e8eaed; white-space: nowrap; }
        .rs-table tbody td { vertical-align: middle; border-color: #e8eaed; }
        .rs-table tbody tr:hover { background: #f8f9fa; }
        .rs-toolbar-card { background: #fff; border: 1px solid #dadce0; border-radius: 8px; margin-bottom: 1rem; }
        .rs-toolbar-card .rs-card-body { padding: 0.75rem 1.25rem; }
        .rs-reseau-selector-form .form-label { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; color: #5f6368; }
        .rs-reseau-selector-form .form-select { min-width: min(320px, 100%); max-width: 100%; font-size: 0.875rem; border-color: #dadce0; }
        .rs-panel-right { background: #fff; border: 1px solid #dadce0; border-radius: 8px; overflow: hidden; min-height: 520px; display: flex; flex-direction: column; }
        .rs-panel-top { padding: 0.85rem 1rem; border-bottom: 1px solid #e8eaed; background: #fff; flex-shrink: 0; }
        .rs-panel-top .rs-panel-title { font-size: 0.92rem; font-weight: 500; color: #202124; margin: 0; }
        .rs-panel-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; width: 100%; }
        .rs-segmented-tabs {
            display: inline-flex; align-items: stretch;
            background: #f1f3f4; border: 1px solid #dadce0; border-radius: 8px;
            padding: 3px; gap: 2px;
        }
        .rs-segmented-tabs .rs-seg-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
            border: none; background: transparent; color: #5f6368;
            font-size: 0.8125rem; font-weight: 500; line-height: 1.25;
            padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
            white-space: nowrap;
        }
        .rs-segmented-tabs .rs-seg-btn:hover { color: #202124; background: rgba(255, 255, 255, 0.6); }
        .rs-segmented-tabs .rs-seg-btn.active {
            background: #fff; color: #1a73e8;
            box-shadow: 0 1px 2px rgba(60, 64, 67, 0.2);
        }
        .rs-segmented-tabs .rs-seg-btn:focus-visible {
            outline: 2px solid #1a73e8; outline-offset: 2px;
        }
        .rs-csv-export-wrap { flex-shrink: 0; margin-left: auto; }
        .rs-csv-export-wrap .btn { white-space: nowrap; }
        .rs-panel-scroll { overflow-y: auto; flex: 1; padding: 0.5rem 0.75rem 0.75rem; min-height: 0; }
        .rs-panel-scroll > .tab-pane { display: none; }
        .rs-panel-scroll > .tab-pane.active { display: block; }
        .rs-layout-row { align-items: flex-start; }
        .rs-charts-panel { background: #fff; border: 1px solid #dadce0; border-radius: 8px; padding: 0.75rem; }
        .rs-charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        @media (max-width: 991.98px) { .rs-charts-grid { grid-template-columns: 1fr; } }
        .rs-chart-cell { border: 1px solid #e8eaed; border-radius: 6px; padding: 0.5rem; background: #fafafa; }
        .rs-chart-canvas-wrap { position: relative; }
        .rs-chart-canvas-wrap canvas { width: 100% !important; height: 100% !important; }
        .rs-charts-panel-modal .rs-charts-grid { gap: 1rem; }
        .rs-charts-panel-modal .rs-chart-cell { padding: 0.75rem; }
        #rsChartsModal .modal-xl { max-width: min(1200px, 96vw); }
        .rs-month-accordion .accordion-item { border-color: #e8eaed; }
        .rs-month-accordion .accordion-button { font-size: 0.88rem; padding: 0.65rem 0.85rem; background: #fff; box-shadow: none; }
        .rs-month-accordion .accordion-button:not(.collapsed) { background: #e8f0fe; color: #1a73e8; }
        .rs-month-accordion .accordion-body { padding: 0.65rem 0.85rem; }
        .rs-acc-dl dt { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.03em; color: #5f6368; margin-bottom: 0.1rem; }
        .rs-acc-dl dd { font-size: 0.85rem; font-weight: 500; margin-bottom: 0; color: #202124; }
        .rs-acc-mois { flex: 1; text-align: left; }
    </style>

    <div class="rs-shell">
        <div class="rs-main">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <nav class="rs-breadcrumb" aria-label="Fil d'Ariane">
                    <a href="?page=home">Accueil</a>
                    <span class="mx-1">/</span>
                    <?php if ($libeleAep !== ''): ?>
                        <a href="?page=aep_dashboard"><?php echo htmlspecialchars($libeleAep, ENT_QUOTES, 'UTF-8'); ?></a>
                        <span class="mx-1">/</span>
                    <?php endif; ?>
                    <span>Réseau</span>
                </nav>
            </div>

            <div class="mb-3">
                <h1 class="rs-page-title">
                        <?php if ($selected_reseau): ?>
                            <?php echo htmlspecialchars($selected_reseau->nom, ENT_QUOTES, 'UTF-8'); ?>
                            <span class="text-muted fs-6">(<?php echo htmlspecialchars($selected_reseau->abreviation, ENT_QUOTES, 'UTF-8'); ?>)</span>
                        <?php elseif ($libeleAep !== ''): ?>
                            <?php echo htmlspecialchars($libeleAep, ENT_QUOTES, 'UTF-8'); ?>
                            <span class="text-muted fs-6">— tous les réseaux</span>
                        <?php else: ?>
                            Ensemble de l'AEP
                        <?php endif; ?>
                    </h1>
                <p class="rs-subtitle mb-0">
                    <?php echo $isAepGlobal ? 'Synthèse AEP' : 'Réseau'; ?> · facturation, recouvrement et consommation par mois
                </p>
            </div>

            <div class="rs-card rs-toolbar-card mb-3">
                <div class="rs-card-body">
                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
                        <?php rs_render_reseau_selector($id_reseau, $libeleAep); ?>
                        <?php if ($periodeReseau && function_exists('render_periode_selector_form')): ?>
                            <div class="d-flex flex-wrap align-items-end gap-2">
                                <?php if (!empty($periodeReseau['periode_libelle'])): ?>
                                    <span class="small text-muted pb-2">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo htmlspecialchars($periodeReseau['periode_libelle'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                                <?php
                                render_periode_selector_form($periodeReseau, array(
                                    'page' => 'reseau',
                                    'form_id' => 'form_reseau_periode',
                                    'select_id' => 'reseau_periode_annee',
                                    'mf_debut_id' => 'reseau_mf_debut',
                                    'mf_fin_id' => 'reseau_mf_fin',
                                    'hidden' => array('id_reseau' => $id_reseau),
                                    'wrapper_class' => 'd-flex flex-wrap align-items-end gap-2',
                                ));
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($showCompteurs && $id_reseau == 0): ?>
                <div class="alert alert-info border-0 shadow-sm" style="border-radius:8px">
                    Sélectionnez un réseau dans le menu déroulant pour afficher ses compteurs.
                </div>
            <?php elseif ($showCompteurs): ?>
                <div class="rs-card">
                    <div class="rs-card-header">Compteurs du réseau</div>
                    <div class="rs-card-body"><?php afficherCompteursReseau($id_reseau); ?></div>
                </div>
            <?php else: ?>
                <?php if (count($rows) > 0): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-lg-3">
                            <div class="rs-kpi">
                                <div class="rs-kpi-label">Montant facturé</div>
                                <div class="rs-kpi-value"><?php echo rs_fmt_fcfa(isset($tot['montant_facture']) ? $tot['montant_facture'] : 0); ?> <small class="text-muted">FCFA</small></div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="rs-kpi">
                                <div class="rs-kpi-label">Montant recouvré</div>
                                <div class="rs-kpi-value text-success"><?php echo rs_fmt_fcfa(isset($tot['montant_recouvert']) ? $tot['montant_recouvert'] : 0); ?> <small class="text-muted">FCFA</small></div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="rs-kpi">
                                <div class="rs-kpi-label">Taux de recouvrement</div>
                                <div class="rs-kpi-value"><?php echo isset($tot['taux_recouvrement']) && $tot['taux_recouvrement'] !== null ? $tot['taux_recouvrement'] . ' %' : '—'; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="rs-kpi">
                                <div class="rs-kpi-label">Consommation</div>
                                <div class="rs-kpi-value"><?php echo rs_fmt_m3(isset($tot['conso']) ? $tot['conso'] : 0); ?> <small class="text-muted">m³</small></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row rs-layout-row g-3">
                    <div class="col-12 col-md-7">
                        <?php if (empty($rows)): ?>
                            <div class="rs-card">
                                <div class="rs-card-body text-center text-muted py-5">
                                    <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:.4"></i>
                                    Aucune donnée pour la période sélectionnée.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php affichergraphiquesReseau($stats); ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-5">
                        <div class="rs-panel-right">
                            <div class="rs-panel-top">
                                <div class="rs-panel-actions">
                                    <h2 class="rs-panel-title mb-0"><i class="bi bi-calendar3 me-1"></i>Détail mensuel</h2>
                                    <div class="d-flex flex-wrap align-items-center gap-2 ms-md-auto">
                                        <div class="rs-segmented-tabs" id="rsMoisTabs" role="tablist" aria-label="Vue du détail mensuel">
                                            <button class="rs-seg-btn active" id="rs-tab-cartes-btn" type="button" role="tab"
                                                data-rs-tab-target="rs-tab-cartes" aria-controls="rs-tab-cartes"
                                                aria-selected="true">
                                                <i class="bi bi-grid-1x2"></i>Cartes
                                            </button>
                                            <button class="rs-seg-btn" id="rs-tab-tableau-btn" type="button" role="tab"
                                                data-rs-tab-target="rs-tab-tableau" aria-controls="rs-tab-tableau"
                                                aria-selected="false">
                                                <i class="bi bi-table"></i>Tableau
                                            </button>
                                        </div>
                                        <?php if ($csvExportHtml !== ''): ?>
                                            <div class="rs-csv-export-wrap" id="rsCsvExportWrap">
                                                <?php echo $csvExportHtml; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-content rs-panel-scroll" id="rsMoisTabsContent">
                                <div class="tab-pane active" id="rs-tab-cartes" role="tabpanel"
                                    aria-labelledby="rs-tab-cartes-btn">
                                    <?php rs_render_month_cards($rows); ?>
                                </div>
                                <div class="tab-pane" id="rs-tab-tableau" role="tabpanel"
                                    aria-labelledby="rs-tab-tableau-btn">
                                    <?php rs_render_month_table($rows, $tot, $csv, $csvName); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$showCompteurs && count($rows) > 0): ?>
                <?php rs_render_charts_modal($stats); ?>
            <?php endif; ?>

        </div>
    </div>

    <script>
    (function () {
        var tabList = document.getElementById('rsMoisTabs');
        var tabContent = document.getElementById('rsMoisTabsContent');
        if (!tabList || !tabContent) return;
        function showRsTab(targetId, btn) {
            tabContent.querySelectorAll('.tab-pane').forEach(function (pane) {
                pane.classList.toggle('active', pane.id === targetId);
            });
            tabList.querySelectorAll('.rs-seg-btn').forEach(function (link) {
                var on = link === btn;
                link.classList.toggle('active', on);
                link.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        tabList.querySelectorAll('[data-rs-tab-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                showRsTab(this.getAttribute('data-rs-tab-target'), this);
            });
        });

        var chartsModal = document.getElementById('rsChartsModal');
        if (chartsModal) {
            chartsModal.addEventListener('shown.bs.modal', function () {
                if (typeof window.rsInitCharts_reseau_modal === 'function') {
                    if (!window._rsChartRegistry || !window._rsChartRegistry.reseau_modal) {
                        window.rsInitCharts_reseau_modal();
                    } else {
                        (window._rsChartRegistry.reseau_modal || []).forEach(function (c) { c.resize(); });
                    }
                }
            });
        }
    })();
    </script>

    <?php
}

function make_formulaire_reseau_colapse($reseau = null, $id_collapse = 'create_reseau_form_colapse')
{
    ?>
    <p class="d-inline-flex gap-1"></p>
    <?php
}

function make_formulaire_reseau($reseau = null)
{
    ?>
    <div class="input-group mb-3">
        <input type="text" required size="32" class="form-control" placeholder="Nom"
            value="<?php echo isset($nom) ? $nom : '' ?>" name="nom" aria-label="Nom">
    </div>
    <div class="input-group mb-3">
        <input type="text" class="form-control" placeholder="Abbreviation"
            value="<?php echo isset($PrixsemBS) ? $PrixsemBS : '' ?>" name="abreviation" aria-label="Abbreviation">
    </div>
    <div class="input-group mb-3">
        <input type="date" required class="form-control" id="date_creation" placeholder="Date de creation"
            value="<?php echo isset($date_creation) ? $date_creation : date('Y-m-d') ?>" name="date_creation">
    </div>
    <div class="input-group mb-3">
        <textarea class="form-control" name="description_reseau" placeholder="Décrivez le réseau"></textarea>
    </div>
    <?php
}

function displayReseauDetails(Reseau $reseau)
{
    if ($reseau == null) {
        return;
    }
}

function rs_resolve_selected_reseau($id_selected_reseau)
{
    if ($id_selected_reseau === 0 || $id_selected_reseau === '0') {
        return null;
    }
    $req = Reseau_t::getAllReseauFromAepId();
    foreach ($req as $line) {
        if ((int) $line['id'] === (int) $id_selected_reseau) {
            return new Reseau(
                $line['id'],
                $line['nom'],
                $line['abreviation'],
                $line['date_creation'],
                $line['description_reseau'],
                $line['id_aep']
            );
        }
    }
    return null;
}

function rs_render_reseau_selector($id_selected_reseau, $libeleAep)
{
    $req = Reseau_t::getAllReseauFromAepId();
    $labelAep = $libeleAep !== '' ? $libeleAep : 'Tout l\'AEP';
    $isGlobal = ($id_selected_reseau === 0 || $id_selected_reseau === '0');
    ?>
    <form method="get" action="" class="rs-reseau-selector-form" id="form_reseau_select">
        <input type="hidden" name="page" value="reseau">
        <?php if (isset($_GET['annee']) && $_GET['annee'] !== ''): ?>
            <input type="hidden" name="annee" value="<?php echo htmlspecialchars((string) $_GET['annee'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['mf_debut']) && $_GET['mf_debut'] !== ''): ?>
            <input type="hidden" name="mf_debut" value="<?php echo (int) $_GET['mf_debut']; ?>">
        <?php endif; ?>
        <?php if (isset($_GET['mf_fin']) && $_GET['mf_fin'] !== ''): ?>
            <input type="hidden" name="mf_fin" value="<?php echo (int) $_GET['mf_fin']; ?>">
        <?php endif; ?>
        <label for="rs_reseau_select" class="form-label mb-1">
            <i class="bi bi-diagram-3 me-1"></i>Réseau
        </label>
        <select name="id_reseau" id="rs_reseau_select" class="form-select form-select-sm"
            onchange="this.form.submit()" aria-label="Choisir un réseau">
            <option value="0"<?php echo $isGlobal ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($labelAep, ENT_QUOTES, 'UTF-8'); ?> — tous les réseaux
            </option>
            <?php foreach ($req as $line):
                $rid = (int) $line['id'];
                $sel = (!$isGlobal && (int) $id_selected_reseau === $rid) ? ' selected' : '';
                ?>
                <option value="<?php echo $rid; ?>"<?php echo $sel; ?>>
                    <?php echo htmlspecialchars($line['nom'], ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo htmlspecialchars($line['abreviation'], ENT_QUOTES, 'UTF-8'); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
}

function display_reseau_list($id_selected_reseau)
{
    return rs_resolve_selected_reseau($id_selected_reseau);
}
