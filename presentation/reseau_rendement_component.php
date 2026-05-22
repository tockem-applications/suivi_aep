<?php

/**
 * Affichage des rendements réseau (RDS / RDC).
 *
 * @param array $rendu sortie de ReseauRendement::calculer()
 * @param array $options reseau_nom, reseau_id (pour noms de fichiers export)
 */
function renderReseauRendementSection($rendu, $options = array())
{
    $typeDist = isset($rendu['type_distribution']) ? $rendu['type_distribution'] : null;
    $isRds = !empty($rendu['is_rds']);
    $isRdc = !empty($rendu['is_rdc']);
    $lignes = isset($rendu['lignes']) ? $rendu['lignes'] : array();
    $labels = isset($rendu['chart_labels']) ? $rendu['chart_labels'] : array();
    $series = isset($rendu['chart_series']) ? $rendu['chart_series'] : array();
    $exportSlug = rrd_export_slug(
        isset($options['reseau_nom']) ? $options['reseau_nom'] : 'reseau',
        isset($options['reseau_id']) ? $options['reseau_id'] : 0
    );

    $badgeType = 'secondary';
    $libelleType = 'Non défini';
    if ($isRds) {
        $badgeType = 'primary';
        $libelleType = 'RDS — Production → Réservoir → Distribution';
    } elseif ($isRdc) {
        $badgeType = 'info';
        $libelleType = 'RDC — Distribution et abonnés (sans production)';
    }
    ?>
    <style>
        .rrd-page .rrd-kpi {
            border: 1px solid #e8eaed;
            border-radius: 12px;
            padding: 1rem 1.1rem;
            background: #fff;
            height: 100%;
        }
        .rrd-page .rrd-kpi .rrd-kpi-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #5f6368;
            margin-bottom: 0.25rem;
        }
        .rrd-page .rrd-kpi .rrd-kpi-value {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .rrd-page .rrd-kpi .rrd-kpi-formula {
            font-size: 0.75rem;
            color: #80868b;
            margin-top: 0.35rem;
        }
        .rrd-page .rrd-section-title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #5f6368;
            margin-bottom: 0.75rem;
        }
        .rrd-page .rrd-pct-good { color: #137333; }
        .rrd-page .rrd-pct-warn { color: #b06000; }
        .rrd-page .rrd-pct-bad { color: #c5221f; }
        .rrd-page .rrd-pct-na { color: #9aa0a6; }
        .rrd-page .table-rendement thead th {
            font-size: 0.72rem;
            white-space: nowrap;
        }
        .rrd-page .rrd-flow-chain {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .rrd-page .rrd-flow-step {
            background: #e8f0fe;
            color: #1a73e8;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-weight: 600;
        }
        .rrd-page .rrd-flow-arrow { color: #5f6368; }

        /* Onglets rendements (classe dédiée — indépendante du menu) */
        .rrd-page ul.rrd-tabs-nav {
            display: flex;
            flex-wrap: wrap;
            list-style: none;
            margin: 0 0 0.75rem 0;
            padding: 0.5rem 0.5rem 0;
            border-bottom: 2px solid #0d6efd;
            background-color: #e9ecef;
            border-radius: 8px 8px 0 0;
            gap: 0.35rem;
        }
        .rrd-page ul.rrd-tabs-nav .nav-item {
            margin: 0;
        }
        .rrd-page button.rrd-tab-btn {
            display: inline-block;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.3;
            color: #212529 !important;
            -webkit-text-fill-color: #212529;
            padding: 0.6rem 1.15rem !important;
            border: 1px solid #adb5bd !important;
            border-bottom: 3px solid transparent !important;
            background-color: #dee2e6 !important;
            cursor: pointer;
            margin: 0;
            border-radius: 6px 6px 0 0;
            box-shadow: none;
        }
        .rrd-page button.rrd-tab-btn:hover {
            color: #0d47a1 !important;
            -webkit-text-fill-color: #0d47a1;
            background-color: #ced4da !important;
            border-bottom-color: #6ea8fe !important;
        }
        .rrd-page button.rrd-tab-btn.active {
            color: #fff !important;
            -webkit-text-fill-color: #fff;
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            border-bottom-color: #084298 !important;
            border-bottom-width: 3px !important;
        }
        .rrd-page .rrd-tab-content {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 1rem 1.1rem;
        }
        /* Pastille « ? » visible sur en-têtes bleus globaux (.table thead th) */
        .rrd-page .table thead th .rrd-th-help {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.15rem;
            height: 1.15rem;
            margin-left: 0.3rem;
            padding: 0;
            border-radius: 50%;
            background: #ffffff !important;
            color: #0d6efd !important;
            font-size: 0.7rem !important;
            font-weight: 800;
            line-height: 1;
            cursor: help;
            border: 1px solid rgba(255, 255, 255, 0.95);
            vertical-align: middle;
            font-family: system-ui, -apple-system, sans-serif !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
        }
        .rrd-page .table thead th .rrd-th-help:hover {
            background: #e8f0fe !important;
            color: #084298 !important;
        }
        .rrd-page .table thead th {
            white-space: nowrap;
            vertical-align: middle;
        }
        .rrd-page .table thead th .rrd-th-label {
            vertical-align: middle;
        }
    </style>

    <div class="rrd-page col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1">Rendements du réseau</h3>
                <span class="badge bg-<?php echo htmlspecialchars($badgeType, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($libelleType, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
        </div>

        <?php if ($isRdc && !empty($rendu['has_compteur_production'])): ?>
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                En <strong>RDC</strong>, les compteurs de <strong>production</strong> ne doivent pas exister sur ce réseau.
            </div>
        <?php endif; ?>

        <?php if ($isRds): ?>
            <div class="rrd-flow-chain">
                <span class="rrd-flow-step">Production</span>
                <span class="rrd-flow-arrow">→</span>
                <span class="rrd-flow-step">Réservoir</span>
                <span class="rrd-flow-arrow">→</span>
                <span class="rrd-flow-step">Distribution</span>
                <span class="rrd-flow-arrow">→</span>
                <span class="text-muted">Abonnés BP/BF · sous-réseaux</span>
            </div>
        <?php endif; ?>

        <?php
        $derniere = !empty($lignes) ? $lignes[0] : null;
        if ($derniere):
            ?>
            <div class="row g-3 mb-4">
                <?php if ($isRds): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="rrd-kpi">
                            <div class="rrd-kpi-label">Refoulement</div>
                            <div class="rrd-kpi-value <?php echo rrd_pct_class($derniere['pct_refoulement']); ?>">
                                <?php echo rrd_fmt_pct($derniere['pct_refoulement']); ?>
                            </div>
                            <div class="rrd-kpi-formula">Réservoir ÷ Production</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="rrd-kpi">
                            <div class="rrd-kpi-label">Adduction</div>
                            <div class="rrd-kpi-value <?php echo rrd_pct_class($derniere['pct_adduction']); ?>">
                                <?php echo rrd_fmt_pct($derniere['pct_adduction']); ?>
                            </div>
                            <div class="rrd-kpi-formula">Distribution ÷ Production</div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="col-md-6 col-lg-3">
                    <div class="rrd-kpi">
                        <div class="rrd-kpi-label">Global (fils directs)</div>
                        <div class="rrd-kpi-value <?php echo rrd_pct_class($derniere['pct_global_enfants']); ?>">
                            <?php echo rrd_fmt_pct($derniere['pct_global_enfants']); ?>
                        </div>
                        <div class="rrd-kpi-formula">(Dist. fils + BP/BF directs) ÷ Dist.</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="rrd-kpi">
                        <div class="rrd-kpi-label">Global (tous BP/BF)</div>
                        <div class="rrd-kpi-value <?php echo rrd_pct_class($derniere['pct_global_tous_abonnes']); ?>">
                            <?php echo rrd_fmt_pct($derniere['pct_global_tous_abonnes']); ?>
                        </div>
                        <div class="rrd-kpi-formula">(BP/BF directs + descendants) ÷ Dist.</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <strong>Volumes et rendements dans le temps</strong>
                    </div>
                    <div class="card-body">
                        <canvas id="reseauRendementChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <strong>Rendements distribution (%)</strong>
                    </div>
                    <div class="card-body">
                        <canvas id="reseauRendementPctChart" height="160"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs rrd-tabs-nav mb-0" id="rrdTabs" role="tablist">
            <?php if ($isRds): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rrd-tab-btn active" data-bs-toggle="tab" data-bs-target="#rrd-tab-rds" type="button">Chaîne RDS</button>
                </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link rrd-tab-btn <?php echo $isRds ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#rrd-tab-global" type="button">Globaux</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rrd-tab-btn" data-bs-toggle="tab" data-bs-target="#rrd-tab-net" type="button">Réseau net</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rrd-tab-btn" data-bs-toggle="tab" data-bs-target="#rrd-tab-branches" type="button">Branches locales</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rrd-tab-btn" data-bs-toggle="tab" data-bs-target="#rrd-tab-detail" type="button">Détail mensuel</button>
            </li>
        </ul>

        <div class="tab-content rrd-tab-content">
            <?php if ($isRds): ?>
                <div class="tab-pane fade show active" id="rrd-tab-rds" role="tabpanel">
                    <?php
                    rrd_render_export_btn(
                        rrd_build_csv_rds($lignes),
                        $exportSlug . '_rendement_chaine_rds.csv',
                        'Exporter le tableau chaîne RDS (volumes et rendements refoulement / adduction)'
                    );
                    rrd_render_table_rds($lignes);
                    ?>
                </div>
            <?php endif; ?>

            <div class="tab-pane fade <?php echo $isRds ? '' : 'show active'; ?>" id="rrd-tab-global" role="tabpanel">
                <p class="small text-muted mb-3">
                    Comparaison du compteur de <strong>distribution</strong> du réseau (amont) avec les consommations aval
                    (compteurs distribution des fils directs + abonnés BP/BF sur ce réseau, ou l’ensemble des BP/BF du patrimoine).
                </p>
                <?php
                rrd_render_export_btn(
                    rrd_build_csv_global($lignes),
                    $exportSlug . '_rendement_globaux.csv',
                    'Exporter le tableau des rendements globaux'
                );
                rrd_render_table_global($lignes, $isRds);
                ?>
            </div>

            <div class="tab-pane fade" id="rrd-tab-net" role="tabpanel">
                <p class="small text-muted mb-3">
                    <strong>Réseau net (direct)</strong> : abonnements BP/BF directement sur ce réseau, rapportés à la distribution
                    du réseau <em>moins</em> les compteurs de distribution des sous-réseaux fils directs.
                </p>
                <?php
                rrd_render_export_btn(
                    rrd_build_csv_net($lignes),
                    $exportSlug . '_rendement_reseau_net.csv',
                    'Exporter le tableau réseau net (direct)'
                );
                rrd_render_table_net($lignes);
                ?>
            </div>

            <div class="tab-pane fade" id="rrd-tab-branches" role="tabpanel">
                <p class="small text-muted mb-3">
                    Un rendement par <strong>branche</strong> (chaque réseau fils direct) : entrée = compteur distribution du fils ;
                    aval = abonnés de la branche + distribution des petits-enfants.
                </p>
                <?php
                rrd_render_export_btn(
                    rrd_build_csv_branches_all($lignes, isset($rendu['enfants_directs']) ? $rendu['enfants_directs'] : array()),
                    $exportSlug . '_rendement_branches_toutes.csv',
                    'Exporter toutes les branches locales dans un seul fichier'
                );
                rrd_render_branches($lignes, isset($rendu['enfants_directs']) ? $rendu['enfants_directs'] : array(), $exportSlug);
                ?>
            </div>

            <div class="tab-pane fade" id="rrd-tab-detail" role="tabpanel">
                <?php
                rrd_render_export_btn(
                    rrd_build_csv_detail($lignes, $isRds),
                    $exportSlug . '_rendement_detail_mensuel.csv',
                    'Exporter le détail mensuel des volumes'
                );
                rrd_render_table_detail($lignes, $isRds);
                ?>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var labels = <?php echo json_encode($labels); ?>;
        var series = <?php echo json_encode($series); ?>;
        var isRds = <?php echo $isRds ? 'true' : 'false'; ?>;

        function monthLabel(ym) {
            if (!ym || ym.length < 7) return ym;
            var p = ym.split('-');
            var months = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
            return (months[parseInt(p[1], 10) - 1] || p[1]) + ' ' + p[0];
        }
        var lbl = labels.map(monthLabel);

        var elVol = document.getElementById('reseauRendementChart');
        if (elVol && typeof Chart !== 'undefined') {
            var ds = [
                { label: 'Distribution réseau (m³)', data: series.vol_distribution, backgroundColor: 'rgba(26,115,232,0.45)', stack: 'v' },
                { label: 'BP/BF directs (m³)', data: series.vol_abonnes_direct, backgroundColor: 'rgba(52,168,83,0.45)', stack: 'v2' },
                { label: 'Dist. fils directs (m³)', data: series.vol_dist_enfants, backgroundColor: 'rgba(251,188,5,0.5)', stack: 'v2' }
            ];
            if (isRds) {
                ds.unshift({ label: 'Production (m³)', data: series.vol_production || [], backgroundColor: 'rgba(234,67,53,0.35)' });
                ds.splice(1, 0, { label: 'Réservoir (m³)', data: series.vol_reservoir || [], backgroundColor: 'rgba(66,133,244,0.35)' });
            }
            new Chart(elVol.getContext('2d'), {
                type: 'bar',
                data: { labels: lbl, datasets: ds },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'm³' } } }
                }
            });
        }

        var elPct = document.getElementById('reseauRendementPctChart');
        if (elPct && typeof Chart !== 'undefined') {
            var dsPct = [
                { label: 'Global fils directs (%)', data: series.pct_global_enfants, borderColor: '#1a73e8', tension: 0.25, spanGaps: true },
                { label: 'Global tous BP/BF (%)', data: series.pct_global_tous_abonnes, borderColor: '#34a853', tension: 0.25, spanGaps: true },
                { label: 'Net direct (%)', data: series.pct_net_direct, borderColor: '#f9ab00', tension: 0.25, spanGaps: true }
            ];
            if (isRds) {
                dsPct.unshift(
                    { label: 'Refoulement (%)', data: series.pct_refoulement, borderColor: '#ea4335', tension: 0.25, spanGaps: true },
                    { label: 'Adduction (%)', data: series.pct_adduction, borderColor: '#4285f4', tension: 0.25, spanGaps: true }
                );
            }
            new Chart(elPct.getContext('2d'), {
                type: 'line',
                data: { labels: lbl, datasets: dsPct },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { beginAtZero: true, suggestedMax: 120, title: { display: true, text: '%' } }
                    }
                }
            });
        }

        if (typeof bootstrap !== 'undefined') {
            document.querySelectorAll('.rrd-page [data-bs-toggle="tooltip"]').forEach(function (el) {
                if (bootstrap.Tooltip.getInstance(el)) {
                    return;
                }
                new bootstrap.Tooltip(el, {
                    container: 'body',
                    delay: { show: 150, hide: 0 },
                    trigger: 'hover focus'
                });
            });
        }
    })();
    </script>
    <?php
}

