<?php
@include_once("traitement/versement_t.php");
@include_once("donnees/redevance.php");
@include_once("../donnees/redevance.php");

// Récupérer l'AEP actuel
$aepId = (int)$_SESSION['id_aep'];

// Récupérer l'ID de la redevance depuis l'URL (si présent)
$redevanceId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Récupérer le mois sélectionné (par défaut, aucun mois sélectionné)
$selectedMoisId = isset($_GET['id_mois']) ? (int)$_GET['id_mois'] : null;

// Récupérer toutes les redevances pour la liste déroulante
$allRedevances = Manager::prepare_query("
    SELECT id, libele
    FROM redevance
    WHERE id_aep = ?
    ORDER BY libele
", array($aepId))->fetchAll();

// Récupérer les mois et les montants totaux des versements
$queryMois = "
    SELECT m.id as mois_id, m.mois, SUM(v.montant) as montant_total
    FROM mois_facturation m
        LEFT JOIN versements v ON v.id_mois_facturation = m.id
        INNER JOIN constante_reseau c ON c.id = m.id_constante
    WHERE c.id_aep = ?
";
$paramsMois = array($aepId);
if ($redevanceId) {
    $queryMois = "
        SELECT m.id as mois_id, m.mois, SUM(v.montant) as montant_total
        FROM mois_facturation m
            LEFT JOIN versements v ON v.id_mois_facturation = m.id AND v.id_redevance = ?
            INNER JOIN constante_reseau c ON c.id = m.id_constante
        WHERE c.id_aep = ?
    ";
    $paramsMois = array($redevanceId, $aepId);
}
$queryMois .= " GROUP BY m.id, m.mois ORDER BY m.mois";
$moisMontants = Manager::prepare_query($queryMois, $paramsMois)->fetchAll();

// Récupérer les redevances (une seule si $redevanceId est défini, toutes sinon)
if ($redevanceId) {
    $redevances = Manager::prepare_query("
        SELECT id, libele, base_calcul
        FROM redevance
        WHERE id = ? AND id_aep = ?
        LIMIT 1
    ", array($redevanceId, $aepId))->fetchAll();
    if (count($redevances) == 0) {
        die('Redevance non trouvée.');
    }
} else {
    $redevances = Manager::prepare_query("
        SELECT id, libele, base_calcul
        FROM redevance
        WHERE id_aep = ?
    ", array($aepId))->fetchAll();
}

// Estimatif brut vs pondéré au taux de recouvrement (base vente d'eau uniquement)
$ponderationVenteEau = array(
    'nb_mois' => 0,
    'somme_facture_ttc' => 0.0,
    'somme_verse' => 0.0,
    'somme_entretien_ttc' => 0.0,
    'somme_recouvre_net' => 0.0,
    'estimatif_brut' => 0.0,
    'estimatif_pondere' => 0.0,
    'nb_redevances_vente_eau' => 0,
);
$moisIdsPonderation = array();
if ($selectedMoisId) {
    $moisIdsPonderation[] = (int) $selectedMoisId;
} else {
    foreach ($moisMontants as $mm) {
        $moisIdsPonderation[] = (int) $mm['mois_id'];
    }
}
$moisIdsPonderation = array_values(array_unique(array_filter($moisIdsPonderation)));
foreach ($redevances as $rd) {
    $base = isset($rd['base_calcul']) ? (string) $rd['base_calcul'] : 'vente_eau';
    if ($base === '' || $base === 'vente_eau') {
        $ponderationVenteEau['nb_redevances_vente_eau']++;
    }
}
if ($ponderationVenteEau['nb_redevances_vente_eau'] > 0 && count($moisIdsPonderation) > 0) {
    foreach ($moisIdsPonderation as $mid) {
        $st = Redevance::getStatFacturationRecouvrementMois($mid, $aepId);
        $ponderationVenteEau['nb_mois']++;
        $ponderationVenteEau['somme_facture_ttc'] += $st['facture_ttc'];
        $ponderationVenteEau['somme_verse'] += $st['verse'];
        $ponderationVenteEau['somme_entretien_ttc'] += $st['entretien_ttc'];
        $ponderationVenteEau['somme_recouvre_net'] += $st['recouvre_net'];
        $tauxMois = $st['taux'];
        foreach ($redevances as $rd) {
            $baseRd = isset($rd['base_calcul']) ? (string) $rd['base_calcul'] : 'vente_eau';
            if ($baseRd !== '' && $baseRd !== 'vente_eau') {
                continue;
            }
            $est = Redevance::calculerMontantEstimatif((int) $rd['id'], $mid);
            $ponderationVenteEau['estimatif_brut'] += $est;
            $ponderationVenteEau['estimatif_pondere'] += $est * $tauxMois;
        }
    }
}
$ponderationVenteEau['taux_implicite'] = ($ponderationVenteEau['estimatif_brut'] > 0)
    ? min(1.0, max(0.0, $ponderationVenteEau['estimatif_pondere'] / $ponderationVenteEau['estimatif_brut']))
    : null;

// Récupérer les détails des versements pour chaque redevance et le mois sélectionné
$versementsDetails = array();
foreach ($redevances as $redevance) {
    $currentRedevanceId = $redevance['id'];
    $query = "
        SELECT v.id, v.montant, v.date_versement, v.est_valide, m.mois
        FROM versements v
            INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
            INNER JOIN constante_reseau c ON c.id = m.id_constante
        WHERE v.id_redevance = ? AND c.id_aep = ?
    ";
    $params = array($currentRedevanceId, $aepId);
    if ($selectedMoisId) {
        $query .= " AND v.id_mois_facturation = ?";
        $params[] = $selectedMoisId;
    }
    $query .= " ORDER BY m.mois, v.date_versement";
    $details = Manager::prepare_query($query, $params)->fetchAll();
    $versementsDetails[$currentRedevanceId] = $details;
}

// Calculer le total des versements pour le mois sélectionné
$totalSelectedMonth = 0;
if ($selectedMoisId) {
    $queryTotal = "
        SELECT SUM(v.montant) as total
        FROM versements v
            INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
            INNER JOIN constante_reseau c ON c.id = m.id_constante
        WHERE v.id_mois_facturation = ? AND c.id_aep = ?
    ";
    $paramsTotal = array($selectedMoisId, $aepId);
    if ($redevanceId) {
        $queryTotal = "
            SELECT SUM(v.montant) as total
            FROM versements v
                INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
                INNER JOIN constante_reseau c ON c.id = m.id_constante
            WHERE v.id_redevance = ? AND v.id_mois_facturation = ? AND c.id_aep = ?
        ";
        $paramsTotal = array($redevanceId, $selectedMoisId, $aepId);
    }
    $totalResult = Manager::prepare_query($queryTotal, $paramsTotal)->fetch();
    $totalSelectedMonth = isset($totalResult['total']) ? $totalResult['total'] : 0;
} else {
    $queryTotal = "
        SELECT SUM(v.montant) as total
        FROM versements v
            INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
            INNER JOIN constante_reseau c ON c.id = m.id_constante
        WHERE c.id_aep = ?
    ";
    $paramsTotal = array($aepId);
    if ($redevanceId) {
        $queryTotal = "
            SELECT SUM(v.montant) as total
            FROM versements v
                INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
                INNER JOIN constante_reseau c ON c.id = m.id_constante
            WHERE v.id_redevance = ? AND c.id_aep = ?
        ";
        $paramsTotal = array($redevanceId, $aepId);
    }
    $totalResult = Manager::prepare_query($queryTotal, $paramsTotal)->fetch();
    $totalSelectedMonth = isset($totalResult['total']) ? $totalResult['total'] : 0;
}

// Calculer le total des versements non validés
$queryNonValidated = "
    SELECT SUM(v.montant) as total_non_validated
    FROM versements v
        INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
        INNER JOIN constante_reseau c ON c.id = m.id_constante
    WHERE v.est_valide = 0 AND c.id_aep = ?
";
$paramsNonValidated = array($aepId);
if ($redevanceId) {
    $queryNonValidated = "
        SELECT SUM(v.montant) as total_non_validated
        FROM versements v
            INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
            INNER JOIN constante_reseau c ON c.id = m.id_constante
        WHERE v.id_redevance = ? AND v.est_valide = 0 AND c.id_aep = ?
    ";
    $paramsNonValidated = array($redevanceId, $aepId);
}
$totalNonValidatedResult = Manager::prepare_query($queryNonValidated, $paramsNonValidated)->fetch();
$totalNonValidated = isset($totalNonValidatedResult['total_non_validated']) ? $totalNonValidatedResult['total_non_validated'] : 0;

// Gérer les messages de retour
$message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'versement_added':
            $message = '<div class="alert alert-success">Versement ajouté avec succès.</div>';
            break;
        case 'versement_deleted':
            $message = '<div class="alert alert-success">Versement supprimé avec succès.</div>';
            break;
        case 'versement_validated':
            $message = '<div class="alert alert-success">Versement validé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_aep':
            $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
            break;
        case 'add_failed':
        case 'delete_failed':
        case 'validate_failed':
            $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
            $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
            break;
    }
}
?>
<div class="container-fluid mt-5">
    <h2 class="mb-4">Détails des Versements<?php echo $redevanceId ? ' - ' . htmlspecialchars($redevances[0]['libele']) : ''; ?></h2>
    <?php echo $message; ?>

    <!-- Navigation entre redevances -->
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="redevanceSelect" class="form-label">Sélectionner une redevance :</label>
            <select id="redevanceSelect" class="form-select" onchange="window.location.href = this.value;">
                <option value="?page=redevance_details<?php echo $selectedMoisId ? '&id_mois=' . $selectedMoisId : ''; ?>" <?php echo !$redevanceId ? 'selected' : ''; ?>>Toutes les redevances</option>
                <?php foreach ($allRedevances as $rdv): ?>
                    <option value="?page=redevance_details&id=<?php echo $rdv['id']; ?><?php echo $selectedMoisId ? '&id_mois=' . $selectedMoisId : ''; ?>"
                        <?php echo $redevanceId == $rdv['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($rdv['libele']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Pondération estimatif / recouvrement (vente d'eau) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary shadow-sm">
                <div class="card-header bg-primary text-white">
                    <strong>Estimatif pondéré au recouvrement (vente d'eau)</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Les versements de redevance ne peuvent pas dépasser ce qui est réellement recouvrable sur la facturation.
                        Pour chaque mois, le <strong>taux de recouvrement</strong> est :
                        somme des montants versés sur factures ÷ facturation TTC du mois (ex. 10 000 ÷ 10 000 = 100 %).
                        L'<strong>estimatif pondéré</strong> est la somme, mois par mois, de (montant estimatif brut du mois × ce taux).
                        Le bloc « recouvrement net » (versé − entretiens) reste un indicateur complémentaire.
                        <?php if ($selectedMoisId): ?>
                            <span class="d-block mt-1">Période affichée : <strong>un mois</strong> (filtre actif).</span>
                        <?php else: ?>
                            <span class="d-block mt-1">Période affichée : <strong>tous les mois</strong> listés à gauche (cumul mois par mois).</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($ponderationVenteEau['nb_redevances_vente_eau'] <= 0): ?>
                        <div class="alert alert-secondary mb-0">
                            Aucune redevance sur la base « vente d'eau » dans le filtre actuel (ex. redevances « branchements » uniquement).
                        </div>
                    <?php elseif (count($moisIdsPonderation) === 0): ?>
                        <div class="alert alert-secondary mb-0">Aucun mois de facturation disponible pour ce calcul.</div>
                    <?php else: ?>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="small text-muted">Facturation TTC (cumul période)</div>
                                <div class="fs-5 fw-bold"><?php echo number_format($ponderationVenteEau['somme_facture_ttc'], 2, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Versé sur factures (cumul)</div>
                                <div class="fs-5"><?php echo number_format($ponderationVenteEau['somme_verse'], 2, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Entretiens compteur TTC (cumul)</div>
                                <div class="fs-5"><?php echo number_format($ponderationVenteEau['somme_entretien_ttc'], 2, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Recouvrement net (versé − entretiens)</div>
                                <div class="fs-5 fw-bold text-success"><?php echo number_format($ponderationVenteEau['somme_recouvre_net'], 2, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Taux implicite sur l'estimatif (pondéré ÷ brut)</div>
                                <div class="fs-5 fw-bold">
                                    <?php echo $ponderationVenteEau['taux_implicite'] !== null ? number_format($ponderationVenteEau['taux_implicite'] * 100, 2, ',', ' ') . ' %' : '—'; ?>
                                </div>
                            </div>
                            <div class="col-12"><hr class="my-2"></div>
                            <div class="col-md-6">
                                <div class="small text-muted">Montant estimatif brut (redevances vente d'eau, période)</div>
                                <div class="fs-5"><?php echo number_format($ponderationVenteEau['estimatif_brut'], 2, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Montant estimatif pondéré (plafond cohérent avec le recouvrement)</div>
                                <div class="fs-5 fw-bold text-primary"><?php echo number_format($ponderationVenteEau['estimatif_pondere'], 2, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau des totaux -->
    <div class="row mb-4">
        <div class="col-12">
            <table class="table table-bordered table-striped bg-light">
                <thead class="table-primary">
                <tr>
                    <th>Total du Mois Sélectionné</th>
                    <th>Total Non Validé</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php echo number_format($totalSelectedMonth, 2); ?></td>
                    <td><?php echo number_format($totalNonValidated, 2); ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex mb-3">
        <a href="?page=redevance" class="btn btn-secondary me-2">Retour aux redevances</a>
        <a href="?page=redevance_details" class="btn btn-primary me-2">Réinitialiser les filtres</a>
        <?php if ($redevanceId): ?>
            <a href="?page=redevance_details<?php echo $selectedMoisId ? '&id_mois=' . $selectedMoisId : ''; ?>" class="btn btn-outline-primary me-2">Toutes les redevances</a>
        <?php endif; ?>
        <?php if ($selectedMoisId): ?>
            <a href="?page=redevance_details<?php echo $redevanceId ? '&id=' . $redevanceId : ''; ?>" class="btn btn-outline-primary">Tous les mois</a>
        <?php endif; ?>
    </div>

    <div class="row">
        <!-- Liste des mois avec montant total (à gauche) -->
        <div class="col-md-3 col-lg-2 sidebar">
            <h4 class="mb-3">Mois</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                    <tr>
                        <th>Mois</th>
                        <th>Montant Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($moisMontants) > 0): ?>
                        <?php foreach ($moisMontants as $mois): ?>
                            <tr class="<?php echo ($selectedMoisId == $mois['mois_id']) ? 'bg-primary text-white' : ''; ?>">
                                <td>
                                    <a href="?page=redevance_details<?php echo $redevanceId ? '&id=' . $redevanceId : ''; ?>&id_mois=<?php echo $mois['mois_id']; ?>"
                                       class="text-decoration-none <?php echo ($selectedMoisId == $mois['mois_id']) ? 'text-white' : 'text-dark'; ?>">
                                        <?php echo htmlspecialchars($mois['mois']); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format(isset($mois['montant_total']) ? $mois['montant_total'] : 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">Aucun mois trouvé.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Détails des versements pour les redevances (à droite) -->
        <div class="col-md-9 col-lg-10">
            <div class="row">
                <?php foreach ($redevances as $redevance): ?>
                    <div class="col-12 mb-4">
                        <h4 class="mb-3"><?php echo htmlspecialchars($redevance['libele']); ?></h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                <tr>
                                    <th>Mois</th>
                                    <th>Montant</th>
                                    <th>Date de Versement</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php $details = isset($versementsDetails[$redevance['id']]) ? $versementsDetails[$redevance['id']] : array(); ?>
                                <?php if (count($details) > 0): ?>
                                    <?php foreach ($details as $versement): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($versement['mois']); ?></td>
                                            <td><?php echo number_format($versement['montant'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($versement['date_versement']); ?></td>
                                            <td><?php echo $versement['est_valide'] ? 'Validé' : 'Non validé'; ?></td>
                                            <td>
                                                <?php if (!$versement['est_valide']): ?>
                                                    <!-- Bouton Valider -->
                                                    <button type="button" class="btn btn-success btn-sm action-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#validateVersementModal"
                                                            data-id="<?php echo $versement['id']; ?>"
                                                            data-montant="<?php echo htmlspecialchars($versement['montant']); ?>">
                                                        Valider
                                                    </button>
                                                    <!-- Bouton Supprimer -->
                                                    <button type="button" class="btn btn-danger btn-sm action-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteVersementModal"
                                                            data-id="<?php echo $versement['id']; ?>"
                                                            data-montant="<?php echo htmlspecialchars($versement['montant']); ?>">
                                                        Supprimer
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Aucun versement trouvé pour ce mois et cette redevance.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour supprimer un versement -->
<div class="modal fade" id="deleteVersementModal" tabindex="-1" aria-labelledby="deleteVersementModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteVersementModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez confirmer la suppression du versement :</p>
                <p><strong>Montant : <span id="delete_montant"></span></strong></p>
                <form id="deleteVersementForm" method="POST" action="">
                    <input type="hidden" name="id" id="delete_id">
                    <input type="hidden" name="action" value="delete_versement">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="deleteVersementForm">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour valider un versement -->
<div class="modal fade" id="validateVersementModal" tabindex="-1" aria-labelledby="validateVersementModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="validateVersementModalLabel">Confirmer la validation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez confirmer la validation du versement :</p>
                <p><strong>Montant : <span id="validate_montant"></span></strong></p>
                <form id="validateVersementForm" method="POST" action="">
                    <input type="hidden" name="id" id="validate_id">
                    <input type="hidden" name="action" value="validate_versement">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-success" form="validateVersementForm">Valider</button>
            </div>
        </div>
    </div>
</div>

<!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>-->
<script>
    // Remplir le modal de suppression
    document.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('button[data-bs-target="#deleteVersementModal"]');
        const validateButton = event.target.closest('button[data-bs-target="#validateVersementModal"]');

        if (deleteButton) {
            const id = deleteButton.getAttribute('data-id');
            const montant = deleteButton.getAttribute('data-montant');
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_montant').textContent = montant;
        }

        if (validateButton) {
            const id = validateButton.getAttribute('data-id');
            const montant = validateButton.getAttribute('data-montant');
            document.getElementById('validate_id').value = id;
            document.getElementById('validate_montant').textContent = montant;
        }
    });
</script>