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
    try {
        // Préparer la sauvegarde avant suppression
        $resAep = Manager::prepare_query("SELECT * FROM aep WHERE id = ?", array($aep_id));
        $aepRow = $resAep ? $resAep->fetch() : array();
        $aepName = isset($aepRow['libele']) ? $aepRow['libele'] : ('AEP_' . $aep_id);
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $aepName);
        $timestamp = date('Ymd_His');
        $backupDir = dirname(__FILE__) . '/../donnees/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0777, true);
        }

        // Collecte des données liées à l'AEP
        $backup = array();
        $backup['meta'] = array('generated_at' => date('c'), 'aep_id' => $aep_id, 'aep_libele' => $aepName);
        $backup['aep'] = $aepRow ? $aepRow : array();

        $q = Manager::prepare_query("SELECT * FROM constante_reseau WHERE id_aep = ?", array($aep_id));
        $backup['constante_reseau'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query("SELECT r.* FROM reseau r WHERE r.id_aep = ?", array($aep_id));
        $reseaux = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();
        $backup['reseau'] = $reseaux;

        $q = Manager::prepare_query(
            "SELECT a.* FROM abone a INNER JOIN reseau r ON r.id = a.id_reseau WHERE r.id_aep = ?",
            array($aep_id)
        );
        $backup['abone'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query(
            "SELECT mf.* FROM mois_facturation mf INNER JOIN constante_reseau c ON c.id = mf.id_constante WHERE c.id_aep = ?",
            array($aep_id)
        );
        $mois = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();
        $backup['mois_facturation'] = $mois;

        $q = Manager::prepare_query(
            "SELECT i.* FROM indexes i WHERE i.id_mois_facturation IN (
                SELECT mf.id FROM mois_facturation mf INNER JOIN constante_reseau c ON c.id = mf.id_constante WHERE c.id_aep = ?
            )",
            array($aep_id)
        );
        $backup['indexes'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query(
            "SELECT f.* FROM facture f WHERE f.id_indexes IN (
                SELECT i.id FROM indexes i WHERE i.id_mois_facturation IN (
                    SELECT mf.id FROM mois_facturation mf INNER JOIN constante_reseau c ON c.id = mf.id_constante WHERE c.id_aep = ?
                )
            )",
            array($aep_id)
        );
        $backup['facture'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query(
            "SELECT v.* FROM versements v WHERE v.id_redevance IN (SELECT id FROM redevance WHERE id_aep = ?) 
             OR v.id_mois_facturation IN (
                SELECT mf.id FROM mois_facturation mf INNER JOIN constante_reseau c ON c.id = mf.id_constante WHERE c.id_aep = ?
             )",
            array($aep_id, $aep_id)
        );
        $backup['versements'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query("SELECT * FROM compteur_aep WHERE id_aep = ?", array($aep_id));
        $backup['compteur_aep'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query(
            "SELECT cr.* FROM compteur_reseau cr INNER JOIN reseau r ON r.id = cr.id_reseau WHERE r.id_aep = ?",
            array($aep_id)
        );
        $backup['compteur_reseau'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query("SELECT * FROM redevance WHERE id_aep = ?", array($aep_id));
        $backup['redevance'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        $q = Manager::prepare_query("SELECT * FROM flux_financier WHERE id_aep = ?", array($aep_id));
        $backup['flux_financier'] = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

        // Écriture du fichier JSON
        $backupFile = $backupDir . '/backup_avant_supression_' . $safeName . '_' . $timestamp . '.json';
        $json = json_encode($backup);
        if ($json === false || file_put_contents($backupFile, $json) === false) {
            throw new Exception("Sauvegarde avant suppression échouée");
        }

        $bd = Connexion::connect();
        $bd->beginTransaction();

        // 1) Versements liés via redevance de l'AEP ou mois de l'AEP
        Manager::prepare_query(
            "DELETE FROM versements WHERE id_redevance IN (SELECT id FROM redevance WHERE id_aep = ?)",
            array($aep_id)
        );
        Manager::prepare_query(
            "DELETE FROM versements WHERE id_mois_facturation IN (
                SELECT mf.id FROM mois_facturation mf
                INNER JOIN constante_reseau c ON c.id = mf.id_constante
                WHERE c.id_aep = ?
            )",
            array($aep_id)
        );

        // 2) Mois de facturation -> indexes -> factures (via ON DELETE CASCADE sur indexes.id)
        Manager::prepare_query(
            "DELETE FROM mois_facturation WHERE id_constante IN (
                SELECT id FROM constante_reseau WHERE id_aep = ?
            )",
            array($aep_id)
        );

        // 3) Constantes (tarifs)
        Manager::prepare_query(
            "DELETE FROM constante_reseau WHERE id_aep = ?",
            array($aep_id)
        );

        // 4) Compteurs liés aux réseaux de l'AEP
        Manager::prepare_query(
            "DELETE FROM compteur_reseau WHERE id_reseau IN (SELECT id FROM reseau WHERE id_aep = ?)",
            array($aep_id)
        );

        // 4b) Liaisons compteurs-abonnés pour les abonnés des réseaux de l'AEP
        Manager::prepare_query(
            "DELETE FROM compteur_abone WHERE id_abone IN (
                SELECT a.id FROM abone a INNER JOIN reseau r ON r.id = a.id_reseau WHERE r.id_aep = ?
            )",
            array($aep_id)
        );

        // 5) Abonnés des réseaux de l'AEP (factures suppr. via ON DELETE CASCADE sur facture.id_abone)
        Manager::prepare_query(
            "DELETE FROM abone WHERE id_reseau IN (SELECT id FROM reseau WHERE id_aep = ?)",
            array($aep_id)
        );

        // 6) Réseaux de l'AEP
        Manager::prepare_query(
            "DELETE FROM reseau WHERE id_aep = ?",
            array($aep_id)
        );

        // 7) Compteurs AEP
        Manager::prepare_query(
            "DELETE FROM compteur_aep WHERE id_aep = ?",
            array($aep_id)
        );

        // 8) Redevances de l'AEP
        Manager::prepare_query(
            "DELETE FROM redevance WHERE id_aep = ?",
            array($aep_id)
        );

        // 9) Flux financiers de l'AEP
        Manager::prepare_query(
            "DELETE FROM flux_financier WHERE id_aep = ?",
            array($aep_id)
        );

        // 10) Enfin supprimer l'AEP
        $ok = Manager::prepare_query("DELETE FROM aep WHERE id = ?", array($aep_id));
        if (!$ok) {
            throw new Exception('Suppression AEP échouée');
        }

        $bd->commit();
        $message = array('type' => 'success', 'text' => 'AEP supprimé avec succès.');
    } catch (Exception $e) {
        if (isset($bd)) {
            try {
                $bd->rollBack();
            } catch (Exception $e2) {
            }
        }
        $msg = 'Erreur de suppression: ' . $e->getMessage();
        $message = array('type' => 'danger', 'text' => $msg);
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
    <a href="?form=aep" class="btn btn-primary mb-3">Creer un Aep</a>

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
                                data-aep='<?php echo json_encode($aep); ?>' onclick="showDetails(this)">Modifier
                            </button>
                            <!--                        <a href="?page=edit_aep&id=-->
                            <?php //echo $aep['id']; ?><!--" class="btn btn-warning btn-sm action-btn">Modifier</a>-->
                            <button class="btn btn-danger btn-sm action-btn" data-bs-toggle="modal"
                                data-bs-target="#deleteAepModal_<?php echo $aep['id']; ?>">
                                Supprimer
                            </button>
                        </td>
                    </tr>

                    <!-- Modale pour les détails -->
                    <div class="modal fade" id="edit_Modal_<?php echo $aep['id']; ?>" tabindex="-1"
                        aria-labelledby="detailsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="detailsModalLabel">Modifier l'AEP</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
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
                                                    <div class="error-message">Le libellé doit contenir au moins 3
                                                        caractères.
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="date" class="form-label">Date <span
                                                            class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="date" name="date"
                                                        value="<?php echo htmlspecialchars($aep['date']); ?>" required>
                                                    <div class="error-message">Veuillez sélectionner une date valide.</div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Description <span
                                                        class="text-danger">*</span></label>
                                                <textarea class="form-control" id="description" name="description" rows="3"
                                                    placeholder="Décrivez votre AEP"
                                                    required><?php echo htmlspecialchars($aep['description']); ?></textarea>
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
                                                    <input type="text" class="form-control" id="nom_banque"
                                                        name="nom_banque"
                                                        value="<?php echo htmlspecialchars($aep['nom_banque']); ?>">
                                                    <div class="error-message">Le nom de la banque ne peut pas dépasser 100
                                                        caractères.
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="numero_compte" class="form-label">Numéro de compte</label>
                                                    <input type="text" class="form-control" id="numero_compte"
                                                        name="numero_compte"
                                                        value="<?php echo htmlspecialchars($aep['numero_compte']); ?>">
                                                    <div class="error-message">Le numéro de compte doit être alphanumérique
                                                        et
                                                        ne pas dépasser 50 caractères.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Modèle de facture <span
                                                    class="text-danger">*</span></label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="fichier_facture"
                                                    id="fokoue" value="model_fokoue" <?php echo $aep['fichier_facture'] === 'model_fokoue' ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="fokoue">Modèle de Fokoué</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="fichier_facture"
                                                    id="nkongzem" value="model_nkongzem" <?php echo $aep['fichier_facture'] === 'model_nkongzem' ? 'checked' : ''; ?>>
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

                    <!-- Modal de suppression avancée -->
                    <div class="modal fade" id="deleteAepModal_<?php echo $aep['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content" data-expected-name="<?php echo htmlspecialchars($aep['libele']); ?>">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">Supprimer l'AEP «
                                        <?php echo htmlspecialchars($aep['libele']); ?> »
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-warning">
                                        <strong>Impact de la suppression :</strong>
                                        <ul class="mb-0">
                                            <li>Tous les réseaux, abonnés et compteurs rattachés seront supprimés</li>
                                            <li>Tous les mois de facturation et leurs indexes seront supprimés</li>
                                            <li>Toutes les factures et versements associés seront supprimés</li>
                                            <li>Les tarifs/constantes de réseau seront supprimés</li>
                                        </ul>
                                    </div>
                                    <p class="mb-2">Pour confirmer, saisissez <strong>exactement</strong> le nom de l’AEP
                                        deux fois :</p>
                                    <div class="mb-3">
                                        <label class="form-label">Nom de l’AEP (1)</label>
                                        <input type="text" class="form-control" data-role="confirm-name-1"
                                            placeholder="<?php echo htmlspecialchars($aep['libele']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nom de l’AEP (2)</label>
                                        <input type="text" class="form-control" data-role="confirm-name-2"
                                            placeholder="<?php echo htmlspecialchars($aep['libele']); ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <form action="" method="post" class="m-0 p-0">
                                        <input type="hidden" name="aep_id" value="<?php echo $aep['id']; ?>">
                                        <button type="submit" name="delete_aep" class="btn btn-danger" disabled
                                            data-role="delete-submit">
                                            Supprimer définitivement
                                        </button>
                                    </form>
                                </div>
                            </div>
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

<script>
    // Activation du bouton de suppression quand les deux saisies correspondent exactement au nom attendu
    (function () {
        document.addEventListener('input', function (e) {
            var target = e.target;
            if (!target || (target.getAttribute('data-role') !== 'confirm-name-1' && target.getAttribute('data-role') !== 'confirm-name-2')) return;
            var modalContent = target.closest('.modal-content');
            if (!modalContent) return;
            var expected = modalContent.getAttribute('data-expected-name') || '';
            var i1 = modalContent.querySelector('[data-role="confirm-name-1"]');
            var i2 = modalContent.querySelector('[data-role="confirm-name-2"]');
            var btn = modalContent.querySelector('[data-role="delete-submit"]');
            var v1 = i1 ? i1.value : '';
            var v2 = i2 ? i2.value : '';
            var ok = (v1 === expected && v2 === expected);
            if (btn) btn.disabled = !ok;
        });
    })();
</script>