/**
 * En-tête de colonne avec infobulle d'aide.
 *
 * @param string $label Libellé visible
 * @param string $help Texte de l'infobulle
 * @param string $class Classes CSS additionnelles (ex. text-end)
 */
function rrd_th($label, $help, $class = '')
{
    $classAttr = trim($class) !== '' ? ' class="' . htmlspecialchars(trim($class), ENT_QUOTES, 'UTF-8') . '"' : '';
    $lbl = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($help, ENT_QUOTES, 'UTF-8');
    echo '<th' . $classAttr . '>';
    echo '<span class="rrd-th-label">' . $lbl . '</span>';
    echo '<span class="rrd-th-help" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . $title . '" title="' . $title . '" tabindex="0" role="button" aria-label="Aide : ' . $lbl . '">?</span>';
    echo '</th>';
}

function rrd_fmt_pct($pct)
{
    if ($pct === null || $pct === '') {
        return '—';
    }
    return number_format((float) $pct, 2, ',', ' ') . ' %';
}

function rrd_fmt_vol($v)
{
    return number_format((float) $v, 2, ',', ' ');
}

function rrd_pct_class($pct)
{
    if ($pct === null || $pct === '') {
        return 'rrd-pct-na';
    }
    $p = (float) $pct;
    if ($p >= 85) {
        return 'rrd-pct-good';
    }
    if ($p >= 70) {
        return 'rrd-pct-warn';
    }
    return 'rrd-pct-bad';
}

