<?php

$backupDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
}
$files = array();
foreach (glob($backupDir . DIRECTORY_SEPARATOR . '*.sql') as $f) {
    $files[] = array(
        'name' => basename($f),
        'size' => filesize($f),
        'mtime' => filemtime($f)
    );
}
usort($files, function ($a, $b) {
    return $b['mtime'] - $a['mtime'];
});

// Gestion des messages de notification
$successMessage = '';
$errorMessage = '';
$infoMessage = '';

// Vérifier si on vient d'une action (pour éviter l'affichage automatique)
$actionPerformed = false;

if (isset($_GET['success'])) {
    $actionPerformed = true;
    switch ($_GET['success']) {
        case 'backup_created':
            $successMessage = 'Backup créé avec succès !';
            if (isset($_GET['filename'])) {
                $successMessage .= ' Fichier : ' . htmlspecialchars($_GET['filename']);
            }
            break;
        case 'backup_created_php':
            $successMessage = 'Backup créé avec succès (méthode PHP) !';
            if (isset($_GET['filename'])) {
                $successMessage .= ' Fichier : ' . htmlspecialchars($_GET['filename']);
            }
            break;
        case 'renamed':
            $successMessage = 'Backup renommé avec succès !';
            if (isset($_GET['old_name']) && isset($_GET['new_name'])) {
                $successMessage .= ' De "' . htmlspecialchars($_GET['old_name']) . '" vers "' . htmlspecialchars($_GET['new_name']) . '"';
            }
            break;
        case 'deleted':
            $successMessage = 'Backup supprimé avec succès !';
            if (isset($_GET['filename'])) {
                $successMessage .= ' Fichier : ' . htmlspecialchars($_GET['filename']);
            }
            break;
        case 'applied':
            $successMessage = 'Fichier SQL appliqué avec succès !';
            if (isset($_GET['prebackup'])) {
                $infoMessage = 'Une sauvegarde préalable a été créée : ' . htmlspecialchars($_GET['prebackup']);
            }
            break;
        case 'bulk_renamed':
            $successMessage = 'Renommage en masse réussi !';
            if (isset($_GET['message'])) {
                $infoMessage = htmlspecialchars($_GET['message']);
            }
            break;
        case 'bulk_deleted':
            $successMessage = 'Suppression en masse réussie !';
            if (isset($_GET['message'])) {
                $infoMessage = htmlspecialchars($_GET['message']);
            }
            break;
    }
}

if (isset($_GET['error'])) {
    $actionPerformed = true;
    switch ($_GET['error']) {
        case 'backup_failed':
            $errorMessage = 'Échec de la création du backup';
            if (isset($_GET['message'])) {
                $errorMessage .= ' : ' . htmlspecialchars($_GET['message']);
            }
            break;
        case 'rename_failed':
            $errorMessage = 'Échec du renommage';
            if (isset($_GET['message'])) {
                $errorMessage .= ' : ' . htmlspecialchars($_GET['message']);
            }
            break;
        case 'delete_failed':
            $errorMessage = 'Échec de la suppression';
            if (isset($_GET['message'])) {
                $errorMessage .= ' : ' . htmlspecialchars($_GET['message']);
            }
            break;
        case 'apply_failed':
            $errorMessage = 'Échec de l\'application du fichier SQL';
            if (isset($_GET['message'])) {
                $errorMessage .= ' : ' . htmlspecialchars($_GET['message']);
            }
            break;
        case 'prebackup_failed':
            $errorMessage = 'Échec de la sauvegarde préalable';
            if (isset($_GET['message'])) {
                $errorMessage .= ' : ' . htmlspecialchars($_GET['message']);
            }
            break;
        case 'empty_file':
            $errorMessage = 'Le fichier SQL est vide ou corrompu';
            break;
        case 'bulk_rename_failed':
            $errorMessage = 'Échec du renommage en masse';
            if (isset($_GET['message'])) {
                $errorMessage .= ' : ' . htmlspecialchars($_GET['message']);
            }
            break;
        case 'bulk_delete_failed':
            $errorMessage = 'Échec de la suppression en masse';
            if (isset($_GET['message'])) {
                $errorMessage .= ' : ' . htmlspecialchars($_GET['message']);
            }
            break;
    }
}

// Nettoyer l'URL après affichage des messages pour éviter la persistance
if ($actionPerformed) {
    // Rediriger vers la page sans paramètres après un court délai
    echo '<script>
        setTimeout(function() {
            window.history.replaceState({}, document.title, window.location.pathname + "?page=backup");
        }, 100);
    </script>';
}

