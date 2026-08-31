<?php
// Page de détail d'un tarif avec gestion des tarifs différenciés

@include_once("../donnees/constante_reseau.php");
@include_once("../donnees/tarif_differencie.php");
@include_once("../donnees/manager.php");
@include_once("donnees/constante_reseau.php");
@include_once("donnees/tarif_differencie.php");
@include_once("donnees/manager.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../?page=login');
    exit;
}

// Vérifier si un AEP est sélectionné
if (!isset($_SESSION['id_aep'])) {
    header('Location: ../?page=tarif_aep&error=no_aep');
    exit;
}

$aepId = (int) $_SESSION['id_aep'];
$tarifId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($tarifId <= 0) {
    header('Location: ../?page=tarif_aep&error=invalid_tarif');
    exit;
}

// Récupérer les détails du tarif
$tarif = Manager::prepare_query(
    'SELECT * FROM constante_reseau WHERE id = ? AND id_aep = ?',
    array($tarifId, $aepId)
)->fetch();

if (!$tarif) {
    header('Location: ../?page=tarif_aep&error=tarif_not_found');
    exit;
}

// Récupérer les tarifs différenciés
$tarifsDifferencies = TarifDifferencie::getTarifsByConstante($tarifId);

// Récupérer les mois facturés
$moisFactures = Manager::prepare_query(
    'SELECT COUNT(*) as count FROM mois_facturation WHERE id_constante = ?',
    array($tarifId)
)->fetch();
$hasMoisFactures = $moisFactures['count'] > 0;

