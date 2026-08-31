<?php

// Inclure la classe Manager et le modèle Redevance
@include_once("../donnees/redevance.php");
@include_once("donnees/redevance.php");
@include_once("../donnees/redevance_synopsis_helper.php");
@include_once("donnees/redevance_synopsis_helper.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
    $redevances = array();
} else {
    // Récupérer les redevances pour l'AEP
    $redevances = Manager::prepare_query(
        "SELECT r.*, a.libele as aep_libele
         FROM redevance r
         LEFT JOIN aep a ON r.id_aep = a.id
         WHERE r.id_aep = ?",
        array($aepId)
    )->fetchAll();
    $message = '';
}

$synRedevancePage = null;
if ($aepId && class_exists('RedevanceSynopsisHelper')) {
    $synRedevancePage = RedevanceSynopsisHelper::compute($aepId, $redevances);
}
$redevancePageSelectedId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$redevancePageSelected = null;
foreach ($redevances as $_rdSel) {
    if ((int) $_rdSel['id'] === $redevancePageSelectedId) {
        $redevancePageSelected = $_rdSel;
        break;
    }
}
$redevancePageSynRow = null;
if ($synRedevancePage && $redevancePageSelectedId > 0) {
    foreach ($synRedevancePage['detail'] as $_dr) {
        if ((int) $_dr['id'] === $redevancePageSelectedId) {
            $redevancePageSynRow = $_dr;
            break;
        }
    }
}