?>
<div class="container mt-3">
    <!-- Notifications -->
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $successMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($infoMessage): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i><?php echo $infoMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h2 class="h4 m-0">Export SQL de la base</h2>
            <form method="post" action="traitement/backup_t.php" class="m-0">
                <input type="hidden" name="action" value="export_sql">
                <div class="input-group">
                    <input type="text" name="backup_name" class="form-control"
                        placeholder="Nom de la backup (optionnel)" value="backup_<?php echo date('Y-m-d_H-i-s'); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-download me-1"></i>Générer un export
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span>Backups disponibles</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleBulkActions()">
                <i class="bi bi-gear"></i> Actions groupées
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="form-check-input">
                        </th>
                        <th>Fichier</th>
                        <th>Taille</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)) { ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Aucun export pour l'instant</td>
                        </tr>
                    <?php } else {
                        foreach ($files as $f) { ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_backups[]"
                                        value="<?php echo htmlspecialchars($f['name']); ?>"
                                        class="form-check-input backup-checkbox">
                                </td>
                                <td>
                                    <span class="backup-name" data-filename="<?php echo htmlspecialchars($f['name']); ?>">
                                        <?php echo htmlspecialchars($f['name']); ?>
                                    </span>
                                    <button class="btn btn-sm btn-link p-0 ms-2"
                                        onclick="editBackupName('<?php echo htmlspecialchars($f['name']); ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                                <td><?php echo number_format($f['size'] / 1024, 1, ',', ' '); ?> Ko</td>
                                <td><?php echo date('Y-m-d H:i:s', $f['mtime']); ?></td>
                                <td class="text-end">
                                    <div class="btn-group" role="group">
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="backups/<?php echo urlencode($f['name']); ?>" download>
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-warning"
                                            onclick="editBackupName('<?php echo htmlspecialchars($f['name']); ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="deleteBackup('<?php echo htmlspecialchars($f['name']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php }
                    } ?>
                </tbody>
            </table>
        </div>

        <!-- Actions groupées -->
        <div id="bulkActions" class="card-footer d-none">
            <div class="d-flex justify-content-between align-items-center">
                <span id="selectedCount">0 backup(s) sélectionné(s)</span>
                <div class="btn-group">
                    <button class="btn btn-warning" onclick="bulkRename()">
                        <i class="bi bi-pencil"></i> Renommer
                    </button>
                    <button class="btn btn-danger" onclick="bulkDelete()">
                        <i class="bi bi-trash"></i> Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header bg-light">Appliquer un fichier SQL</div>
        <div class="card-body">
            <p class="text-muted">Avant application, une sauvegarde complète de l'état actuel est créée automatiquement.
            </p>
            <form method="post" action="traitement/backup_t.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="apply_sql">
                <div class="input-group">
                    <input type="file" name="sql_file" accept=".sql" class="form-control" required>
                    <button class="btn btn-danger" type="submit"><i class="bi bi-upload me-1"></i>Appliquer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour renommer -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renommer la backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="renameForm" method="post" action="traitement/backup_t.php">
                    <input type="hidden" name="action" value="rename_backup">
                    <input type="hidden" name="old_name" id="oldName">
                    <div class="mb-3">
                        <label for="newName" class="form-label">Nouveau nom</label>
                        <input type="text" class="form-control" id="newName" name="new_name" required>
                        <div class="form-text">N'oubliez pas l'extension .sql</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="renameForm" class="btn btn-primary">Renommer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer la backup <strong id="deleteFileName"></strong> ?</p>
                <p class="text-danger">Cette action est irréversible !</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<script>
    let backupToDelete = null;

    function toggleBulkActions() {
        const bulkActions = document.getElementById('bulkActions');
        bulkActions.classList.toggle('d-none');
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.backup-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.backup-checkbox:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').textContent = count + ' backup(s) sélectionné(s)';
    }

    // Ajouter les événements pour les checkboxes
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.backup-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
    });

    function editBackupName(filename) {
        document.getElementById('oldName').value = filename;
        document.getElementById('newName').value = filename.replace('.sql', '');
        const modal = new bootstrap.Modal(document.getElementById('renameModal'));
        modal.show();
    }

    function deleteBackup(filename) {
        backupToDelete = filename;
        document.getElementById('deleteFileName').textContent = filename;
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    function confirmDelete() {
        if (backupToDelete) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'traitement/backup_t.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_backup';

            const filenameInput = document.createElement('input');
            filenameInput.type = 'hidden';
            filenameInput.name = 'filename';
            filenameInput.value = backupToDelete;

            form.appendChild(actionInput);
            form.appendChild(filenameInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function bulkRename() {
        const checkboxes = document.querySelectorAll('.backup-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Veuillez sélectionner au moins une backup');
            return;
        }

        const newName = prompt('Entrez le nouveau nom (sans extension) :');
        if (newName && newName.trim() !== '') {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'traitement/backup_t.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_rename';

            const newNameInput = document.createElement('input');
            newNameInput.type = 'hidden';
            newNameInput.name = 'new_name';
            newNameInput.value = newName.trim();

            checkboxes.forEach(checkbox => {
                const filenameInput = document.createElement('input');
                filenameInput.type = 'hidden';
                filenameInput.name = 'filenames[]';
                filenameInput.value = checkbox.value;
                form.appendChild(filenameInput);
            });

            form.appendChild(actionInput);
            form.appendChild(newNameInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function bulkDelete() {
        const checkboxes = document.querySelectorAll('.backup-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Veuillez sélectionner au moins une backup');
            return;
        }

        if (confirm(`Êtes-vous sûr de vouloir supprimer ${checkboxes.length} backup(s) ?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'traitement/backup_t.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_delete';

            checkboxes.forEach(checkbox => {
                const filenameInput = document.createElement('input');
                filenameInput.type = 'hidden';
                filenameInput.name = 'filenames[]';
                filenameInput.value = checkbox.value;
                form.appendChild(filenameInput);
            });

            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>