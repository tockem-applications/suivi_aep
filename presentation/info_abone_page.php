<?php
/**
 * Fiche abonné — disposition type GCP (menu latéral + zone principale).
 */
@include_once(__DIR__ . '/../traitement/abone_t.php');
@include_once('traitement/abone_t.php');
@include_once(__DIR__ . '/../donnees/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/../donnees/branchement_abonne.php');
@include_once('donnees/branchement_abonne.php');
@include_once(__DIR__ . '/../donnees/compteur_remplacement.php');
@include_once('donnees/compteur_remplacement.php');

$id_abone = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$section = isset($_GET['section']) ? preg_replace('/[^a-z_]/', '', $_GET['section']) : 'accueil';
$layout_classic = isset($_GET['layout']) && $_GET['layout'] === 'classic';

$sections_nav = array(
    'accueil' => array('label' => 'Accueil', 'icon' => 'bi-house-door'),
    'branchement' => array('label' => 'Branchement', 'icon' => 'bi-plug'),
    'index' => array('label' => 'Index & relevés', 'icon' => 'bi-speedometer2'),
    'fiche' => array('label' => 'Fiche abonné', 'icon' => 'bi-person-gear'),
);

$sections_recouvrement = array(
    'analyse_penalite' => array('label' => 'Analyse avant pénalité', 'icon' => 'bi-graph-up-arrow'),
    'liste_recouvrements' => array('label' => 'Liste des recouvrements', 'icon' => 'bi-table'),
    'factures' => array('label' => 'Ajouter / retirer factures', 'icon' => 'bi-receipt-cutoff'),
);

function ia_section_meta($section, $sections_nav, $sections_recouvrement)
{
    if (isset($sections_nav[$section])) {
        return $sections_nav[$section];
    }
    if (isset($sections_recouvrement[$section])) {
        return $sections_recouvrement[$section];
    }
    return null;
}

$recouvrement_section_keys = array_keys($sections_recouvrement);

if (!isset($sections_nav[$section]) && !isset($sections_recouvrement[$section])) {
    $section = 'accueil';
}

$ctx = Abone_t::loadAboneContext($id_abone);
if (!$ctx) {
    echo '<div class="container py-5"><div class="alert alert-danger">Abonné introuvable.</div>
        <a href="?page=abonne" class="btn btn-secondary">Retour à la liste</a></div>';
    return;
}

$data = $ctx['data'];
$id_compteur = (int) $ctx['id_compteur'];
$branchement = $ctx['branchement'];

/**
 * Position GPS du compteur, ou null si aucune position exploitable.
 *
 * Même garde-fou qu'ailleurs dans le projet : (0,0) est ce qu'écrit
 * l'application mobile quand elle n'a rien capté, et l'emprise du Cameroun
 * écarte une latitude et une longitude inversées — l'erreur la plus courante
 * sur ce type de donnée. Bornes distinctes par axe, sinon l'inversion passe.
 */
$ia_position = null;
$ia_lat = isset($data['latitude']) ? (float) $data['latitude'] : 0.0;
$ia_lon = isset($data['longitude']) ? (float) $data['longitude'] : 0.0;
if ($ia_lat >= 1.0 && $ia_lat <= 14.0 && $ia_lon >= 7.0 && $ia_lon <= 17.0) {
    $ia_position = array(
        'texte' => number_format($ia_lat, 6, ',', ' ') . ' · ' . number_format($ia_lon, 6, ',', ' '),
        // Le point décimal reste anglo-saxon dans l'URL : c'est ce qu'attend OSM.
        'osm' => 'https://www.openstreetmap.org/?mlat=' . rawurlencode(sprintf('%.6F', $ia_lat))
            . '&mlon=' . rawurlencode(sprintf('%.6F', $ia_lon)) . '#map=18/'
            . sprintf('%.6F', $ia_lat) . '/' . sprintf('%.6F', $ia_lon),
    );
}

$stats = Manager::prepare_query(
    "SELECT
        COUNT(DISTINCT vaf.id_mois) AS nb_mois,
        COALESCE(SUM(vaf.montant_total), 0) AS total_facture,
        COALESCE(SUM(vaf.montant_verse), 0) AS total_verse,
        COALESCE(SUM(vaf.montant_restant), 0) AS total_restant,
        COALESCE(SUM(vaf.consommation), 0) AS total_conso,
        COUNT(CASE WHEN vaf.montant_restant > 0 THEN 1 END) AS nb_impayes
     FROM vue_abones_facturation vaf WHERE vaf.id_abone = ?",
    array($id_abone)
);
$st = $stats ? $stats->fetch(PDO::FETCH_ASSOC) : array();

