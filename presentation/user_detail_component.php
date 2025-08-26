<?php
ob_start();
// Inclure la classe Manager
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'ID de l'utilisateur
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    header('Location: manage_users.php?error=invalid_user');
    exit;
}

// Récupérer les informations de l'utilisateur
$user = Manager::prepare_query(
    "SELECT id, nom, prenom, numero_telephone, email FROM users WHERE id = ?",
    array($userId)
)->fetch();
if (!$user) {
    header('Location: manage_users.php?error=user_not_found');
    exit;
}

// Récupérer les rôles de l'utilisateur
$userRoles = Manager::prepare_query(
    "SELECT r.id, r.nom
     FROM roles r
     INNER JOIN user_roles ur ON r.id = ur.role_id
     WHERE ur.user_id = ?",
    array($userId)
)->fetchAll();

// Récupérer tous les rôles disponibles pour l'ajout
$availableRoles = Manager::prepare_query(
    "SELECT id, nom
     FROM roles r
     WHERE r.id NOT IN (SELECT role_id FROM user_roles WHERE user_id = ?)",
    array($userId)
)->fetchAll();

// Récupérer les clés associées à l'utilisateur
$userClefs = Manager::prepare_query(
    "SELECT c.id, c.value
     FROM clefs c
     INNER JOIN user_clefs uc ON c.id = uc.clef_id
     WHERE uc.user_id = ?",
    array($userId)
)->fetchAll();

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_user') {
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $email = trim($_POST['email']);
            $numero_telephone = trim($_POST['numero_telephone']);
            if (empty($nom) || empty($prenom) || empty($email)|| empty($numero_telephone)) {
                $message = '<div class="alert alert-danger">Tous les champs sont requis.</div>';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = '<div class="alert alert-danger">Adresse email invalide.</div>';
            } else {
                try {
                    $query = Manager::prepare_query(
                        "UPDATE users SET nom = ?, prenom = ?, email = ?, numero_telephone=? WHERE id = ?",
                        array($nom, $prenom, $email, $numero_telephone,$userId)
                    );
                    $message = '<div class="alert alert-success">Utilisateur mis à jour avec succès.</div>';
                    // Rafraîchir les données de l'utilisateur
                    $user = Manager::prepare_query(
                        "SELECT id, nom, prenom, email, numero_telephone FROM users WHERE id = ?",
                        array($userId)
                    )->fetch();
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Erreur lors de la mise à jour : ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        } elseif ($_POST['action'] === 'delete_user') {
            try {
                // Supprimer les associations (rôles, clés, etc.)
                Manager::prepare_query("DELETE FROM user_roles WHERE user_id = ?", array($userId));
                Manager::prepare_query("DELETE FROM user_clefs WHERE user_id = ?", array($userId));
                // Supprimer l'utilisateur
                Manager::prepare_query("DELETE FROM users WHERE id = ?", array($userId));
                header('Location: manage_users.php?success=user_deleted');
                exit;
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">Erreur lors de la suppression : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } elseif ($_POST['action'] === 'add_role') {
            $roleId = (int)$_POST['role_id'];
            try {
                Manager::prepare_query(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    array($userId, $roleId)
                );
                $message = '<div class="alert alert-success">Rôle ajouté avec succès.</div>';
                // Rafraîchir les rôles
                $userRoles = Manager::prepare_query(
                    "SELECT r.id, r.nom
                     FROM roles r
                     INNER JOIN user_roles ur ON r.id = ur.role_id
                     WHERE ur.user_id = ?",
                    array($userId)
                )->fetchAll();
                $availableRoles = Manager::prepare_query(
                    "SELECT id, nom
                     FROM roles r
                     WHERE r.id NOT IN (SELECT role_id FROM user_roles WHERE user_id = ?)",
                    array($userId)
                )->fetchAll();
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">Erreur lors de l\'ajout du rôle : ' /* htmlspecialchars($e->getMessage())*/ . '</div>';
            }
        } elseif ($_POST['action'] === 'remove_role') {
            $roleId = (int)$_POST['role_id'];
            try {
                Manager::prepare_query(
                    "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?",
                    array($userId, $roleId)
                );
                $message = '<div class="alert alert-success">Rôle retiré avec succès.</div>';
                // Rafraîchir les rôles
                $userRoles = Manager::prepare_query(
                    "SELECT r.id, r.nom
                     FROM roles r
                     INNER JOIN user_roles ur ON r.id = ur.role_id
                     WHERE ur.user_id = ?",
                    array($userId)
                )->fetchAll();
                $availableRoles = Manager::prepare_query(
                    "SELECT id, nom
                     FROM roles r
                     WHERE r.id NOT IN (SELECT role_id FROM user_roles WHERE user_id = ?)",
                    array($userId)
                )->fetchAll();
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">Erreur lors du retrait du rôle : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}
$_POST = array();
ob_get_clean();
?>
<div class="container-fluid mt-5">
    <h2 class="mb-4">Détails de l'utilisateur : <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></h2>
    <?php echo $message; ?>
    <a href="?page=clefs" class="btn btn-secondary mb-3">Retour à la gestion des clefs</a>

    <!-- Informations de l'utilisateur -->
    <div class="row">
        <div class=" col-md-6">
            <div class="col-md-12">
                <div class="card p-4 mb-4">
                    <h3>Informations</h3>
                    <form id="updateUserForm" method="POST">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom"
                                   value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                            <div class="invalid-feedback">Veuillez entrer un nom.</div>
                        </div>
                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom"
                                   value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                            <div class="invalid-feedback">Veuillez entrer un prénom.</div>
                        </div>
                        <div class="mb-3">
                            <label for="numero_telephone" class="form-label">Numero de telephone</label>
                            <input type="text" class="form-control" id="numero_telephone" name="numero_telephone"
                                   value="<?php echo htmlspecialchars($user['numero_telephone']); ?>" required>
                            <div class="invalid-feedback">Veuillez entrer un numero.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <div class="invalid-feedback">Veuillez entrer un Email.</div>
                        </div>
                        <input type="hidden" name="action" value="update_user">
                        <button type="submit" class="btn btn-primary" onclick="return validateUpdateUserForm()">Mettre à
                            jour
                        </button>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                data-bs-target="#deleteUserModal">Supprimer l'utilisateur
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Rôles de l'utilisateur -->
        <div class="row col-md-6">
            <div class="col-md-12">
                <div class="card p-4 mb-4">
                    <h3>Rôles associés</h3>
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (count($userRoles) > 0): ?>
                            <?php foreach ($userRoles as $role): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($role['id']); ?></td>
                                    <td><?php echo htmlspecialchars($role['nom']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm action-btn"
                                                data-bs-toggle="modal" data-bs-target="#removeRoleModal"
                                                data-role-id="<?php echo $role['id']; ?>"
                                                data-role-name="<?php echo htmlspecialchars($role['nom']); ?>">Retirer
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Aucun rôle associé.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <!-- Formulaire pour ajouter un rôle -->
                    <h4>Ajouter un rôle</h4>
                    <form id="addRoleForm" method="POST">
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Sélectionner un rôle</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">-- Choisir un rôle --</option>
                                <?php foreach ($availableRoles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un rôle.</div>
                        </div>
                        <input type="hidden" name="action" value="add_role">
                        <button type="submit" class="btn btn-primary" onclick="return validateAddRoleForm()">Ajouter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Clés associées à l'utilisateur -->
    <div class="row">
        <div class="col-md-6">
            <div class="card p-4 mb-4">
                <h3>Clés associées</h3>
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Valeur</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($userClefs) > 0): ?>
                        <?php foreach ($userClefs as $clef): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($clef['id']); ?></td>
                                <td><?php echo htmlspecialchars($clef['value']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">Aucune clé associée.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <a href="?page=clefs" class="btn btn-primary">Gérer les clés</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour supprimer l'utilisateur -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Voulez-vous vraiment supprimer cet utilisateur ? Cette action est irréversible.</p>
                <p><strong><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></strong></p>
                <form id="deleteUserForm" method="POST">
                    <input type="hidden" name="action" value="delete_user">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="deleteUserForm">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour retirer un rôle -->
<div class="modal fade" id="removeRoleModal" tabindex="-1" aria-labelledby="removeRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeRoleModalLabel">Confirmer le retrait du rôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez confirmer le retrait du rôle :</p>
                <p><strong id="remove_role_name"></strong></p>
                <form id="removeRoleForm" method="POST">
                    <input type="hidden" name="role_id" id="remove_role_id">
                    <input type="hidden" name="action" value="remove_role">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="removeRoleForm">Retirer</button>
            </div>
        </div>
    </div>
</div>



<script>
    function validateUpdateUserForm() {
        const form = document.getElementById('updateUserForm');
        const nom = document.getElementById('nom');
        const prenom = document.getElementById('prenom');
        const email = document.getElementById('email');
        let isValid = true;

        nom.classList.remove('is-invalid');
        prenom.classList.remove('is-invalid');
        email.classList.remove('is-invalid');

        if (nom.value.trim().length === 0) {
            nom.classList.add('is-invalid');
            isValid = false;
        }
        if (prenom.value.trim().length === 0) {
            prenom.classList.add('is-invalid');
            isValid = false;
        }
        if (email.value.trim().length === 0 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            email.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    function validateAddRoleForm() {
        const form = document.getElementById('addRoleForm');
        const roleId = document.getElementById('role_id');
        let isValid = true;

        roleId.classList.remove('is-invalid');
        if (!roleId.value) {
            roleId.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    // Remplir le modal pour retirer un rôle
    document.addEventListener('click', function(event) {
        const button = event.target.closest('button[data-bs-target="#removeRoleModal"]');
        if (button) {
            const roleId = button.getAttribute('data-role-id');
            const roleName = button.getAttribute('data-role-name');
            document.getElementById('remove_role_id').value = roleId;
            document.getElementById('remove_role_name').textContent = roleName;
        }
    });
</script>
