<?php
/**
 * Avis de coupure — sélection des abonnés à couper pour un mois de facturation
 * et édition des avis au format PDF (deux avis par page A4).
 */

@include_once(__DIR__ . '/../donnees/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/../donnees/mois_facturation.php');
@include_once('donnees/mois_facturation.php');
@include_once(__DIR__ . '/../donnees/avis_coupure.php');
@include_once('donnees/avis_coupure.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$libeleAep = isset($_SESSION['libele_aep']) ? $_SESSION['libele_aep'] : '';

if (!$aepId) {
    echo '<div class="container py-5"><div class="alert alert-warning">Sélectionnez un AEP pour gérer les avis de coupure.</div>'
        . '<a href="?page=aep_dashboard" class="btn btn-primary btn-sm mt-2">Tableau de bord</a></div>';
    return;
}

AvisCoupure::ensureTables();

/** Libellé lisible d'un mois de facturation. */
function ac_mois_label($mois)
{
    $label = function_exists('getLetterMonth') ? getLetterMonth($mois) : '';
    return $label !== '' ? $label : (string) $mois;
}

function ac_fcfa($montant)
{
    return number_format((float) $montant, 0, ',', ' ');
}

$liste_mois = MoisFacturation::getOrderedMonthList($aepId)->fetchAll(PDO::FETCH_ASSOC);

// Mois de travail : celui demandé, sinon le mois actif de l'AEP, sinon le plus récent.
$id_mois = isset($_GET['id_mois']) ? (int) $_GET['id_mois'] : 0;
$ids_mois_valides = array();
foreach ($liste_mois as $m) {
    $ids_mois_valides[] = (int) $m['id'];
}
if ($id_mois <= 0 || !in_array($id_mois, $ids_mois_valides, true)) {
    $id_mois = 0;
    $actif = Manager::prepare_query(
        "SELECT m.id FROM mois_facturation m
           INNER JOIN constante_reseau c ON c.id = m.id_constante
          WHERE c.id_aep = ? AND m.est_actif = 1
          ORDER BY m.mois DESC LIMIT 1",
        array($aepId)
    )->fetch(PDO::FETCH_ASSOC);
    if ($actif) {
        $id_mois = (int) $actif['id'];
    } elseif (!empty($ids_mois_valides)) {
        $id_mois = $ids_mois_valides[0];
    }
}

if ($id_mois <= 0) {
    echo '<div class="container py-5"><div class="alert alert-warning">'
        . "Aucun mois de facturation n'est disponible pour cet AEP : créez-en un avant d'éditer des avis de coupure."
        . '</div><a href="?list=mois_facturation" class="btn btn-primary btn-sm mt-2">Mois facturés</a></div>';
    return;
}

$mois_valeur = AvisCoupure::getMoisValeur($id_mois);
$libelle_mois = $mois_valeur !== null ? ac_mois_label($mois_valeur) : '';

$campagne = AvisCoupure::getCampagne($aepId, $id_mois);
// Le seuil peut être ajusté depuis l'URL pour simuler une autre règle avant
// de l'enregistrer avec la sélection.
$seuil = isset($_GET['seuil']) ? max(1, (int) $_GET['seuil']) : (int) $campagne['seuil_mois'];

$filtre_reseau = isset($_GET['reseau_id']) ? (int) $_GET['reseau_id'] : 0;
$filtre_q = isset($_GET['q']) ? trim($_GET['q']) : '';

$reseaux = Manager::prepare_query(
    "SELECT id, nom FROM reseau WHERE id_aep = ? ORDER BY nom",
    array($aepId)
)->fetchAll(PDO::FETCH_ASSOC);

$responsables = AvisCoupure::getResponsablesPossibles();
$id_responsable = (int) $campagne['id_responsable'];
if ($id_responsable <= 0) {
    $id_responsable = (int) $_SESSION['user_id'];
}
$date_avis = $campagne['date_avis'] !== '' ? $campagne['date_avis'] : date('Y-m-d');

$candidats = AvisCoupure::getCandidats(
    $aepId,
    $id_mois,
    $seuil,
    array('id_reseau' => $filtre_reseau, 'recherche' => $filtre_q)
);

// Les totaux « retenus » portent sur toute la campagne, les totaux « candidats »
// sur ce que l'écran affiche après filtrage.
$totaux_retenus = AvisCoupure::getTotauxRetenus($aepId, $id_mois);
$nb_retenus_total = (int) $totaux_retenus['nb'];
$nb_candidats = 0;
$montant_candidats = 0.0;
foreach ($candidats as $c) {
    if ((int) $c['est_actif'] === 1 && (int) $c['nb_mois_impayes'] >= $seuil) {
        $nb_candidats++;
        $montant_candidats += (float) $c['montant_impaye'];
    }
}

// Messages de retour du traitement
$flash = array('type' => '', 'text' => '');
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'selection_saved') {
        $ajoutes = isset($_GET['a']) ? (int) $_GET['a'] : 0;
        $retires = isset($_GET['r']) ? (int) $_GET['r'] : 0;
        $flash = array(
            'type' => 'success',
            'text' => 'Sélection enregistrée : ' . $ajoutes . ' avis ajouté(s), ' . $retires . ' retiré(s).',
        );
    } elseif ($_GET['success'] === 'campagne_saved') {
        $flash = array('type' => 'success', 'text' => 'Paramètres de la campagne enregistrés.');
    }
} elseif (isset($_GET['error'])) {
    $msg = isset($_GET['message']) ? urldecode($_GET['message']) : 'Une erreur est survenue.';
    $flash = array('type' => 'danger', 'text' => $msg);
}