$tarifDiff = Manager::prepare_query(
    "SELECT tarif_differencie_autorise FROM abone WHERE id = ?",
    array($id_abone)
)->fetch();
$tarifDiffAutorise = $tarifDiff ? (int) $tarifDiff['tarif_differencie_autorise'] : 1;

function ia_fmt_fcfa($n)
{
    return number_format((float) $n, 0, ',', ' ');
}

function ia_section_url($id, $sec)
{
    return '?page=info_abone&id=' . (int) $id . '&section=' . urlencode($sec);
}

$etatClass = ($data['etat'] === 'actif') ? 'success' : (($data['etat'] === 'suspendu') ? 'warning' : 'secondary');
$nom_abone = htmlspecialchars($data['nom'], ENT_QUOTES, 'UTF-8');

// Remplacement du compteur : situation courante (index minimal de dépose) et
// historique des remplacements déjà effectués.
$ia_situation_compteur = CompteurRemplacement::getSituation($id_abone);
$ia_remplacements = CompteurRemplacement::getHistorique($id_abone);
?>

<link rel="stylesheet" href="presentation/assets/css/info_abone.css">

<?php if ($layout_classic): ?>
<div class="container-fluid p-4">
    <a href="<?php echo ia_section_url($id_abone, 'accueil'); ?>" class="btn btn-outline-primary btn-sm mb-3">
        <i class="bi bi-layout-sidebar"></i> Vue fiche
    </a>
    <a href="?page=abonne" class="btn btn-secondary btn-sm mb-3">< Liste des abonnés</a>
    <div class="row">
        <article class="col-12 col-xl-5 border-end">
            <?php $id_compteur = Abone_t::afficheInfoAbone($id_abone, 'all'); ?>
        </article>
        <aside class="col-12 col-xl-7">
            <script src="js/penalty_evaluation.js"></script>
            <?php echo Abone_t::afficheInputRecouvrementAbone($id_compteur); ?>
        </aside>
    </div>
</div>
<?php return; endif; ?>

