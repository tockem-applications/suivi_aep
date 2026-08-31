<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();
@include_once("../donnees/compteur.php");
@include_once("donnees/compteur.php");

class Compteur_t
{
    private static function ensureCompteurTypeColumn()
    {
        try {
            $exists = Manager::prepare_query(
                "SELECT COUNT(*) AS c
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'compteur_reseau'
                   AND column_name = 'type_compteur'",
                array()
            )->fetch();
            if (!$exists || (int) $exists['c'] === 0) {
                Manager::prepare_query(
                    "ALTER TABLE `compteur_reseau`
                     ADD COLUMN `type_compteur` ENUM('production','distribution','reservoir')
                     NOT NULL DEFAULT 'distribution' AFTER `id_compteur`",
                    array()
                );
            } else {
                Manager::prepare_query(
                    "UPDATE compteur_reseau SET type_compteur = 'distribution' WHERE type_compteur IS NULL OR type_compteur = ''",
                    array()
                );
            }
        } catch (Exception $e) {
            // fallback silencieux
        }
    }
    private static function renderCompteurIndexesModalContent($compteur_id, $reseau_id)
    {
        $indexes = Manager::prepare_query(
            "SELECT i.id, i.ancien_index, i.nouvel_index, mf.mois
             FROM indexes i
             INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
             WHERE i.id_compteur = ?
             ORDER BY mf.mois DESC",
            array($compteur_id)
        )->fetchAll();

        $aepRow = Manager::prepare_query(
            "SELECT id_aep FROM reseau WHERE id = ?",
            array($reseau_id)
        )->fetch();
        $aepId = $aepRow ? (int) $aepRow['id_aep'] : 0;

        $availableMonths = array();
        if ($aepId > 0) {
            $availableMonths = Manager::prepare_query(
                "SELECT mf.id, mf.mois
                 FROM mois_facturation mf
                 INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
                 LEFT JOIN indexes i ON i.id_mois_facturation = mf.id AND i.id_compteur = ?
                 WHERE cr.id_aep = ? AND i.id IS NULL
                 ORDER BY mf.mois DESC",
                array($compteur_id, $aepId)
            )->fetchAll();
        }

        ob_start();
        ?>
        <form method="post" action="traitement/compteur_t.php" id="form-edit-indexes-compteur">
            <input type="hidden" name="action" value="update_compteur_indexes">
            <input type="hidden" name="compteur_id" value="<?php echo (int) $compteur_id; ?>">
            <input type="hidden" name="reseau_id" value="<?php echo (int) $reseau_id; ?>">

            <?php if (empty($indexes)): ?>
                <div class="alert alert-secondary mb-0">
                    Aucun index trouve pour ce compteur.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-2">
                        <thead class="table-light">
                            <tr>
                                <th>Mois</th>
                                <th>Ancien index</th>
                                <th>Nouvel index</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indexes as $idx): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(getLetterMonth($idx['mois'])); ?></td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm"
                                            name="ancien_index[<?php echo (int) $idx['id']; ?>]"
                                            value="<?php echo htmlspecialchars((string) $idx['ancien_index']); ?>"
                                            step="0.01" min="0" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm"
                                            name="nouvel_index[<?php echo (int) $idx['id']; ?>]"
                                            value="<?php echo htmlspecialchars((string) $idx['nouvel_index']); ?>"
                                            step="0.01" min="0" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info py-2 mb-0">
                    Regle: le nouvel index doit etre superieur ou egal a l'ancien index.
                </div>
            <?php endif; ?>

