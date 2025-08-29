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
    return $b['mtime'] != $a['mtime'];
});

?>
<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h2 class="h4 m-0">Export SQL de la base</h2>
            <form method="post" action="traitement/backup_t.php" class="m-0">
                <input type="hidden" name="action" value="export_sql">
                <button class="btn btn-primary"><i class="bi bi-download me-1"></i>Générer un export</button>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header bg-light">Backups disponibles</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fichier</th>
                        <th>Taille</th>
                        <th>Date</th>
                        <th class="text-end">Télécharger</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)) { ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Aucun export pour l'instant</td>
                        </tr>
                    <?php } else {
                        foreach ($files as $f) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($f['name']); ?></td>
                                <td><?php echo number_format($f['size'] / 1024, 1, ',', ' '); ?> Ko</td>
                                <td><?php echo date('Y-m-d H:i:s', $f['mtime']); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary"
                                        href="backups/<?php echo urlencode($f['name']); ?>" download>Télécharger</a>
                                </td>
                            </tr>
                        <?php }
                    } ?>
                </tbody>
            </table>
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