// Gérer les messages
$message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'tarif_differencie_added':
            $message = '<div class="alert alert-success">Tarif différencié ajouté avec succès.</div>';
            break;
        case 'tarif_differencie_deleted':
            $message = '<div class="alert alert-success">Tarif différencié supprimé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
    $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-currency-exchange"></i> Détails du Tarif</h2>
        <a href="?page=tarif_aep" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour à la liste
        </a>
    </div>

    <?php echo $message; ?>

    <!-- Informations du tarif de base -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informations du Tarif de Base</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Prix par m³ :</th>
                            <td><strong><?php echo number_format($tarif['prix_metre_cube_eau'], 0, ',', ' '); ?> FCFA</strong></td>
                        </tr>
                        <tr>
                            <th>Entretien compteur :</th>
                            <td><strong><?php echo number_format($tarif['prix_entretient_compteur'], 0, ',', ' '); ?> FCFA</strong></td>
                        </tr>
                        <tr>
                            <th>TVA :</th>
                            <td><strong><?php echo number_format($tarif['prix_tva'], 2, ',', ' '); ?>%</strong></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Date création :</th>
                            <td><?php echo date('d/m/Y', strtotime($tarif['date_creation'])); ?></td>
                        </tr>
                        <tr>
                            <th>Statut :</th>
                            <td>
                                <?php if ($tarif['est_actif']): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Description :</th>
                            <td><?php echo htmlspecialchars($tarif['description'] ?: 'Aucune description'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Tarifs Différenciés -->
    <div class="card">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-layers"></i> Tarifs Différenciés par Intervalle de Consommation</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTarifDifferencieModal"
                <?php echo $hasMoisFactures ? 'disabled title="Impossible d\'ajouter des tarifs différenciés car ce tarif a déjà des mois de facturation associés."' : ''; ?>>
                <i class="bi bi-plus-circle"></i> Ajouter un tarif différencié
            </button>
        </div>
        <div class="card-body">
            <?php if ($hasMoisFactures): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Ce tarif a déjà des mois de facturation associés. Il n'est plus possible d'ajouter ou de supprimer des tarifs différenciés.
                </div>
            <?php endif; ?>
            <?php if (count($tarifsDifferencies) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Intervalle de consommation (m³)</th>
                                <th>Prix m³ (FCFA)</th>
                                <th>Entretien (FCFA)</th>
                                <th>TVA (%)</th>
                                <th>Date création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tarifsDifferencies as $td): ?>
                                <tr>
                                    <td>
                                        <strong>[<?php echo number_format($td['min_consommation'], 2, ',', ' '); ?> 
                                        <?php if ($td['max_consommation'] !== null): ?>
                                            ; <?php echo number_format($td['max_consommation'], 2, ',', ' '); ?>[
                                        <?php else: ?>
                                            ; +∞[
                                        <?php endif; ?>
                                        </strong>
                                    </td>
                                    <td><?php echo number_format($td['prix_metre_cube_eau'], 0, ',', ' '); ?></td>
                                    <td><?php echo number_format($td['prix_entretient_compteur'], 0, ',', ' '); ?></td>
                                    <td><?php echo number_format($td['prix_tva'], 2, ',', ' '); ?>%</td>
                                    <td><?php echo date('d/m/Y', strtotime($td['date_creation'])); ?></td>
                                    <td>
                                        <form method="POST" action="traitement/tarif_t.php" class="d-inline"
                                              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce tarif différencié ?');">
                                            <input type="hidden" name="action" value="delete_tarif_differencie">
                                            <input type="hidden" name="tarif_differencie_id" value="<?php echo $td['id']; ?>">
                                            <input type="hidden" name="id_constante_reseau" value="<?php echo $tarifId; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                <?php echo $hasMoisFactures ? 'disabled title="Impossible de supprimer car ce tarif a déjà des mois de facturation associés."' : ''; ?>>
                                                <i class="bi bi-trash"></i> Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-info-circle fs-1"></i>
                    <p class="mt-2">Aucun tarif différencié configuré pour ce tarif de base</p>
                    <p class="small">Les abonnés utiliseront le tarif de base pour toutes les consommations</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un tarif différencié -->
<div class="modal fade" id="addTarifDifferencieModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Ajouter un Tarif Différencié</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="traitement/tarif_t.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_tarif_differencie">
                    <input type="hidden" name="id_constante_reseau" value="<?php echo $tarifId; ?>">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note :</strong> L'intervalle est défini comme [min_consommation ; max_consommation[ (max non inclus).
                        Si max n'est pas défini, le tarif s'applique à toutes les consommations supérieures ou égales au min.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="min_consommation" class="form-label">Consommation minimale (m³) *</label>
                                <input type="number" class="form-control" id="min_consommation" name="min_consommation"
                                       required min="0" step="0.01" value="0">
                                <small class="form-text text-muted">Consommation minimale (peut être 0)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_consommation" class="form-label">Consommation maximale (m³)</label>
                                <input type="number" class="form-control" id="max_consommation" name="max_consommation"
                                       min="0" step="0.01" placeholder="Laisser vide pour +∞">
                                <small class="form-text text-muted">Laisser vide pour un intervalle illimité</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="prix_metre_cube_diff" class="form-label">Prix par m³ (FCFA) *</label>
                                <input type="number" class="form-control" id="prix_metre_cube_diff" name="prix_metre_cube_eau"
                                       required min="1" step="1" value="<?php echo $tarif['prix_metre_cube_eau']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="prix_entretien_diff" class="form-label">Entretien compteur (FCFA) *</label>
                                <input type="number" class="form-control" id="prix_entretien_diff" name="prix_entretient_compteur"
                                       required min="0" step="1" value="<?php echo $tarif['prix_entretient_compteur']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="prix_tva_diff" class="form-label">TVA (%) *</label>
                                <input type="number" class="form-control" id="prix_tva_diff" name="prix_tva"
                                       required min="0" max="100" step="0.01" value="<?php echo $tarif['prix_tva']; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="date_creation_diff" class="form-label">Date de création</label>
                        <input type="date" class="form-control" id="date_creation_diff" name="date_creation"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description_diff" class="form-label">Description</label>
                        <textarea class="form-control" id="description_diff" name="description" rows="2"
                                  placeholder="Description du tarif différencié..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter le tarif différencié</button>
                </div>
            </form>
        </div>
    </div>
</div>