            <hr>
            <h6 class="mb-3">Ajouter un index pour un ancien mois de l'AEP</h6>
            <?php if (empty($availableMonths)): ?>
                <div class="alert alert-secondary mb-0">
                    Aucun mois disponible a ajouter (tous les mois AEP ont deja un index pour ce compteur).
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Mois AEP</label>
                        <select class="form-select" name="new_id_mois_facturation">
                            <option value="">Ne pas ajouter de nouveau mois</option>
                            <?php foreach ($availableMonths as $m): ?>
                                <option value="<?php echo (int) $m['id']; ?>">
                                    <?php echo htmlspecialchars(getLetterMonth($m['mois'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ancien index</label>
                        <input type="number" class="form-control" name="new_ancien_index" step="0.01" min="0"
                            placeholder="0,00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nouvel index</label>
                        <input type="number" class="form-control" name="new_nouvel_index" step="0.01" min="0"
                            placeholder="0,00">
                    </div>
                </div>
                <div class="form-text mt-2">
                    Si un mois est selectionne, les champs ancien/nouvel index deviennent obligatoires.
                </div>
            <?php endif; ?>
        </form>
        <?php
        return ob_get_clean();
    }


    public static function getAllCompteurFromIdReseau($id_reseau)
    {
        $req = Compteur::getAllByIdReseau($id_reseau);
        //        $tab = array();

        $req = $req->fetchAll();
        return $req;
    }

    public static function ajouterCompteurReseau()
    {
        self::ensureCompteurTypeColumn();
        if (isset($_GET['ajouter_compteur_reseau'], $_GET['id_reseau'])) {
            $reseau_id = (int) $_GET['id_reseau'];
            try {
                $compteur = self::createCompteurFromPost($_POST);
                if ($compteur instanceof Compteur) {
                    $res = $compteur->save_compteur_reseau($reseau_id);
                    if ($res) {
                        header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&success=compteur_added');
                    } else {
                        header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=save&message=' . urlencode("Erreur d'enregistrement du compteur."));
                    }
                } else {
                    header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=invalid&message=' . urlencode('Données compteur invalides.'));
                }
            } catch (Exception $e) {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=exception&message=' . urlencode($e->getMessage()));
            }
            exit;
        }
    }


    public static function createCompteurFromPost(array $postData)
    {
        try {
            // Vérification des champs requis et suppression des espaces
            $id = isset($postData['id']) ? trim($postData['id']) : '';
            $numero_compteur = isset($postData['numero_compteur']) ? trim($postData['numero_compteur']) : '';
            $longitude = isset($postData['longitude']) ? floatval($postData['longitude']) : 0.0;
            $latitude = isset($postData['latitude']) ? floatval($postData['latitude']) : 0.0;
            $dernier_index = isset($postData['derniers_index']) ? floatval($postData['derniers_index']) : 0.0;
            $description = isset($postData['description']) ? trim($postData['description']) : '';
            $type_compteur = isset($postData['type_compteur']) ? trim($postData['type_compteur']) : 'distribution';
            if (!in_array($type_compteur, array('production', 'distribution', 'reservoir'), true)) {
                $type_compteur = 'distribution';
            }
            // Validation des données (ajoutez d'autres validations si nécessaire)
            if (empty($numero_compteur)) {
                throw new Exception("Le numéro de compteur est requis.");
            }

            // Création de l'objet Compteur
            $compteur = new Compteur($id, $numero_compteur, $longitude, $latitude, $dernier_index, $description, $type_compteur);

            return $compteur; // Retourner l'objet crée
        } catch (Exception $e) {
            // Gérer l'erreur (vous pouvez aussi logger l'erreur selon vos besoins)
            echo "Erreur : " . $e->getMessage();
            return null; // Retourner null en cas d'erreur
        }
    }

    public static function postActions()
    {
        self::ensureCompteurTypeColumn();
        // Chargement AJAX du contenu modal indexes
        if (isset($_GET['action']) && $_GET['action'] === 'get_compteur_indexes_modal') {
            $compteur_id = isset($_GET['compteur_id']) ? (int) $_GET['compteur_id'] : 0;
            $reseau_id = isset($_GET['reseau_id']) ? (int) $_GET['reseau_id'] : 0;
            if ($compteur_id <= 0 || $reseau_id <= 0) {
                echo '<div class="alert alert-danger mb-0">Parametres invalides.</div>';
                exit;
            }
            try {
                echo self::renderCompteurIndexesModalContent($compteur_id, $reseau_id);
            } catch (Exception $e) {
                echo '<div class="alert alert-danger mb-0">Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            exit;
        }

        // Update compteur
        if (isset($_POST['action']) && $_POST['action'] === 'update_compteur') {
            $compteur_id = isset($_POST['compteur_id']) ? (int) $_POST['compteur_id'] : 0;
            $reseau_id = isset($_POST['reseau_id']) ? (int) $_POST['reseau_id'] : 0;
            $numero = isset($_POST['numero_compteur']) ? trim($_POST['numero_compteur']) : '';
            $index = isset($_POST['derniers_index']) ? (float) $_POST['derniers_index'] : 0;
            $longitude = isset($_POST['longitude']) ? (float) $_POST['longitude'] : null;
            $latitude = isset($_POST['latitude']) ? (float) $_POST['latitude'] : null;
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $type_compteur = isset($_POST['type_compteur']) ? trim($_POST['type_compteur']) : 'distribution';
            if (!in_array($type_compteur, array('production', 'distribution', 'reservoir'), true)) {
                $type_compteur = 'distribution';
            }

            if ($compteur_id <= 0 || $reseau_id <= 0 || $numero === '') {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=invalid');
                exit;
            }

            try {
                // Update compteur table
                Manager::prepare_query(
                    'UPDATE compteur SET numero_compteur = ?, derniers_index = ?, longitude = ?, latitude = ?, description = ? WHERE id = ?',
                    array($numero, $index, $longitude, $latitude, $description, $compteur_id)
                );
                Manager::prepare_query(
                    'UPDATE compteur_reseau SET type_compteur = ? WHERE id_compteur = ? AND id_reseau = ?',
                    array($type_compteur, $compteur_id, $reseau_id)
                );
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&success=compteur_updated');
                exit;
            } catch (Exception $e) {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=exception&message=' . urlencode($e->getMessage()));
                exit;
            }
        }

        // Delete compteur
        if (isset($_POST['action']) && $_POST['action'] === 'delete_compteur') {
            $compteur_id = isset($_POST['compteur_id']) ? (int) $_POST['compteur_id'] : 0;
            $reseau_id = isset($_POST['reseau_id']) ? (int) $_POST['reseau_id'] : 0;
            if ($compteur_id <= 0 || $reseau_id <= 0) {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=invalid');
                exit;
            }
            try {
                Manager::prepare_query('DELETE FROM compteur_reseau WHERE id_compteur = ?', array($compteur_id));
                Manager::prepare_query('DELETE FROM compteur_abone WHERE id_compteur = ?', array($compteur_id));
                Manager::prepare_query('DELETE FROM indexes WHERE id_compteur = ?', array($compteur_id));
                Manager::prepare_query('DELETE FROM compteur WHERE id = ?', array($compteur_id));
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&success=compteur_deleted');
                exit;
            } catch (Exception $e) {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=exception&message=' . urlencode($e->getMessage()));
                exit;
            }
        }

        // Ajouter un index manuel pour un compteur réseau
        if (isset($_POST['action']) && $_POST['action'] === 'add_index_compteur_reseau') {
            $compteur_id = isset($_POST['compteur_id']) ? (int) $_POST['compteur_id'] : 0;
            $reseau_id = isset($_POST['reseau_id']) ? (int) $_POST['reseau_id'] : 0;
            $id_mois = isset($_POST['id_mois_facturation']) ? (int) $_POST['id_mois_facturation'] : 0;
            $ancien_index = isset($_POST['ancien_index']) ? (float) $_POST['ancien_index'] : 0;
            $nouvel_index = isset($_POST['nouvel_index']) ? (float) $_POST['nouvel_index'] : 0;

            if ($compteur_id <= 0 || $reseau_id <= 0 || $id_mois <= 0) {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=invalid');
                exit;
            }
            if ($ancien_index < 0 || $nouvel_index < 0 || $nouvel_index < $ancien_index) {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=invalid&message=' . urlencode('Index invalides: le nouvel index doit etre >= ancien index et non negatif.'));
                exit;
            }

            try {
                // Empêcher les doublons pour le même mois
                $exists = Manager::prepare_query(
                    'SELECT id FROM indexes WHERE id_compteur = ? AND id_mois_facturation = ?',
                    array($compteur_id, $id_mois)
                )->fetch();
                if ($exists) {
                    header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=invalid&message=' . urlencode('Un index existe deja pour ce compteur et ce mois.'));
                    exit;
                }

                Manager::prepare_query(
                    'INSERT INTO indexes (id_compteur, id_mois_facturation, ancien_index, nouvel_index, message) VALUES (?, ?, ?, ?, ?)',
                    array($compteur_id, $id_mois, $ancien_index, $nouvel_index, '')
                );

                // Synchroniser le dernier index du compteur
                Manager::prepare_query(
                    'UPDATE compteur SET derniers_index = ? WHERE id = ?',
                    array($nouvel_index, $compteur_id)
                );

                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&success=index_added');
                exit;
            } catch (Exception $e) {
                header('Location: ../?page=reseau_detail&id=' . $reseau_id . '&error=exception&message=' . urlencode($e->getMessage()));
                exit;
            }
        }

        // Mise a jour AJAX des indexes d'un compteur
        if (isset($_POST['action']) && $_POST['action'] === 'update_compteur_indexes') {
            header('Content-Type: application/json; charset=UTF-8');

            $compteur_id = isset($_POST['compteur_id']) ? (int) $_POST['compteur_id'] : 0;
            $reseau_id = isset($_POST['reseau_id']) ? (int) $_POST['reseau_id'] : 0;
            $ancien_indexes = isset($_POST['ancien_index']) ? $_POST['ancien_index'] : array();
            $nouvel_indexes = isset($_POST['nouvel_index']) ? $_POST['nouvel_index'] : array();
            $new_id_mois = isset($_POST['new_id_mois_facturation']) ? (int) $_POST['new_id_mois_facturation'] : 0;
            $new_ancien_index = isset($_POST['new_ancien_index']) && $_POST['new_ancien_index'] !== '' ? (float) $_POST['new_ancien_index'] : null;
            $new_nouvel_index = isset($_POST['new_nouvel_index']) && $_POST['new_nouvel_index'] !== '' ? (float) $_POST['new_nouvel_index'] : null;

            if ($compteur_id <= 0 || $reseau_id <= 0) {
                echo json_encode(array('success' => false, 'message' => 'Parametres invalides.'));
                exit;
            }

            try {
                foreach ($ancien_indexes as $index_id => $ancien_index_raw) {
                    $index_id = (int) $index_id;
                    $ancien_index = (float) $ancien_index_raw;
                    $nouvel_index = isset($nouvel_indexes[$index_id]) ? (float) $nouvel_indexes[$index_id] : 0;

                    if ($index_id <= 0 || $ancien_index < 0 || $nouvel_index < 0 || $nouvel_index < $ancien_index) {
                        echo json_encode(array('success' => false, 'message' => 'Valeurs invalides detectees.'));
                        exit;
                    }

                    Manager::prepare_query(
                        'UPDATE indexes SET ancien_index = ?, nouvel_index = ? WHERE id = ? AND id_compteur = ?',
                        array($ancien_index, $nouvel_index, $index_id, $compteur_id)
                    );
                }

                // Ajout optionnel d'un index pour un ancien mois AEP
                if ($new_id_mois > 0) {
                    if ($new_ancien_index === null || $new_nouvel_index === null) {
                        echo json_encode(array('success' => false, 'message' => 'Ancien et nouvel index sont obligatoires pour ajouter un mois.'));
                        exit;
                    }
                    if ($new_ancien_index < 0 || $new_nouvel_index < 0 || $new_nouvel_index < $new_ancien_index) {
                        echo json_encode(array('success' => false, 'message' => 'Le nouvel index doit etre >= a l\'ancien index.'));
                        exit;
                    }

                    $existsNew = Manager::prepare_query(
                        'SELECT id FROM indexes WHERE id_compteur = ? AND id_mois_facturation = ?',
                        array($compteur_id, $new_id_mois)
                    )->fetch();
                    if ($existsNew) {
                        echo json_encode(array('success' => false, 'message' => 'Un index existe deja pour ce mois.'));
                        exit;
                    }

                    Manager::prepare_query(
                        'INSERT INTO indexes (id_compteur, id_mois_facturation, ancien_index, nouvel_index, message) VALUES (?, ?, ?, ?, ?)',
                        array($compteur_id, $new_id_mois, $new_ancien_index, $new_nouvel_index, '')
                    );
                }

                $last = Manager::prepare_query(
                    'SELECT MAX(nouvel_index) AS max_idx FROM indexes WHERE id_compteur = ?',
                    array($compteur_id)
                )->fetch();
                $lastIndex = $last ? (float) $last['max_idx'] : 0;
                Manager::prepare_query('UPDATE compteur SET derniers_index = ? WHERE id = ?', array($lastIndex, $compteur_id));

                echo json_encode(array('success' => true, 'message' => 'Indexes mis a jour avec succes.'));
                exit;
            } catch (Exception $e) {
                echo json_encode(array('success' => false, 'message' => $e->getMessage()));
                exit;
            }
        }
    }
}

Compteur_t::ajouterCompteurReseau();
Compteur_t::postActions();
