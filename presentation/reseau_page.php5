<?php
//session_start();
require_once 'traitement/reseau_t.php';
require_once 'header.php'; // Inclure votre header personnalisé

if (!isset($_SESSION['id_aep'])) {
    header("Location: index.php?form=login");
    exit();
}

$reseaux = getAllReseaux();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        addReseau($_POST['nom'], $_POST['abreviation'], $_POST['date_creation'], $_POST['description_reseau']);
        header("Location: reseau.php");
        exit();
    } elseif ($_POST['action'] === 'update') {
        updateReseau($_POST['id'], $_POST['nom'], $_POST['abreviation'], $_POST['date_creation'], $_POST['description_reseau']);
        header("Location: reseau.php");
        exit();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        deleteReseau($_POST['id']);
        header("Location: reseau.php");
        exit();
    }
}
?>

    <div class="container mt-5">
        <h2>Liste des Réseaux</h2>
        <a href="?form=reseau" class="btn btn-primary mb-3">Ajouter un Réseau</a>

        <table class="table table-striped">
            <thead>
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Abréviation</th>
                <th>Date de Création</th>
                <th>Description</th>
                <th>AEP</th>
                <th>Total Abonnés</th>
                <th>Abonnés Actifs</th>
                <th>Abonnés Inactifs</th>
                <th>Personnes Facturées</th>
                <th>Taux de Recouvrement (%)</th>
                <th>Nombre de Compteurs</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reseaux as $reseau): ?>
                <tr>
                    <td><?php echo htmlspecialchars($reseau->getId()); ?></td>
                    <td><?php echo htmlspecialchars($reseau->getNom()); ?></td>
                    <td><?php echo htmlspecialchars($reseau->getAbreviation()); ?></td>
                    <td><?php echo htmlspecialchars($reseau->getDateCreation()); ?></td>
                    <td><?php echo htmlspecialchars($reseau->getDescriptionReseau()); ?></td>
                    <td><?php echo htmlspecialchars($reseau->getIdAep()); ?></td>
                    <td><?php echo getTotalAbonnesByReseau($reseau->getId()); ?></td>
                    <td><?php echo getActiveAbonnesByReseau($reseau->getId()); ?></td>
                    <td><?php echo getInactiveAbonnesByReseau($reseau->getId()); ?></td>
                    <td><?php echo getFactureCountByReseau($reseau->getId()); ?></td>
                    <td><?php echo number_format(getRecouvrementRateByReseau($reseau->getId()), 2); ?></td>
                    <td><?php echo getCompteurCountByReseau($reseau->getId()); ?></td>
                    <td>
                        <a href="?page=edit_reseau&id=<?php echo $reseau->getId(); ?>" class="btn btn-warning btn-sm">Modifier</a>
                        <form action="" method="post" style="display:inline;" onsubmit="return confirm('Voulez-vous vraiment supprimer ce réseau ?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $reseau->getId(); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                        </form>
                        <a href="?list=abone&id_reseau=<?php echo $reseau->getId(); ?>" class="btn btn-info btn-sm">Abonnés</a>
                        <a href="?list=compteur_reseau&id_reseau=<?php echo $reseau->getId(); ?>" class="btn btn-secondary btn-sm">Compteurs</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php if (isset($_GET['form']) && $_GET['form'] === 'reseau'): ?>
    <div class="container mt-5">
        <h2><?php echo isset($_GET['id']) ? 'Modifier Réseau' : 'Ajouter Réseau'; ?></h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="<?php echo isset($_GET['id']) ? 'update' : 'add'; ?>">
            <?php if (isset($_GET['id'])): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($_GET['id']); ?>">
                <?php
                $reseau = getReseauById($_GET['id']);
                if ($reseau) {
                    $nom = $reseau->getNom();
                    $abreviation = $reseau->getAbreviation();
                    $date_creation = $reseau->getDateCreation();
                    $description_reseau = $reseau->getDescriptionReseau();
                }
                ?>
        <?php else: ?>
            <div class="mb-3">
                <label for="nom" class="form-label">Nom</label>
                <input type="text" class="form-control" id="nom" name="nom" value="<?php echo isset($nom) ? htmlspecialchars($nom) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="abreviation" class="form-label">Abréviation</label>
                <input type="text" class="form-control" id="abreviation" name="abreviation" value="<?php echo isset($abreviation) ? htmlspecialchars($abreviation) : ''; ?>">
            </div>
            <div class="mb-3">
                <label for="date_creation" class="form-label">Date de Création</label>
                <input type="date" class="form-control" id="date_creation" name="date_creation" value="<?php echo isset($date_creation) ? htmlspecialchars($date_creation) : date('Y-m-d'); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description_reseau" class="form-label">Description</label>
                <textarea class="form-control" id="description_reseau" name="description_reseau"><?php echo isset($description_reseau) ? htmlspecialchars($description_reseau) : ''; ?></textarea>
            </div>
            <button type="submit" class="btn btn-success">Enregistrer</button>
            <a href="reseau.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
<?php endif; ?>
