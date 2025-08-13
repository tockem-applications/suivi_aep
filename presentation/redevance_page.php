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
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
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

    <!-- Bouton pour ajouter une redevance -->
    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal"
            data-bs-target="#addRedevanceModal" <?php echo $aepId ? '' : 'disabled'; ?>>Ajouter une redevance
    </button>

    <!-- Tableau des redevances -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
            <tr>
<!--                <th>ID</th>-->
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
<!--                        <td>--><?php //echo htmlspecialchars($redevance['id']); ?><!--</td>-->
                        <td>
                            <a href="?page=redevance_details&id=<?php echo htmlspecialchars($redevance['id']); ?>"><?php echo htmlspecialchars($redevance['libele']); ?></a>
                        </td>

                        <td><?php echo htmlspecialchars($redevance['pourcentage']); ?>%</td>
                        <td><?php echo htmlspecialchars($redevance['type']); ?></td>
                        <td><?php echo getLetterMonth(htmlspecialchars($redevance['mois_debut'])); ?></td>
                        <td><?php echo htmlspecialchars($redevance['description'] ?: 'Aucune'); ?></td>
                        <td>
                            <button type="button" class="btn btn-warning btn-sm action-btn" data-bs-toggle="modal"
                                    data-bs-target="#editRedevanceModal"
                                    data-id="<?php echo $redevance['id']; ?>"
                                    data-libele="<?php echo htmlspecialchars($redevance['libele']); ?>"
                                    data-type="<?php echo $redevance['type']; ?>"
                                    data-pourcentage="<?php echo $redevance['pourcentage']; ?>"
                                    data-mois_debut="<?php echo $redevance['mois_debut']; ?>"
                                    data-description="<?php echo htmlspecialchars($redevance['description']); ?>">
                                Modifier
                            </button>
                            <button type="button" class="btn btn-danger btn-sm action-btn" data-bs-toggle="modal"
                                    data-bs-target="#deleteRedevanceModal" data-id="<?php echo $redevance['id']; ?>"
                                    data-libele="<?php echo htmlspecialchars($redevance['libele']); ?>">Supprimer
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">Aucune redevance trouvée.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal pour ajouter une redevance -->
<div class="modal fade" id="addRedevanceModal" tabindex="-1" aria-labelledby="addRedevanceModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRedevanceModalLabel">Ajouter une redevance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addRedevanceForm" method="POST" action="traitement/redevance_t.php">
                    <div class="mb-3">
                        <label for="add_libele" class="form-label">Libellé</label>
                        <input type="text" class="form-control" id="add_libele" name="libele" required maxlength="64">
                        <div class="invalid-feedback">Veuillez entrer un libellé (1 à 64 caractères).</div>
                    </div>
                    <div class="mb-3">
                        <label for="add_pourcentage" class="form-label">Pourcentage (%)</label>
                        <input type="number" step="0.01" class="form-control" id="add_pourcentage" name="pourcentage"
                               required min="0" max="100">
                        <div class="invalid-feedback">Veuillez entrer un pourcentage entre 0 et 100.</div>
                    </div>
                    <div class="mb-3">
                        <label for="mois_debut" class="form-label">Mois de Debut</label>
                        <input type="month" class="form-control" id="mois_debut" name="mois_debut" required>
                        <div class="invalid-feedback">Veuillez selectionner le mois de debut</div>
                    </div>
                    <div class="mb-3">
                        <label for="type_redevance" class="form-label">Type de redevance</label>
                        <select class="form-select" name="type" id="type_redevance">
                            <option value="sortie">Sortie</option>
                            <option value="entree">Entrée</option>
                        </select>
                        <div class="invalid-feedback">Veuillez selectionner le type de redevance</div>
                    </div>
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="action" value="add_redevance">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" form="addRedevanceForm"
                        onclick="return validateAddRedevanceForm()">Ajouter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une redevance -->