function rrd_mois_label($mois)
{
    if (function_exists('getLetterMonth')) {
        return htmlspecialchars(getLetterMonth($mois), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($mois, ENT_QUOTES, 'UTF-8');
}

function rrd_csv_mois($mois)
{
    if (function_exists('getLetterMonth')) {
        return getLetterMonth($mois);
    }
    return $mois;
}

function rrd_csv_num($v)
{
    return round((float) $v, 2);
}

function rrd_csv_pct($pct)
{
    if ($pct === null || $pct === '') {
        return '';
    }
    return round((float) $pct, 2);
}

function rrd_export_slug($reseauNom, $reseauId)
{
    $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim((string) $reseauNom));
    if ($slug === '') {
        $slug = 'reseau';
    }
    $id = (int) $reseauId;
    if ($id > 0) {
        $slug .= '_' . $id;
    }
    return $slug;
}

function rrd_render_export_btn($data, $filename, $tooltip, $wrapperClass = 'd-flex justify-content-end mb-2')
{
    if (empty($data) || !function_exists('create_csv_exportation_button')) {
        return;
    }
    echo '<div class="' . htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8') . '">';
    create_csv_exportation_button(
        $data,
        $filename,
        $tooltip,
        'btn btn-outline-secondary btn-sm',
        'Exporter CSV'
    );
    echo '</div>';
}

function rrd_build_csv_rds($lignes)
{
    $out = array();
    foreach ($lignes as $row) {
        $out[] = array(
            'Mois' => rrd_csv_mois($row['mois']),
            'Production_m3' => rrd_csv_num($row['vol_production']),
            'Reservoir_m3' => rrd_csv_num($row['vol_reservoir']),
            'Distribution_m3' => rrd_csv_num($row['vol_distribution']),
            'Refoulement_pct' => rrd_csv_pct($row['pct_refoulement']),
            'Adduction_pct' => rrd_csv_pct($row['pct_adduction']),
        );
    }
    return $out;
}

function rrd_build_csv_global($lignes)
{
    $out = array();
    foreach ($lignes as $row) {
        $out[] = array(
            'Mois' => rrd_csv_mois($row['mois']),
            'Distribution_reseau_m3' => rrd_csv_num($row['vol_distribution']),
            'Distribution_fils_m3' => rrd_csv_num($row['vol_dist_enfants_direct']),
            'BP_BF_directs_m3' => rrd_csv_num($row['vol_abonnes_direct']),
            'BP_BF_descendants_m3' => rrd_csv_num($row['vol_abonnes_descendants']),
            'Aval_fils_et_direct_m3' => rrd_csv_num($row['aval_enfants_direct']),
            'Aval_tous_abonnes_m3' => rrd_csv_num($row['aval_tous_abonnes']),
            'Rendement_fils_directs_pct' => rrd_csv_pct($row['pct_global_enfants']),
            'Rendement_tous_abonnes_pct' => rrd_csv_pct($row['pct_global_tous_abonnes']),
        );
    }
    return $out;
}

function rrd_build_csv_net($lignes)
{
    $out = array();
    foreach ($lignes as $row) {
        $out[] = array(
            'Mois' => rrd_csv_mois($row['mois']),
            'Distribution_reseau_m3' => rrd_csv_num($row['vol_distribution']),
            'Distribution_fils_m3' => rrd_csv_num($row['vol_dist_enfants_direct']),
            'Amont_net_m3' => rrd_csv_num($row['amont_net_direct']),
            'BP_BF_directs_m3' => rrd_csv_num($row['vol_abonnes_direct']),
            'Rendement_net_pct' => rrd_csv_pct($row['pct_net_direct']),
        );
    }
    return $out;
}

function rrd_build_csv_detail($lignes, $isRds)
{
    $out = array();
    foreach ($lignes as $row) {
        $line = array(
            'Mois' => rrd_csv_mois($row['mois']),
        );
        if ($isRds) {
            $line['Production_m3'] = rrd_csv_num($row['vol_production']);
            $line['Reservoir_m3'] = rrd_csv_num($row['vol_reservoir']);
        }
        $line['Distribution_m3'] = rrd_csv_num($row['vol_distribution']);
        $line['BP_m3'] = rrd_csv_num($row['vol_abonnes_bp']);
        $line['BF_m3'] = rrd_csv_num($row['vol_abonnes_bf']);
        $line['Distribution_fils_m3'] = rrd_csv_num($row['vol_dist_enfants_direct']);
        $line['BP_BF_descendants_m3'] = rrd_csv_num($row['vol_abonnes_descendants']);
        $out[] = $line;
    }
    return $out;
}

function rrd_build_csv_branches_all($lignes, $enfantsDirects)
{
    $out = array();
    foreach ($enfantsDirects as $enfant) {
        $eid = (int) $enfant['id'];
        $nom = isset($enfant['nom']) ? $enfant['nom'] : '';
        foreach ($lignes as $row) {
            foreach ($row['branches'] as $br) {
                if ((int) $br['id'] !== $eid) {
                    continue;
                }
                $out[] = array(
                    'Branche' => $nom,
                    'Mois' => rrd_csv_mois($row['mois']),
                    'Distribution_branche_m3' => rrd_csv_num($br['vol_dist']),
                    'Abonnes_branche_m3' => rrd_csv_num($br['vol_abonnes']),
                    'Distribution_petits_fils_m3' => rrd_csv_num($br['vol_dist_enfants']),
                    'Aval_m3' => rrd_csv_num($br['aval']),
                    'Rendement_pct' => rrd_csv_pct($br['pct']),
                );
            }
        }
    }
    return $out;
}

function rrd_build_csv_branch_one($lignes, $enfantId, $enfantNom)
{
    $out = array();
    $eid = (int) $enfantId;
    foreach ($lignes as $row) {
        foreach ($row['branches'] as $br) {
            if ((int) $br['id'] !== $eid) {
                continue;
            }
            $out[] = array(
                'Branche' => $enfantNom,
                'Mois' => rrd_csv_mois($row['mois']),
                'Distribution_branche_m3' => rrd_csv_num($br['vol_dist']),
                'Abonnes_branche_m3' => rrd_csv_num($br['vol_abonnes']),
                'Distribution_petits_fils_m3' => rrd_csv_num($br['vol_dist_enfants']),
                'Aval_m3' => rrd_csv_num($br['aval']),
                'Rendement_pct' => rrd_csv_pct($br['pct']),
            );
        }
    }
    return $out;
}

function rrd_render_table_rds($lignes)
{
    ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover table-rendement align-middle">
            <thead class="table-light">
                <tr>
                    <?php
                    rrd_th('Mois', 'Mois de facturation (période des relevés d\'index).');
                    rrd_th('Prod. (m³)', 'Volume mensuel des compteurs de type production installés sur ce réseau.', 'text-end');
                    rrd_th('Rés. (m³)', 'Volume mensuel des compteurs de type réservoir sur ce réseau.', 'text-end');
                    rrd_th('Dist. (m³)', 'Volume mensuel des compteurs de type distribution sur ce réseau.', 'text-end');
                    rrd_th('Refoulement', 'Rendement refoulement = Réservoir ÷ Production × 100. Mesure le transfert production → réservoir (chaîne RDS).', 'text-end');
                    rrd_th('Adduction', 'Rendement adduction = Distribution ÷ Production × 100. Part de la production qui atteint le réseau de distribution.', 'text-end');
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                    <tr><td colspan="6" class="text-muted text-center">Aucune donnée</td></tr>
                <?php else: ?>
                    <?php foreach ($lignes as $row): ?>
                        <tr>
                            <td><?php echo rrd_mois_label($row['mois']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_production']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_reservoir']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_distribution']); ?></td>
                            <td class="text-end fw-semibold <?php echo rrd_pct_class($row['pct_refoulement']); ?>"><?php echo rrd_fmt_pct($row['pct_refoulement']); ?></td>
                            <td class="text-end fw-semibold <?php echo rrd_pct_class($row['pct_adduction']); ?>"><?php echo rrd_fmt_pct($row['pct_adduction']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function rrd_render_table_global($lignes, $isRds)
{
    ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover table-rendement align-middle">
            <thead class="table-light">
                <tr>
                    <?php
                    rrd_th('Mois', 'Mois de facturation.');
                    rrd_th('Dist. réseau', 'Amont : somme des index du (des) compteur(s) de distribution sur ce réseau.', 'text-end');
                    rrd_th('Dist. fils', 'Volume des compteurs de distribution des réseaux fils directs (sous-réseaux de 1er niveau).', 'text-end');
                    rrd_th('BP/BF directs', 'Consommation des abonnés BP et BF rattachés directement à ce réseau (pas aux sous-réseaux).', 'text-end');
                    rrd_th('BP/BF descendants', 'Consommation BP/BF sur toute la descendance (sous-réseaux et leurs abonnés).', 'text-end');
                    rrd_th('Aval (fils+direct)', 'Aval pour rendement « fils directs » = Dist. fils + BP/BF directs.', 'text-end');
                    rrd_th('Aval (tous ab.)', 'Aval élargi = BP/BF directs + BP/BF descendants (tous les abonnés du patrimoine sous ce réseau).', 'text-end');
                    rrd_th('Rend. fils', 'Rendement global fils directs = Aval (fils+direct) ÷ Dist. réseau × 100.', 'text-end');
                    rrd_th('Rend. tous', 'Rendement global tous abonnés = Aval (tous ab.) ÷ Dist. réseau × 100.', 'text-end');
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                    <tr><td colspan="9" class="text-muted text-center">Aucune donnée</td></tr>
                <?php else: ?>
                    <?php foreach ($lignes as $row): ?>
                        <tr>
                            <td><?php echo rrd_mois_label($row['mois']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_distribution']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_dist_enfants_direct']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_abonnes_direct']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_abonnes_descendants']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['aval_enfants_direct']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['aval_tous_abonnes']); ?></td>
                            <td class="text-end fw-semibold <?php echo rrd_pct_class($row['pct_global_enfants']); ?>"><?php echo rrd_fmt_pct($row['pct_global_enfants']); ?></td>
                            <td class="text-end fw-semibold <?php echo rrd_pct_class($row['pct_global_tous_abonnes']); ?>"><?php echo rrd_fmt_pct($row['pct_global_tous_abonnes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function rrd_render_table_net($lignes)
{
    ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover table-rendement align-middle">
            <thead class="table-light">
                <tr>
                    <?php
                    rrd_th('Mois', 'Mois de facturation.');
                    rrd_th('Dist. réseau', 'Compteur(s) de distribution du réseau (volume brut avant retrait des sous-réseaux).', 'text-end');
                    rrd_th('− Dist. fils', 'Part du volume distribution évacuée vers les réseaux fils directs (soustraite de l\'amont).', 'text-end');
                    rrd_th('= Amont net', 'Amont net direct = Dist. réseau − Dist. fils. Volume « restant » sur le tronçon du réseau hors branches filles.', 'text-end');
                    rrd_th('BP/BF directs', 'Consommation des abonnés BP/BF directement sur ce réseau (aval du tronçon net).', 'text-end');
                    rrd_th('Rend. net', 'Rendement réseau net = BP/BF directs ÷ Amont net × 100.', 'text-end');
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                    <tr><td colspan="6" class="text-muted text-center">Aucune donnée</td></tr>
                <?php else: ?>
                    <?php foreach ($lignes as $row): ?>
                        <tr>
                            <td><?php echo rrd_mois_label($row['mois']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_distribution']); ?></td>
                            <td class="text-end text-danger">− <?php echo rrd_fmt_vol($row['vol_dist_enfants_direct']); ?></td>
                            <td class="text-end fw-semibold"><?php echo rrd_fmt_vol($row['amont_net_direct']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_abonnes_direct']); ?></td>
                            <td class="text-end fw-semibold <?php echo rrd_pct_class($row['pct_net_direct']); ?>"><?php echo rrd_fmt_pct($row['pct_net_direct']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function rrd_render_branches($lignes, $enfantsDirects, $exportSlug = 'reseau')
{
    if (empty($enfantsDirects)) {
        echo '<p class="text-muted">Aucun réseau fils direct pour ce réseau.</p>';
        return;
    }
    foreach ($enfantsDirects as $idx => $enfant) {
        $eid = (int) $enfant['id'];
        $nomEnfant = isset($enfant['nom']) ? $enfant['nom'] : 'branche';
        $slugBranche = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $nomEnfant);
        ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <strong><?php echo htmlspecialchars($nomEnfant, ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php
                rrd_render_export_btn(
                    rrd_build_csv_branch_one($lignes, $eid, $nomEnfant),
                    $exportSlug . '_rendement_branche_' . $slugBranche . '.csv',
                    'Exporter cette branche : ' . $nomEnfant,
                    'mb-0'
                );
                ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <?php
                                rrd_th('Mois', 'Mois de facturation.');
                                rrd_th('Dist. branche', 'Entrée de branche : compteur de distribution du réseau fils direct concerné.', 'text-end');
                                rrd_th('Abonnés branche', 'Consommation BP/BF sur ce fils et toute sa descendance (arborescence sous la branche).', 'text-end');
                                rrd_th('Dist. petits-fils', 'Volume des compteurs de distribution des petits-enfants (réseaux sous le fils direct).', 'text-end');
                                rrd_th('Aval', 'Aval de branche = Abonnés branche + Dist. petits-fils.', 'text-end');
                                rrd_th('Rendement', 'Rendement de branche = Aval ÷ Dist. branche × 100.', 'text-end');
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $has = false;
                            foreach ($lignes as $row) {
                                foreach ($row['branches'] as $br) {
                                    if ((int) $br['id'] !== $eid) {
                                        continue;
                                    }
                                    $has = true;
                                    ?>
                                    <tr>
                                        <td><?php echo rrd_mois_label($row['mois']); ?></td>
                                        <td class="text-end"><?php echo rrd_fmt_vol($br['vol_dist']); ?></td>
                                        <td class="text-end"><?php echo rrd_fmt_vol($br['vol_abonnes']); ?></td>
                                        <td class="text-end"><?php echo rrd_fmt_vol($br['vol_dist_enfants']); ?></td>
                                        <td class="text-end"><?php echo rrd_fmt_vol($br['aval']); ?></td>
                                        <td class="text-end fw-semibold <?php echo rrd_pct_class($br['pct']); ?>"><?php echo rrd_fmt_pct($br['pct']); ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                            if (!$has) {
                                echo '<tr><td colspan="6" class="text-muted text-center">Aucune donnée</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

function rrd_render_table_detail($lignes, $isRds)
{
    $colspan = $isRds ? 8 : 6;
    ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover table-rendement align-middle">
            <thead class="table-light">
                <tr>
                    <?php
                    rrd_th('Mois', 'Mois de facturation.');
                    if ($isRds) {
                        rrd_th('Prod.', 'Volume production (m³) — compteurs production du réseau.', 'text-end');
                        rrd_th('Rés.', 'Volume réservoir (m³).', 'text-end');
                    }
                    rrd_th('Dist.', 'Volume distribution (m³) du réseau.', 'text-end');
                    rrd_th('BP', 'Consommation des abonnés Borne publique (BP) sur ce réseau.', 'text-end');
                    rrd_th('BF', 'Consommation des abonnés Borne fontaine (BF) sur ce réseau.', 'text-end');
                    rrd_th('Dist.fils', 'Distribution des réseaux fils directs.', 'text-end');
                    rrd_th('BP/BF desc.', 'Consommation BP/BF sur l\'ensemble des sous-réseaux (descendants).', 'text-end');
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                    <tr><td colspan="<?php echo (int) $colspan; ?>" class="text-muted text-center">Aucune donnée</td></tr>
                <?php else: ?>
                    <?php foreach ($lignes as $row): ?>
                        <tr>
                            <td><?php echo rrd_mois_label($row['mois']); ?></td>
                            <?php if ($isRds): ?>
                                <td class="text-end"><?php echo rrd_fmt_vol($row['vol_production']); ?></td>
                                <td class="text-end"><?php echo rrd_fmt_vol($row['vol_reservoir']); ?></td>
                            <?php endif; ?>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_distribution']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_abonnes_bp']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_abonnes_bf']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_dist_enfants_direct']); ?></td>
                            <td class="text-end"><?php echo rrd_fmt_vol($row['vol_abonnes_descendants']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
