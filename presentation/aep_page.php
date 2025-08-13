<?php
// Inclure la classe Aep
@include_once("../donnees/aep.php");
@include_once("donnees/aep.php");

// Vérifier si l'utilisateur est connecté (à adapter selon votre logique d'authentification)
if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

// Supprimer un AEP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_aep'])) {
    $aep_id = intval($_POST['aep_id']);
    $aep = new Aep($aep_id, '', '', '', '', '', '');
    $constraint = $aep->getConstraint();
    $query = Manager::prepare_query(
        "DELETE FROM aep WHERE id = ?",
        array($constraint['value'])
    );
    if ($query) {
        $message = array('type' => 'success', 'text' => 'AEP supprimé avec succès.');
    } else {
        $message = array('type' => 'danger', 'text' => 'Erreur lors de la suppression de l\'AEP.');
    }
}

// Récupérer tous les AEP
$aeps = Manager::prepare_query("SELECT * FROM aep", array())->fetchAll();
?>


<div class="container my-5">
    <h2 class="mb-4">Administration des AEP</h2>

    <!-- Afficher les messages -->
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $message['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <a href="?form=aep" class="btn btn-primary mb-3" >Creer un Aep</a>

    <!-- Tableau des AEP -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th>Libellé</th>
                <th>Date</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($aeps

            as $aep): ?>
            <tr>
                <td><?php echo htmlspecialchars($aep['libele']); ?></td>
                <td><?php echo htmlspecialchars($aep['date']); ?></td>
                <td class="truncate"><?php echo htmlspecialchars($aep['description']); ?></td>
                <td>
                    <button class="btn btn-info btn-sm action-btn" data-bs-toggle="modal"
                            data-bs-target="#detailsModal" data-aep='<?php echo json_encode($aep); ?>'
                            onclick="showDetails(this)">Détails
                    </button>
                    <button class="btn btn-warning btn-sm action-btn" data-bs-toggle="modal"
                            data-bs-target="#edit_Modal_<?php echo $aep['id']; ?>"
                            data-aep='<?php echo json_encode($aep); ?>'
                            onclick="showDetails(this)">Modifier
                    </button>
                    <!--                        <a href="?page=edit_aep&id=-->
                    <?php //echo $aep['id']; ?><!--" class="btn btn-warning btn-sm action-btn">Modifier</a>-->
                    <form action="" method="post" style="display: inline;"
                          onsubmit="return confirm('Voulez-vous vraiment supprimer cet AEP ?');">
                        <input type="hidden" name="aep_id" value="<?php echo $aep['id']; ?>">
                        <button type="submit" name="delete_aep" class="btn btn-danger btn-sm action-btn">Supprimer
                        </button>
                    </form>
                </td>
            </tr>

            <!-- Modale pour les détails -->
            <div class="modal fade" id="edit_Modal_<?php echo $aep['id']; ?>" tabindex="-1"
                 aria-labelledby="detailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="detailsModalLabel">Modifier l'AEP</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form action="?page=edit_aep&id=<?php echo $aep['id']; ?>" method="post">
                                <div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="libele" class="form-label">Libellé <span
                                                        class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="libele" name="libele"
                                                   value="<?php echo htmlspecialchars($aep['libele']); ?>" required>
                                            <div class="error-message">Le libellé doit contenir au moins 3 caractères.
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="date" name="date"
                                                   value="<?php echo htmlspecialchars($aep['date']); ?>" required>
                                            <div class="error-message">Veuillez sélectionner une date valide.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description <span
                                                    class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="3"
                                                  placeholder="Décrivez votre AEP" required>Mon AEP</textarea>
                                        <div class="error-message">La description doit contenir au moins 10
                                            caractères.
                                        </div>
                                    </div>
                                </div>

                                <!-- Section : Détails bancaires -->
                                <div class="form-section">
                                    <h5>Détails bancaires (optionnel)</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nom_banque" class="form-label">Nom de la banque</label>
                                            <input type="text" class="form-control" id="nom_banque" name="nom_banque" value="<?php echo htmlspecialchars($aep['nom_banque']); ?>">
                                            <div class="error-message">Le nom de la banque ne peut pas dépasser 100
                                                caractères.
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="numero_compte" class="form-label">Numéro de compte</label>
                                            <input type="text" class="form-control" id="numero_compte"
                                                   name="numero_compte" value="<?php echo htmlspecialchars($aep['numero_compte']); ?>">
                                            <div class="error-message">Le numéro de compte doit être alphanumérique et
                                                ne pas dépasser 50 caractères.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Modèle de facture <span class="text-danger">*</span></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fichier_facture" id="fokoue"
                                               value="model_fokoue" <?php echo $aep['fichier_facture'] === 'model_fokoue' ? 'checked' : ''; ?>
                                               required>
                                        <label class="form-check-label" for="fokoue">Modèle de Fokoué</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fichier_facture" id="nkongzem"
                                               value="model_nkongzem" <?php echo $aep['fichier_facture'] === 'model_nkongzem' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="nkongzem">Modèle de Nkongzem</label>
                                    </div>
                                </div>
                        </div>

                        <div class="text-end pb-3 pe-5">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                            <!--                                        <a href="?page=edit_aep&id=-->
                            <?php //echo $aep['id']; ?><!--" class="btn btn-secondary">Annuler</a>-->
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                        </form>
                    </div>
                    <!--                            <div class="modal-footer">-->
                    <!--                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>-->
                    <!--                            </div>-->
                </div>
            </div>
    </div>
<?php endforeach; ?>
    <?php if (empty($aeps)): ?>
        <tr>
            <td colspan="4" class="text-center">Aucun AEP trouvé.</td>
        </tr>
    <?php endif; ?>
    </tbody>
    </table>
</div>
</div>

<!-- Modale pour les détails -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Détails de l'AEP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Libellé</h6>
                <p id="detail-libele"></p>
                <h6>Date</h6>
                <p id="detail-date"></p>
                <h6>Description</h6>
                <p id="detail-description"></p>
                <h6>Nom de la banque</h6>
                <p id="detail-nom_banque"></p>
                <h6>Numéro de compte</h6>
                <p id="detail-numero_compte"></p>
                <h6>Modèle de facture</h6>
                <p id="detail-fichier_facture"></p>
                <img id="detail-image" src="" alt="Modèle de facture" class="d-none">
                <h6>Statistiques</h6>
                <ul>
                    <li>Nombre de réseaux : <span id="detail-nb_reseaux">0</span></li>
                    <li>Nombre d'abonnés : <span id="detail-nb_abonnes">0</span></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

