<?php
@include_once(__DIR__ . '/../donnees/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/../donnees/cartographie.php');
@include_once('donnees/cartographie.php');
@include_once(__DIR__ . '/../donnees/reseau.php');
@include_once('donnees/reseau.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$libele_aep = isset($_SESSION['libele_aep']) ? htmlspecialchars($_SESSION['libele_aep'], ENT_QUOTES, 'UTF-8') : '';

if (!$id_aep) {
    echo '<div class="container mt-5"><div class="alert alert-warning">Sélectionne un AEP pour afficher sa cartographie.</div></div>';
    exit;
}

Cartographie::ensureTables();

$reseaux = Reseau::getAllByIdReseau($id_aep)->fetchAll(PDO::FETCH_ASSOC);
$csrf_token = Csrf::token();
?>
<link rel="stylesheet" href="presentation/assets/leaflet/leaflet.css">
<style>
    .carto-page {
        --carto-accent: #2c9D11;
        --carto-bg: #f4f6f8;
        --carto-surface: #ffffff;
        --carto-border: #e3e8ee;
        --carto-text: #1f2933;
        --carto-muted: #6b7684;
        --carto-radius: 12px;
        background: var(--carto-bg);
        color: var(--carto-text);
        padding: 16px;
    }

    /* --- Bandeau de tête --- */
    .carto-topbar {
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        background: var(--carto-surface); border: 1px solid var(--carto-border);
        border-radius: var(--carto-radius); padding: 12px 16px; margin-bottom: 12px;
    }
    .carto-topbar h1 { font-size: 1.1rem; font-weight: 650; margin: 0; letter-spacing: -0.01em; }
    .carto-chip {
        display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;
        background: rgba(44,157,17,.1); color: #216d0d; padding: 3px 10px; border-radius: 999px;
    }
    .carto-search { position: relative; min-width: 240px; }
    .carto-search input { padding-left: 32px; border-radius: 999px; }
    .carto-search .bi-search { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--carto-muted); font-size: 13px; }
    .carto-search-results {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1010; margin-top: 4px;
        background: var(--carto-surface); border: 1px solid var(--carto-border); border-radius: 8px;
        box-shadow: 0 8px 24px rgba(16,24,40,.12); max-height: 260px; overflow-y: auto; display: none;
    }
    .carto-search-results div { padding: 7px 12px; font-size: 13px; cursor: pointer; }
    .carto-search-results div:hover { background: #f0f4f8; }

    /* --- Structure carte + panneau --- */
    .carto-shell { display: flex; gap: 12px; height: calc(100vh - 210px); min-height: 540px; }
    /* z-index sous celui des modales Bootstrap (1055), sinon elles passeraient derrière */
    .carto-page.carto-fullscreen { position: fixed; inset: 0; z-index: 1030; overflow: auto; padding: 12px; }
    .carto-page.carto-fullscreen .carto-shell { height: calc(100vh - 130px); }

    .carto-sidebar {
        flex: 0 0 330px; width: 330px; overflow-y: auto; overflow-x: hidden;
        background: var(--carto-surface); border: 1px solid var(--carto-border);
        border-radius: var(--carto-radius); padding: 4px 0;
        transition: flex-basis .2s ease, width .2s ease, opacity .15s ease;
    }
    .carto-shell.carto-sidebar-hidden .carto-sidebar { flex-basis: 0; width: 0; opacity: 0; border-width: 0; padding: 0; }

    .carto-section { padding: 14px 16px; border-bottom: 1px solid var(--carto-border); }
    .carto-section:last-child { border-bottom: 0; }
    .carto-section-title {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
        color: var(--carto-muted); margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;
    }
    .carto-section label.form-label { font-size: 12px; color: var(--carto-muted); margin-bottom: 3px; }
    .carto-section .form-select, .carto-section .form-control { font-size: 13px; border-radius: 8px; border-color: var(--carto-border); }

    /* --- Indicateurs --- */
    .carto-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .carto-kpi { background: var(--carto-bg); border-radius: 10px; padding: 9px 10px; text-align: center; }
    .carto-kpi .val { font-size: 15px; font-weight: 700; line-height: 1.2; display: block; }
    .carto-kpi .lbl { font-size: 10px; color: var(--carto-muted); text-transform: uppercase; letter-spacing: .04em; }

    /* --- Réseaux --- */
    .carto-reseau-row {
        display: flex; align-items: center; gap: 8px; padding: 5px 6px; font-size: 13px;
        border-radius: 7px; cursor: pointer;
    }
    .carto-reseau-row:hover { background: var(--carto-bg); }
    .carto-reseau-row input { cursor: pointer; margin: 0; flex: 0 0 auto; }
    .carto-reseau-pastille { width: 11px; height: 11px; border-radius: 50%; flex: 0 0 auto; }
    .carto-reseau-nom { flex: 1 1 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .carto-reseau-nb { font-size: 11px; color: var(--carto-muted); font-variant-numeric: tabular-nums; }
    .carto-reseau-row.eteint .carto-reseau-nom,
    .carto-reseau-row.eteint .carto-reseau-nb { opacity: .45; }
    .carto-reseau-row.eteint .carto-reseau-pastille { opacity: .25; }

    /* --- Calques --- */
    .carto-layer-row { display: flex; align-items: center; justify-content: space-between; padding: 5px 0; font-size: 13px; }
    .carto-layer-row .form-check { margin: 0; min-height: auto; }
    .carto-layer-row .form-check-input { cursor: pointer; }

    /* --- Carte --- */
    .carto-map-wrap {
        flex: 1; position: relative; border: 1px solid var(--carto-border);
        border-radius: var(--carto-radius); overflow: hidden; background: #e9edf1;
    }
    #cartoMap { position: absolute; inset: 0; }
    #cartoMap.carto-mode-add, #cartoMap.carto-mode-draw { cursor: crosshair; }

    /* --- Outils flottants sur la carte --- */
    .carto-tools {
        position: absolute; top: 12px; right: 12px; z-index: 1000;
        display: flex; flex-direction: column; gap: 6px;
    }
    .carto-tools .btn, .carto-tools .dropdown > .btn {
        width: 38px; height: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center;
        background: var(--carto-surface); border: 1px solid var(--carto-border); color: var(--carto-text);
        border-radius: 9px; box-shadow: 0 2px 6px rgba(16,24,40,.08); font-size: 16px;
    }
    .carto-tools .btn:hover { background: #f0f4f8; }
    .carto-tools .btn.actif { background: var(--carto-accent); border-color: var(--carto-accent); color: #fff; }
    .carto-tools .dropdown-toggle::after { display: none; }

    .carto-mode-banner {
        position: absolute; top: 12px; left: 50%; transform: translateX(-50%); z-index: 1000;
        background: #1f2933; color: #fff; font-size: 12.5px; padding: 8px 14px; border-radius: 999px;
        box-shadow: 0 4px 12px rgba(16,24,40,.2); display: none; max-width: 70%; text-align: center;
    }

    /* --- Légende --- */
    .carto-legend {
        background: rgba(255,255,255,.96); padding: 10px 12px; border-radius: 10px;
        box-shadow: 0 2px 10px rgba(16,24,40,.12); font-size: 11.5px; line-height: 1.7;
        border: 1px solid var(--carto-border); max-width: 210px;
    }
    .carto-legend strong { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--carto-muted); }
    .carto-legend .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 7px; vertical-align: middle; }
    .carto-badge-type {
        display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px;
        border-radius: 50%; color: #fff; font-size: 9px; font-weight: 700; margin-right: 6px;
    }

    /* --- Anomalies --- */
    .carto-ano-tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
    .carto-ano-tabs button {
        flex: 1 1 auto; font-size: 11px; padding: 5px 6px; border-radius: 7px;
        border: 1px solid var(--carto-border); background: var(--carto-surface); color: var(--carto-muted);
    }
    .carto-ano-tabs button.active { background: var(--carto-text); border-color: var(--carto-text); color: #fff; }
    .carto-ano-liste { max-height: 240px; overflow-y: auto; font-size: 12px; }
    .carto-ano-liste .carto-ano-zoom:hover { background: #f0f4f8; }

    @media (max-width: 992px) {
        .carto-shell { flex-direction: column; height: auto; }
        .carto-sidebar { flex-basis: auto; width: 100%; max-height: 340px; }
        .carto-map-wrap { height: 65vh; }
    }
</style>
<div class="carto-page" id="cartoPage">

    <div class="carto-topbar">
        <button type="button" class="btn btn-light btn-sm border" id="cartoSidebarToggle" title="Afficher / masquer le panneau">
            <i class="bi bi-layout-sidebar"></i>
        </button>
        <div>
            <h1>Cartographie du réseau</h1>
            <div class="d-flex align-items-center gap-2 mt-1">
                <span class="carto-chip"><i class="bi bi-droplet-fill"></i><?php echo $libele_aep; ?></span>
                <span class="carto-chip" style="background:#eef1f5;color:#4a5560;"><i class="bi bi-wifi-off"></i>Hors ligne</span>
            </div>
        </div>
        <div class="carto-search ms-auto">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control form-control-sm" id="cartoRecherche" placeholder="Rechercher un abonné, un compteur…" autocomplete="off">
            <div class="carto-search-results" id="cartoRechercheResultats"></div>
        </div>
    </div>

    <div class="carto-shell" id="cartoShell">
        <aside class="carto-sidebar">

            <div class="carto-section">
                <div class="carto-section-title">Vue d'ensemble</div>
                <div class="carto-kpis">
                    <div class="carto-kpi">
                        <span class="val" id="cartoKpiGeo">—</span>
                        <span class="lbl">Géolocalisés</span>
                    </div>
                    <div class="carto-kpi">
                        <span class="val text-danger" id="cartoKpiImpaye">—</span>
                        <span class="lbl">Impayé</span>
                    </div>
                    <div class="carto-kpi">
                        <span class="val" id="cartoKpiTaux">—</span>
                        <span class="lbl">Recouvré</span>
                    </div>
                </div>
                <div class="small text-muted mt-2" id="cartoStatsGeoloc">Chargement…</div>
            </div>

            <div class="carto-section">
                <div class="carto-section-title">Analyse</div>
                <label class="form-label" for="cartoThematique">Thématique</label>
                <select id="cartoThematique" class="form-select form-select-sm mb-2">
                    <option value="reseau" selected>Réseau d'appartenance</option>
                    <option value="etat">État de l'abonné</option>
                    <option value="impaye">Impayé du mois</option>
                    <option value="impaye_cumule">Impayé cumulé</option>
                    <option value="consommation">Consommation du mois</option>
                    <option value="taux">Taux de recouvrement</option>
                    <option value="releves">Relevés manquants</option>
                    <option value="penalite">Pénalités</option>
                </select>
                <label class="form-label" for="cartoMois">Mois</label>
                <select id="cartoMois" class="form-select form-select-sm"></select>
            </div>

            <div class="carto-section">
                <div class="carto-section-title">
                    Réseaux
                    <span>
                        <a href="#" class="text-decoration-none small" id="cartoReseauxTous">tous</a>
                        <span class="text-muted">·</span>
                        <a href="#" class="text-decoration-none small" id="cartoReseauxAucun">aucun</a>
                    </span>
                </div>
                <div id="cartoReseauxListe" class="carto-reseaux"></div>
            </div>

            <div class="carto-section">
                <div class="carto-section-title">Calques</div>
                <div class="carto-layer-row">
                    <span>Compteurs abonnés</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input carto-layer-toggle" type="checkbox" id="cartoToggleCompteurs" data-layer="compteurs" checked>
                    </div>
                </div>
                <div class="carto-layer-row">
                    <span>Ouvrages</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input carto-layer-toggle" type="checkbox" id="cartoToggleOuvrages" data-layer="ouvrages" checked>
                    </div>
                </div>
                <div class="carto-layer-row">
                    <span>Conduites</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input carto-layer-toggle" type="checkbox" id="cartoToggleConduites" data-layer="conduites" checked>
                    </div>
                </div>
                <div class="carto-layer-row">
                    <span>Secteurs</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input carto-layer-toggle" type="checkbox" id="cartoToggleSecteurs" data-layer="secteurs" checked>
                    </div>
                </div>
            </div>

            <div class="carto-section">
                <div class="carto-section-title">
                    Qualité des données
                    <span class="badge bg-secondary" id="cartoAnomaliesTotal">—</span>
                </div>
                <div class="carto-ano-tabs">
                    <button type="button" class="carto-ano-tab active" data-cible="cartoAnoSansGps">Sans GPS <span id="cartoAnoSansGpsNb">0</span></button>
                    <button type="button" class="carto-ano-tab" data-cible="cartoAnoRattach">Rattachement <span id="cartoAnoRattachNb">0</span></button>
                </div>
                <div class="carto-ano-tabs">
                    <button type="button" class="carto-ano-tab" data-cible="cartoAnoDoublons">Doublons <span id="cartoAnoDoublonsNb">0</span></button>
                    <button type="button" class="carto-ano-tab" data-cible="cartoAnoAberrants">Aberrants <span id="cartoAnoAberrantsNb">0</span></button>
                </div>
                <div class="carto-ano-liste">
                    <div id="cartoAnoSansGps"></div>
                    <div id="cartoAnoRattach" style="display:none;"></div>
                    <div id="cartoAnoDoublons" style="display:none;"></div>
                    <div id="cartoAnoAberrants" style="display:none;"></div>
                </div>
                <button type="button" class="btn btn-light btn-sm border w-100 mt-2" id="cartoExportSansGps">
                    <i class="bi bi-download me-1"></i>Exporter les compteurs sans position
                </button>
            </div>

            <div class="carto-section">
                <div class="carto-section-title">
                    Fond de carte
                    <a href="#" id="cartoTuilesToggle" class="text-decoration-none small">Gérer</a>
                </div>
                <div class="small text-muted" id="cartoTuilesInfo">Chargement…</div>
                <div id="cartoTuilesGestion" style="display:none;" class="mt-2">
                    <form id="cartoUploadForm">
                        <label class="form-label" for="cartoFichierTuiles">
                            Archive ZIP de tuiles ({z}/{x}/{y})
                            <span class="text-muted">— max <?php echo htmlspecialchars(ini_get('upload_max_filesize'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </label>
                        <input type="file" id="cartoFichierTuiles" name="fichier_tuiles" accept=".zip" class="form-control form-control-sm mb-2"
                               data-max-octets="<?php echo (int) (str_replace(array('M', 'm'), '', ini_get('upload_max_filesize')) * 1048576); ?>">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-sm flex-fill">Importer</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="cartoDeleteTuilesBtn" title="Supprimer toutes les tuiles">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </form>
                    <div class="small mt-2" id="cartoUploadStatus"></div>
                </div>
            </div>
        </aside>

        <div class="carto-map-wrap">
            <div id="cartoMap"></div>

            <div class="carto-mode-banner" id="cartoModeInfo"></div>

            <div class="carto-tools">
                <div class="dropdown">
                    <button class="btn" type="button" data-bs-toggle="dropdown" title="Ajouter un ouvrage">
                        <i class="bi bi-geo-alt-fill"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Choisir un type, puis cliquer sur la carte</h6></li>
                        <li><a class="dropdown-item carto-add-type" href="#" data-type="captage">Captage</a></li>
                        <li><a class="dropdown-item carto-add-type" href="#" data-type="reservoir">Réservoir</a></li>
                        <li><a class="dropdown-item carto-add-type" href="#" data-type="vanne">Vanne</a></li>
                        <li><a class="dropdown-item carto-add-type" href="#" data-type="compteur_reseau">Compteur réseau</a></li>
                        <li><a class="dropdown-item carto-add-type" href="#" data-type="borne_fontaine">Borne fontaine</a></li>
                        <li><a class="dropdown-item carto-add-type" href="#" data-type="autre">Autre</a></li>
                    </ul>
                </div>
                <button type="button" class="btn" id="cartoDrawBtn" title="Tracer une conduite">
                    <i class="bi bi-vector-pen"></i>
                </button>
                <button type="button" class="btn" id="cartoSecteurBtn" title="Délimiter un secteur">
                    <i class="bi bi-bounding-box"></i>
                </button>
                <div class="dropdown">
                    <button class="btn" type="button" data-bs-toggle="dropdown" title="Exporter">
                        <i class="bi bi-download"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" id="cartoExportCompteursCsv">Compteurs (CSV)</a></li>
                        <li><a class="dropdown-item" href="#" id="cartoExportCompteursGeojson">Compteurs (GeoJSON)</a></li>
                        <li><a class="dropdown-item" href="#" id="cartoExportOuvragesGeojson">Ouvrages (GeoJSON)</a></li>
                        <li><a class="dropdown-item" href="#" id="cartoExportConduitesGeojson">Conduites (GeoJSON)</a></li>
                    </ul>
                </div>
                <button type="button" class="btn" id="cartoFullscreenBtn" title="Plein écran">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal : ajouter/modifier un ouvrage -->
<div class="modal fade" id="cartoOuvrageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ouvrage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cartoOuvrageId" value="0">
                <input type="hidden" id="cartoOuvrageLat">
                <input type="hidden" id="cartoOuvrageLon">
                <div class="mb-2">
                    <label class="form-label small">Type</label>
                    <select id="cartoOuvrageType" class="form-select form-select-sm">
                        <option value="captage">Captage</option>
                        <option value="reservoir">Réservoir</option>
                        <option value="vanne">Vanne</option>
                        <option value="compteur_reseau">Compteur réseau</option>
                        <option value="borne_fontaine">Borne fontaine</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Nom</label>
                    <input type="text" id="cartoOuvrageNom" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Réseau (optionnel)</label>
                    <select id="cartoOuvrageReseau" class="form-select form-select-sm">
                        <option value="">—</option>
                        <?php foreach ($reseaux as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['nom'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Description</label>
                    <textarea id="cartoOuvrageDescription" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto" id="cartoOuvrageStatus"></span>
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="cartoOuvrageDeleteBtn">Supprimer</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary btn-sm" id="cartoOuvrageSaveBtn">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal : ajouter/modifier une conduite -->
<div class="modal fade" id="cartoConduiteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conduite</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cartoConduiteId" value="0">
                <input type="hidden" id="cartoConduitePoints">
                <p class="small text-muted" id="cartoConduiteLongueur"></p>
                <div class="mb-2">
                    <label class="form-label small">Nom</label>
                    <input type="text" id="cartoConduiteNom" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Réseau (optionnel)</label>
                    <select id="cartoConduiteReseau" class="form-select form-select-sm">
                        <option value="">—</option>
                        <?php foreach ($reseaux as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['nom'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Description</label>
                    <textarea id="cartoConduiteDescription" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto" id="cartoConduiteStatus"></span>
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="cartoConduiteDeleteBtn">Supprimer</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary btn-sm" id="cartoConduiteSaveBtn">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal : secteur -->
<div class="modal fade" id="cartoSecteurModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Secteur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cartoSecteurId" value="0">
                <input type="hidden" id="cartoSecteurPolygone">
                <div class="mb-2">
                    <label class="form-label small">Nom du secteur</label>
                    <input type="text" id="cartoSecteurNom" class="form-control form-control-sm" placeholder="ex. Quartier Zemeto">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Description</label>
                    <textarea id="cartoSecteurDescription" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto" id="cartoSecteurStatus"></span>
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="cartoSecteurDeleteBtn">Supprimer</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary btn-sm" id="cartoSecteurSaveBtn">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script src="presentation/assets/leaflet/leaflet.js"></script>
<script>
(function () {
    var csrfToken = <?php echo json_encode($csrf_token); ?>;
    var idAep = <?php echo (int) $id_aep; ?>;
    var ajaxUrl = 'traitement/cartographie_t.php';
    var tuilesUrlBase = 'donnees/tuiles/aep_' + idAep + '/{z}/{x}/{y}';

    var TYPE_LABELS = {
        captage: 'Captage', reservoir: 'Réservoir', vanne: 'Vanne',
        compteur_reseau: 'Compteur réseau', borne_fontaine: 'Borne fontaine', autre: 'Autre'
    };
    var TYPE_COLORS = {
        captage: '#1565c0', reservoir: '#00897b', vanne: '#f57c00',
        compteur_reseau: '#6a1b9a', borne_fontaine: '#00acc1', autre: '#616161'
    };
    var TYPE_SHORT = {
        captage: 'C', reservoir: 'R', vanne: 'V',
        compteur_reseau: 'CR', borne_fontaine: 'BF', autre: 'A'
    };

    function el(id) { return document.getElementById(id); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function fcfa(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v || 0) + ' FCFA'; }

    /**
     * Rend lisible une reponse serveur qui n'est pas du JSON : en developpement,
     * Xdebug renvoie un rapport d'erreur en HTML, illisible tel quel. On en
     * extrait la ligne d'erreur PHP.
     */
    function messageErreurServeur(txt, statut) {
        var brut = String(txt).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        var m = brut.match(/(Fatal error|Parse error|Warning|Notice)[\s:].{0,250}/i);
        if (m) { return m[0].trim(); }
        return 'Réponse inattendue du serveur (HTTP ' + statut + ') : ' + brut.slice(0, 250);
    }

    function lireJson(reponse) {
        return reponse.text().then(function (txt) {
            try {
                return JSON.parse(txt);
            } catch (e) {
                throw new Error(messageErreurServeur(txt, reponse.status));
            }
        });
    }

    /**
     * Cartes thématiques : chaque thème donne une couleur par compteur, une
     * légende, et la valeur à afficher dans l'infobulle.
     */
    function palier(valeur, paliers) {
        for (var i = 0; i < paliers.length; i++) {
            if (valeur <= paliers[i].max) return paliers[i].couleur;
        }
        return paliers[paliers.length - 1].couleur;
    }

    var THEMATIQUES = {
        reseau: {
            label: "Réseau d'appartenance",
            // La couleur vient de couleursReseau, construit au chargement des données.
            couleur: function (c) { return couleursReseau[c.id_reseau] || '#9e9e9e'; },
            valeur: function (c) { return 'Réseau : ' + esc(c.nom_reseau); },
            legende: null // dynamique : voir majLegende()
        },
        etat: {
            label: "État de l'abonné",
            couleur: function (c) { return c.etat === 'actif' ? '#2e7d32' : '#9e9e9e'; },
            valeur: function (c) { return 'État : ' + esc(c.etat); },
            legende: [{ c: '#2e7d32', l: 'Actif' }, { c: '#9e9e9e', l: 'Inactif' }]
        },
        impaye: {
            label: 'Impayé du mois',
            couleur: function (c) {
                var v = parseFloat(c.impaye) || 0;
                if (v <= 0) return '#2e7d32';
                return palier(v, [{ max: 5000, couleur: '#fbc02d' }, { max: 20000, couleur: '#f57c00' }, { max: Infinity, couleur: '#c62828' }]);
            },
            valeur: function (c) { return 'Impayé du mois : ' + fcfa(c.impaye); },
            legende: [{ c: '#2e7d32', l: 'À jour' }, { c: '#fbc02d', l: '≤ 5 000' }, { c: '#f57c00', l: '≤ 20 000' }, { c: '#c62828', l: '> 20 000' }]
        },
        impaye_cumule: {
            label: 'Impayé cumulé',
            couleur: function (c) {
                var v = parseFloat(c.impaye_cumule) || 0;
                if (v <= 0) return '#2e7d32';
                return palier(v, [{ max: 20000, couleur: '#fbc02d' }, { max: 100000, couleur: '#f57c00' }, { max: Infinity, couleur: '#c62828' }]);
            },
            valeur: function (c) { return 'Impayé cumulé : ' + fcfa(c.impaye_cumule); },
            legende: [{ c: '#2e7d32', l: 'À jour' }, { c: '#fbc02d', l: '≤ 20 000' }, { c: '#f57c00', l: '≤ 100 000' }, { c: '#c62828', l: '> 100 000' }]
        },
        consommation: {
            label: 'Consommation du mois',
            couleur: function (c) {
                if (c.consommation === null || c.consommation === undefined) return '#bdbdbd';
                var v = parseFloat(c.consommation) || 0;
                if (v <= 0) return '#616161';
                return palier(v, [{ max: 10, couleur: '#b3e5fc' }, { max: 30, couleur: '#4fc3f7' }, { max: 60, couleur: '#0288d1' }, { max: Infinity, couleur: '#01579b' }]);
            },
            valeur: function (c) { return 'Consommation : ' + (c.consommation === null ? 'aucun relevé' : (c.consommation + ' m³')); },
            legende: [{ c: '#616161', l: 'Nulle (à vérifier)' }, { c: '#b3e5fc', l: '≤ 10 m³' }, { c: '#4fc3f7', l: '≤ 30 m³' }, { c: '#0288d1', l: '≤ 60 m³' }, { c: '#01579b', l: '> 60 m³' }, { c: '#bdbdbd', l: 'Pas de facture' }]
        },
        taux: {
            label: 'Taux de recouvrement',
            couleur: function (c) {
                if (c.taux_recouvrement === null) return '#bdbdbd';
                var v = parseFloat(c.taux_recouvrement);
                if (v >= 100) return '#2e7d32';
                return palier(v, [{ max: 0, couleur: '#c62828' }, { max: 50, couleur: '#f57c00' }, { max: 99.99, couleur: '#fbc02d' }]);
            },
            valeur: function (c) { return 'Recouvrement : ' + (c.taux_recouvrement === null ? '—' : c.taux_recouvrement + ' %'); },
            legende: [{ c: '#c62828', l: 'Rien payé' }, { c: '#f57c00', l: '< 50 %' }, { c: '#fbc02d', l: '< 100 %' }, { c: '#2e7d32', l: 'Soldé' }, { c: '#bdbdbd', l: 'Pas de facture' }]
        },
        releves: {
            label: 'Relevés manquants',
            couleur: function (c) {
                if (c.mois_sans_releve === null) return '#c62828';
                var v = parseInt(c.mois_sans_releve, 10);
                return palier(v, [{ max: 0, couleur: '#2e7d32' }, { max: 2, couleur: '#fbc02d' }, { max: 6, couleur: '#f57c00' }, { max: Infinity, couleur: '#c62828' }]);
            },
            valeur: function (c) {
                return 'Dernier relevé : ' + (c.dernier_mois_releve ? esc(c.dernier_mois_releve) + ' (' + c.mois_sans_releve + ' mois)' : 'jamais');
            },
            legende: [{ c: '#2e7d32', l: 'À jour' }, { c: '#fbc02d', l: '1 à 2 mois' }, { c: '#f57c00', l: '3 à 6 mois' }, { c: '#c62828', l: '> 6 mois / jamais' }]
        },
        penalite: {
            label: 'Pénalités',
            couleur: function (c) { return (parseFloat(c.penalite) || 0) > 0 ? '#c62828' : '#2e7d32'; },
            valeur: function (c) { return 'Pénalité : ' + fcfa(c.penalite); },
            legende: [{ c: '#2e7d32', l: 'Aucune' }, { c: '#c62828', l: 'Pénalisé' }]
        }
    };

    // Tuiles : essaie .png puis .jpg par tuile, pour supporter des imports mixtes
    // (ex. satellite JPG en zoom large + rendu vectoriel PNG en zoom serré).
    // Pixel entierement transparent, en GIF de 43 octets.
    var TUILE_VIDE = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

    var DualExtTileLayer = L.TileLayer.extend({
        createTile: function (coords, done) {
            var img = document.createElement('img');
            var base = this._url
                .replace('{z}', coords.z)
                .replace('{x}', coords.x)
                .replace('{y}', coords.y);
            var urlPng = base + '.png';
            var urlJpg = base + '.jpg';

            img.onerror = function () {
                // QGIS exporte en PNG, QTiles parfois en JPG : on tente les deux.
                if (img.getAttribute('data-tried-jpg') !== '1') {
                    img.setAttribute('data-tried-jpg', '1');
                    img.src = urlJpg;
                    return;
                }
                // Les deux extensions ont echoue : la zone n'est pas couverte a
                // ce niveau de zoom. On substitue un pixel transparent, sans
                // quoi le navigateur laisse son icone d'image cassee en place --
                // c'est la grille de vignettes grises qu'on voyait sur la carte.
                img.onerror = null;
                img.onload = null;
                img.src = TUILE_VIDE;
                done(null, img);
            };
            img.onload = function () { done(null, img); };
            img.src = urlPng;
            return img;
        }
    });

    var map = L.map('cartoMap').setView([7.37, 12.35], 6);
    var tileLayer = new DualExtTileLayer(tuilesUrlBase, { minZoom: 1, maxZoom: 20, attribution: '© contributeurs OpenStreetMap' });
    tileLayer.addTo(map);

    /**
     * Cale la couche sur ce que l'import couvre reellement.
     *
     * Sans ces bornes, Leaflet reclame des tuiles pour toute la planete et pour
     * vingt niveaux de zoom, alors que l'import n'en contient qu'une poignee
     * autour de l'AEP : d'ou des centaines de requetes vouees a l'echec.
     *
     * `maxNativeZoom` est le detail qui change tout a l'usage : au-dela du zoom
     * importe, Leaflet agrandit la derniere tuile disponible au lieu d'en
     * demander de plus fines qui n'existent pas. Le fond devient flou, mais il
     * reste present -- bien preferable a un ecran vide.
     */
    function calerTuiles(t) {
        if (!t || !t.disponible) return;
        if (t.zoom_max !== null && t.zoom_max !== undefined) {
            tileLayer.options.maxNativeZoom = parseInt(t.zoom_max, 10);
        }
        if (t.zoom_min !== null && t.zoom_min !== undefined) {
            tileLayer.options.minNativeZoom = parseInt(t.zoom_min, 10);
        }
        if (t.emprise) {
            tileLayer.options.bounds = L.latLngBounds(
                [t.emprise.lat_min, t.emprise.lon_min],
                [t.emprise.lat_max, t.emprise.lon_max]
            );
        }
        tileLayer.redraw();
    }

    L.control.attribution({ prefix: false }).addTo(map);

    var legendDiv = null;
    var legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
        legendDiv = L.DomUtil.create('div', 'carto-legend');
        return legendDiv;
    };
    legend.addTo(map);

    function majLegende() {
        if (!legendDiv) return;
        var th = THEMATIQUES[etat.thematique] || THEMATIQUES.reseau;
        var html = '<strong>' + esc(th.label) + '</strong><br>';

        // La légende du thème "réseau" dépend des réseaux réellement présents,
        // et n'affiche que ceux qui sont visibles sur la carte.
        var entrees = th.legende;
        if (!entrees) {
            entrees = construireCouleursReseau()
                .filter(function (r) { return reseauVisible(r.id); })
                .map(function (r) { return { c: r.couleur, l: r.nom }; });
            if (!entrees.length) {
                entrees = [{ c: '#9e9e9e', l: 'Aucun réseau affiché' }];
            }
        }
        entrees.forEach(function (item) {
            html += '<span class="dot" style="background:' + item.c + '"></span>' + esc(item.l) + '<br>';
        });
        html += '<hr class="my-1"><strong>Ouvrages</strong><br>';
        Object.keys(TYPE_LABELS).forEach(function (k) {
            html += '<span class="carto-badge-type" style="background:' + TYPE_COLORS[k] + '">' + TYPE_SHORT[k] + '</span>' + TYPE_LABELS[k] + '<br>';
        });
        legendDiv.innerHTML = html;
    }

    var layerCompteurs = L.layerGroup().addTo(map);
    var layerOuvrages = L.layerGroup().addTo(map);
    var layerConduites = L.layerGroup().addTo(map);
    var layerSecteurs = L.layerGroup().addTo(map);

    var etat = {
        compteurs: [], ouvrages: [], conduites: [], secteurs: [], peutEcrire: false,
        reseauxVisibles: {}, // id de réseau -> booléen ; vide = tout visible
        mode: null, addType: null, drawPoints: [],
        thematique: 'reseau', mois: null, anomalies: null
    };

    /**
     * Couleur stable par réseau : l'ordre est celui des identifiants triés, donc
     * un réseau garde la même couleur d'une session à l'autre.
     */
    var PALETTE_RESEAUX = [
        '#1a73e8', '#e8710a', '#34a853', '#a142f4', '#d93025',
        '#00897b', '#f9ab00', '#c2185b', '#5c6bc0', '#827717'
    ];
    var couleursReseau = {};

    function construireCouleursReseau() {
        var ids = {};
        etat.compteurs.forEach(function (c) { ids[c.id_reseau] = c.nom_reseau; });
        var listeIds = Object.keys(ids).sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });
        couleursReseau = {};
        listeIds.forEach(function (id, i) {
            couleursReseau[id] = PALETTE_RESEAUX[i % PALETTE_RESEAUX.length];
        });
        return listeIds.map(function (id) {
            return { id: id, nom: ids[id], couleur: couleursReseau[id] };
        });
    }

    function reseauVisible(idReseau) {
        return etat.reseauxVisibles[idReseau] !== false;
    }

    function toutesLesCoords() {
        var coords = [];
        etat.compteurs.forEach(function (c) { coords.push([parseFloat(c.latitude), parseFloat(c.longitude)]); });
        etat.ouvrages.forEach(function (o) { coords.push([parseFloat(o.latitude), parseFloat(o.longitude)]); });
        etat.conduites.forEach(function (c) { (c.trace || []).forEach(function (p) { coords.push([p[1], p[0]]); }); });
        return coords;
    }

    function rendreCompteurs() {
        layerCompteurs.clearLayers();
        var th = THEMATIQUES[etat.thematique] || THEMATIQUES.reseau;
        etat.compteurs.forEach(function (c) {
            if (!reseauVisible(c.id_reseau)) return;
            var m = L.circleMarker([c.latitude, c.longitude], {
                radius: 6, color: '#fff', weight: 1, fillColor: th.couleur(c), fillOpacity: 0.9
            });
            var html = '<strong>' + esc(c.nom_abone) + '</strong><br>'
                + 'Compteur : ' + esc(c.numero_compteur) + '<br>'
                + 'Réseau : ' + esc(c.nom_reseau) + '<br>'
                + '<hr class="my-1">'
                + th.valeur(c) + '<br>'
                + 'Consommation : ' + (c.consommation === null ? '—' : c.consommation + ' m³') + '<br>'
                + 'Facturé : ' + fcfa(c.montant_total) + ' · Versé : ' + fcfa(c.montant_verse) + '<br>'
                + 'Impayé cumulé : ' + fcfa(c.impaye_cumule) + '<br>'
                + '<a href="?page=info_abone&id=' + encodeURIComponent(c.id_abone) + '">Voir la fiche</a>';
            m.bindPopup(html);
            layerCompteurs.addLayer(m);
        });
    }

    function ouvrirModalOuvrage(data) {
        el('cartoOuvrageId').value = data.id || 0;
        el('cartoOuvrageLat').value = data.latitude;
        el('cartoOuvrageLon').value = data.longitude;
        el('cartoOuvrageType').value = data.type || 'autre';
        el('cartoOuvrageNom').value = data.nom || '';
        el('cartoOuvrageReseau').value = data.id_reseau || '';
        el('cartoOuvrageDescription').value = data.description || '';
        el('cartoOuvrageStatus').textContent = '';
        el('cartoOuvrageDeleteBtn').classList.toggle('d-none', !(data.id > 0));
        bootstrap.Modal.getOrCreateInstance(el('cartoOuvrageModal')).show();
    }

    function rendreOuvrages() {
        layerOuvrages.clearLayers();
        etat.ouvrages.forEach(function (o) {
            // Un ouvrage sans réseau rattaché reste toujours visible.
            if (!reseauVisible(o.id_reseau)) return;
            var color = TYPE_COLORS[o.type] || TYPE_COLORS.autre;
            var icon = L.divIcon({
                className: '',
                html: '<div class="carto-badge-type" style="background:' + color + '">' + (TYPE_SHORT[o.type] || 'A') + '</div>',
                iconSize: [18, 18]
            });
            var m = L.marker([o.latitude, o.longitude], { icon: icon });
            var html = '<strong>' + esc(o.nom) + '</strong><br>'
                + (TYPE_LABELS[o.type] || o.type) + (o.nom_reseau ? (' · ' + esc(o.nom_reseau)) : '') + '<br>'
                + (o.description ? esc(o.description) + '<br>' : '');
            if (etat.peutEcrire) {
                html += '<button type="button" class="btn btn-sm btn-outline-primary mt-1 carto-edit-ouvrage" data-id="' + o.id + '">Modifier</button>';
            }
            m.bindPopup(html);
            m.on('popupopen', function () {
                var btn = document.querySelector('.carto-edit-ouvrage[data-id="' + o.id + '"]');
                if (btn) btn.addEventListener('click', function () { ouvrirModalOuvrage(o); });
            });
            layerOuvrages.addLayer(m);
        });
    }

    function ouvrirModalConduite(data) {
        el('cartoConduiteId').value = data.id || 0;
        el('cartoConduitePoints').value = JSON.stringify(data.trace || []);
        el('cartoConduiteNom').value = data.nom || '';
        el('cartoConduiteReseau').value = data.id_reseau || '';
        el('cartoConduiteDescription').value = data.description || '';
        el('cartoConduiteStatus').textContent = '';
        var longueur = data.longueur_m || 0;
        el('cartoConduiteLongueur').textContent = 'Longueur : ' + Math.round(longueur) + ' m';
        el('cartoConduiteDeleteBtn').classList.toggle('d-none', !(data.id > 0));
        bootstrap.Modal.getOrCreateInstance(el('cartoConduiteModal')).show();
    }

    function rendreConduites() {
        layerConduites.clearLayers();
        etat.conduites.forEach(function (c) {
            if (!reseauVisible(c.id_reseau)) return;
            var latlngs = (c.trace || []).map(function (p) { return [p[1], p[0]]; });
            var line = L.polyline(latlngs, { color: '#1a237e', weight: 3 });
            var html = '<strong>' + esc(c.nom || 'Conduite') + '</strong><br>'
                + Math.round(c.longueur_m) + ' m'
                + (c.nom_reseau ? (' · ' + esc(c.nom_reseau)) : '') + '<br>';
            if (etat.peutEcrire) {
                html += '<button type="button" class="btn btn-sm btn-outline-primary mt-1 carto-edit-conduite" data-id="' + c.id + '">Modifier</button>';
            }
            line.bindPopup(html);
            line.on('popupopen', function () {
                var btn = document.querySelector('.carto-edit-conduite[data-id="' + c.id + '"]');
                if (btn) btn.addEventListener('click', function () { ouvrirModalConduite(c); });
            });
            layerConduites.addLayer(line);
        });
    }

    function ouvrirModalSecteur(data) {
        el('cartoSecteurId').value = data.id || 0;
        el('cartoSecteurPolygone').value = JSON.stringify(data.polygone || []);
        el('cartoSecteurNom').value = data.nom || '';
        el('cartoSecteurDescription').value = data.description || '';
        el('cartoSecteurStatus').textContent = '';
        el('cartoSecteurDeleteBtn').classList.toggle('d-none', !(data.id > 0));
        bootstrap.Modal.getOrCreateInstance(el('cartoSecteurModal')).show();
    }

    function htmlStatsSecteur(s) {
        var lignes = [
            ['Compteurs', s.nb_compteurs + ' (' + s.nb_actifs + ' actifs)'],
            ['Consommation', s.consommation_m3 + ' m³'],
            ['Facturé', fcfa(s.montant_facture)],
            ['Versé', fcfa(s.montant_verse)],
            ['Impayé', fcfa(s.impaye)],
            ['Taux recouvrement', s.taux_recouvrement === null ? '—' : s.taux_recouvrement + ' %']
        ];
        var html = '<table class="table table-sm mb-1"><tbody>';
        lignes.forEach(function (l) {
            html += '<tr><td class="small text-muted">' + l[0] + '</td><td class="small text-end"><strong>' + esc(l[1]) + '</strong></td></tr>';
        });
        html += '</tbody></table>';
        var reseaux = Object.keys(s.par_reseau || {});
        if (reseaux.length) {
            html += '<div class="small text-muted">Réseaux : ' + reseaux.map(function (r) {
                return esc(r) + ' (' + s.par_reseau[r] + ')';
            }).join(', ') + '</div>';
        }
        return html;
    }

    function rendreSecteurs() {
        layerSecteurs.clearLayers();
        etat.secteurs.forEach(function (s) {
            var latlngs = (s.polygone || []).map(function (p) { return [p[1], p[0]]; });
            if (latlngs.length < 3) return;
            var poly = L.polygon(latlngs, { color: '#6a1b9a', weight: 2, fillOpacity: 0.08 });
            poly.bindPopup('<strong>' + esc(s.nom) + '</strong><br><em class="small">Calcul des statistiques…</em>');
            poly.on('popupopen', function () {
                var url = ajaxUrl + '?action=get_stats_secteur'
                    + '&mois=' + encodeURIComponent(etat.mois)
                    + '&polygone=' + encodeURIComponent(JSON.stringify(s.polygone));
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) return;
                        var html = '<strong>' + esc(s.nom) + '</strong>'
                            + '<div class="small text-muted mb-1">' + esc(etat.mois) + '</div>'
                            + htmlStatsSecteur(data.stats);
                        if (etat.peutEcrire) {
                            html += '<button type="button" class="btn btn-sm btn-outline-primary carto-edit-secteur" data-id="' + s.id + '">Modifier</button>';
                        }
                        poly.setPopupContent(html);
                        var btn = document.querySelector('.carto-edit-secteur[data-id="' + s.id + '"]');
                        if (btn) btn.addEventListener('click', function () { ouvrirModalSecteur(s); });
                    });
            });
            layerSecteurs.addLayer(poly);
        });
    }

    function majStats(stats) {
        if (!stats) return;
        el('cartoKpiGeo').textContent = stats.geolocalises + '/' + stats.total;
        el('cartoStatsGeoloc').textContent = stats.manquants > 0
            ? stats.manquants + ' compteurs restent à géolocaliser'
            : 'Tous les compteurs sont géolocalisés.';
    }

    function majKpis() {
        var impaye = 0, facture = 0, verse = 0;
        etat.compteurs.forEach(function (c) {
            impaye += parseFloat(c.impaye) || 0;
            facture += parseFloat(c.montant_total) || 0;
            verse += parseFloat(c.montant_verse) || 0;
        });
        el('cartoKpiImpaye').textContent = impaye >= 1000
            ? Math.round(impaye / 1000) + 'k'
            : Math.round(impaye);
        el('cartoKpiTaux').textContent = facture > 0 ? Math.round(verse * 100 / facture) + '%' : '—';
    }

    function majTuilesInfo(t) {
        calerTuiles(t);
        var infoEl = el('cartoTuilesInfo');
        if (!t || !t.disponible) {
            infoEl.textContent = 'Aucune tuile importée pour cet AEP — la carte affiche un fond vide.';
            return;
        }
        var poidsMo = (t.poids_octets / (1024 * 1024)).toFixed(1);
        infoEl.textContent = 'Zoom ' + t.zoom_min + ' à ' + t.zoom_max + ' · ' + t.nb_tuiles + ' tuiles · ' + poidsMo + ' Mo'
            + (t.date_import ? (' · importé le ' + t.date_import) : '');
    }

    function afficherErreur(message) {
        el('cartoStatsGeoloc').innerHTML = '<span class="text-danger">' + esc(message) + '</span>';
        el('cartoTuilesInfo').innerHTML = '<span class="text-danger">' + esc(message) + '</span>';
    }

    function majSelecteurMois(moisDisponibles, moisCourant) {
        var sel = el('cartoMois');
        if (sel.options.length && sel.value === moisCourant) return;
        sel.innerHTML = '';
        (moisDisponibles || []).forEach(function (m) {
            var opt = document.createElement('option');
            opt.value = m.mois;
            opt.textContent = m.mois + (parseInt(m.est_actif, 10) === 1 ? ' (actif)' : '');
            sel.appendChild(opt);
        });
        sel.value = moisCourant;
    }

    function ligneAnomalie(c, complement) {
        var geo = c.latitude && c.longitude;
        return '<div class="border-bottom py-1 small' + (geo ? ' carto-ano-zoom" style="cursor:pointer"'
            + ' data-lat="' + c.latitude + '" data-lon="' + c.longitude + '"' : '"') + '>'
            + '<strong>' + esc(c.nom_abone) + '</strong> · ' + esc(c.numero_compteur || 'sans numéro')
            + ' <span class="text-muted">(' + esc(c.nom_reseau) + ')</span>'
            + (complement ? ' <span class="text-warning-emphasis">' + complement + '</span>' : '')
            + '</div>';
    }

    function rendreAnomalies(a) {
        etat.anomalies = a;
        var total = a.sans_gps.length + a.rattachements.length + a.doublons.length + a.aberrants.length;
        el('cartoAnomaliesTotal').textContent = total + ' à examiner';
        el('cartoAnomaliesTotal').className = 'badge ' + (total > 0 ? 'bg-warning text-dark' : 'bg-success');

        el('cartoAnoSansGpsNb').textContent = a.sans_gps.length;
        el('cartoAnoRattachNb').textContent = a.rattachements.length;
        el('cartoAnoDoublonsNb').textContent = a.doublons.length;
        el('cartoAnoAberrantsNb').textContent = a.aberrants.length;

        el('cartoAnoSansGps').innerHTML = a.sans_gps.length
            ? a.sans_gps.map(function (c) { return ligneAnomalie(c, null); }).join('')
            : '<p class="small text-success mb-0">Tous les compteurs sont géolocalisés.</p>';

        el('cartoAnoRattach').innerHTML = a.rattachements.length
            ? '<p class="small text-muted">Ces compteurs sont géographiquement bien plus proches du barycentre d\'un autre réseau que du leur — rattachement à vérifier.</p>'
              + a.rattachements.map(function (c) {
                  return ligneAnomalie(c, '→ proche de ' + esc(c.reseau_suggere)
                      + ' (' + c.distance_suggere_m + ' m contre ' + c.distance_sien_m + ' m)');
              }).join('')
            : '<p class="small text-success mb-0">Aucune incohérence détectée.</p>';

        el('cartoAnoDoublons').innerHTML = a.doublons.length
            ? a.doublons.map(function (d) {
                  return '<div class="border-bottom py-1"><div class="small text-muted">Position ' + esc(d.position) + '</div>'
                      + d.compteurs.map(function (c) { return ligneAnomalie(c, null); }).join('') + '</div>';
              }).join('')
            : '<p class="small text-success mb-0">Aucun doublon de position.</p>';

        el('cartoAnoAberrants').innerHTML = a.aberrants.length
            ? a.aberrants.map(function (c) { return ligneAnomalie(c, 'à ' + c.distance_m + ' m du centre'); }).join('')
            : '<p class="small text-success mb-0">Aucune position aberrante.</p>';

        document.querySelectorAll('.carto-ano-zoom').forEach(function (row) {
            row.addEventListener('click', function () {
                map.setView([parseFloat(this.getAttribute('data-lat')), parseFloat(this.getAttribute('data-lon'))], 18);
                document.getElementById('cartoMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }

    function chargerAnomalies() {
        fetch(ajaxUrl + '?action=get_anomalies', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (data && data.ok) rendreAnomalies(data.anomalies); })
            .catch(function () { el('cartoAnomaliesTotal').textContent = 'erreur'; });
    }

    function chargerDonnees() {
        fetch(ajaxUrl + '?action=get_donnees' + (etat.mois ? '&mois=' + encodeURIComponent(etat.mois) : ''), { credentials: 'same-origin' })
            .then(lireJson)
            .then(function (data) {
                if (!data || !data.ok) {
                    afficherErreur((data && data.error) ? data.error : 'Chargement impossible.');
                    return;
                }
                etat.compteurs = data.compteurs || [];
                etat.ouvrages = data.ouvrages || [];
                etat.conduites = data.conduites || [];
                etat.secteurs = data.secteurs || [];
                etat.peutEcrire = !!data.peut_ecrire;
                etat.mois = data.mois_courant;
                majSelecteurMois(data.mois_disponibles, data.mois_courant);
                rendreListeReseaux();
                rendreCompteurs();
                rendreOuvrages();
                rendreConduites();
                rendreSecteurs();
                majLegende();
                majStats(data.stats_geoloc);
                majKpis();
                majTuilesInfo(data.tuiles);
                el('cartoDrawBtn').style.display = etat.peutEcrire ? '' : 'none';
                el('cartoSecteurBtn').style.display = etat.peutEcrire ? '' : 'none';
                var coords = toutesLesCoords();
                if (coords.length > 0) {
                    map.fitBounds(coords, { maxZoom: 17, padding: [20, 20] });
                } else if (data.tuiles && data.tuiles.emprise) {
                    // Aucun point encore saisi : on cadre sur la zone couverte
                    // par les tuiles, sinon la carte resterait sur une vue vide.
                    var e = data.tuiles.emprise;
                    map.fitBounds([[e.lat_min, e.lon_min], [e.lat_max, e.lon_max]], { padding: [20, 20] });
                }
            })
            .catch(function (err) {
                afficherErreur(err && err.message ? err.message : 'Erreur réseau.');
            });
    }

    // --- Réseaux : cases à cocher, pastilles de couleur et compte de compteurs ---
    function rendreListeReseaux() {
        var reseaux = construireCouleursReseau();
        var contenant = el('cartoReseauxListe');

        if (!reseaux.length) {
            contenant.innerHTML = '<div class="small text-muted">Aucun compteur géolocalisé pour l\'instant.</div>';
            return;
        }

        var comptes = {};
        etat.compteurs.forEach(function (c) {
            comptes[c.id_reseau] = (comptes[c.id_reseau] || 0) + 1;
        });

        contenant.innerHTML = reseaux.map(function (r) {
            var visible = reseauVisible(r.id);
            return '<label class="carto-reseau-row' + (visible ? '' : ' eteint') + '">'
                + '<input type="checkbox" class="form-check-input carto-reseau-check" data-id="' + esc(r.id) + '"'
                + (visible ? ' checked' : '') + '>'
                + '<span class="carto-reseau-pastille" style="background:' + r.couleur + '"></span>'
                + '<span class="carto-reseau-nom">' + esc(r.nom) + '</span>'
                + '<span class="carto-reseau-nb">' + (comptes[r.id] || 0) + '</span>'
                + '</label>';
        }).join('');

        Array.prototype.forEach.call(contenant.querySelectorAll('.carto-reseau-check'), function (cb) {
            cb.addEventListener('change', function () {
                etat.reseauxVisibles[this.getAttribute('data-id')] = this.checked;
                rafraichirApresFiltreReseau();
            });
        });
    }

    /** Les trois couches dépendent de la visibilité des réseaux. */
    function rafraichirApresFiltreReseau() {
        rendreListeReseaux();
        rendreCompteurs();
        rendreOuvrages();
        rendreConduites();
        majLegende();
    }

    function basculerTousReseaux(visible) {
        construireCouleursReseau().forEach(function (r) {
            etat.reseauxVisibles[r.id] = visible;
        });
        rafraichirApresFiltreReseau();
    }

    el('cartoReseauxTous').addEventListener('click', function (e) {
        e.preventDefault();
        basculerTousReseaux(true);
    });
    el('cartoReseauxAucun').addEventListener('click', function (e) {
        e.preventDefault();
        basculerTousReseaux(false);
    });

    // --- Thématique, mois et calques ---
    el('cartoThematique').addEventListener('change', function () {
        etat.thematique = this.value;
        rendreCompteurs();
        majLegende();
    });
    el('cartoMois').addEventListener('change', function () {
        etat.mois = this.value;
        chargerDonnees();
    });
    document.querySelectorAll('.carto-layer-toggle').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var layer = {
                compteurs: layerCompteurs, ouvrages: layerOuvrages,
                conduites: layerConduites, secteurs: layerSecteurs
            }[this.getAttribute('data-layer')];
            if (this.checked) { map.addLayer(layer); } else { map.removeLayer(layer); }
        });
    });

    // --- Ajout d'ouvrage ---
    function afficherBanniere(texte) {
        el('cartoModeInfo').textContent = texte;
        el('cartoModeInfo').style.display = 'block';
    }

    function quitterModes() {
        etat.mode = null;
        etat.addType = null;
        etat.drawPoints = [];
        el('cartoMap').classList.remove('carto-mode-add', 'carto-mode-draw');
        el('cartoModeInfo').style.display = 'none';
        el('cartoDrawBtn').classList.remove('actif');
        el('cartoSecteurBtn').classList.remove('actif');
        if (window._cartoDrawLine) { map.removeLayer(window._cartoDrawLine); window._cartoDrawLine = null; }
    }
    document.querySelectorAll('.carto-add-type').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            quitterModes();
            etat.mode = 'add';
            etat.addType = this.getAttribute('data-type');
            el('cartoMap').classList.add('carto-mode-add');
            afficherBanniere('Clique sur la carte pour positionner : ' + (TYPE_LABELS[etat.addType] || etat.addType) + ' — Échap pour annuler');
        });
    });

    // --- Tracé de conduite ---
    el('cartoDrawBtn').addEventListener('click', function () {
        if (etat.mode === 'draw') { quitterModes(); return; }
        quitterModes();
        etat.mode = 'draw';
        etat.drawPoints = [];
        el('cartoMap').classList.add('carto-mode-draw');
        el('cartoDrawBtn').classList.add('actif');
        afficherBanniere('Clique les points de la conduite, puis double-clique pour terminer — Échap pour annuler');
    });

    el('cartoSecteurBtn').addEventListener('click', function () {
        if (etat.mode === 'secteur') { quitterModes(); return; }
        quitterModes();
        etat.mode = 'secteur';
        etat.drawPoints = [];
        el('cartoMap').classList.add('carto-mode-draw');
        el('cartoSecteurBtn').classList.add('actif');
        afficherBanniere('Clique les sommets du secteur (3 minimum), puis double-clique pour fermer — Échap pour annuler');
    });

    map.on('click', function (e) {
        if (etat.mode === 'add') {
            var lat = e.latlng.lat, lon = e.latlng.lng;
            quitterModes();
            ouvrirModalOuvrage({ id: 0, type: etat.addType, latitude: lat, longitude: lon });
        } else if (etat.mode === 'draw' || etat.mode === 'secteur') {
            etat.drawPoints.push([e.latlng.lng, e.latlng.lat]);
            if (window._cartoDrawLine) map.removeLayer(window._cartoDrawLine);
            var latlngs = etat.drawPoints.map(function (p) { return [p[1], p[0]]; });
            window._cartoDrawLine = (etat.mode === 'secteur')
                ? L.polygon(latlngs, { color: '#6a1b9a', dashArray: '6,4', fillOpacity: 0.08 }).addTo(map)
                : L.polyline(latlngs, { color: '#d32f2f', dashArray: '6,4' }).addTo(map);
        }
    });
    map.on('dblclick', function () {
        if (etat.mode === 'draw' && etat.drawPoints.length >= 2) {
            var points = etat.drawPoints.slice();
            quitterModes();
            ouvrirModalConduite({ id: 0, trace: points, longueur_m: 0 });
        } else if (etat.mode === 'secteur' && etat.drawPoints.length >= 3) {
            var sommets = etat.drawPoints.slice();
            quitterModes();
            ouvrirModalSecteur({ id: 0, polygone: sommets });
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') quitterModes();
    });

    // --- Sauvegarde ouvrage ---
    el('cartoOuvrageSaveBtn').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('action', 'save_ouvrage');
        fd.append('_csrf', csrfToken);
        fd.append('id', el('cartoOuvrageId').value);
        fd.append('latitude', el('cartoOuvrageLat').value);
        fd.append('longitude', el('cartoOuvrageLon').value);
        fd.append('type', el('cartoOuvrageType').value);
        fd.append('nom', el('cartoOuvrageNom').value);
        fd.append('id_reseau', el('cartoOuvrageReseau').value);
        fd.append('description', el('cartoOuvrageDescription').value);
        el('cartoOuvrageStatus').textContent = 'Enregistrement…';
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    bootstrap.Modal.getOrCreateInstance(el('cartoOuvrageModal')).hide();
                    chargerDonnees();
                } else {
                    el('cartoOuvrageStatus').textContent = (data && data.error) ? data.error : 'Échec.';
                }
            });
    });
    el('cartoOuvrageDeleteBtn').addEventListener('click', function () {
        if (!confirm('Supprimer cet ouvrage ?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_ouvrage');
        fd.append('_csrf', csrfToken);
        fd.append('id', el('cartoOuvrageId').value);
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function () {
                bootstrap.Modal.getOrCreateInstance(el('cartoOuvrageModal')).hide();
                chargerDonnees();
            });
    });

    // --- Sauvegarde conduite ---
    el('cartoConduiteSaveBtn').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('action', 'save_conduite');
        fd.append('_csrf', csrfToken);
        fd.append('id', el('cartoConduiteId').value);
        fd.append('points', el('cartoConduitePoints').value);
        fd.append('nom', el('cartoConduiteNom').value);
        fd.append('id_reseau', el('cartoConduiteReseau').value);
        fd.append('description', el('cartoConduiteDescription').value);
        el('cartoConduiteStatus').textContent = 'Enregistrement…';
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    bootstrap.Modal.getOrCreateInstance(el('cartoConduiteModal')).hide();
                    chargerDonnees();
                } else {
                    el('cartoConduiteStatus').textContent = (data && data.error) ? data.error : 'Échec.';
                }
            });
    });
    el('cartoConduiteDeleteBtn').addEventListener('click', function () {
        if (!confirm('Supprimer cette conduite ?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_conduite');
        fd.append('_csrf', csrfToken);
        fd.append('id', el('cartoConduiteId').value);
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function () {
                bootstrap.Modal.getOrCreateInstance(el('cartoConduiteModal')).hide();
                chargerDonnees();
            });
    });

    // --- Sauvegarde secteur ---
    el('cartoSecteurSaveBtn').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('action', 'save_secteur');
        fd.append('_csrf', csrfToken);
        fd.append('id', el('cartoSecteurId').value);
        fd.append('polygone', el('cartoSecteurPolygone').value);
        fd.append('nom', el('cartoSecteurNom').value);
        fd.append('description', el('cartoSecteurDescription').value);
        el('cartoSecteurStatus').textContent = 'Enregistrement…';
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    bootstrap.Modal.getOrCreateInstance(el('cartoSecteurModal')).hide();
                    chargerDonnees();
                } else {
                    el('cartoSecteurStatus').textContent = (data && data.error) ? data.error : 'Échec.';
                }
            });
    });
    el('cartoSecteurDeleteBtn').addEventListener('click', function () {
        if (!confirm('Supprimer ce secteur ?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_secteur');
        fd.append('_csrf', csrfToken);
        fd.append('id', el('cartoSecteurId').value);
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function () {
                bootstrap.Modal.getOrCreateInstance(el('cartoSecteurModal')).hide();
                chargerDonnees();
            });
    });

    // --- Import / suppression des tuiles ---
    el('cartoUploadForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var champ = el('cartoFichierTuiles');
        var fichier = champ.files[0];
        if (!fichier) return;

        // Refus immediat si le fichier depasse la limite du serveur : inutile
        // de televerser 30 Mo pour se faire rejeter a l'arrivee.
        var maxOctets = parseInt(champ.getAttribute('data-max-octets'), 10);
        if (maxOctets > 0 && fichier.size > maxOctets) {
            el('cartoUploadStatus').innerHTML = '<span class="text-danger">Archive de '
                + (fichier.size / 1048576).toFixed(1) + ' Mo : dépasse la limite de '
                + Math.round(maxOctets / 1048576) + ' Mo du serveur.</span>';
            return;
        }

        var fd = new FormData();
        fd.append('action', 'upload_tuiles');
        fd.append('_csrf', csrfToken);
        fd.append('fichier_tuiles', fichier);
        el('cartoUploadStatus').textContent = 'Import en cours… (peut prendre du temps selon la taille)';
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(lireJson)
            .then(function (data) {
                if (data && data.ok) {
                    el('cartoUploadStatus').textContent = data.acceptes + ' tuile(s) importée(s), ' + data.rejetes + ' rejetée(s).';
                    majTuilesInfo(data.tuiles);
                    tileLayer.redraw();
                } else {
                    el('cartoUploadStatus').textContent = (data && data.error) ? data.error : 'Échec de l\'import.';
                }
            })
            .catch(function () { el('cartoUploadStatus').textContent = 'Erreur réseau (fichier trop volumineux ?).'; });
    });
    el('cartoDeleteTuilesBtn').addEventListener('click', function () {
        if (!confirm('Supprimer toutes les tuiles de cet AEP ?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_tuiles');
        fd.append('_csrf', csrfToken);
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function () {
                majTuilesInfo({ disponible: false });
                tileLayer.redraw();
            });
    });

    // --- Export ---
    function telecharger(nomFichier, contenu, type) {
        var blob = new Blob([contenu], { type: type });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nomFichier;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    el('cartoExportCompteursCsv').addEventListener('click', function (e) {
        e.preventDefault();
        var lignes = ['numero_compteur;nom_abonne;reseau;etat;latitude;longitude'];
        etat.compteurs.forEach(function (c) {
            lignes.push([c.numero_compteur, c.nom_abone, c.nom_reseau, c.etat, c.latitude, c.longitude].join(';'));
        });
        telecharger('compteurs_geolocalises.csv', lignes.join('\n'), 'text/csv;charset=utf-8;');
    });
    function geojsonPoints(items, propsFn) {
        return JSON.stringify({
            type: 'FeatureCollection',
            features: items.map(function (it) {
                return { type: 'Feature', geometry: { type: 'Point', coordinates: [parseFloat(it.longitude), parseFloat(it.latitude)] }, properties: propsFn(it) };
            })
        }, null, 2);
    }
    el('cartoExportCompteursGeojson').addEventListener('click', function (e) {
        e.preventDefault();
        var geojson = geojsonPoints(etat.compteurs, function (c) {
            return { numero_compteur: c.numero_compteur, nom_abonne: c.nom_abone, reseau: c.nom_reseau, etat: c.etat };
        });
        telecharger('compteurs.geojson', geojson, 'application/geo+json');
    });
    el('cartoExportOuvragesGeojson').addEventListener('click', function (e) {
        e.preventDefault();
        var geojson = geojsonPoints(etat.ouvrages, function (o) {
            return { nom: o.nom, type: o.type, reseau: o.nom_reseau, description: o.description };
        });
        telecharger('ouvrages.geojson', geojson, 'application/geo+json');
    });
    el('cartoExportConduitesGeojson').addEventListener('click', function (e) {
        e.preventDefault();
        var geojson = JSON.stringify({
            type: 'FeatureCollection',
            features: etat.conduites.map(function (c) {
                return { type: 'Feature', geometry: { type: 'LineString', coordinates: c.trace }, properties: { nom: c.nom, longueur_m: c.longueur_m, reseau: c.nom_reseau } };
            })
        }, null, 2);
        telecharger('conduites.geojson', geojson, 'application/geo+json');
    });

    el('cartoExportSansGps').addEventListener('click', function () {
        if (!etat.anomalies) return;
        var lignes = ['numero_compteur;nom_abonne;reseau'];
        etat.anomalies.sans_gps.forEach(function (c) {
            lignes.push([c.numero_compteur, c.nom_abone, c.nom_reseau].join(';'));
        });
        telecharger('compteurs_sans_position.csv', lignes.join('\n'), 'text/csv;charset=utf-8;');
    });

    // --- Onglets des anomalies ---
    document.querySelectorAll('.carto-ano-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.carto-ano-tab').forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');
            var cible = this.getAttribute('data-cible');
            ['cartoAnoSansGps', 'cartoAnoRattach', 'cartoAnoDoublons', 'cartoAnoAberrants'].forEach(function (id) {
                el(id).style.display = (id === cible) ? '' : 'none';
            });
        });
    });

    // --- Panneau latéral, plein écran, gestion des tuiles ---
    el('cartoSidebarToggle').addEventListener('click', function () {
        el('cartoShell').classList.toggle('carto-sidebar-hidden');
        setTimeout(function () { map.invalidateSize(); }, 220);
    });
    el('cartoFullscreenBtn').addEventListener('click', function () {
        var page = el('cartoPage');
        page.classList.toggle('carto-fullscreen');
        var plein = page.classList.contains('carto-fullscreen');
        this.innerHTML = plein ? '<i class="bi bi-fullscreen-exit"></i>' : '<i class="bi bi-arrows-fullscreen"></i>';
        setTimeout(function () { map.invalidateSize(); }, 100);
    });
    el('cartoTuilesToggle').addEventListener('click', function (e) {
        e.preventDefault();
        var g = el('cartoTuilesGestion');
        var ouvert = g.style.display !== 'none';
        g.style.display = ouvert ? 'none' : '';
        this.textContent = ouvert ? 'Gérer' : 'Fermer';
    });

    // --- Recherche d'un abonné / compteur ---
    var champRecherche = el('cartoRecherche');
    var boiteResultats = el('cartoRechercheResultats');

    function fermerResultats() { boiteResultats.style.display = 'none'; }

    champRecherche.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        if (q.length < 2) { fermerResultats(); return; }
        var trouves = etat.compteurs.filter(function (c) {
            return (c.nom_abone || '').toLowerCase().indexOf(q) !== -1
                || (c.numero_compteur || '').toLowerCase().indexOf(q) !== -1;
        }).slice(0, 12);

        if (!trouves.length) {
            boiteResultats.innerHTML = '<div class="text-muted">Aucun compteur géolocalisé ne correspond.</div>';
            boiteResultats.style.display = 'block';
            return;
        }
        boiteResultats.innerHTML = trouves.map(function (c, i) {
            return '<div data-idx="' + i + '"><strong>' + esc(c.nom_abone) + '</strong>'
                + '<span class="text-muted"> · ' + esc(c.numero_compteur || 'sans n°') + ' · ' + esc(c.nom_reseau) + '</span></div>';
        }).join('');
        boiteResultats.style.display = 'block';
        boiteResultats.querySelectorAll('div[data-idx]').forEach(function (ligne) {
            ligne.addEventListener('click', function () {
                var c = trouves[parseInt(this.getAttribute('data-idx'), 10)];
                map.setView([c.latitude, c.longitude], 18);
                fermerResultats();
                champRecherche.value = c.nom_abone;
            });
        });
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.carto-search')) fermerResultats();
    });

    chargerDonnees();
    chargerAnomalies();
})();
</script>
