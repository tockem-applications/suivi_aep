<?php

// Inclure la classe Manager
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_clef') {
            $value = trim($_POST['value']);
            if (strlen($value) < 1 || strlen($value) > 32) {
                $message = '<div class="alert alert-danger">La valeur de la clé doit être entre 1 et 32 caractères.</div>';
            } else {
                try {
                    $query = Manager::prepare_query(
                        "INSERT INTO clefs (value) VALUES (?)",
                        array($value)
                    );
                    $message = '<div class="alert alert-success">Clé ajoutée avec succès.</div>';
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Erreur lors de l\'ajout de la clé : ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        } elseif ($_POST['action'] === 'delete_clef') {
            $clef_id = (int)$_POST['clef_id'];
            try {
                // Supprimer l'association avec un utilisateur (si elle existe)
                Manager::prepare_query(
                    "DELETE FROM user_clefs WHERE clef_id = ?",
                    array($clef_id)
                );
                // Supprimer la clé
                Manager::prepare_query(
                    "DELETE FROM clefs WHERE id = ?",
                    array($clef_id)
                );
                $message = '<div class="alert alert-success">Clé supprimée avec succès.</div>';
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">Erreur lors de la suppression de la clé : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } elseif ($_POST['action'] === 'update_clef') {
            $clef_id = (int)$_POST['clef_id'];
            $value = trim($_POST['value']);
            $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;

            if (strlen($value) < 1 || strlen($value) > 32) {
                $message = '<div class="alert alert-danger">La valeur de la clé doit être entre 1 et 32 caractères.</div>';
            } else {
                try {
                    // Mettre à jour la valeur de la clé
                    Manager::prepare_query(
                        "UPDATE clefs SET value = ? WHERE id = ?",
                        array($value, $clef_id)
                    );
                    // Mettre à jour l'association avec l'utilisateur
                    Manager::prepare_query(
                        "DELETE FROM user_clefs WHERE clef_id = ?",
                        array($clef_id)
                    );
                    if ($user_id) {
                        Manager::prepare_query(
                            "INSERT INTO user_clefs (clef_id, user_id) VALUES (?, ?)",
                            array($clef_id, $user_id)
                        );
                    }
                    $message = '<div class="alert alert-success">Clé mise à jour avec succès.</div>';
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Erreur lors de la mise à jour de la clé : ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
}

// Récupérer toutes les clés avec leurs utilisateurs associés
$clefs = Manager::prepare_query(
    "SELECT c.id, c.value, u.id as user_id, u.nom, u.prenom
     FROM clefs c
     LEFT JOIN user_clefs uc ON c.id = uc.clef_id
     LEFT JOIN users u ON uc.user_id = u.id",
    array()
)->fetchAll();

// Récupérer tous les utilisateurs pour les formulaires
$users = Manager::prepare_query("SELECT id, nom, prenom FROM users", array())->fetchAll();
?>
<div class="container mt-5">
    <h2 class="mb-4">Gestion des Clés</h2>
    <?php echo $message; ?>
<!--    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>-->

    <!-- Bouton pour ajouter une clé -->
    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addClefModal">Ajouter une clé</button>

    <!-- Tableau des clés -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Valeur</th>
                <th>Utilisateur Associé</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($clefs) > 0): ?>
                <?php foreach ($clefs as $clef): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($clef['id']); ?></td>
                        <td><?php echo htmlspecialchars($clef['value']); ?></td>
                        <td class="text-dark">
                            <?php if ($clef['user_id']): ?>
                                <a href="?page=user_details&id=<?php echo $clef['user_id']?>"><?php echo htmlspecialchars($clef['nom'] . ' ' . $clef['prenom']) ; ?></a>
                            <?php else:?>
<!--                                --><?php //echo $clef['user_id'] ? htmlspecialchars($clef['nom'] . ' ' . $clef['prenom']) : 'Aucun'; ?>
                                Aucun
                            <?php endif?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-warning btn-sm action-btn" data-bs-toggle="modal" data-bs-target="#editClefModal" data-clef-id="<?php echo $clef['id']; ?>" data-clef-value="<?php echo htmlspecialchars($clef['value']); ?>" data-user-id="<?php echo $clef['user_id'] ?: ''; ?>">Modifier</button>
                            <button type="button" class="btn btn-danger btn-sm action-btn" data-bs-toggle="modal" data-bs-target="#deleteClefModal" data-clef-id="<?php echo $clef['id']; ?>" data-clef-value="<?php echo htmlspecialchars($clef['value']); ?>">Supprimer</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">Aucune clé trouvée.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal pour ajouter une clé -->
<div class="modal fade" id="addClefModal" tabindex="-1" aria-labelledby="addClefModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addClefModalLabel">Ajouter une clé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addClefForm" method="POST">
                    <div class="mb-3">
                        <label for="add_clef_value" class="form-label">Valeur de la clé</label>
                        <input type="text" class="form-control" id="add_clef_value" name="value" required maxlength="32">
                        <div class="invalid-feedback">Veuillez entrer une valeur valide (1 à 32 caractères).</div>
                    </div>
                    <input type="hidden" name="action" value="add_clef">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" form="addClefForm" onclick="return validateAddClefForm()">Ajouter</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une clé -->
<div class="modal fade" id="editClefModal" tabindex="-1" aria-labelledby="editClefModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editClefModalLabel">Modifier une clé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editClefForm" method="POST">
                    <div class="mb-3">
                        <label for="edit_clef_value" class="form-label">Valeur de la clé</label>
                        <input type="text" class="form-control" id="edit_clef_value" name="value" required maxlength="32">
                        <div class="invalid-feedback">Veuillez entrer une valeur valide (1 à 32 caractères).</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_clef_user" class="form-label">Associer à un utilisateur</label>
                        <select class="form-select" id="edit_clef_user" name="user_id">
                            <option value="">Aucun utilisateur</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="clef_id" id="edit_clef_id">
                    <input type="hidden" name="action" value="update_clef">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" form="editClefForm" onclick="return validateEditClefForm()">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour supprimer une clé -->
<div class="modal fade" id="deleteClefModal" tabindex="-1" aria-labelledby="deleteClefModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteClefModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez confirmer la suppression de la clé :</p>
                <p><strong id="delete_clef_value"></strong></p>
                <form id="deleteClefForm" method="POST">
                    <input type="hidden" name="clef_id" id="delete_clef_id">
                    <input type="hidden" name="action" value="delete_clef">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="deleteClefForm">Supprimer</button>
            </div>
        </div>
    </div>
</div>


<script>
    function validateAddClefForm() {
        const form = document.getElementById('addClefForm');
        const value = document.getElementById('add_clef_value');
        let isValid = true;

        value.classList.remove('is-invalid');
        if (value.value.trim().length < 1 || value.value.trim().length > 32) {
            value.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    function validateEditClefForm() {
        const form = document.getElementById('editClefForm');
        const value = document.getElementById('edit_clef_value');
        let isValid = true;

        value.classList.remove('is-invalid');
        if (value.value.trim().length < 1 || value.value.trim().length > 32) {
            value.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    // Remplir le modal de modification
    document.addEventListener('click', function(event) {
        const button = event.target.closest('button[data-bs-target="#editClefModal"]');
        if (button) {
            const clefId = button.getAttribute('data-clef-id');
            const clefValue = button.getAttribute('data-clef-value');
            const userId = button.getAttribute('data-user-id');
            document.getElementById('edit_clef_id').value = clefId;
            document.getElementById('edit_clef_value').value = clefValue;
            document.getElementById('edit_clef_user').value = userId || '';
        }
    });

    // Remplir le modal de suppression
    document.addEventListener('click', function(event) {
        const button = event.target.closest('button[data-bs-target="#deleteClefModal"]');
        if (button) {
            const clefId = button.getAttribute('data-clef-id');
            const clefValue = button.getAttribute('data-clef-value');
            document.getElementById('delete_clef_id').value = clefId;
            document.getElementById('delete_clef_value').textContent = clefValue;
        }
    });
</script>