<style>
    .ia-shell { display: flex; min-height: calc(100vh - 120px); background: #f8f9fa; margin: -0.5rem -0.75rem 0; }
    .ia-nav { width: 260px; flex-shrink: 0; background: #fff; border-right: 1px solid #dadce0; padding: 0; }
    .ia-nav-header { padding: 1rem 1.25rem; border-bottom: 1px solid #e8eaed; }
    .ia-nav-title { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #5f6368; margin: 1rem 1.25rem 0.35rem; }
    .ia-nav-link { display: flex; align-items: center; gap: 0.65rem; padding: 0.55rem 1.25rem; color: #3c4043; text-decoration: none; font-size: 0.875rem; border-left: 3px solid transparent; }
    .ia-nav-link:hover { background: #f1f3f4; color: #1a73e8; }
    .ia-nav-link.active { background: #e8f0fe; color: #1a73e8; border-left-color: #1a73e8; font-weight: 500; }
    .ia-nav-link i { font-size: 1.1rem; width: 1.25rem; text-align: center; opacity: .85; }
    .ia-main { flex: 1; min-width: 0; padding: 1.25rem 1.5rem 2rem; overflow-x: auto; }
    .ia-topbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1.25rem; }
    .ia-breadcrumb { font-size: 0.8rem; color: #5f6368; margin-bottom: 0.25rem; }
    .ia-breadcrumb a { color: #1a73e8; text-decoration: none; }
    .ia-page-title { font-size: 1.35rem; font-weight: 400; color: #202124; margin: 0; }
    @media (max-width: 991.98px) {
        .ia-shell { flex-direction: column; }
        .ia-nav { width: 100%; border-right: none; border-bottom: 1px solid #dadce0; }
        .ia-nav-links { display: flex; flex-wrap: wrap; padding: 0.5rem; gap: 0.25rem; }
        .ia-nav-title { display: none; }
        .ia-nav-link { border-left: none; border-radius: 999px; padding: 0.4rem 0.85rem; }
        .ia-nav-link.active { border-left: none; }
    }
</style>

<div class="ia-shell">
    <nav class="ia-nav" aria-label="Navigation fiche abonné">
        <div class="ia-nav-header">
            <a href="?page=abonne" class="text-decoration-none small text-muted d-inline-flex align-items-center gap-1 mb-2">
                <i class="bi bi-arrow-left"></i> Abonnés
            </a>
            <div class="fw-semibold text-truncate" title="<?php echo $nom_abone; ?>"><?php echo $nom_abone; ?></div>
            <div class="small text-muted mt-1">
                <span class="badge bg-<?php echo $etatClass; ?>"><?php echo htmlspecialchars($data['etat'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="ms-1"><?php echo htmlspecialchars($data['reseau'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="ia-nav-title">Navigation</div>
        <div class="ia-nav-links pb-2">
            <?php foreach ($sections_nav as $key => $meta): ?>
                <a class="ia-nav-link<?php echo $key === $section ? ' active' : ''; ?>"
                    href="<?php echo ia_section_url($id_abone, $key); ?>">
                    <i class="bi <?php echo $meta['icon']; ?>"></i>
                    <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="ia-nav-title">Recouvrement</div>
        <div class="ia-nav-links pb-2">
            <?php foreach ($sections_recouvrement as $key => $meta): ?>
                <a class="ia-nav-link<?php echo $key === $section ? ' active' : ''; ?>"
                    href="<?php echo ia_section_url($id_abone, $key); ?>">
                    <i class="bi <?php echo $meta['icon']; ?>"></i>
                    <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="ia-nav-title">Autres vues</div>
        <div class="ia-nav-links pb-3">
            <a class="ia-nav-link" href="?page=info_abone&id=<?php echo $id_abone; ?>&layout=classic">
                <i class="bi bi-columns"></i> Vue classique
            </a>
        </div>
    </nav>

    <main class="ia-main">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="ia-topbar">
            <div>
                <div class="ia-breadcrumb">
                    <a href="?page=abonne">Abonnés</a>
                    <span class="mx-1">/</span>
                    <span><?php echo $nom_abone; ?></span>
                    <?php if ($section !== 'accueil'): ?>
                        <span class="mx-1">/</span>
                        <span><?php $ia_meta = ia_section_meta($section, $sections_nav, $sections_recouvrement); echo htmlspecialchars($ia_meta ? $ia_meta['label'] : '', ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="ia-page-title">
                    <?php
                    if ($section === 'accueil') {
                        echo $nom_abone;
                    } else {
                        $ia_meta = ia_section_meta($section, $sections_nav, $sections_recouvrement);
                        echo htmlspecialchars($ia_meta ? $ia_meta['label'] : '', ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                </h1>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="https://wa.me/237<?php echo htmlspecialchars($data['numero_telephone'], ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
                <a href="?page=releves" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-speedometer"></i> Relevés
                </a>
                <?php if ($ia_situation_compteur !== null): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal"
                        data-bs-target="#modalChangerCompteur">
                        <i class="bi bi-arrow-repeat"></i> Changer le compteur
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($ia_situation_compteur !== null): ?>
            <!-- Modal : remplacement du compteur -->
            <div class="modal fade" id="modalChangerCompteur" tabindex="-1" aria-labelledby="modalChangerCompteurLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="post" action="traitement/compteur_remplacement_t.php"
                            onsubmit="return confirm('Confirmer le remplacement du compteur ? L\'ancien compteur sera clos à son index de dépose.');">
                            <input type="hidden" name="action" value="remplacer_compteur">
                            <input type="hidden" name="id_abone" value="<?php echo (int) $id_abone; ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalChangerCompteurLabel">
                                    <i class="bi bi-arrow-repeat me-1"></i> Changer le compteur de <?php echo $nom_abone; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-light border small mb-3">
                                    <div class="fw-semibold mb-1"><i class="bi bi-speedometer2 me-1"></i> Compteur actuel : n° <?php echo htmlspecialchars($ia_situation_compteur['numero_compteur'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    Dernier index : <?php echo CompteurRemplacement::formatIndex($ia_situation_compteur['derniers_index']); ?> m³.
                                    <?php if ($ia_situation_compteur['releve_courant'] !== null): ?>
                                        Relevé du mois en cours (<?php echo htmlspecialchars(getLetterMonth($ia_situation_compteur['releve_courant']['mois']), ENT_QUOTES, 'UTF-8'); ?>) :
                                        ancien index <?php echo CompteurRemplacement::formatIndex($ia_situation_compteur['releve_courant']['ancien_index']); ?>,
                                        nouvel index <?php echo CompteurRemplacement::formatIndex($ia_situation_compteur['releve_courant']['nouvel_index']); ?>.
                                        Ce relevé sera arrêté à l'index de dépose : la consommation de l'ancien compteur est facturée ce mois-ci.
                                    <?php else: ?>
                                        Aucun relevé ouvert sur le mois en cours pour ce compteur.
                                    <?php endif; ?>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="cc_index_depose" class="form-label fw-semibold">Index de dépose de l'ancien compteur <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="<?php echo htmlspecialchars(sprintf('%.2F', (float) $ia_situation_compteur['index_minimum']), ENT_QUOTES, 'UTF-8'); ?>"
                                            class="form-control" id="cc_index_depose" name="index_depose" required
                                            value="<?php echo htmlspecialchars(sprintf('%.2F', max((float) $ia_situation_compteur['derniers_index'], (float) $ia_situation_compteur['index_minimum'])), ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="form-text">Index lu sur l'ancien compteur au moment du retrait (minimum <?php echo CompteurRemplacement::formatIndex($ia_situation_compteur['index_minimum']); ?>).</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cc_date" class="form-label fw-semibold">Date du remplacement <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="cc_date" name="date_remplacement" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cc_numero" class="form-label fw-semibold">Numéro du nouveau compteur <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="cc_numero" name="numero_compteur" maxlength="16" required autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cc_index_initial" class="form-label fw-semibold">Index de départ du nouveau compteur <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="cc_index_initial" name="index_initial" required value="0">
                                        <div class="form-text">Le prochain mois de facturation partira de cet index.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cc_latitude" class="form-label">Latitude</label>
                                        <input type="text" class="form-control" id="cc_latitude" name="latitude" inputmode="decimal"
                                            value="<?php echo $ia_situation_compteur['latitude'] !== null ? htmlspecialchars((string) $ia_situation_compteur['latitude'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cc_longitude" class="form-label">Longitude</label>
                                        <input type="text" class="form-control" id="cc_longitude" name="longitude" inputmode="decimal"
                                            value="<?php echo $ia_situation_compteur['longitude'] !== null ? htmlspecialchars((string) $ia_situation_compteur['longitude'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                        <div class="form-text">Position reprise de l'ancien compteur si elle n'est pas modifiée.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="cc_motif" class="form-label">Motif</label>
                                        <input type="text" class="form-control" id="cc_motif" name="motif" maxlength="255"
                                            placeholder="Compteur bloqué, cassé, illisible…">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-arrow-repeat me-1"></i> Remplacer le compteur
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($section === 'accueil'): ?>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="ia-kpi">
                        <div class="ia-kpi-label">N° compteur</div>
                        <div class="ia-kpi-value"><?php echo htmlspecialchars($data['numero_compteur'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($ia_remplacements)): ?>
                            <div class="small text-muted mt-1" title="Historique des remplacements dans « Index & relevés »">
                                <i class="bi bi-arrow-repeat"></i>
                                Posé le <?php echo date('d/m/Y', strtotime($ia_remplacements[0]['date_remplacement'])); ?>
                                (ancien n° <?php echo htmlspecialchars($ia_remplacements[0]['ancien_numero'], ENT_QUOTES, 'UTF-8'); ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="ia-kpi">
                        <div class="ia-kpi-label">Dernier index</div>
                        <div class="ia-kpi-value"><?php echo number_format((float) $data['derniers_index'], 2, ',', ' '); ?> m³</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="ia-kpi">
                        <div class="ia-kpi-label">Consommation totale</div>
                        <div class="ia-kpi-value"><?php echo number_format((float) (isset($st['total_conso']) ? $st['total_conso'] : $data['consommation']), 2, ',', ' '); ?> m³</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="ia-kpi">
                        <div class="ia-kpi-label">Reste à payer</div>
                        <div class="ia-kpi-value <?php echo ((float) (isset($st['total_restant']) ? $st['total_restant'] : 0) > 0) ? 'text-danger' : 'text-success'; ?>">
                            <?php echo ia_fmt_fcfa(isset($st['total_restant']) ? $st['total_restant'] : 0); ?> FCFA
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="ia-card mb-3">
                        <div class="ia-card-header">Informations essentielles</div>
                        <div class="ia-card-body">
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4 text-muted">Nom</dt>
                                <dd class="col-sm-8 fw-semibold"><?php echo $nom_abone; ?></dd>
                                <dt class="col-sm-4 text-muted">Téléphone</dt>
                                <dd class="col-sm-8">
                                    <a href="tel:<?php echo htmlspecialchars($data['numero_telephone'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($data['numero_telephone'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </dd>
                                <dt class="col-sm-4 text-muted">Réseau</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($data['reseau'], ENT_QUOTES, 'UTF-8'); ?></dd>
                                <dt class="col-sm-4 text-muted">Position GPS</dt>
                                <dd class="col-sm-8">
                                    <?php if ($ia_position !== null): ?>
                                        <span class="font-monospace"><?php echo $ia_position['texte']; ?></span>
                                        <a class="ms-2 text-decoration-none"
                                            href="<?php echo htmlspecialchars($ia_position['osm'], ENT_QUOTES, 'UTF-8'); ?>"
                                            target="_blank" rel="noopener"
                                            title="Ouvrir dans OpenStreetMap (nécessite une connexion)">
                                            <i class="bi bi-geo-alt-fill"></i> Voir
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Non relevée</span>
                                        <i class="bi bi-info-circle text-muted ms-1"
                                            title="La position est captée par l'application mobile lors d'un relevé, quand le GPS est activé pour la tournée."></i>
                                    <?php endif; ?>
                                </dd>
                                <dt class="col-sm-4 text-muted">État</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-<?php echo $etatClass; ?>"><?php echo htmlspecialchars($data['etat'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </dd>
                                <dt class="col-sm-4 text-muted">Tarif différencié</dt>
                                <dd class="col-sm-8">
                                    <span class="badge <?php echo $tarifDiffAutorise ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $tarifDiffAutorise ? 'Autorisé' : 'Non autorisé'; ?>
                                    </span>
                                </dd>
                                <dt class="col-sm-4 text-muted">Branchement</dt>
                                <dd class="col-sm-8">
                                    <?php if ($branchement): ?>
                                        <?php echo htmlspecialchars($branchement['quartier'], ENT_QUOTES, 'UTF-8'); ?>
                                        — <?php echo ia_fmt_fcfa($branchement['versement_fcfa']); ?> FCFA
                                    <?php else: ?>
                                        <span class="text-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="ia-card mb-3">
                        <div class="ia-card-header">Synthèse financière</div>
                        <div class="ia-card-body">
                            <ul class="list-unstyled mb-0 small">
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted">Mois facturés</span>
                                    <strong><?php echo (int) (isset($st['nb_mois']) ? $st['nb_mois'] : $data['duree']); ?></strong>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted">Total facturé</span>
                                    <strong><?php echo ia_fmt_fcfa(isset($st['total_facture']) ? $st['total_facture'] : 0); ?> FCFA</strong>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted">Total versé</span>
                                    <strong class="text-success"><?php echo ia_fmt_fcfa(isset($st['total_verse']) ? $st['total_verse'] : $data['montant_verse']); ?> FCFA</strong>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted">Mois impayés</span>
                                    <strong class="<?php echo ((int) (isset($st['nb_impayes']) ? $st['nb_impayes'] : 0) > 0) ? 'text-danger' : ''; ?>">
                                        <?php echo (int) (isset($st['nb_impayes']) ? $st['nb_impayes'] : 0); ?>
                                    </strong>
                                </li>
                                <li class="d-flex justify-content-between py-2">
                                    <span class="text-muted">Dernier paiement</span>
                                    <strong><?php echo $data['date_paiement'] ? htmlspecialchars($data['date_paiement'], ENT_QUOTES, 'UTF-8') : '—'; ?></strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="<?php echo ia_section_url($id_abone, 'liste_recouvrements'); ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-cash-coin me-1"></i> Saisir un recouvrement
                        </a>
                        <a href="<?php echo ia_section_url($id_abone, 'fiche'); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil me-1"></i> Modifier la fiche
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <?php if ($section === 'analyse_penalite'): ?>
                <script src="js/penalty_evaluation.js"></script>
            <?php endif; ?>
            <div class="ia-section-content">
                <?php
                if (in_array($section, $recouvrement_section_keys, true)) {
                    echo Abone_t::afficheInputRecouvrementAbone($id_compteur, $section);
                } else {
                    Abone_t::afficheInfoAbone($id_abone, $section);
                }
                ?>
            </div>
        <?php endif; ?>
    </main>
</div>