// Gérer les messages de retour
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'redevance_added':
            $message = '<div class="alert alert-success">Redevance ajoutée avec succès.</div>';
            break;
        case 'redevance_updated':
            $message = '<div class="alert alert-success">Redevance mise à jour avec succès.</div>';
            break;
        case 'redevance_deleted':
            $message = '<div class="alert alert-success">Redevance supprimée avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_aep':
            $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
            break;
        case 'invalid_libele':
            $message = '<div class="alert alert-danger">Le libellé est invalide (1 à 64 caractères).</div>';
            break;
        case 'invalid_type':
            $message = '<div class="alert alert-danger">Le type de redevance est invalide (Entree ou Sortie).</div>';
            break;
        case 'invalid_mois_debut':
            $message = '<div class="alert alert-danger">Le mois de debut est invalide (doit avoir 7 caracteres comme 2025-01).</div>';
            break;
        case 'invalid_pourcentage':
            $message = '<div class="alert alert-danger">Le pourcentage doit être compris entre 0 et 100.</div>';
            break;
        case 'invalid_base_calcul':
            $message = '<div class="alert alert-danger">La base de calcul est invalide (doit être vente_eau ou branchements).</div>';
            break;
        case 'invalid_type_calcul':
            $message = '<div class="alert alert-danger">Le type de calcul est invalide (doit être pourcentage ou montant_fixe).</div>';
            break;
        case 'invalid_montant_par_m3':
            $message = '<div class="alert alert-danger">Le montant fixe doit être supérieur à 0.</div>';
            break;
        case 'add_failed':
        case 'update_failed':
        case 'delete_failed':
            $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
            $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
            break;
        case 'invalid_request':
            $message = '<div class="alert alert-danger">Requête invalide.</div>';
            break;
    }
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">Gestion des redevances</h2>
        <div class="d-flex flex-wrap gap-2">
            <a href="?page=aep_dashboard" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2"></i> Tableau de bord AEP
            </a>
            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal"
                data-bs-target="#addRedevanceModal" <?php echo $aepId ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> Ajouter une redevance
            </button>
        </div>
    </div>
    <?php echo $message; ?>

    <?php if (!$aepId): ?>
        <div class="alert alert-warning">Sélectionnez un AEP pour gérer les redevances.</div>
    <?php else: ?>
        <div class="row g-3">
            <!-- Liste à gauche -->
            <div class="col-lg-3 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light py-2">
                        <strong><i class="bi bi-list-ul"></i> Liste</strong>
                        <span class="d-block small text-muted fw-normal mt-1">Clic sur la ligne = détail · <i class="bi bi-cash-coin text-success"></i> = versements</span>
                    </div>
                    <div class="list-group list-group-flush overflow-auto" style="max-height: 75vh;">
                        <?php
                        $rdqPeriod = array('page' => 'redevance');
                        if (isset($_GET['annee'])) {
                            $rdqPeriod['annee'] = $_GET['annee'];
                        }
                        if (isset($_GET['mf_debut']) && (int) $_GET['mf_debut'] > 0) {
                            $rdqPeriod['mf_debut'] = (int) $_GET['mf_debut'];
                        }
                        if (isset($_GET['mf_fin']) && (int) $_GET['mf_fin'] > 0) {
                            $rdqPeriod['mf_fin'] = (int) $_GET['mf_fin'];
                        }
                        $hrefToutes = '?' . http_build_query($rdqPeriod);
                        ?>
                        <a href="<?php echo htmlspecialchars($hrefToutes); ?>"
                            class="list-group-item list-group-item-action py-2 small <?php echo $redevancePageSelectedId === 0 ? 'border border-primary border-2 bg-light fw-semibold' : ''; ?>">
                            <i class="bi bi-grid-3x3-gap"></i> Vue globale (synthèse)
                        </a>
                        <?php if (count($redevances) > 0): ?>
                            <?php foreach ($redevances as $redevance):
                                $rdq = $rdqPeriod;
                                $rdq['id'] = (int) $redevance['id'];
                                $hrefRd = '?' . http_build_query($rdq);
                                $base = isset($redevance['base_calcul']) ? $redevance['base_calcul'] : 'vente_eau';
                                $type_calc = isset($redevance['type_calcul']) ? $redevance['type_calcul'] : 'pourcentage';
                                if ($type_calc == 'pourcentage') {
                                    $valeurListe = number_format($redevance['pourcentage'], 2) . ' %';
                                } else {
                                    $montant = isset($redevance['montant_par_m3']) ? $redevance['montant_par_m3'] : 0;
                                    $valeurListe = number_format($montant, 0, ',', ' ') . ' FCFA'
                                        . ($base == 'vente_eau' ? ' / m³' : ' / br.');
                                }
                                $isActive = $redevancePageSelectedId === (int) $redevance['id'];
                                $hrefVersements = '?page=redevance_versements&id_redevance=' . (int) $redevance['id'];
                                ?>
                                <div class="list-group-item py-2 d-flex align-items-stretch gap-1 <?php echo $isActive ? 'border border-primary border-2 bg-light' : ''; ?>">
                                    <a href="<?php echo htmlspecialchars($hrefRd); ?>"
                                        class="list-group-item-action flex-grow-1 text-decoration-none text-reset py-0 pe-0 min-w-0">
                                        <div class="fw-semibold text-truncate" title="<?php echo htmlspecialchars($redevance['libele']); ?>">
                                            <?php echo htmlspecialchars($redevance['libele']); ?></div>
                                        <div class="small mt-1">
                                            <span class="badge <?php echo $base == 'vente_eau' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                <?php echo $base == 'vente_eau' ? 'Eau' : 'Branchements'; ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted mt-1"><?php echo htmlspecialchars($valeurListe); ?></div>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($hrefVersements); ?>"
                                        class="btn btn-success btn-sm align-self-center flex-shrink-0 px-2"
                                        title="Versements (un clic)">
                                        <i class="bi bi-cash-coin"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-muted small">Aucune redevance</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Zone principale : période + même synthèse que le tableau de bord -->
            <div class="col-lg-9 col-md-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <form method="get" action="" id="form_redevance_periode" class="d-flex flex-wrap align-items-end gap-2 mb-0">
                            <input type="hidden" name="page" value="redevance">
                            <?php if ($redevancePageSelectedId > 0): ?>
                                <input type="hidden" name="id" value="<?php echo (int) $redevancePageSelectedId; ?>">
                            <?php endif; ?>
                            <div>
                                <label for="redevance_periode_annee" class="form-label small text-muted mb-0">Période</label>
                                <select name="annee" id="redevance_periode_annee" class="form-select form-select-sm"
                                    style="min-width: 8rem; max-width: 12rem;"
                                    title="<?php echo $synRedevancePage && $synRedevancePage['periode_libelle'] !== '' ? htmlspecialchars($synRedevancePage['periode_libelle']) : ''; ?>"
                                    onchange="this.form.submit();">
                                    <?php
                                    $spm = $synRedevancePage ? $synRedevancePage['periode_mode'] : '12';
                                    $snm = $synRedevancePage ? (int) $synRedevancePage['nb_mois_glissant'] : 12;
                                    ?>
                                    <option value="m3" <?php echo ($spm === '12' && $snm === 4) ? 'selected' : ''; ?>>3 derniers mois</option>
                                    <option value="m6" <?php echo ($spm === '12' && $snm === 7) ? 'selected' : ''; ?>>6 derniers mois</option>
                                    <option value="" <?php echo ($spm === '12' && in_array($snm, array(12, 13), true)) ? 'selected' : ''; ?>>12 derniers mois</option>
                                    <option value="tous" <?php echo $spm === 'tous' ? 'selected' : ''; ?>>Tous les mois</option>
                                    <option value="intervalle" <?php echo $spm === 'intervalle' ? 'selected' : ''; ?>>Intervalle (mois)</option>
                                    <?php if ($synRedevancePage): ?>
                                        <?php foreach ($synRedevancePage['annees_disponibles'] as $yDisp): ?>
                                            <option value="<?php echo (int) $yDisp; ?>" <?php echo $spm === 'annee' && (int) $synRedevancePage['annee_vue'] === (int) $yDisp ? 'selected' : ''; ?>>
                                                <?php echo (int) $yDisp; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="d-flex flex-wrap align-items-end gap-1 <?php echo ($synRedevancePage && $synRedevancePage['periode_mode'] === 'intervalle') ? '' : 'opacity-50'; ?>">
                                <div>
                                    <label for="redevance_mf_debut" class="form-label small text-muted mb-0">Mois début</label>
                                    <select name="mf_debut" id="redevance_mf_debut" class="form-select form-select-sm" style="min-width: 9rem; max-width: 11rem;"
                                        <?php echo ($synRedevancePage && $synRedevancePage['periode_mode'] === 'intervalle') ? '' : 'disabled'; ?>
                                        onchange="this.form.submit();">
                                        <?php if ($synRedevancePage): ?>
                                            <?php foreach ($synRedevancePage['liste_mois_fact'] as $lm): ?>
                                                <option value="<?php echo (int) $lm['id']; ?>" <?php echo (int) $lm['id'] === (int) $synRedevancePage['mf_debut_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(getLetterMonth($lm['mois'])); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="redevance_mf_fin" class="form-label small text-muted mb-0">Mois fin</label>
                                    <select name="mf_fin" id="redevance_mf_fin" class="form-select form-select-sm" style="min-width: 9rem; max-width: 11rem;"
                                        <?php echo ($synRedevancePage && $synRedevancePage['periode_mode'] === 'intervalle') ? '' : 'disabled'; ?>
                                        onchange="this.form.submit();">
                                        <?php if ($synRedevancePage): ?>
                                            <?php foreach ($synRedevancePage['liste_mois_fact'] as $lm): ?>
                                                <option value="<?php echo (int) $lm['id']; ?>" <?php echo (int) $lm['id'] === (int) $synRedevancePage['mf_fin_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(getLetterMonth($lm['mois'])); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($synRedevancePage && count($synRedevancePage['detail']) > 0): ?>
                    <p class="small text-muted mb-2">
                        Synthèse sur la période
                        (<strong><?php echo $synRedevancePage['periode_libelle'] !== '' ? htmlspecialchars($synRedevancePage['periode_libelle']) : '—'; ?></strong>).
                    </p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card border-primary h-100 shadow-sm">
                                <div class="card-header bg-primary text-white py-2">
                                    <strong><i class="bi bi-droplet me-1"></i>Vente d'eau</strong>
                                    <span class="badge bg-light text-primary ms-1"><?php echo (int) $synRedevancePage['recap']['vente_eau']['count']; ?> redevance(s)</span>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row g-2 small">
                                        <div class="col-6 text-muted">Estimatif</div>
                                        <div class="col-6 text-end fw-semibold"><?php echo number_format($synRedevancePage['recap']['vente_eau']['estimatif'], 0, ',', ' '); ?> FCFA</div>
                                        <div class="col-6 text-muted">Versé</div>
                                        <div class="col-6 text-end text-success fw-semibold"><?php echo number_format($synRedevancePage['recap']['vente_eau']['verse'], 0, ',', ' '); ?> FCFA</div>
                                        <div class="col-6 text-muted">Reste</div>
                                        <div class="col-6 text-end text-warning fw-semibold"><?php echo number_format($synRedevancePage['recap']['vente_eau']['reste'], 0, ',', ' '); ?> FCFA</div>
                                        <div class="col-6 text-muted">Taux versement</div>
                                        <div class="col-6 text-end fw-bold"><?php echo $synRedevancePage['recap']['vente_eau']['taux_pct'] !== null ? (int) $synRedevancePage['recap']['vente_eau']['taux_pct'] . ' %' : '—'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-secondary h-100 shadow-sm">
                                <div class="card-header bg-secondary text-white py-2">
                                    <strong><i class="bi bi-diagram-3 me-1"></i>Branchements</strong>
                                    <span class="badge bg-light text-secondary ms-1"><?php echo (int) $synRedevancePage['recap']['branchements']['count']; ?> redevance(s)</span>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row g-2 small">
                                        <div class="col-6 text-muted">Estimatif</div>
                                        <div class="col-6 text-end fw-semibold"><?php echo number_format($synRedevancePage['recap']['branchements']['estimatif'], 0, ',', ' '); ?> FCFA</div>
                                        <div class="col-6 text-muted">Versé</div>
                                        <div class="col-6 text-end text-success fw-semibold"><?php echo number_format($synRedevancePage['recap']['branchements']['verse'], 0, ',', ' '); ?> FCFA</div>
                                        <div class="col-6 text-muted">Reste</div>
                                        <div class="col-6 text-end text-warning fw-semibold"><?php echo number_format($synRedevancePage['recap']['branchements']['reste'], 0, ',', ' '); ?> FCFA</div>
                                        <div class="col-6 text-muted">Taux versement</div>
                                        <div class="col-6 text-end fw-bold"><?php echo $synRedevancePage['recap']['branchements']['taux_pct'] !== null ? (int) $synRedevancePage['recap']['branchements']['taux_pct'] . ' %' : '—'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($redevancePageSelected && $redevancePageSynRow): ?>
                        <div class="card border-warning shadow-sm mb-3">
                            <div class="card-header bg-warning text-dark d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <strong><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($redevancePageSelected['libele']); ?></strong>
                                <div class="btn-group btn-group-sm">
                                    <a href="?page=redevance_versements&id_redevance=<?php echo (int) $redevancePageSelected['id']; ?>"
                                        class="btn btn-success"><i class="bi bi-cash-coin"></i> Versements</a>
                                    <button type="button" class="btn btn-primary" onclick="editRedevance(<?php echo (int) $redevancePageSelected['id']; ?>)">
                                        <i class="bi bi-pencil"></i> Modifier
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="deleteRedevance(<?php echo (int) $redevancePageSelected['id']; ?>)">
                                        <i class="bi bi-trash"></i> Supprimer
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 small mb-3">
                                    <div class="col-md-4"><span class="text-muted">Type de calcul</span><br>
                                        <span class="badge bg-secondary"><?php echo (isset($redevancePageSelected['type_calcul']) && $redevancePageSelected['type_calcul'] === 'montant_fixe') ? 'Montant fixe' : 'Pourcentage'; ?></span>
                                    </div>
                                    <div class="col-md-4"><span class="text-muted">Mois de début</span><br>
                                        <strong><?php echo htmlspecialchars($redevancePageSelected['mois_debut'] ? $redevancePageSelected['mois_debut'] : '—'); ?></strong>
                                    </div>
                                    <div class="col-md-4"><span class="text-muted">Sur la période</span><br>
                                        Estimatif <strong><?php echo number_format($redevancePageSynRow['estimatif'], 0, ',', ' '); ?></strong> FCFA —
                                        Versé <strong class="text-success"><?php echo number_format($redevancePageSynRow['verse'], 0, ',', ' '); ?></strong> FCFA —
                                        Reste <strong class="text-warning"><?php echo number_format($redevancePageSynRow['reste'], 0, ',', ' '); ?></strong> FCFA
                                    </div>
                                    <?php if (!empty($redevancePageSelected['description'])): ?>
                                        <div class="col-12"><span class="text-muted">Description</span><br>
                                            <?php echo nl2br(htmlspecialchars($redevancePageSelected['description'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <a href="?page=redevance_details&id=<?php echo (int) $redevancePageSelected['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-table"></i> Détail analytique (redevance_details)
                                </a>
                            </div>
                        </div>
                    <?php elseif ($redevancePageSelected && !$redevancePageSynRow): ?>
                        <div class="alert alert-secondary">Données de synthèse indisponibles pour cette période.</div>
                    <?php endif; ?>

                    <p class="small text-muted mb-2">Détail par redevance</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Redevance</th>
                                    <th class="text-end">Estimatif (FCFA)</th>
                                    <th class="text-end">Versé (FCFA)</th>
                                    <th class="text-end">Restant (FCFA)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($synRedevancePage['detail'] as $dr): ?>
                                    <tr class="<?php echo $redevancePageSelectedId === (int) $dr['id'] ? 'table-info' : ''; ?>">
                                        <td>
                                            <a href="?<?php
                                            $q = $rdqPeriod;
                                            $q['id'] = (int) $dr['id'];
                                            echo htmlspecialchars(http_build_query($q));
                                            ?>"><?php echo htmlspecialchars($dr['libele']); ?></a>
                                            <?php if (isset($dr['base_calcul']) && $dr['base_calcul'] === 'branchements'): ?>
                                                <span class="badge bg-secondary ms-1">Branchements</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary ms-1">Vente d'eau</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo number_format($dr['estimatif'], 0, ',', ' '); ?></td>
                                        <td class="text-end"><?php echo number_format($dr['verse'], 0, ',', ' '); ?></td>
                                        <td class="text-end"><?php echo number_format($dr['reste'], 0, ',', ' '); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-0">
                        Les montants versés sont ceux dont la <strong>date de versement</strong> (année-mois) est dans la période sélectionnée.
                    </p>
                <?php elseif ($synRedevancePage): ?>
                    <div class="alert alert-info mb-0">Aucune donnée de synthèse pour cette période (pas de mois de facturation ou plage vide).</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal pour ajouter une redevance -->
<div class="modal fade" id="addRedevanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Redevance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRedevanceForm" method="post" action="traitement/redevance_t.php">
                    <input type="hidden" name="action" value="add_redevance">
                    <input type="hidden" name="id_aep" value="<?php echo $aepId; ?>">

                    <div class="row g-3">
                        <!-- Libellé - Pleine largeur -->
                        <div class="col-12">
                            <label for="libele" class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="libele" name="libele" required maxlength="64">
                        </div>

                        <!-- Base de calcul et Type de calcul - Côte à côte -->
                        <div class="col-md-6">
                            <label for="base_calcul" class="form-label">Base de calcul <span class="text-danger">*</span></label>
                            <select class="form-select" id="base_calcul" name="base_calcul" required>
                                <option value="vente_eau">Vente d'eau consommée</option>
                                <option value="branchements">Branchements</option>
                            </select>
                            <small class="form-text text-muted">Sur quoi se base le calcul</small>
                        </div>

                        <div class="col-md-6">
                            <label for="type_calcul" class="form-label">Type de calcul <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_calcul" name="type_calcul" required>
                                <option value="pourcentage">Pourcentage</option>
                                <option value="montant_fixe">Montant fixe</option>
                            </select>
                            <small class="form-text text-muted">Comment calculer la redevance</small>
                        </div>

                        <!-- Pourcentage ou Montant fixe - Pleine largeur -->
                        <div class="col-12" id="div_pourcentage">
                            <label for="pourcentage" class="form-label">Pourcentage (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="pourcentage" name="pourcentage" min="0" max="100" step="0.01" value="0">
                            <small class="form-text text-muted">Pourcentage à appliquer sur la base de calcul</small>
                        </div>

                        <div class="col-12" id="div_montant_fixe" style="display: none;">
                            <label for="montant_par_m3" class="form-label">Montant fixe (FCFA) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="montant_par_m3" name="montant_par_m3" min="0" step="0.01" value="0">
                            <small class="form-text text-muted" id="montant_help">Montant fixe par m³ consommé</small>
                        </div>

                        <!-- Mois de début et Description - Côte à côte -->
                        <div class="col-md-6">
                            <label for="mois_debut" class="form-label">Mois de début <span class="text-danger">*</span></label>
                            <input type="month" class="form-control" id="mois_debut" name="mois_debut" required value="<?php echo date('Y-m'); ?>">
                            <small class="form-text text-muted">Mois d'application</small>
                        </div>

                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" style="resize: none;"></textarea>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> <strong>Note :</strong> Toutes les redevances sont des sorties. Le calcul sera estimatif pour connaître le maximum à verser.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="addRedevanceForm" class="btn btn-primary">Ajouter</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une redevance -->
<div class="modal fade" id="editRedevanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Redevance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRedevanceForm" method="post" action="traitement/redevance_t.php">
                    <input type="hidden" name="action" value="update_redevance">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="id_aep" value="<?php echo $aepId; ?>">

                    <div class="row g-3">
                        <!-- Libellé - Pleine largeur -->
                        <div class="col-12">
                            <label for="edit_libele" class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_libele" name="libele" required maxlength="64">
                        </div>

                        <!-- Base de calcul et Type de calcul - Côte à côte -->
                        <div class="col-md-6">
                            <label for="edit_base_calcul" class="form-label">Base de calcul <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_base_calcul" name="base_calcul" required>
                                <option value="vente_eau">Vente d'eau consommée</option>
                                <option value="branchements">Branchements</option>
                            </select>
                            <small class="form-text text-muted">Sur quoi se base le calcul</small>
                        </div>

                        <div class="col-md-6">
                            <label for="edit_type_calcul" class="form-label">Type de calcul <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_type_calcul" name="type_calcul" required>
                                <option value="pourcentage">Pourcentage</option>
                                <option value="montant_fixe">Montant fixe</option>
                            </select>
                            <small class="form-text text-muted">Comment calculer la redevance</small>
                        </div>

                        <!-- Pourcentage ou Montant fixe - Pleine largeur -->
                        <div class="col-12" id="edit_div_pourcentage">
                            <label for="edit_pourcentage" class="form-label">Pourcentage (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_pourcentage" name="pourcentage" min="0" max="100" step="0.01" value="0">
                            <small class="form-text text-muted">Pourcentage à appliquer sur la base de calcul</small>
                        </div>

                        <div class="col-12" id="edit_div_montant_fixe" style="display: none;">
                            <label for="edit_montant_par_m3" class="form-label">Montant fixe (FCFA) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_montant_par_m3" name="montant_par_m3" min="0" step="0.01" value="0">
                            <small class="form-text text-muted" id="edit_montant_help">Montant fixe par m³ consommé</small>
                        </div>

                        <!-- Mois de début et Description - Côte à côte -->
                        <div class="col-md-6">
                            <label for="edit_mois_debut" class="form-label">Mois de début <span class="text-danger">*</span></label>
                            <input type="month" class="form-control" id="edit_mois_debut" name="mois_debut" required>
                            <small class="form-text text-muted">Mois d'application</small>
                        </div>

                        <div class="col-md-6">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" style="resize: none;"></textarea>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> <strong>Note :</strong> Toutes les redevances sont des sorties. Le calcul sera estimatif pour connaître le maximum à verser.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="editRedevanceForm" class="btn btn-primary">Modifier</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteRedevanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirmation de suppression</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cette redevance ?</p>
                <p class="text-muted"><strong>Attention :</strong> Cette action est irréversible. Tous les versements associés à cette redevance seront également supprimés.</p>
                <form id="deleteRedevanceForm" method="post" action="traitement/redevance_t.php">
                    <input type="hidden" name="action" value="delete_redevance">
                    <input type="hidden" name="id" id="delete_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="deleteRedevanceForm" class="btn btn-danger">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Gérer l'affichage conditionnel des champs selon le type de calcul
    document.getElementById('type_calcul').addEventListener('change', function() {
        const typeCalcul = this.value;
        const divPourcentage = document.getElementById('div_pourcentage');
        const divMontantFixe = document.getElementById('div_montant_fixe');
        const pourcentageInput = document.getElementById('pourcentage');
        const montantInput = document.getElementById('montant_par_m3');
        const baseCalcul = document.getElementById('base_calcul').value;
        const montantHelp = document.getElementById('montant_help');

        if (typeCalcul === 'pourcentage') {
            divPourcentage.style.display = 'block';
            divMontantFixe.style.display = 'none';
            pourcentageInput.required = true;
            montantInput.required = false;
            montantInput.value = '';
        } else {
            divPourcentage.style.display = 'none';
            divMontantFixe.style.display = 'block';
            pourcentageInput.required = false;
            pourcentageInput.value = '0';
            montantInput.required = true;
            
            // Mettre à jour le texte d'aide selon la base de calcul
            if (baseCalcul === 'vente_eau') {
                montantHelp.textContent = 'Montant fixe par m³ consommé';
            } else {
                montantHelp.textContent = 'Montant fixe par branchement';
            }
        }
    });

    // Mettre à jour le texte d'aide du montant fixe selon la base de calcul
    document.getElementById('base_calcul').addEventListener('change', function() {
        const baseCalcul = this.value;
        const typeCalcul = document.getElementById('type_calcul').value;
        const montantHelp = document.getElementById('montant_help');
        
        if (typeCalcul === 'montant_fixe') {
            if (baseCalcul === 'vente_eau') {
                montantHelp.textContent = 'Montant fixe par m³ consommé';
            } else {
                montantHelp.textContent = 'Montant fixe par branchement';
            }
        }
    });

    // Données des redevances pour l'édition
    const redevancesData = <?php echo json_encode($redevances, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function editRedevance(id) {
        // Trouver la redevance à modifier
        const redevance = redevancesData.find(r => parseInt(r.id) === parseInt(id));
        if (!redevance) {
            alert('Redevance introuvable');
            return;
        }

        // Remplir le formulaire d'édition
        document.getElementById('edit_id').value = redevance.id;
        document.getElementById('edit_libele').value = redevance.libele || '';
        document.getElementById('edit_base_calcul').value = redevance.base_calcul || 'vente_eau';
        document.getElementById('edit_type_calcul').value = redevance.type_calcul || 'pourcentage';
        document.getElementById('edit_pourcentage').value = redevance.pourcentage || 0;
        document.getElementById('edit_montant_par_m3').value = redevance.montant_par_m3 || '';
        document.getElementById('edit_mois_debut').value = redevance.mois_debut || '';
        document.getElementById('edit_description').value = redevance.description || '';

        // Gérer l'affichage conditionnel des champs
        updateEditFieldsVisibility();

        // Ouvrir le modal
        const modal = new bootstrap.Modal(document.getElementById('editRedevanceModal'));
        modal.show();
    }

    function deleteRedevance(id) {
        // Remplir le formulaire de suppression
        document.getElementById('delete_id').value = id;

        // Ouvrir le modal de confirmation
        const modal = new bootstrap.Modal(document.getElementById('deleteRedevanceModal'));
        modal.show();
    }

    // Fonction pour mettre à jour la visibilité des champs dans le modal d'édition
    function updateEditFieldsVisibility() {
        const typeCalcul = document.getElementById('edit_type_calcul').value;
        const divPourcentage = document.getElementById('edit_div_pourcentage');
        const divMontantFixe = document.getElementById('edit_div_montant_fixe');
        const pourcentageInput = document.getElementById('edit_pourcentage');
        const montantInput = document.getElementById('edit_montant_par_m3');
        const baseCalcul = document.getElementById('edit_base_calcul').value;
        const montantHelp = document.getElementById('edit_montant_help');

        if (typeCalcul === 'pourcentage') {
            divPourcentage.style.display = 'block';
            divMontantFixe.style.display = 'none';
            pourcentageInput.required = true;
            montantInput.required = false;
        } else {
            divPourcentage.style.display = 'none';
            divMontantFixe.style.display = 'block';
            pourcentageInput.required = false;
            montantInput.required = true;
            
            // Mettre à jour le texte d'aide selon la base de calcul
            if (baseCalcul === 'vente_eau') {
                montantHelp.textContent = 'Montant fixe par m³ consommé';
            } else {
                montantHelp.textContent = 'Montant fixe par branchement';
            }
        }
    }

    // Gérer l'affichage conditionnel des champs dans le modal d'édition
    document.getElementById('edit_type_calcul').addEventListener('change', function() {
        updateEditFieldsVisibility();
    });

    document.getElementById('edit_base_calcul').addEventListener('change', function() {
        const baseCalcul = this.value;
        const typeCalcul = document.getElementById('edit_type_calcul').value;
        const montantHelp = document.getElementById('edit_montant_help');
        
        if (typeCalcul === 'montant_fixe') {
            if (baseCalcul === 'vente_eau') {
                montantHelp.textContent = 'Montant fixe par m³ consommé';
            } else {
                montantHelp.textContent = 'Montant fixe par branchement';
            }
        }
    });
</script>