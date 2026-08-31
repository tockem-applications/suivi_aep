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
        <table class="table_searching table table-striped table-hover">
            <thead>
                <tr>
                    <th>Libellé</th>
                    <th>Type</th>
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
                        <td>
                            <?php
                            $td = isset($aep['type_distribution']) ? Aep::normaliserTypeDistribution($aep['type_distribution']) : null;
                            if ($td === 'RDS') {
                                echo '<span class="badge bg-primary" title="Refoulement Distribution Séparé">RDS</span>';
                            } elseif ($td === 'RDC') {
                                echo '<span class="badge bg-info text-dark" title="Refoulement Distribution Confondu">RDC</span>';
                            } else {
                                echo '<span class="badge bg-light text-muted border" title="Type non défini">—</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($aep['date']); ?></td>
                        <td class="truncate"><?php echo htmlspecialchars($aep['description']); ?></td>
                        <td class="d-flex gap-2">
<!--                            <button class="btn btn-info btn-sm action-btn" data-bs-toggle="modal"-->
<!--                                data-bs-target="#detailsModal" data-aep='--><?php //echo json_encode($aep); ?><!--'-->
<!--                                onclick="showDetails(this)">Détails-->
<!--                            </button>-->
                            <a class="btn btn-outline-secondary btn-sm action-btn"
                                href="?page=aep_detail&aep_id=<?php echo $aep['id']; ?>">Page Détails</a>
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

                    <?php
                    $aid = (int) $aep['id'];
                    $type_dist_actuel = isset($aep['type_distribution']) ? Aep::normaliserTypeDistribution($aep['type_distribution']) : null;
                    $modele_actuel = isset($aep['fichier_facture']) ? $aep['fichier_facture'] : '';
                    ?>
                    <div class="modal fade aep-edit-modal" id="edit_Modal_<?php echo $aid; ?>" tabindex="-1"
                        aria-labelledby="editModalLabel_<?php echo $aid; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content border-0 shadow-lg">
                                <form id="aep-edit-form-<?php echo $aid; ?>" class="aep-edit-form"
                                    action="?page=edit_aep&id=<?php echo $aid; ?>" method="post" novalidate>
                                    <div class="modal-header aep-edit-header text-white border-0">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="aep-edit-icon rounded-circle d-inline-flex align-items-center justify-content-center">
                                                <i class="bi bi-droplet-half fs-4"></i>
                                            </div>
                                            <div>
                                                <h5 class="modal-title mb-0 fw-semibold" id="editModalLabel_<?php echo $aid; ?>">
                                                    Modifier l'AEP
                                                </h5>
                                                <div class="small opacity-75">
                                                    <i class="bi bi-pencil-square me-1"></i>
                                                    <?php echo htmlspecialchars($aep['libele']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                            aria-label="Fermer"></button>
                                    </div>

                                    <div class="modal-body bg-body-tertiary p-4">
                                        <!-- Identité -->
                                        <section class="aep-edit-section card border-0 shadow-sm mb-3">
                                            <div class="card-body">
                                                <h6 class="text-uppercase text-muted small fw-bold mb-3">
                                                    <i class="bi bi-info-circle me-1"></i> Identité
                                                </h6>
                                                <div class="row g-3">
                                                    <div class="col-md-7">
                                                        <label for="libele_<?php echo $aid; ?>" class="form-label">
                                                            Libellé <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="text" class="form-control" id="libele_<?php echo $aid; ?>"
                                                            name="libele" value="<?php echo htmlspecialchars($aep['libele']); ?>"
                                                            minlength="3" required>
                                                        <div class="invalid-feedback">Le libellé doit contenir au moins 3 caractères.</div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <label for="date_<?php echo $aid; ?>" class="form-label">
                                                            Date <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="date" class="form-control" id="date_<?php echo $aid; ?>"
                                                            name="date"
                                                            value="<?php echo htmlspecialchars($aep['date']); ?>" required>
                                                        <div class="invalid-feedback">Veuillez sélectionner une date valide.</div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label for="description_<?php echo $aid; ?>" class="form-label">
                                                            Description
                                                        </label>
                                                        <textarea class="form-control" id="description_<?php echo $aid; ?>"
                                                            name="description" rows="2"
                                                            placeholder="Décrivez votre AEP"><?php echo htmlspecialchars($aep['description']); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <!-- Type de réseau -->
                                        <section class="aep-edit-section card border-0 shadow-sm mb-3">
                                            <div class="card-body">
                                                <h6 class="text-uppercase text-muted small fw-bold mb-1">
                                                    <i class="bi bi-diagram-3 me-1"></i> Type de réseau
                                                </h6>
                                                <p class="small text-muted mb-3">
                                                    Conditionne le calcul du <strong>rendement</strong> du réseau (production / distribution).
                                                </p>
                                                <div class="row g-2 aep-type-options">
                                                    <div class="col-md-4">
                                                        <input class="form-check-input visually-hidden aep-type-input"
                                                            type="radio" name="type_distribution"
                                                            id="td_rds_<?php echo $aid; ?>" value="RDS"
                                                            <?php echo $type_dist_actuel === 'RDS' ? 'checked' : ''; ?>>
                                                        <label class="aep-type-card border rounded-3 p-3 d-block h-100"
                                                            for="td_rds_<?php echo $aid; ?>">
                                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                                <i class="bi bi-arrow-right-square text-primary fs-5"></i>
                                                                <span class="fw-bold">RDS</span>
                                                                <i class="bi bi-check-circle-fill aep-type-check ms-auto text-success"></i>
                                                            </div>
                                                            <div class="small text-muted">Refoulement Distribution <strong>Séparé</strong></div>
                                                        </label>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input class="form-check-input visually-hidden aep-type-input"
                                                            type="radio" name="type_distribution"
                                                            id="td_rdc_<?php echo $aid; ?>" value="RDC"
                                                            <?php echo $type_dist_actuel === 'RDC' ? 'checked' : ''; ?>>
                                                        <label class="aep-type-card border rounded-3 p-3 d-block h-100"
                                                            for="td_rdc_<?php echo $aid; ?>">
                                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                                <i class="bi bi-share text-info fs-5"></i>
                                                                <span class="fw-bold">RDC</span>
                                                                <i class="bi bi-check-circle-fill aep-type-check ms-auto text-success"></i>
                                                            </div>
                                                            <div class="small text-muted">Refoulement Distribution <strong>Confondu</strong></div>
                                                        </label>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input class="form-check-input visually-hidden aep-type-input"
                                                            type="radio" name="type_distribution"
                                                            id="td_none_<?php echo $aid; ?>" value=""
                                                            <?php echo $type_dist_actuel === null ? 'checked' : ''; ?>>
                                                        <label class="aep-type-card border rounded-3 p-3 d-block h-100"
                                                            for="td_none_<?php echo $aid; ?>">
                                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                                <i class="bi bi-dash-circle text-muted fs-5"></i>
                                                                <span class="fw-bold text-muted">Non défini</span>
                                                                <i class="bi bi-check-circle-fill aep-type-check ms-auto text-success"></i>
                                                            </div>
                                                            <div class="small text-muted">À renseigner ultérieurement</div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <!-- Détails bancaires -->
                                        <section class="aep-edit-section card border-0 shadow-sm mb-3">
                                            <div class="card-body">
                                                <h6 class="text-uppercase text-muted small fw-bold mb-3">
                                                    <i class="bi bi-bank me-1"></i> Détails bancaires
                                                    <span class="text-muted fw-normal text-lowercase">— optionnel</span>
                                                </h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label for="nom_banque_<?php echo $aid; ?>" class="form-label">Nom de la banque</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-body-tertiary"><i class="bi bi-building"></i></span>
                                                            <input type="text" class="form-control"
                                                                id="nom_banque_<?php echo $aid; ?>" name="nom_banque"
                                                                maxlength="100"
                                                                value="<?php echo htmlspecialchars($aep['nom_banque']); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="numero_compte_<?php echo $aid; ?>" class="form-label">Numéro de compte</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-body-tertiary"><i class="bi bi-credit-card-2-front"></i></span>
                                                            <input type="text" class="form-control"
                                                                id="numero_compte_<?php echo $aid; ?>" name="numero_compte"
                                                                pattern="[A-Za-z0-9\-]{0,50}"
                                                                value="<?php echo htmlspecialchars($aep['numero_compte']); ?>">
                                                        </div>
                                                        <div class="invalid-feedback">Alphanumérique, 50 caractères max.</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <!-- Modèle de facture -->
                                        <section class="aep-edit-section card border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="text-uppercase text-muted small fw-bold mb-3">
                                                    <i class="bi bi-receipt me-1"></i> Modèle de facture
                                                    <span class="text-danger">*</span>
                                                </h6>
                                                <div class="row g-3 aep-model-options">
                                                    <div class="col-md-6">
                                                        <input class="visually-hidden aep-model-input" type="radio"
                                                            name="fichier_facture" id="mf_fokoue_<?php echo $aid; ?>"
                                                            value="model_fokoue" required
                                                            <?php echo $modele_actuel === 'model_fokoue' ? 'checked' : ''; ?>>
                                                        <label for="mf_fokoue_<?php echo $aid; ?>"
                                                            class="aep-model-card border rounded-3 d-block h-100 overflow-hidden">
                                                            <div class="ratio ratio-16x9 bg-body-tertiary">
                                                                <img src="presentation/assets/images/model_fokoue.png"
                                                                    alt="Modèle Fokoué" class="object-fit-contain p-2">
                                                            </div>
                                                            <div class="p-2 d-flex align-items-center justify-content-between">
                                                                <span class="fw-semibold">Modèle de Fokoué</span>
                                                                <i class="bi bi-check-circle-fill aep-model-check text-success"></i>
                                                            </div>
                                                        </label>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <input class="visually-hidden aep-model-input" type="radio"
                                                            name="fichier_facture" id="mf_nkong_<?php echo $aid; ?>"
                                                            value="model_nkongzem"
                                                            <?php echo $modele_actuel === 'model_nkongzem' ? 'checked' : ''; ?>>
                                                        <label for="mf_nkong_<?php echo $aid; ?>"
                                                            class="aep-model-card border rounded-3 d-block h-100 overflow-hidden">
                                                            <div class="ratio ratio-16x9 bg-body-tertiary">
                                                                <img src="presentation/assets/images/model_nkongzem.png"
                                                                    alt="Modèle Nkongzem" class="object-fit-contain p-2">
                                                            </div>
                                                            <div class="p-2 d-flex align-items-center justify-content-between">
                                                                <span class="fw-semibold">Modèle de Nkongzem</span>
                                                                <i class="bi bi-check-circle-fill aep-model-check text-success"></i>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <div class="modal-footer bg-white border-top">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                            <i class="bi bi-x-lg me-1"></i> Annuler
                                        </button>
                                        <a href="?page=edit_aep&id=<?php echo $aid; ?>"
                                            class="btn btn-outline-secondary" title="Ouvrir la page complète">
                                            <i class="bi bi-arrows-fullscreen me-1"></i> Page complète
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check2 me-1"></i> Enregistrer
                                        </button>
                                    </div>
                                </form>
                            </div>
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
            <td colspan="5" class="text-center">Aucun AEP trouvé.</td>
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

<style>
    /* Modal de modification d'AEP — apparence moderne */
    .aep-edit-modal .modal-content { border-radius: 1rem; overflow: hidden; }
    .aep-edit-header {
        background: linear-gradient(135deg, #0d6efd 0%, #4dabf7 100%);
        padding: 1.1rem 1.5rem;
    }
    .aep-edit-icon {
        width: 2.75rem; height: 2.75rem;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.35);
    }
    .aep-edit-section { transition: box-shadow .2s ease, transform .2s ease; }
    .aep-edit-section:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.06) !important; }

    /* Cartes radio (type de réseau) */
    .aep-type-card {
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease, transform .15s ease, box-shadow .15s ease;
        background-color: #fff;
    }
    .aep-type-card .aep-type-check { opacity: 0; transform: scale(.7); transition: opacity .15s ease, transform .15s ease; }
    .aep-type-card:hover { border-color: #0d6efd; transform: translateY(-1px); box-shadow: 0 .25rem .5rem rgba(13,110,253,.08); }
    .aep-type-input:checked + .aep-type-card {
        border-color: #0d6efd; border-width: 2px;
        background-color: #f1f7ff;
        box-shadow: 0 .25rem .75rem rgba(13,110,253,.12);
    }
    .aep-type-input:checked + .aep-type-card .aep-type-check { opacity: 1; transform: scale(1); }
    .aep-type-input:focus-visible + .aep-type-card { outline: 2px solid #0d6efd; outline-offset: 2px; }

    /* Cartes radio (modèle de facture) */
    .aep-model-card {
        cursor: pointer;
        transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
        background-color: #fff;
    }
    .aep-model-card .aep-model-check { opacity: 0; transform: scale(.7); transition: opacity .15s ease, transform .15s ease; }
    .aep-model-card:hover { border-color: #0d6efd; transform: translateY(-1px); box-shadow: 0 .25rem .5rem rgba(13,110,253,.08); }
    .aep-model-input:checked + .aep-model-card {
        border-color: #0d6efd; border-width: 2px;
        box-shadow: 0 .25rem .75rem rgba(13,110,253,.12);
    }
    .aep-model-input:checked + .aep-model-card .aep-model-check { opacity: 1; transform: scale(1); }
    .aep-model-input:focus-visible + .aep-model-card { outline: 2px solid #0d6efd; outline-offset: 2px; }
</style>
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

    // Validation Bootstrap moderne du formulaire de modification d'AEP
    (function () {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.classList.contains('aep-edit-form')) return;
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, true);
    })();
</script>