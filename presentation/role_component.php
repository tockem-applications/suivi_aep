<?php
ob_start();
var_dump("jjjjjjjjjjjj");
require_once('traitement/role_t.php');
//require_once 'donnees/RoleProcessor.php';

// Vérifier si l'utilisateur est Administrateur
//$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
//if (!$userId || !Role::hasRole($userId, "Administrateur")) {
//    header("Location: login.php?error=access_denied");
//    exit;
//}

// Traitement des actions
// Vérifier si l'utilisateur est Administrateur
//$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
//if (!$userId || !RoleManager::hasRole($userId, "Administrateur")) {
//    header("Location: login.php?error=access_denied");
//    exit;
//}

// Traitement des actions
$editMode = false;
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_role') {
            $result = RoleProcessor::addRole($_POST);
            $message = $result['success'] ?
                '<div class="alert alert-success">Rôle ajouté avec succès.</div>' :
                '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        } elseif ($_POST['action'] === 'update_role') {
            $result = RoleProcessor::updateRole($_POST);
            $message = $result['success'] ?
                '<div class="alert alert-success">Rôle modifié avec succès.</div>' :
                '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        } elseif ($_POST['action'] === 'confirm_delete') {
            try {
                $result = RoleProcessor::confirmDeleteRole($_POST);
            } catch (Exception $e) {

            }
            $message = $result['success'] ?
                '<div class="alert alert-success">Rôle supprimé avec succès.</div>' :
                '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $roleToEdit = RoleProcessor::getRole((int)$_GET['id']);
    if ($roleToEdit) {
        $editMode = true;
    } else {
        $message = '<div class="alert alert-danger">Rôle non trouvé.</div>';
        $editMode = false;
    }
} else {
    $editMode = false;
    $roleToEdit = null;
}

// Récupérer tous les rôles
$roles = RoleProcessor::getAllRoles();
ob_get_clean();
?>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4"><?php echo $editMode ? 'Modifier un rôle' : 'Ajouter un rôle'; ?></h2>
            <?php echo $message; ?>
            <form method="POST" id="roleForm" onsubmit="return validateRoleForm()">
                <div class="mb-3">
                    <label for="role_name" class="form-label">Nom du rôle</label>
                    <input type="text" class="form-control" id="role_name" name="role_name"
                           value="<?php echo $editMode ? htmlspecialchars($roleToEdit['nom']) : ''; ?>" required>
                    <div class="invalid-feedback">Le nom du rôle est requis.</div>
                </div>
                <?php if ($editMode): ?>
                    <input type="hidden" name="role_id" value="<?php echo $roleToEdit['id']; ?>">
                    <input type="hidden" name="action" value="update_role">
                    <button type="submit" class="btn btn-primary">Modifier</button>
                    <a href="?page=role" class="btn btn-secondary">Annuler</a>
                <?php else: ?>
                    <input type="hidden" name="action" value="add_role">
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="row mt-5">
        <div class="col-md-12">
            <h3>Liste des rôles</h3>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($roles) > 0): ?>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($role['id']); ?>
                            </td>
                            <td>
                                <a href="?page=role_detail&role_name=<?php echo $role['nom'] ?>&id=<?php echo $role['id'] ?>"
                                   style="color: black"><?php echo htmlspecialchars($role['nom']); ?></a>
                            </td>
                            <td>
                                <a href="?page=role&action=edit&id=<?php echo $role['id']; ?>"
                                   class="btn btn-warning btn-sm">Modifier</a>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#confirmDeleteModal" data-role-id="<?php echo $role['id']; ?>"
                                        data-role-name="<?php echo htmlspecialchars($role['nom']); ?>">Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Aucun rôle trouvé.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez saisir le nom du rôle pour confirmer la suppression :</p>
                <form id="deleteRoleForm" method="POST">
                    <div class="mb-3">
                        <label for="confirm_role_name" id="nom_role" class="form-label">Nom du rôle</label>
                        <input type="text" class="form-control" id="confirm_role_name" name="confirm_role_name"
                               required>
                        <div class="invalid-feedback">Le nom du rôle est requis.</div>
                    </div>
                    <input type="hidden" name="role_id" id="delete_role_id">
                    <input type="hidden" name="action" value="confirm_delete">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="deleteRoleForm"
                        onclick="return validateDeleteForm()">Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function validateRoleForm() {
        const form = document.getElementById('roleForm');
        const roleName = document.getElementById('role_name');
        let isValid = true;

        roleName.classList.remove('is-invalid');
        if (roleName.value.trim().length === 0) {
            roleName.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    function validateDeleteForm() {
        const confirmRoleName = document.getElementById('confirm_role_name');
        let isValid = true;

        confirmRoleName.classList.remove('is-invalid');
        if (confirmRoleName.value.trim().length === 0) {
            confirmRoleName.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    // Remplir le modal avec les données du rôle
    document.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-bs-target="#confirmDeleteModal"]');
        if (button) {
            const roleId = button.getAttribute('data-role-id');
            const roleName = button.getAttribute('data-role-name');
            document.getElementById('delete_role_id').value = roleId;
            document.getElementById('nom_role').innerHTML += `: <strong>${roleName}</strong>`;
            document.getElementById('confirm_role_name').value = '';
            document.getElementById('confirm_role_name').placeholder = 'Saisir "' + roleName + '"';
        }
    });
</script>