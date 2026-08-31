<?php
$importMessage = '';
if (isset($_SESSION['LICENCE_IMPORT_ERROR'])) {
    $importMessage = (string) $_SESSION['LICENCE_IMPORT_ERROR'];
    unset($_SESSION['LICENCE_IMPORT_ERROR']);
}

$lic = app_licence();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activation licence — Suivi réseau AEP</title>
    <link rel="stylesheet" href="presentation/assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="presentation/assets/bootstrap-icons-1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock text-primary" style="font-size: 2.5rem;"></i>
                <h1 class="h4 mt-2">Activation de la licence</h1>
                <p class="text-muted small mb-0">
                    Importez le fichier <code>.lic</code> fourni par l'éditeur pour utiliser l'application.
                </p>
            </div>

            <?php if ($importMessage !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($importMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if (!$lic->isAccesAutorise()): ?>
                <div class="alert alert-warning small">
                    <strong>Statut :</strong>
                    <?php echo htmlspecialchars($lic->getStatutLibelle(), ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($lic->getLastError() !== '' && $lic->getStatut() === 'signature_invalide'): ?>
                        <br><span class="text-muted"><?php echo htmlspecialchars($lic->getLastError(), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="index.php?page=licence" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_licence">
                        <?php echo Csrf::hiddenField(); ?>
                        <div class="mb-3">
                            <label for="licence_file" class="form-label">Fichier licence (.lic)</label>
                            <input type="file" class="form-control" id="licence_file" name="licence_file" accept=".lic" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i> Importer la licence
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-muted small mt-4 mb-0">
                Contact éditeur : Tockem — Suivi AEP
            </p>
        </div>
    </div>
</div>
</body>
</html>
