<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");
@include_once("../donnees/categorie_flux_manuel.php");
@include_once("donnees/categorie_flux_manuel.php");
@include_once("../donnees/cout_service_charge.php");
@include_once("donnees/cout_service_charge.php");

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

CoutServiceCharge::ensureTable();

// Paramètres de période
$mois_debut = isset($_GET['mois_debut']) ? $_GET['mois_debut'] : date('Y-m', strtotime('-12 months'));
$mois_fin = isset($_GET['mois_fin']) ? $_GET['mois_fin'] : date('Y-m');

// Fonction pour calculer les données du graphique.
// Les charges prises en compte dans le coût du service sont une sélection éditable
// par mois (catégories de flux + redevances), avec report automatique d'un mois au
// suivant — voir la classe CoutServiceCharge.
function getDonneesGraphiqueCoutRecouvrement($id_aep, $mois_debut, $mois_fin)
{
    $donnees = array();
    $mois_list = CoutServiceCharge::getMoisListPeriode($id_aep, $mois_debut, $mois_fin);

    foreach ($mois_list as $mois) {
        $selection = CoutServiceCharge::getSelectionEffective($id_aep, $mois);
        $cout = CoutServiceCharge::calculerCoutMois($id_aep, $mois, $selection);
        $details_categories = CoutServiceCharge::calculerDetailDepenses($id_aep, $mois, $selection);

        $donnees[] = array(
            'mois' => $mois,
            'total_depenses' => $cout['total_depenses'],
            'total_conso' => $cout['total_conso'],
            'cout_par_m3' => $cout['cout_par_m3'],
            'prix_moyen_m3' => $cout['prix_moyen_m3'],
            'details_categories' => $details_categories,
            'herite_de' => $selection['herite_de'],
        );
    }

    return $donnees;
}

$donnees_graphique = getDonneesGraphiqueCoutRecouvrement($id_aep, $mois_debut, $mois_fin);

// Préparer les données pour Chart.js
$chart_labels = array();
$chart_cout_par_m3 = array();
$chart_prix_moyen_m3 = array();

$mois_options = array();
$mois_labels_map = array();
foreach ($donnees_graphique as $data) {
    $label_mois = date('M Y', strtotime($data['mois'] . '-01'));
    $chart_labels[] = $label_mois;
    $chart_cout_par_m3[] = round($data['cout_par_m3'], 2);
    $chart_prix_moyen_m3[] = round($data['prix_moyen_m3'], 2);
    $mois_options[] = array('mois' => $data['mois'], 'label' => $label_mois);
    $mois_labels_map[$data['mois']] = $label_mois;
}
// Listes déroulantes : mois le plus récent en premier (et sélectionné par défaut).
$mois_options_desc = array_reverse($mois_options);

