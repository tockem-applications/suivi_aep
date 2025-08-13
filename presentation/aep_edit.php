<?php

// Inclure la classe Aep
@include_once("../donnees/aep.php");
@include_once("donnees/aep.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'AEP à modifier
$aep_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aep_data = Manager::prepare_query("SELECT * FROM aep WHERE id = ?", array($aep_id))->fetch();
if (!$aep_data) {
    header('Location: admin_aep.php');
    exit;
}

// Mettre à jour l'AEP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $libele = trim($_POST['libele']);
    $date = trim($_POST['date']);
    $description = trim($_POST['description']);
    $nom_banque = trim($_POST['nom_banque']);
    $numero_compte = trim($_POST['numero_compte']);
    $fichier_facture = trim($_POST['fichier_facture']);

    // Validation
    $errors = array();
    if (strlen($libele) < 3) {
        $errors[] = 'Le libellé doit contenir au moins 3 caractères.';
    }
    if (empty($date)) {
        $errors[] = 'La date est requise.';
    }
    if (strlen($description) < 10) {
        $errors[] = 'La description doit contenir au moins 10 caractères.';
    }
    if (empty($fichier_facture)) {
        $errors[] = 'Veuillez sélectionner un modèle de facture.';
    }

    if (empty($errors)) {
        $aep = new Aep($aep_id, $libele, $fichier_facture, $date, $description, $nom_banque, $numero_compte);
        $data = $aep->getDonnee();
        $query = Manager::prepare_query(
            "UPDATE aep SET libele = ?, fichier_facture = ?, date = ?, description = ?, nom_banque = ?, numero_compte = ? WHERE id = ?",
            array($data['libele'], $data['fichier_facture'], $data['date'], $data['description'], $data['nom_banque'], $data['numero_compte'], $aep_id)
        );
        if ($query) {
            header('Location: ?page=aep');
            exit;
        } else {
            $errors[] = 'Erreur lors de la mise à jour de l\'AEP.';
        }
    }
}
?>

<div class="container my-5">
        <div class="card p-4">
            <h2 class="card-title text-center mb-4">Modifier l'AEP</h2>

            <!-- Afficher les erreurs -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <div class="mb-3">
                    <label for="libele" class="form-label">Libellé <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="libele" name="libele" value="<?php echo htmlspecialchars($aep_data['libele']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($aep_data['date']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($aep_data['description']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="nom_banque" class="form-label">Nom de la banque</label>
                    <input type="text" class="form-control" id="nom_banque" name="nom_banque" value="<?php echo htmlspecialchars($aep_data['nom_banque']); ?>">
                </div>
                <div class="mb-3">
                    <label for="numero_compte" class="form-label">Numéro de compte</label>
                    <input type="text" class="form-control" id="numero_compte" name="numero_compte" value="<?php echo htmlspecialchars($aep_data['numero_compte']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Modèle de facture <span class="text-danger">*</span></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="fichier_facture" id="fokoue" value="model_fokoue" <?php echo $aep_data['fichier_facture'] === 'model_fokoue' ? 'checked' : ''; ?> required>
                        <label class="form-check-label" for="fokoue">Modèle de Fokoué</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="fichier_facture" id="nkongzem" value="model_nkongzem" <?php echo $aep_data['fichier_facture'] === 'model_nkongzem' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="nkongzem">Modèle de Nkongzem</label>
                    </div>
                </div>
                <div class="text-end">
                    <a href="admin_aep.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>