?>
<style>
    .ac-shell { background: #f8f9fa; margin: -0.5rem -0.75rem 0; min-height: calc(100vh - 100px); padding: 0 0 2.5rem; }
    .ac-main { max-width: 1400px; margin: 0 auto; padding: 1rem 1.5rem 0; }
    .ac-toprow { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.5rem; }
    .ac-breadcrumb { font-size: 0.8rem; color: #5f6368; margin: 0; }
    .ac-breadcrumb a { color: #1a73e8; text-decoration: none; }
    .ac-breadcrumb a:hover { text-decoration: underline; }
    .ac-page-title { font-size: 1.35rem; font-weight: 400; color: #202124; margin: 0 0 0.15rem; }
    .ac-subtitle { font-size: 0.85rem; color: #5f6368; margin: 0; }
    .ac-kpi { background: #fff; border: 1px solid #dadce0; border-radius: 8px; padding: 1rem 1.1rem; height: 100%; }
    .ac-kpi-label { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; color: #5f6368; margin-bottom: 0.35rem; }
    .ac-kpi-value { font-size: 1.35rem; font-weight: 500; color: #202124; line-height: 1.2; }
    .ac-kpi-value small { font-size: 0.8rem; font-weight: 400; color: #5f6368; }
    .ac-card { background: #fff; border: 1px solid #dadce0; border-radius: 8px; overflow: hidden; margin-bottom: 1.25rem; }
    .ac-card-header { padding: 0.85rem 1.25rem; border-bottom: 1px solid #e8eaed; font-weight: 500; font-size: 0.92rem; color: #202124; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; }
    .ac-card-body { padding: 1.25rem; }
    .ac-card-body.p-0 { padding: 0; }
    .ac-table { font-size: 0.875rem; margin-bottom: 0; }
    .ac-table thead th { background: #f8f9fa; color: #5f6368; font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid #e8eaed; padding: 0.65rem 1rem; white-space: nowrap; }
    /* style.css impose un en-tête de table bleu : la case à cocher y serait invisible. */
    .ac-table thead th .form-check-input { background-color: #fff; border-color: #fff; }
    .ac-table tbody td { padding: 0.6rem 1rem; vertical-align: middle; border-color: #e8eaed; }
    .ac-table tbody tr:hover { background: #f8f9fa; }
    .ac-table tbody tr.ac-retenu { background: #e8f0fe; }
    .ac-table tbody tr.ac-retenu:hover { background: #d9e7fd; }
    .ac-badge-reseau { background: #e8f0fe; color: #1967d2; font-size: 0.75rem; font-weight: 500; }
    .ac-badge-mois { background: #fce8e6; color: #c5221f; font-weight: 500; font-size: 0.78rem; padding: 0.25rem 0.5rem; border-radius: 4px; }
    .ac-badge-neutre { background: #f1f3f4; color: #5f6368; font-size: 0.75rem; padding: 0.2rem 0.45rem; border-radius: 4px; }
    .ac-btn-primary { background: #1a73e8; border-color: #1a73e8; color: #fff; font-size: 0.8125rem; }
    .ac-btn-primary:hover { background: #1765cc; border-color: #1765cc; color: #fff; }
    .ac-btn-text { color: #1a73e8; font-size: 0.8125rem; padding: 0.25rem 0.5rem; }
    .ac-btn-text:hover { background: #e8f0fe; color: #1557b0; }
    .ac-empty { text-align: center; padding: 3rem 1.5rem; color: #5f6368; }
    .ac-empty i { font-size: 2.5rem; opacity: 0.35; display: block; margin-bottom: 0.75rem; }
    .ac-actionbar { position: sticky; bottom: 0; z-index: 6; background: #fff; border-top: 1px solid #dadce0; padding: 0.75rem 1.25rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .ac-hint { font-size: 0.8rem; color: #5f6368; }
    @media (max-width: 767.98px) {
        .ac-main { padding: 0.75rem; }
    }
</style>

<div class="ac-shell">
    <div class="ac-main">
        <div class="ac-toprow">
            <nav class="ac-breadcrumb" aria-label="Fil d'Ariane">
                <a href="?page=home">Accueil</a>
                <span class="mx-1">/</span>
                <?php if ($libeleAep !== ''): ?>
                    <a href="?page=aep_dashboard"><?php echo htmlspecialchars($libeleAep, ENT_QUOTES, 'UTF-8'); ?></a>
                    <span class="mx-1">/</span>
                <?php endif; ?>
                <span>Avis de coupure</span>
            </nav>
            <form method="get" action="index.php" class="d-flex align-items-center gap-2 flex-shrink-0">
                <input type="hidden" name="page" value="avis_coupure">
                <input type="hidden" name="seuil" value="<?php echo (int) $seuil; ?>">
                <?php if ($filtre_reseau > 0): ?>
                    <input type="hidden" name="reseau_id" value="<?php echo (int) $filtre_reseau; ?>">
                <?php endif; ?>
                <?php if ($filtre_q !== ''): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <label class="form-label small mb-0 text-muted" for="ac_id_mois">Mois de facturation</label>
                <select name="id_mois" id="ac_id_mois" class="form-select form-select-sm" style="width: 11rem"
                    onchange="this.form.submit()">
                    <?php foreach ($liste_mois as $m): ?>
                        <option value="<?php echo (int) $m['id']; ?>" <?php echo (int) $m['id'] === $id_mois ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ac_mois_label($m['mois']), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="mb-3">
            <h1 class="ac-page-title">Avis de coupure</h1>
            <p class="ac-subtitle">
                Abonnés actifs comptant au moins <?php echo (int) $seuil; ?> mois non réglés depuis leur dernier avis
                <?php if ($libelle_mois !== ''): ?>
                    — facturation <?php echo htmlspecialchars($libelle_mois, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($flash['text'] !== ''): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> py-2 small"><?php echo htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="ac-kpi">
                    <div class="ac-kpi-label">Candidats</div>
                    <div class="ac-kpi-value"><?php echo $nb_candidats; ?> <small>abonné(s)</small></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ac-kpi">
                    <div class="ac-kpi-label">Retenus pour la coupure</div>
                    <div class="ac-kpi-value"><?php echo $nb_retenus_total; ?> <small>avis</small></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ac-kpi">
                    <div class="ac-kpi-label">Impayés des candidats</div>
                    <div class="ac-kpi-value"><?php echo ac_fcfa($montant_candidats); ?> <small>FCFA</small></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ac-kpi">
                    <div class="ac-kpi-label">Impayés des retenus</div>
                    <div class="ac-kpi-value"><?php echo ac_fcfa($totaux_retenus['montant']); ?> <small>FCFA</small></div>
                </div>
            </div>
        </div>

        <!-- Paramètres de la campagne + filtres -->
        <div class="ac-card">
            <div class="ac-card-header">
                <span><i class="bi bi-sliders me-1"></i> Paramètres et filtres</span>
            </div>
            <div class="ac-card-body">
                <form method="get" action="index.php" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="avis_coupure">
                    <input type="hidden" name="id_mois" value="<?php echo (int) $id_mois; ?>">
                    <div class="col-6 col-md-2">
                        <label for="ac_seuil" class="form-label small text-muted mb-1">Mois impayés minimum</label>
                        <input type="number" min="1" max="60" class="form-control form-control-sm" id="ac_seuil"
                            name="seuil" value="<?php echo (int) $seuil; ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="ac_reseau" class="form-label small text-muted mb-1">Réseau</label>
                        <select class="form-select form-select-sm" id="ac_reseau" name="reseau_id">
                            <option value="0">Tous les réseaux</option>
                            <?php foreach ($reseaux as $r): ?>
                                <option value="<?php echo (int) $r['id']; ?>" <?php echo $filtre_reseau === (int) $r['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="ac_q" class="form-label small text-muted mb-1">Rechercher un abonné</label>
                        <input type="text" class="form-control form-control-sm" id="ac_q" name="q"
                            value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Nom de l'abonné">
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm ac-btn-primary"><i class="bi bi-funnel"></i> Appliquer</button>
                        <a href="?page=avis_coupure&amp;id_mois=<?php echo (int) $id_mois; ?>"
                            class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <form method="post" action="traitement/avis_coupure_t.php" id="ac_form_selection">
            <input type="hidden" name="action" value="save_selection">
            <input type="hidden" name="id_mois" value="<?php echo (int) $id_mois; ?>">
            <input type="hidden" name="seuil" value="<?php echo (int) $seuil; ?>">
            <input type="hidden" name="reseau_id" value="<?php echo (int) $filtre_reseau; ?>">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($filtre_q, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Responsable de la coupure -->
            <div class="ac-card">
                <div class="ac-card-header">
                    <span><i class="bi bi-person-badge me-1"></i> Responsable et mise en page de l'avis</span>
                </div>
                <div class="ac-card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="ac_responsable" class="form-label small text-muted mb-1">Coupure effectuée par</label>
                            <select class="form-select form-select-sm" id="ac_responsable" name="id_responsable">
                                <option value="0">— Non défini —</option>
                                <?php foreach ($responsables as $u): ?>
                                    <option value="<?php echo (int) $u['id']; ?>" <?php echo $id_responsable === (int) $u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(AvisCoupure::nomComplet($u), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($u['numero_telephone'])): ?>
                                            (<?php echo htmlspecialchars($u['numero_telephone'], ENT_QUOTES, 'UTF-8'); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Son nom et son téléphone sont imprimés sur chaque avis.</div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="ac_date" class="form-label small text-muted mb-1">Date de l'avis</label>
                            <input type="date" class="form-control form-control-sm" id="ac_date" name="date_avis"
                                value="<?php echo htmlspecialchars($date_avis, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="ac_entete" class="form-label small text-muted mb-1">En-tête imprimé</label>
                            <input type="text" class="form-control form-control-sm" id="ac_entete" name="entete"
                                maxlength="255"
                                value="<?php echo htmlspecialchars($campagne['entete'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-text">Le cachet et la signature sont apposés à la main après impression.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des abonnés -->
            <div class="ac-card">
                <div class="ac-card-header">
                    <span>
                        <i class="bi bi-list-check me-1"></i>
                        Abonnés concernés
                        <span class="ac-badge-neutre ms-1"><?php echo count($candidats); ?></span>
                    </span>
                    <span class="d-flex gap-2">
                        <button type="button" class="btn btn-sm ac-btn-text" id="ac_select_all">
                            <i class="bi bi-check2-square"></i> Tout sélectionner
                        </button>
                        <button type="button" class="btn btn-sm ac-btn-text" id="ac_select_none">
                            <i class="bi bi-square"></i> Tout désélectionner
                        </button>
                    </span>
                </div>

                <?php if (empty($candidats)): ?>
                    <div class="ac-empty">
                        <i class="bi bi-emoji-smile"></i>
                        <p class="mb-0">Aucun abonné n'atteint <?php echo (int) $seuil; ?> mois non réglés depuis son dernier avis.</p>
                    </div>
                <?php else: ?>
                    <div class="ac-card-body p-0 table-responsive">
                        <table class="table ac-table">
                            <thead>
                                <tr>
                                    <th style="width: 2.5rem">
                                        <input class="form-check-input" type="checkbox" id="ac_check_head"
                                            title="Tout sélectionner / désélectionner">
                                    </th>
                                    <th>Abonné</th>
                                    <th>Réseau</th>
                                    <th>Compteur</th>
                                    <th>État</th>
                                    <th class="text-end">Mois non réglés</th>
                                    <th class="text-end">Montant impayé</th>
                                    <th>Dernier avis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidats as $c): ?>
                                    <?php
                                    $retenu = (int) $c['est_retenu'] > 0;
                                    $actif = (int) $c['est_actif'] === 1;
                                    $sousSeuil = (int) $c['nb_mois_impayes'] < $seuil;
                                    ?>
                                    <tr class="<?php echo $retenu ? 'ac-retenu' : ''; ?>">
                                        <td>
                                            <input type="hidden" name="visibles[]" value="<?php echo (int) $c['id_abone']; ?>">
                                            <input class="form-check-input ac-check" type="checkbox" name="abones[]"
                                                value="<?php echo (int) $c['id_abone']; ?>" <?php echo $retenu ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <a class="text-decoration-none fw-medium"
                                                href="?page=info_abone&amp;id=<?php echo (int) $c['id_abone']; ?>">
                                                <?php echo htmlspecialchars($c['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <?php if (!empty($c['numero_telephone'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($c['numero_telephone'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge ac-badge-reseau"><?php echo htmlspecialchars($c['nom_reseau'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td class="text-muted small">
                                            <?php echo $c['numero_compteur'] !== null && $c['numero_compteur'] !== ''
                                                ? htmlspecialchars($c['numero_compteur'], ENT_QUOTES, 'UTF-8') : '—'; ?>
                                        </td>
                                        <td>
                                            <?php if ($actif): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($c['etat']), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <span class="<?php echo $sousSeuil ? 'ac-badge-neutre' : 'ac-badge-mois'; ?>">
                                                <?php echo (int) $c['nb_mois_impayes']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-medium"><?php echo ac_fcfa($c['montant_impaye']); ?> FCFA</td>
                                        <td class="text-muted small">
                                            <?php echo !empty($c['dernier_avis_mois'])
                                                ? htmlspecialchars(ac_mois_label($c['dernier_avis_mois']), ENT_QUOTES, 'UTF-8')
                                                : 'Jamais'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="ac-actionbar">
                    <span class="ac-hint">
                        <i class="bi bi-info-circle"></i>
                        <span id="ac_compteur_selection">0</span> abonné(s) coché(s) sur les
                        <?php echo count($candidats); ?> affiché(s).
                        <?php if ($filtre_reseau > 0 || $filtre_q !== ''): ?>
                            <br><i class="bi bi-funnel"></i>
                            Liste filtrée : l'enregistrement ne touche que les abonnés affichés ci-dessus,
                            les <?php echo $nb_retenus_total; ?> avis déjà retenus ce mois-ci sont conservés.
                        <?php endif; ?>
                    </span>
                    <span class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-sm ac-btn-primary">
                            <i class="bi bi-save"></i> Enregistrer la sélection
                        </button>
                        <a class="btn btn-sm btn-outline-danger <?php echo $nb_retenus_total > 0 ? '' : 'disabled'; ?>"
                            href="traitement/avis_coupure_pdf_t.php?id_mois=<?php echo (int) $id_mois; ?>">
                            <i class="bi bi-file-earmark-pdf"></i>
                            Télécharger les avis (PDF) — <?php echo $nb_retenus_total; ?>
                        </a>
                    </span>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('ac_form_selection');
        if (!form) {
            return;
        }
        var cases = form.querySelectorAll('.ac-check');
        var compteur = document.getElementById('ac_compteur_selection');
        var entete = document.getElementById('ac_check_head');

        function majCompteur() {
            var n = 0;
            for (var i = 0; i < cases.length; i++) {
                if (cases[i].checked) {
                    n++;
                }
            }
            if (compteur) {
                compteur.textContent = n;
            }
            if (entete) {
                entete.checked = (n === cases.length && n > 0);
                entete.indeterminate = (n > 0 && n < cases.length);
            }
        }

        function toutCocher(valeur) {
            for (var i = 0; i < cases.length; i++) {
                cases[i].checked = valeur;
            }
            majCompteur();
        }

        for (var i = 0; i < cases.length; i++) {
            cases[i].addEventListener('change', majCompteur);
        }
        var btnAll = document.getElementById('ac_select_all');
        var btnNone = document.getElementById('ac_select_none');
        if (btnAll) {
            btnAll.addEventListener('click', function () { toutCocher(true); });
        }
        if (btnNone) {
            btnNone.addEventListener('click', function () { toutCocher(false); });
        }
        if (entete) {
            entete.addEventListener('change', function () { toutCocher(entete.checked); });
        }
        majCompteur();
    })();
</script>