// Agrégats de la période : affichés en indicateurs de tête, et repris tels quels
// dans le pied du tableau de détail (calculés une seule fois).
$total_depenses_global = 0;
$total_conso_global = 0;
$somme_prix_moyens = 0;
foreach ($donnees_graphique as $data) {
    $total_depenses_global += $data['total_depenses'];
    $total_conso_global += $data['total_conso'];
    $somme_prix_moyens += $data['prix_moyen_m3'];
}
$nb_mois_periode = count($donnees_graphique);
$cout_moyen = $total_conso_global > 0 ? ($total_depenses_global / $total_conso_global) : 0;
$prix_moyen_global = $nb_mois_periode > 0 ? ($somme_prix_moyens / $nb_mois_periode) : 0;
$ecart_moyen = $cout_moyen - $prix_moyen_global;
$ratio_moyen = $prix_moyen_global > 0 ? (($cout_moyen / $prix_moyen_global) * 100) : 0;
?>
<style>
    .af-page {
        --af-accent: #2c9D11;
        --af-bg: #f4f6f8;
        --af-surface: #ffffff;
        --af-border: #e3e8ee;
        --af-text: #1f2933;
        --af-muted: #6b7684;
        background: var(--af-bg);
        color: var(--af-text);
        padding: 16px;
        min-height: calc(100vh - 90px);
    }

    .af-topbar {
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        background: var(--af-surface); border: 1px solid var(--af-border);
        border-radius: 12px; padding: 12px 16px; margin-bottom: 12px;
    }
    .af-topbar h1 { font-size: 1.1rem; font-weight: 650; margin: 0; letter-spacing: -0.01em; }
    .af-chip {
        display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;
        background: rgba(44,157,17,.1); color: #216d0d; padding: 3px 10px; border-radius: 999px;
    }

    .af-card { background: var(--af-surface); border: 1px solid var(--af-border); border-radius: 12px; margin-bottom: 12px; }
    .af-card-head {
        padding: 12px 16px; border-bottom: 1px solid var(--af-border);
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .af-card-head h2 { font-size: .95rem; font-weight: 650; margin: 0; }
    .af-card-body { padding: 16px; }

    /* --- Indicateurs --- */
    .af-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .af-kpi { background: var(--af-surface); border: 1px solid var(--af-border); border-radius: 12px; padding: 14px 16px; }
    .af-kpi .lbl {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
        color: var(--af-muted); display: block; margin-bottom: 4px;
    }
    .af-kpi .val { font-size: 1.35rem; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .af-kpi .unit { font-size: .8rem; font-weight: 500; color: var(--af-muted); }
    .af-kpi .sub { font-size: 11px; color: var(--af-muted); display: block; margin-top: 3px; }
    .af-val-danger { color: #c62828; }
    .af-val-success { color: #2e7d32; }

    /* --- Barre de simulation --- */
    .af-sim {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        background: var(--af-bg); border-radius: 10px; padding: 10px 12px; margin-bottom: 14px;
    }
    .af-sim .form-select, .af-sim .form-control { font-size: 13px; border-radius: 8px; border-color: var(--af-border); }
    .af-sim-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--af-muted); }

    .af-hint { font-size: 12.5px; color: var(--af-muted); }
    .mois-row:hover { background-color: #f0f4f8 !important; }
    .mois-row td { user-select: none; }
    .af-table thead th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
</style>

<div class="af-page">

    <div class="af-topbar">
        <div>
            <h1>Analyse financière</h1>
            <div class="d-flex align-items-center gap-2 mt-1">
                <span class="af-chip"><i class="bi bi-droplet-fill"></i><?php echo htmlspecialchars(isset($_SESSION['libele_aep']) ? $_SESSION['libele_aep'] : '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="af-chip" style="background:#eef1f5;color:#4a5560;">
                    <i class="bi bi-calendar3"></i><?php echo $nb_mois_periode; ?> mois analysé<?php echo $nb_mois_periode > 1 ? 's' : ''; ?>
                </span>
            </div>
        </div>

        <form method="get" action="" class="ms-auto d-flex align-items-end gap-2">
            <input type="hidden" name="page" value="analyse_financiere">
            <div>
                <label for="mois_debut_select" class="form-label small text-muted mb-1">Du</label>
                <input type="month" id="mois_debut_select" name="mois_debut" class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($mois_debut); ?>" onchange="this.form.submit()">
            </div>
            <div>
                <label for="mois_fin_select" class="form-label small text-muted mb-1">Au</label>
                <input type="month" id="mois_fin_select" name="mois_fin" class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($mois_fin); ?>" onchange="this.form.submit()">
            </div>
        </form>

        <!-- Bouton de configuration des charges, masqué pour le moment :
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
            data-bs-target="#chargesCoutServiceModal">
            <i class="bi bi-sliders me-1"></i>Configurer les charges
        </button>
        -->
        <?php /* le bouton est retiré, le reste de la logique (modale, JS) est intact */ ?>
    </div>

    <!-- Indicateurs de la période : auparavant enfouis dans le pied du tableau de détail -->
    <div class="af-kpis">
        <div class="af-kpi">
            <span class="lbl">Coût du service</span>
            <span class="val"><?php echo number_format($cout_moyen, 2, ',', ' '); ?> <span class="unit">FCFA/m³</span></span>
            <span class="sub"><?php echo number_format($total_depenses_global, 0, ',', ' '); ?> FCFA de charges</span>
        </div>
        <div class="af-kpi">
            <span class="lbl">Prix moyen facturé</span>
            <span class="val"><?php echo number_format($prix_moyen_global, 2, ',', ' '); ?> <span class="unit">FCFA/m³</span></span>
            <span class="sub"><?php echo number_format($total_conso_global, 0, ',', ' '); ?> m³ consommés</span>
        </div>
        <div class="af-kpi">
            <span class="lbl">Écart</span>
            <span class="val <?php echo $ecart_moyen > 0 ? 'af-val-danger' : ($ecart_moyen < 0 ? 'af-val-success' : ''); ?>">
                <?php echo ($ecart_moyen > 0 ? '+' : '') . number_format($ecart_moyen, 2, ',', ' '); ?> <span class="unit">FCFA/m³</span>
            </span>
            <span class="sub"><?php echo $ecart_moyen > 0 ? 'Le service coûte plus qu\'il ne rapporte' : 'Le prix couvre le coût'; ?></span>
        </div>
        <div class="af-kpi">
            <span class="lbl">Ratio coût / prix</span>
            <span class="val <?php echo $ratio_moyen > 100 ? 'af-val-danger' : ($ratio_moyen < 100 ? 'af-val-success' : ''); ?>">
                <?php echo number_format($ratio_moyen, 1, ',', ' '); ?> <span class="unit">%</span>
            </span>
            <span class="sub">100 % = équilibre</span>
        </div>
    </div>

    <div class="af-card">
        <div class="af-card-head">
            <h2><i class="bi bi-graph-up me-2"></i>Coût du service par m³ vs prix moyen facturé</h2>
            <button type="button" class="btn btn-light btn-sm border ms-auto" data-bs-toggle="collapse"
                data-bs-target="#afDetails" aria-expanded="false">
                <i class="bi bi-table me-1"></i>Détail mois par mois
            </button>
        </div>
        <div class="af-card-body">
            <p class="af-hint mb-3">
                Le coût par m³ est la somme des charges <strong>sélectionnées pour chaque mois</strong> divisée par le
                volume consommé. Ces charges se configurent mois par mois et sont reportées automatiquement d'un mois
                sur le suivant tant qu'on ne les modifie pas.
            </p>

            <?php /* Barre de simulation, masquée pour le moment. Les identifiants
                     (cscSimBtn, cscSimMoisSource, ...) restent référencés par le
                     JS plus bas ; celui-ci se contente de ne rien trouver via
                     document.getElementById et de ne rien faire. */ ?>
            <!--
            <div class="af-sim">
                <span class="af-sim-label"><i class="bi bi-beaker me-1"></i>Simulation</span>
                <select id="cscSimMoisSource" class="form-select form-select-sm" style="width: auto;">
                    <?php foreach ($mois_options_desc as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt['mois']); ?>"><?php echo htmlspecialchars($opt['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-success btn-sm" id="cscSimBtn">Appliquer à tous les mois</button>
                <button type="button" class="btn btn-light btn-sm border" id="cscSimResetBtn" style="display:none;">Réinitialiser</button>
                <div class="form-check form-switch mb-0" id="cscSimHideRealWrap" style="display:none;">
                    <input class="form-check-input" type="checkbox" id="cscSimHideReal">
                    <label class="form-check-label small" for="cscSimHideReal">Masquer la courbe réelle</label>
                </div>
                <span class="small text-muted ms-auto" id="cscSimStatus">Aperçu uniquement — rien n'est enregistré.</span>
            </div>
            -->

            <canvas id="coutRecouvrementChart" style="max-height: 380px;"></canvas>
        </div>
    </div>

<!-- Modal : configurer les charges prises en compte pour un mois -->
<div class="modal fade" id="chargesCoutServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-sliders me-2"></i>Charges prises en compte dans le coût du service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <label for="cscMois" class="form-label mb-0">Mois :</label>
                    <select id="cscMois" class="form-select form-select-sm" style="width: auto;">
                        <?php foreach ($mois_options_desc as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt['mois']); ?>"><?php echo htmlspecialchars($opt['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-info small py-2" id="cscHeriteInfo" style="display:none;"></div>
                <div id="cscLoading" class="text-muted small">Chargement…</div>
                <div id="cscForm" style="display:none;">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="cscSansCategorie">
                        <label class="form-check-label" for="cscSansCategorie">Sorties sans catégorie</label>
                    </div>
                    <h6 class="text-muted mt-3">Catégories de charges</h6>
                    <div id="cscCategories" class="row mb-3"></div>
                    <h6 class="text-muted">Redevances</h6>
                    <div id="cscRedevances" class="row row-cols-1 row-cols-md-2 g-1"></div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto" id="cscSaveStatus"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="cscSaveBtn">Enregistrer pour ce mois</button>
            </div>
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

        window._afChart = new Chart(ctx, {
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

<script>
(function () {
    var cscCsrfToken = <?php echo json_encode(Csrf::token()); ?>;
    var cscAjaxUrl = 'traitement/cout_service_charge_t.php';
    var cscMoisDebut = <?php echo json_encode($mois_debut); ?>;
    var cscMoisFin = <?php echo json_encode($mois_fin); ?>;
    var cscMoisLabels = <?php echo json_encode($mois_labels_map); ?>;

    function cscEl(id) { return document.getElementById(id); }

    var cscActiviteLabels = { vente_eau: 'Vente d\'eau (VE)', branchements: 'Abonnement service (AS)', autre: 'Autre' };
    var cscActiviteOrder = ['vente_eau', 'branchements', 'autre'];

    function cscMakeCheckboxCol(item, nameAttr, labelKey) {
        var col = document.createElement('div');
        col.className = 'col';
        var wrap = document.createElement('div');
        wrap.className = 'form-check';
        var input = document.createElement('input');
        input.type = 'checkbox';
        input.className = 'form-check-input';
        input.value = item.id;
        input.checked = !!item.checked;
        input.id = nameAttr + '_' + item.id;
        input.setAttribute('data-csc-el', nameAttr);
        var label = document.createElement('label');
        label.className = 'form-check-label small';
        label.setAttribute('for', input.id);
        label.textContent = item[labelKey] + (item.actif === false ? ' (inactive)' : '');
        wrap.appendChild(input);
        wrap.appendChild(label);
        col.appendChild(wrap);
        return col;
    }

    function cscBuildCheckboxes(container, items, nameAttr, labelKey) {
        container.innerHTML = '';
        items.forEach(function (item) {
            container.appendChild(cscMakeCheckboxCol(item, nameAttr, labelKey));
        });
    }

    // Catégories groupées par activité associée (Vente d'eau / Abonnement service / Autre)
    // pour qu'on distingue clairement à quoi chaque charge est rattachée.
    function cscBuildCategoriesGrouped(container, items) {
        container.innerHTML = '';
        var byActivite = {};
        items.forEach(function (item) {
            var act = item.activite_associee || 'autre';
            if (!byActivite[act]) byActivite[act] = [];
            byActivite[act].push(item);
        });
        var ordreAffiche = cscActiviteOrder.concat(Object.keys(byActivite).filter(function (act) {
            return cscActiviteOrder.indexOf(act) === -1;
        }));
        ordreAffiche.forEach(function (act) {
            var list = byActivite[act];
            if (!list || !list.length) return;
            var title = document.createElement('div');
            title.className = 'col-12 small fw-bold text-muted mt-2';
            title.textContent = cscActiviteLabels[act] || act;
            container.appendChild(title);
            var row = document.createElement('div');
            row.className = 'row row-cols-1 row-cols-md-2 g-1 col-12';
            list.forEach(function (item) {
                row.appendChild(cscMakeCheckboxCol(item, 'cscCat', 'nom'));
            });
            container.appendChild(row);
        });
    }

    function cscLoadMois(mois) {
        cscEl('cscForm').style.display = 'none';
        cscEl('cscLoading').style.display = '';
        cscEl('cscHeriteInfo').style.display = 'none';
        cscEl('cscSaveStatus').textContent = '';
        fetch(cscAjaxUrl + '?action=get_form&mois=' + encodeURIComponent(mois), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                cscEl('cscLoading').style.display = 'none';
                if (!data || !data.ok) {
                    cscEl('cscSaveStatus').textContent = (data && data.error) ? data.error : 'Erreur de chargement.';
                    return;
                }
                cscEl('cscForm').style.display = '';
                cscEl('cscSansCategorie').checked = !!data.sans_categorie_checked;
                cscBuildCategoriesGrouped(cscEl('cscCategories'), data.categories);
                cscBuildCheckboxes(cscEl('cscRedevances'), data.redevances, 'cscRed', 'libele');
                if (data.herite_de) {
                    var lbl = cscMoisLabels[data.herite_de] || data.herite_de;
                    cscEl('cscHeriteInfo').style.display = '';
                    cscEl('cscHeriteInfo').textContent = 'Sélection reportée du mois ' + lbl + ' (aucune configuration propre à ce mois pour l’instant).';
                }
            })
            .catch(function () {
                cscEl('cscLoading').style.display = 'none';
                cscEl('cscSaveStatus').textContent = 'Erreur réseau.';
            });
    }

    var cscMoisSelect = cscEl('cscMois');
    if (cscMoisSelect) {
        cscMoisSelect.addEventListener('change', function () { cscLoadMois(this.value); });
        var chargesModal = cscEl('chargesCoutServiceModal');
        if (chargesModal) {
            chargesModal.addEventListener('show.bs.modal', function () { cscLoadMois(cscMoisSelect.value); });
        }
    }

    var cscSaveBtn = cscEl('cscSaveBtn');
    if (cscSaveBtn) {
        cscSaveBtn.addEventListener('click', function () {
            var mois = cscMoisSelect.value;
            var formData = new FormData();
            formData.append('action', 'save');
            formData.append('_csrf', cscCsrfToken);
            formData.append('mois', mois);
            formData.append('sans_categorie', cscEl('cscSansCategorie').checked ? '1' : '');
            [].slice.call(document.querySelectorAll('[data-csc-el="cscCat"]:checked')).forEach(function (el) {
                formData.append('categories[]', el.value);
            });
            [].slice.call(document.querySelectorAll('[data-csc-el="cscRed"]:checked')).forEach(function (el) {
                formData.append('redevances[]', el.value);
            });
            cscEl('cscSaveStatus').textContent = 'Enregistrement…';
            fetch(cscAjaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        cscEl('cscSaveStatus').textContent = 'Enregistré. Rechargement…';
                        window.location.reload();
                    } else {
                        cscEl('cscSaveStatus').textContent = (data && data.error) ? data.error : 'Échec de l’enregistrement.';
                    }
                })
                .catch(function () {
                    cscEl('cscSaveStatus').textContent = 'Erreur réseau.';
                });
        });
    }

    // Simulation : aperçu non enregistré, superposé sur le graphique existant.
    var cscSimBtn = cscEl('cscSimBtn');
    var cscSimResetBtn = cscEl('cscSimResetBtn');
    var cscSimHideReal = cscEl('cscSimHideReal');
    var cscSimHideRealWrap = cscEl('cscSimHideRealWrap');

    function cscRemoveSimDataset() {
        var chart = window._afChart;
        if (!chart) return;
        chart.data.datasets = chart.data.datasets.filter(function (ds) { return ds.id !== 'cscSimulation'; });
        if (chart.data.datasets[0]) {
            chart.data.datasets[0].hidden = false;
        }
        chart.update();
        cscSimResetBtn.style.display = 'none';
        cscSimHideRealWrap.style.display = 'none';
        if (cscSimHideReal) cscSimHideReal.checked = false;
        cscEl('cscSimStatus').textContent = '';
    }

    if (cscSimBtn) {
        cscSimBtn.addEventListener('click', function () {
            var moisSource = cscEl('cscSimMoisSource').value;
            cscEl('cscSimStatus').textContent = 'Calcul en cours…';
            var url = cscAjaxUrl + '?action=simulate'
                + '&mois_source=' + encodeURIComponent(moisSource)
                + '&mois_debut=' + encodeURIComponent(cscMoisDebut)
                + '&mois_fin=' + encodeURIComponent(cscMoisFin);
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        cscEl('cscSimStatus').textContent = (data && data.error) ? data.error : 'Échec de la simulation.';
                        return;
                    }
                    var chart = window._afChart;
                    if (!chart) return;
                    var label = 'Simulation (mois modèle : ' + (cscMoisLabels[moisSource] || moisSource) + ')';
                    chart.data.datasets = chart.data.datasets.filter(function (ds) { return ds.id !== 'cscSimulation'; });
                    chart.data.datasets.push({
                        id: 'cscSimulation',
                        label: label,
                        data: data.cout_par_m3,
                        borderColor: 'rgb(111, 66, 193)',
                        backgroundColor: 'rgba(111, 66, 193, 0.1)',
                        borderDash: [6, 4],
                        tension: 0.4,
                        fill: false,
                        yAxisID: 'y'
                    });
                    chart.update();
                    cscSimResetBtn.style.display = '';
                    cscSimHideRealWrap.style.display = '';
                    cscEl('cscSimStatus').textContent = 'Aperçu affiché — rien n’a été enregistré.';
                })
                .catch(function () {
                    cscEl('cscSimStatus').textContent = 'Erreur réseau.';
                });
        });
    }

    if (cscSimResetBtn) {
        cscSimResetBtn.addEventListener('click', cscRemoveSimDataset);
    }

    if (cscSimHideReal) {
        cscSimHideReal.addEventListener('change', function () {
            var chart = window._afChart;
            if (!chart || !chart.data.datasets[0]) return;
            chart.data.datasets[0].hidden = cscSimHideReal.checked;
            chart.update();
        });
    }
})();
</script>

