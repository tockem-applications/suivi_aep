<?php
ob_start();
@require_once 'traitement/role_t.php';

// Récupérer l'AEP actuel
$roleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = Role::getRole($roleId);
if (!$role) {
    header("Location: manage_roles.php?error=role_not_found");
    exit;
}

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
if (!$aepId) {
    header("Location: manage_roles.php?error=no_aep");
    exit;
}

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_page_access') {
            $result = RoleDetailsProcessor::updatePageAccess($_POST, $roleId);
            $message = $result['success'] ?
                '<div class="alert alert-success">Accès aux pages mis à jour avec succès.</div>' :
                '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        } elseif ($_POST['action'] === 'add_user_role') {
            $result = RoleDetailsProcessor::addUserRole($_POST, $roleId);
            $message = $result['success'] ?
                '<div class="alert alert-success">Utilisateur ajouté au rôle avec succès.</div>' :
                '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        } elseif ($_POST['action'] === 'remove_page_access') {
            $result = RoleDetailsProcessor::removePageAccess($_POST, $roleId);
            $message = $result['success'] ?
                '<div class="alert alert-success">Accès à la page retiré avec succès.</div>' :
                '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        } elseif ($_POST['action'] === 'remove_user_role') {
            $result = RoleDetailsProcessor::removeUserRole($_POST, $roleId);
            $message = $result['success'] ?
                '<div class="alert alert-success">Utilisateur retiré du rôle avec succès.</div>' :
                '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        }
    }
}

// Récupérer les utilisateurs ayant ce rôle
$users = RoleDetailsProcessor::getUsersWithRole($roleId);

// Récupérer les pages accessibles par ce rôle pour l'AEP
$pages = RoleDetailsProcessor::getPagesWithAccess($roleId);

// Récupérer toutes les pages disponibles (y compris celles déjà associées)
$allPages = RoleDetailsProcessor::getAvailablePagesWithPage($roleId);

