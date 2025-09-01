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
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Libellé</th>
                            <th>Pourcentage</th>
                            <th>Type</th>
                            <th>Mois de debut</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($redevances) > 0): ?>
                            <?php foreach ($redevances as $redevance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($redevance['libele']); ?></td>
                                    <td><?php echo $redevance['pourcentage']; ?>%</td>
                                    <td>
                                        <span
                                            class="badge <?php echo $redevance['type'] === 'Entree' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $redevance['type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $redevance['mois_debut']; ?></td>
                                    <td><?php echo htmlspecialchars($redevance['description']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
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
                                <td colspan="6" class="text-center text-muted">Aucune redevance configurée</td>
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
                        <label for="libele" class="form-label">Libellé</label>
                        <input type="text" class="form-control" id="libele" name="libele" required maxlength="64">
                    </div>

                    <div class="mb-3">
                        <label for="pourcentage" class="form-label">Pourcentage</label>
                        <input type="number" class="form-control" id="pourcentage" name="pourcentage" required min="0"
                            max="100" step="0.01">
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Sélectionner un type</option>
                            <option value="Entree">Entrée</option>
                            <option value="Sortie">Sortie</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="mois_debut" class="form-label">Mois de début (YYYY-MM)</label>
                        <input type="month" class="form-control" id="mois_debut" name="mois_debut" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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