<?php
@include_once(__DIR__ . '/../traitement/licence_t.php');

$importMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_licence') {
    $result = LicenceT::handleImportUpload();
    if (!empty($result['success'])) {
        header('Location: ?page=a_propos');
        exit;
    }
    $importMessage = isset($result['message']) ? $result['message'] : 'Erreur import.';
}

$lic = app_licence();
?>
<div class="container-fluid py-3 px-2">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="?page=home">Accueil</a></li>
            <li class="breadcrumb-item active" aria-current="page">Licence</li>
        </ol>
    </nav>

    <h1 class="h4 mb-3">Importer ou renouveler la licence</h1>

    <?php if ($importMessage !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($importMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted small">Fichier <code>.lic</code> fourni par l'éditeur. Statut actuel :
                        <span class="badge <?php echo htmlspecialchars($lic->getStatutBadgeClass(), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($lic->getStatutLibelle(), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_licence">
                        <div class="mb-3">
                            <label for="licence_file" class="form-label">Fichier licence</label>
                            <input type="file" class="form-control" id="licence_file" name="licence_file" accept=".lic" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i> Importer
                        </button>
                        <a href="?page=a_propos" class="btn btn-outline-secondary ms-2">À propos</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