<!-- Détail mois par mois : affiché en place sous le graphique (repliable), plutôt
     que dans une modale — on peut ainsi comparer courbe et tableau d'un coup d'oeil. -->
<div class="collapse" id="afDetails">
    <div class="af-card">
        <div class="af-card-head">
            <h2><i class="bi bi-table me-2"></i>Détail mois par mois</h2>
        </div>
            <div class="af-card-body">
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
                            // Les totaux de la période sont calculés en tête de fichier
                            // (ils alimentent aussi les indicateurs), pas ici.
                            $mois_index = 0;
                            foreach ($donnees_graphique as $data):
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
                                            <i class="bi bi-chevron-down" id="icon_<?php echo $mois_id; ?>"></i>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo date('M Y', strtotime($data['mois'] . '-01')); ?></strong>
                                        <?php if (!empty($data['herite_de'])): ?>
                                            <span class="badge bg-light text-muted border ms-1" title="Charges reportées du mois <?php echo htmlspecialchars(date('M Y', strtotime($data['herite_de'] . '-01')), ENT_QUOTES, 'UTF-8'); ?>">reporté</span>
                                        <?php endif; ?>
                                    </td>
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
                                                <i class="bi bi-list-ul me-2"></i>Détail des catégories de dépenses
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
                                <td class="text-end"><?php echo number_format($cout_moyen, 2, ',', ' '); ?></td>
                                <td class="text-end"><?php echo number_format($prix_moyen_global, 2, ',', ' '); ?></td>
                                <td class="text-end">
                                    <?php $class_ecart_moyen = $ecart_moyen > 0 ? 'text-danger' : ($ecart_moyen < 0 ? 'text-success' : 'text-muted'); ?>
                                    <span class="<?php echo $class_ecart_moyen; ?>">
                                        <?php echo $ecart_moyen > 0 ? '+' : ''; ?><?php echo number_format($ecart_moyen, 2, ',', ' '); ?>
                                    </span>
                                </td>
                                <td class="text-center">
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
                        <li><strong>Dépenses totales :</strong> Somme des charges sélectionnées pour ce mois
                            (catégories de flux + redevances — configurables via « Configurer les charges d'un mois »,
                            reportées automatiquement du mois précédent tant qu'elles ne sont pas modifiées)</li>
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
    </div>
</div>

</div><!-- /.af-page -->

<script>
    function toggleDetails(moisId) {
        const detailsRow = document.getElementById('details_' + moisId);
        const icon = document.getElementById('icon_' + moisId);
        
        if (detailsRow && icon) {
            if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                detailsRow.style.display = '';
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-up');
            } else {
                detailsRow.style.display = 'none';
                icon.classList.remove('bi-chevron-up');
                icon.classList.add('bi-chevron-down');
            }
        }
    }
</script>