<div class="modal fade" id="editRedevanceModal" tabindex="-1" aria-labelledby="editRedevanceModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRedevanceModalLabel">Modifier une redevance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editRedevanceForm" method="POST" action="traitement/redevance_t.php">
                    <div class="mb-3">
                        <label for="edit_libele" class="form-label">Libellé</label>
                        <input type="text" class="form-control" id="edit_libele" name="libele" required maxlength="64">
                        <div class="invalid-feedback">Veuillez entrer un libellé (1 à 64 caractères).</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_pourcentage" class="form-label">Pourcentage (%)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_pourcentage" name="pourcentage"
                               required min="0" max="100">
                        <div class="invalid-feedback">Veuillez entrer un pourcentage entre 0 et 100.</div>
                    </div>

                    <div class="mb-3">
                        <label for="mois_debut" class="form-label">Mois de Debut</label>
                        <input type="month" class="form-control" id="mois_debut" name="mois_debut" required>
                        <div class="invalid-feedback">Veuillez selectionner le mois de debut</div>
                    </div>
                    <div class="mb-3">
                        <label for="type_redevance" class="form-label">Type de redevance</label>
                        <select class="form-select" name="type" id="type_redevance">
                            <option value="sortie">Sortie</option>
                            <option value="entree">Entrée</option>
                        </select>
                        <div class="invalid-feedback">Veuillez selectionner le type de redevance</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="action" value="update_redevance">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" form="editRedevanceForm"
                        onclick="return validateEditRedevanceForm()">Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour supprimer une redevance -->
<div class="modal fade" id="deleteRedevanceModal" tabindex="-1" aria-labelledby="deleteRedevanceModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRedevanceModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez confirmer la suppression de la redevance :</p>
                <p><strong id="delete_libele"></strong></p>
                <form id="deleteRedevanceForm" method="POST" action="traitement/redevance_t.php">
                    <input type="hidden" name="id" id="delete_id">
                    <input type="hidden" name="action" value="delete_redevance">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="deleteRedevanceForm">Supprimer</button>
            </div>
        </div>
    </div>
</div>
<script>
    function validateAddRedevanceForm() {
        const form = document.getElementById('addRedevanceForm');
        const libele = document.getElementById('add_libele');
        const pourcentage = document.getElementById('add_pourcentage');
        let isValid = true;

        libele.classList.remove('is-invalid');
        pourcentage.classList.remove('is-invalid');

        if (libele.value.trim().length < 1 || libele.value.trim().length > 64) {
            libele.classList.add('is-invalid');
            isValid = false;
        }
        if (pourcentage.value <= 0 || pourcentage.value > 100) {
            pourcentage.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    function validateEditRedevanceForm() {
        const form = document.getElementById('editRedevanceForm');
        const libele = document.getElementById('edit_libele');
        const pourcentage = document.getElementById('edit_pourcentage');
        let isValid = true;

        libele.classList.remove('is-invalid');
        pourcentage.classList.remove('is-invalid');

        if (libele.value.trim().length < 1 || libele.value.trim().length > 64) {
            libele.classList.add('is-invalid');
            isValid = false;
        }
        if (pourcentage.value <= 0 || pourcentage.value > 100) {
            pourcentage.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    // Remplir le modal de modification
    document.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-bs-target="#editRedevanceModal"]');
        if (button) {
            const id = button.getAttribute('data-id');
            const libele = button.getAttribute('data-libele');
            const pourcentage = button.getAttribute('data-pourcentage');
            const description = button.getAttribute('data-description');
            // const type_redevance = button.getAttribute('data-description');
            const mois_debut = button.getAttribute('data-mois_debut');
            alert(mois_debut);
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_libele').value = libele;
            document.getElementById('edit_pourcentage').value = pourcentage;
            // document.getElementById('edit_type').value = pourcentage;
            document.getElementById('edit_mois_debut').value = mois_debut;
            document.getElementById('edit_description').value = description || '';
        }
    });

    // Remplir le modal de suppression
    document.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-bs-target="#deleteRedevanceModal"]');
        if (button) {
            const id = button.getAttribute('data-id');
            const libele = button.getAttribute('data-libele');
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_libele').textContent = libele;
        }
    });
</script>