// Récupérer les utilisateurs sans ce rôle
$availableUsers = RoleDetailsProcessor::getAvailableUsers($roleId);
ob_get_clean();
//var_dump($allPages);
?>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Détails du rôle : <?php echo htmlspecialchars($role['nom']); ?></h2>
            <?php echo $message; ?>
            <a href="?page=role" class="btn btn-secondary mb-3">Retour à la gestion des rôles</a>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <h3>Utilisateurs avec ce rôle</h3>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['nom']); ?></td>
                            <td><?php echo htmlspecialchars($user['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirmRemoveUserModal" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>">Retirer</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Aucun utilisateur associé à ce rôle.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="col-md-6">
            <h3>Pages accessibles (AEP <?php echo htmlspecialchars($aepId); ?>)</h3>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Libellé</th>
                    <th>Access</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($pages) > 0): ?>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($page['id']); ?></td>
                            <td><?php echo htmlspecialchars($page['libelle']); ?></td>
                            <td><?php echo htmlspecialchars($page['write_access'] == 1 ? 'Lecture/Ecriture' : 'Lecture'); ?></td>
                            <td><?php echo htmlspecialchars($page['description'] ?: 'Aucune'); ?></td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirmRemovePageModal" data-page-id="<?php echo $page['id']; ?>" data-page-name="<?php echo htmlspecialchars($page['libelle']); ?>">Retirer</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Aucune page accessible pour ce rôle.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <!-- Bouton pour modifier les accès -->
            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modifyAccessModal">Modifier les accès</button>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-md-6">
            <h3>Ajouter un utilisateur à ce rôle</h3>
            <form method="POST" id="addUserForm" onsubmit="return validateAddUserForm()">
                <div class="mb-3">
                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($roleId); ?>">
                    <label for="user_id" class="form-label">Sélectionner un utilisateur</label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value="">-- Choisir un utilisateur --</option>
                        <?php foreach ($availableUsers as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom'] . ' (' . $user['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Veuillez sélectionner un utilisateur.</div>
                </div>
                <input type="hidden" name="action" value="add_user_role">
                <button type="submit" class="btn btn-primary">Ajouter au rôle</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour retirer un utilisateur -->
<div class="modal fade" id="confirmRemoveUserModal" tabindex="-1" aria-labelledby="confirmRemoveUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmRemoveUserModalLabel">Confirmer le retrait de l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez saisir le nom complet de l'utilisateur pour confirmer le retrait :</p>
                <form id="removeUserForm" method="POST">
                    <div class="mb-3">
                        <label for="confirm_user_name" class="form-label">Nom complet</label>
                        <input type="text" class="form-control" id="confirm_user_name" name="confirm_user_name" required>
                        <div class="invalid-feedback">Le nom complet est requis.</div>
                    </div>
                    <input type="hidden" name="user_id" id="remove_user_id">
                    <input type="hidden" name="action" value="remove_user_role">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="removeUserForm" onclick="return validateRemoveUserForm()">Retirer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour retirer une page -->
<div class="modal fade" id="confirmRemovePageModal" tabindex="-1" aria-labelledby="confirmRemovePageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmRemovePageModalLabel">Confirmer le retrait de la page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez saisir le libellé de la page pour confirmer le retrait :</p>
                <form id="removePageForm" method="POST">
                    <div class="mb-3">
                        <label for="confirm_page_name" class="form-label">Libellé de la page</label>
                        <input type="text" class="form-control" id="confirm_page_name" name="confirm_page_name" required>
                        <div class="invalid-feedback">Le libellé de la page est requis.</div>
                    </div>
                    <input type="hidden" name="page_id" id="remove_page_id">
                    <input type="hidden" name="action" value="remove_page_access">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="removePageForm" onclick="return validateRemovePageForm()">Retirer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier les accès -->
<div class="modal fade" id="modifyAccessModal" tabindex="-1" aria-labelledby="modifyAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modifyAccessModalLabel">Modifier les accès du rôle <?php echo $role['nom']?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="modifyAccessForm" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Sélectionnez les pages et leur niveau d'accès :</label>
                        <?php foreach ($allPages as $page): ?>
                            <?php
                            // Vérifier si la page est déjà associée au rôle
                            $currentAccess = $page['write_access'];
                            $isChecked = $currentAccess !== null;
                            ?>
                            <div class="row mb-2 align-items-center">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input page-checkbox" type="checkbox" name="pages[<?php echo $page['id']; ?>][selected]" value="1" id="page_<?php echo $page['id']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="page_<?php echo $page['id']; ?>">
                                            <?php echo htmlspecialchars($page['libelle'] . ' -> ' . $page['chaine']); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select access-level" name="pages[<?php echo $page['id']; ?>][write_access]" <?php echo $isChecked ? '' : 'disabled'; ?>>
                                        <option value="0" <?php echo $currentAccess === '0' ? 'selected' : ''; ?>>Lecture seule</option>
                                        <option value="1" <?php echo $currentAccess === '1' ? 'selected' : ''; ?>>Lecture et écriture</option>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="action" value="update_page_access">
                    <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($roleId); ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" form="modifyAccessForm">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script>
    function validateAddUserForm() {
        const form = document.getElementById('addUserForm');
        const userId = document.getElementById('user_id');
        let isValid = true;

        userId.classList.remove('is-invalid');
        if (!userId.value) {
            userId.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    function validateRemoveUserForm() {
        const confirmUserName = document.getElementById('confirm_user_name');
        let isValid = true;

        confirmUserName.classList.remove('is-invalid');
        if (confirmUserName.value.trim().length === 0) {
            confirmUserName.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    function validateRemovePageForm() {
        const confirmPageName = document.getElementById('confirm_page_name');
        let isValid = true;

        confirmPageName.classList.remove('is-invalid');
        if (confirmPageName.value.trim().length === 0) {
            confirmPageName.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    // Remplir le modal pour retirer un utilisateur
    document.addEventListener('click', function(event) {
        const button = event.target.closest('button[data-bs-target="#confirmRemoveUserModal"]');
        if (button) {
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            document.getElementById('remove_user_id').value = userId;
            document.getElementById('confirm_user_name').value = '';
            document.getElementById('confirm_user_name').placeholder = 'Saisir "' + userName + '"';
        }
    });

    // Remplir le modal pour retirer une page
    document.addEventListener('click', function(event) {
        const button = event.target.closest('button[data-bs-target="#confirmRemovePageModal"]');
        if (button) {
            const pageId = button.getAttribute('data-page-id');
            const pageName = button.getAttribute('data-page-name');
            document.getElementById('remove_page_id').value = pageId;
            document.getElementById('confirm_page_name').value = '';
            document.getElementById('confirm_page_name').placeholder = 'Saisir "' + pageName + '"';
        }
    });

    // Activer/désactiver le sélecteur de niveau d'accès en fonction de la case à cocher
    document.querySelectorAll('.page-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const select = this.closest('.row').querySelector('.access-level');
            select.disabled = !this.checked;
        });
    });
</script>