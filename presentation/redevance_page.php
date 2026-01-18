<?php

// Inclure la classe Manager et le modèle Redevance
@include_once("../donnees/redevance.php");
@include_once("donnees/redevance.php");

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

<div class="container mt-5">
    <h2 class="mb-4">Gestion des Redevances</h2>
    <?php echo $message; ?>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>

    <!-- Section des Redevances -->
    <div class="card">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0"><i class="bi bi-percent"></i> Redevances</h4>
        </div>
        <div class="card-body">
            <!-- Bouton pour ajouter une redevance -->
            <button type="button" class="btn btn-warning mb-3" data-bs-toggle="modal"
                data-bs-target="#addRedevanceModal" <?php echo $aepId ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> Ajouter une redevance
            </button>

            <!-- Tableau des redevances -->
            <div class="table-responsive">
                <table class="table_searching table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Libellé</th>
                            <th>Base de calcul</th>
                            <th>Type de calcul</th>
                            <th>Valeur</th>
                            <th>Mois de début</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($redevances) > 0): ?>
                            <?php foreach ($redevances as $redevance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($redevance['libele']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php 
                                            $base = isset($redevance['base_calcul']) ? $redevance['base_calcul'] : 'vente_eau';
                                            echo $base == 'vente_eau' ? 'Vente d\'eau' : 'Branchements'; 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php 
                                            $type_calc = isset($redevance['type_calcul']) ? $redevance['type_calcul'] : 'pourcentage';
                                            echo $type_calc == 'pourcentage' ? 'Pourcentage' : 'Montant fixe'; 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $type_calc = isset($redevance['type_calcul']) ? $redevance['type_calcul'] : 'pourcentage';
                                        if ($type_calc == 'pourcentage') {
                                            echo number_format($redevance['pourcentage'], 2) . '%';
                                        } else {
                                            $montant = isset($redevance['montant_par_m3']) ? $redevance['montant_par_m3'] : 0;
                                            echo number_format($montant, 0, ',', ' ') . ' FCFA';
                                            if ($base == 'vente_eau') {
                                                echo ' / m³';
                                            } else {
                                                echo ' / branchement';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $redevance['mois_debut']; ?></td>
                                    <td><?php echo htmlspecialchars($redevance['description']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?page=redevance_versements&id_redevance=<?php echo $redevance['id']; ?>" 
                                               class="btn btn-sm btn-outline-success" title="Gérer les versements">
                                                <i class="bi bi-cash-coin"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="editRedevance(<?php echo $redevance['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteRedevance(<?php echo $redevance['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Aucune redevance configurée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter une redevance -->
<div class="modal fade" id="addRedevanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Redevance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRedevanceForm" method="post" action="traitement/redevance_t.php">
                    <input type="hidden" name="action" value="add_redevance">
                    <input type="hidden" name="id_aep" value="<?php echo $aepId; ?>">

                    <div class="mb-3">
                        <label for="libele" class="form-label">Libellé <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="libele" name="libele" required maxlength="64">
                    </div>

                    <div class="mb-3">
                        <label for="base_calcul" class="form-label">Base de calcul <span class="text-danger">*</span></label>
                        <select class="form-select" id="base_calcul" name="base_calcul" required>
                            <option value="vente_eau">Vente d'eau consommée</option>
                            <option value="branchements">Branchements</option>
                        </select>
                        <small class="form-text text-muted">Sur quoi se base le calcul de la redevance</small>
                    </div>

                    <div class="mb-3">
                        <label for="type_calcul" class="form-label">Type de calcul <span class="text-danger">*</span></label>
                        <select class="form-select" id="type_calcul" name="type_calcul" required>
                            <option value="pourcentage">Pourcentage</option>
                            <option value="montant_fixe">Montant fixe</option>
                        </select>
                        <small class="form-text text-muted">Comment calculer la redevance</small>
                    </div>

                    <div class="mb-3" id="div_pourcentage">
                        <label for="pourcentage" class="form-label">Pourcentage (%) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="pourcentage" name="pourcentage" min="0" max="100" step="0.01" value="0">
                        <small class="form-text text-muted">Pourcentage à appliquer sur la base de calcul</small>
                    </div>

                    <div class="mb-3" id="div_montant_fixe" style="display: none;">
                        <label for="montant_par_m3" class="form-label">Montant fixe (FCFA) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="montant_par_m3" name="montant_par_m3" min="0" step="0.01" value="0">
                        <small class="form-text text-muted" id="montant_help">Montant fixe par m³ consommé</small>
                    </div>

                    <div class="mb-3">
                        <label for="mois_debut" class="form-label">Mois de début (YYYY-MM) <span class="text-danger">*</span></label>
                        <input type="month" class="form-control" id="mois_debut" name="mois_debut" required value="<?php echo date('Y-m'); ?>">
                        <small class="form-text text-muted">Mois à partir duquel cette redevance s'applique</small>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="alert alert-info">
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

    function editRedevance(id) {
        // Implémenter l'édition des redevances
        alert('Fonctionnalité d\'édition à implémenter');
    }

    function deleteRedevance(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette redevance ?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'traitement/redevance_t.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_redevance';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>