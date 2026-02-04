<?php
session_start();

@include_once("../donnees/constante_reseau.php");
@include_once("../donnees/manager.php");
@include_once("../donnees/tarif_differencie.php");
@include_once("donnees/constante_reseau.php");
@include_once("donnees/manager.php");
@include_once("donnees/tarif_differencie.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../?page=login');
    exit;
}

// Vérifier si un AEP est sélectionné
if (!isset($_SESSION['id_aep'])) {
    header('Location: ../?page=tarif_aep&error=no_aep');
    exit;
}

$aepId = (int) $_SESSION['id_aep'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add_tarif':
            ajouterTarif();
            break;
        case 'activate_tarif':
            activerTarif();
            break;
        case 'add_tarif_differencie':
            ajouterTarifDifferencie();
            break;
        case 'delete_tarif_differencie':
            supprimerTarifDifferencie();
            break;
        default:
            header('Location: ../?page=tarif_aep&error=invalid_request');
            exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'get_details':
            getDetailsTarif();
            break;
        default:
            header('Location: ../?page=tarif_aep&error=invalid_request');
            exit;
    }
}

function ajouterTarif()
{
    global $aepId;

    try {
        // Validation des données
        $prixMetreCube = (float) (isset($_POST['prix_metre_cube_eau']) ? $_POST['prix_metre_cube_eau'] : 0);
        $prixEntretien = (float) (isset($_POST['prix_entretient_compteur']) ? $_POST['prix_entretient_compteur'] : 0);
        $prixTva = (float) (isset($_POST['prix_tva']) ? $_POST['prix_tva'] : 0);
        $dateCreation = isset($_POST['date_creation']) ? $_POST['date_creation'] : date('Y-m-d');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
        $activerImmediatement = isset($_POST['activer_immediatement']);

        // Validation
        if ($prixMetreCube <= 0) {
            throw new Exception('Le prix par m³ doit être supérieur à 0');
        }
        if ($prixEntretien < 0) {
            throw new Exception('Le prix d\'entretien ne peut pas être négatif');
        }
        if ($prixTva < 0 || $prixTva > 100) {
            throw new Exception('La TVA doit être comprise entre 0 et 100%');
        }

        // Créer le nouveau tarif
        $nouveauTarif = new ConstanteReseau(
            0, // ID sera généré automatiquement
            $prixMetreCube,
            $prixEntretien,
            $prixTva,
            $dateCreation,
            $activerImmediatement, // true si on veut l'activer immédiatement
            $description,
            $aepId
        );

        if ($activerImmediatement) {
            // Ajouter et activer le tarif (désactivera l'ancien)
            $resultat = $nouveauTarif->ajouterEtActiver();
        } else {
            // Ajouter le tarif sans l'activer
            $resultat = $nouveauTarif->ajouter();
        }

        if ($resultat) {
            header('Location: ../?page=tarif_aep&success=tarif_added');
        } else {
            throw new Exception('Impossible d\'ajouter le tarif');
        }

    } catch (Exception $e) {
        header('Location: ../?page=tarif_aep&error=add_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

function activerTarif()
{
    global $aepId;

    try {
        $tarifId = (int) (isset($_POST['tarif_id']) ? $_POST['tarif_id'] : 0);

        if ($tarifId <= 0) {
            throw new Exception('ID de tarif invalide');
        }

        // Vérifier que le tarif appartient bien à l'AEP
        $tarif = Manager::prepare_query(
            'SELECT * FROM constante_reseau WHERE id = ? AND id_aep = ?',
            array($tarifId, $aepId)
        )->fetch();

        if (!$tarif) {
            throw new Exception('Tarif introuvable ou non autorisé');
        }

        // Désactiver tous les tarifs de l'AEP
        Manager::prepare_query(
            'UPDATE constante_reseau SET est_actif = false WHERE id_aep = ?',
            array($aepId)
        );

        // Activer le tarif sélectionné
        $resultat = Manager::prepare_query(
            'UPDATE constante_reseau SET est_actif = true WHERE id = ?',
            array($tarifId)
        );

        if ($resultat) {
            header('Location: ../?page=tarif_aep&success=tarif_activated');
        } else {
            throw new Exception('Impossible d\'activer le tarif');
        }

    } catch (Exception $e) {
        header('Location: ../?page=tarif_aep&error=tarif_activation_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

function getDetailsTarif()
{
    global $aepId;

    $tarifId = (int) (isset($_GET['id']) ? $_GET['id'] : 0);

    if ($tarifId <= 0) {
        echo '<div class="alert alert-danger">ID de tarif invalide</div>';
        return;
    }

    try {
        // Récupérer les détails du tarif
        $tarif = Manager::prepare_query(
            'SELECT * FROM constante_reseau WHERE id = ? AND id_aep = ?',
            array($tarifId, $aepId)
        )->fetch();

        if (!$tarif) {
            echo '<div class="alert alert-danger">Tarif introuvable</div>';
            return;
        }

        // Récupérer les mois facturés avec ce tarif
        $moisFactures = Manager::prepare_query(
            'SELECT mf.*, 
                    COUNT(vaf.id_facture) as nb_factures,
                    SUM(vaf.montant_verse) as total_verse,
                    SUM(vaf.consommation) as total_consommation,
                    SUM(vaf.montant_conso_tva) as total_montant_tarif
             FROM mois_facturation mf
             LEFT JOIN vue_abones_facturation vaf ON mf.id = vaf.id_mois
             WHERE mf.id_constante = ?
             GROUP BY mf.id
             ORDER BY mf.mois DESC',
            array($tarifId)
        )->fetchAll();

        // Récupérer les tarifs différenciés
        $tarifsDifferencies = TarifDifferencie::getTarifsByConstante($tarifId);
        $hasMoisFactures = count($moisFactures) > 0;

        // Afficher les détails
        ?>
        <div class="row">
            <div class="col-md-5">
                <h6 class="text-primary">Informations du Tarif</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Prix par m³ :</strong></td>
                        <td><?php echo number_format($tarif['prix_metre_cube_eau'], 0, ',', ' '); ?> FCFA</td>
                    </tr>
                    <tr>
                        <td><strong>Entretien compteur :</strong></td>
                        <td><?php echo number_format($tarif['prix_entretient_compteur'], 0, ',', ' '); ?> FCFA</td>
                    </tr>
                    <tr>
                        <td><strong>TVA :</strong></td>
                        <td><?php echo number_format($tarif['prix_tva'], 2, ',', ' '); ?>%</td>
                    </tr>
                    <tr>
                        <td><strong>Date création :</strong></td>
                        <td><?php echo date('d/m/Y', strtotime($tarif['date_creation'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Statut :</strong></td>
                        <td>
                            <?php if ($tarif['est_actif']): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Description :</strong></td>
                        <td><?php echo htmlspecialchars($tarif['description'] ?: 'Aucune description'); ?></td>
                    </tr>
                </table>
            </div>

            <div class="col-md-7">
                <h6 class="text-info">Statistiques d'Utilisation</h6>
                <?php if (count($moisFactures) > 0): ?>
                    <div class="alert alert-info">
                        <strong><?php echo count($moisFactures); ?> mois</strong> ont été facturés avec ce tarif
                    </div>

                    <h6>Mois Facturés</h6>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Mois</th>
                                    <th>Factures</th>
                                    <th>Consommation (m³)</th>
                                    <th>Montant Tarif (TVA incluse)</th>
                                    <th>Montant Versé</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($moisFactures as $mois): ?>
                                    <tr>
                                        <td><?php echo getLetterMonth($mois['mois']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $mois['nb_factures']; ?></span>
                                        </td>
                                        <td><?php echo number_format(isset($mois['total_consommation']) ? $mois['total_consommation'] : 0, 2, ',', ' '); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format(isset($mois['total_montant_tarif']) ? $mois['total_montant_tarif'] : 0, 0, ',', ' '); ?>
                                                FCFA</strong>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format(isset($mois['total_verse']) ? $mois['total_verse'] : 0, 0, ',', ' '); ?>
                                                FCFA</strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Aucun mois n'a encore été facturé avec ce tarif
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$tarif['est_actif']): ?>
            <div class="mt-3 text-center">
                <button type="button" class="btn btn-success" onclick="activerTarif(<?php echo $tarif['id']; ?>)">
                    <i class="bi bi-check-circle"></i> Activer ce Tarif
                </button>
            </div>
        <?php endif; ?>

        <!-- Section Tarifs Différenciés -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-layers"></i> Tarifs Différenciés par Intervalle de Consommation</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTarifDifferencieModal"
                            <?php echo $hasMoisFactures ? 'disabled title="Impossible d\'ajouter des tarifs différenciés car ce tarif a déjà des mois de facturation associés."' : ''; ?>>
                            <i class="bi bi-plus-circle"></i> Ajouter un tarif différencié
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if ($hasMoisFactures): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Ce tarif a déjà des mois de facturation associés. Il n'est plus possible d'ajouter ou de supprimer des tarifs différenciés.
                            </div>
                        <?php endif; ?>
                        <?php if (count($tarifsDifferencies) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Intervalle de consommation (m³)</th>
                                            <th>Prix m³ (FCFA)</th>
                                            <th>Entretien (FCFA)</th>
                                            <th>TVA (%)</th>
                                            <th>Date création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tarifsDifferencies as $td): ?>
                                            <tr>
                                                <td>
                                                    <strong>[<?php echo number_format($td['min_consommation'], 2, ',', ' '); ?> 
                                                    <?php if ($td['max_consommation'] !== null): ?>
                                                        ; <?php echo number_format($td['max_consommation'], 2, ',', ' '); ?>[
                                                    <?php else: ?>
                                                        ; +∞[
                                                    <?php endif; ?>
                                                    </strong>
                                                </td>
                                                <td><?php echo number_format($td['prix_metre_cube_eau'], 0, ',', ' '); ?></td>
                                                <td><?php echo number_format($td['prix_entretient_compteur'], 0, ',', ' '); ?></td>
                                                <td><?php echo number_format($td['prix_tva'], 2, ',', ' '); ?>%</td>
                                                <td><?php echo date('d/m/Y', strtotime($td['date_creation'])); ?></td>
                                                <td>
                                                    <form method="POST" action="traitement/tarif_t.php" class="d-inline"
                                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce tarif différencié ?');">
                                                        <input type="hidden" name="action" value="delete_tarif_differencie">
                                                        <input type="hidden" name="tarif_differencie_id" value="<?php echo $td['id']; ?>">
                                                        <input type="hidden" name="id_constante_reseau" value="<?php echo $tarifId; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                            <?php echo $hasMoisFactures ? 'disabled title="Impossible de supprimer car ce tarif a déjà des mois de facturation associés."' : ''; ?>>
                                                            <i class="bi bi-trash"></i> Supprimer
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-info-circle fs-1"></i>
                                <p class="mt-2">Aucun tarif différencié configuré pour ce tarif de base</p>
                                <p class="small">Les abonnés utiliseront le tarif de base pour toutes les consommations</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal pour ajouter un tarif différencié -->
        <div class="modal fade" id="addTarifDifferencieModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Ajouter un Tarif Différencié</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="traitement/tarif_t.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_tarif_differencie">
                            <input type="hidden" name="id_constante_reseau" value="<?php echo $tarifId; ?>">
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Note :</strong> L'intervalle est défini comme [min_consommation ; max_consommation[ (max non inclus).
                                Si max n'est pas défini, le tarif s'applique à toutes les consommations supérieures ou égales au min.
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="min_consommation" class="form-label">Consommation minimale (m³) *</label>
                                        <input type="number" class="form-control" id="min_consommation" name="min_consommation"
                                               required min="0" step="0.01" value="0">
                                        <small class="form-text text-muted">Consommation minimale (peut être 0)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_consommation" class="form-label">Consommation maximale (m³)</label>
                                        <input type="number" class="form-control" id="max_consommation" name="max_consommation"
                                               min="0" step="0.01" placeholder="Laisser vide pour +∞">
                                        <small class="form-text text-muted">Laisser vide pour un intervalle illimité</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="prix_metre_cube_diff" class="form-label">Prix par m³ (FCFA) *</label>
                                        <input type="number" class="form-control" id="prix_metre_cube_diff" name="prix_metre_cube_eau"
                                               required min="1" step="1" value="<?php echo $tarif['prix_metre_cube_eau']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="prix_entretien_diff" class="form-label">Entretien compteur (FCFA) *</label>
                                        <input type="number" class="form-control" id="prix_entretien_diff" name="prix_entretient_compteur"
                                               required min="0" step="1" value="<?php echo $tarif['prix_entretient_compteur']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="prix_tva_diff" class="form-label">TVA (%) *</label>
                                        <input type="number" class="form-control" id="prix_tva_diff" name="prix_tva"
                                               required min="0" max="100" step="0.01" value="<?php echo $tarif['prix_tva']; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="date_creation_diff" class="form-label">Date de création</label>
                                <input type="date" class="form-control" id="date_creation_diff" name="date_creation"
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="description_diff" class="form-label">Description</label>
                                <textarea class="form-control" id="description_diff" name="description" rows="2"
                                          placeholder="Description du tarif différencié..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Ajouter le tarif différencié</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php

    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Erreur lors du chargement des détails : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function ajouterTarifDifferencie()
{
    global $aepId;

    try {
        $idConstante = (int) (isset($_POST['id_constante_reseau']) ? $_POST['id_constante_reseau'] : 0);
        $prixMetreCube = (int) (isset($_POST['prix_metre_cube_eau']) ? $_POST['prix_metre_cube_eau'] : 0);
        $prixEntretien = (int) (isset($_POST['prix_entretient_compteur']) ? $_POST['prix_entretient_compteur'] : 0);
        $prixTva = (float) (isset($_POST['prix_tva']) ? $_POST['prix_tva'] : 0);
        $minConsommation = (float) (isset($_POST['min_consommation']) ? $_POST['min_consommation'] : 0);
        $maxConsommation = isset($_POST['max_consommation']) && $_POST['max_consommation'] !== '' ? (float) $_POST['max_consommation'] : null;
        $dateCreation = isset($_POST['date_creation']) ? $_POST['date_creation'] : date('Y-m-d');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');

        // Validation
        if ($idConstante <= 0) {
            throw new Exception('ID de tarif de base invalide');
        }

        // Vérifier que le tarif de base appartient à l'AEP
        $constante = Manager::prepare_query(
            'SELECT * FROM constante_reseau WHERE id = ? AND id_aep = ?',
            array($idConstante, $aepId)
        )->fetch();

        if (!$constante) {
            throw new Exception('Tarif de base introuvable ou non autorisé');
        }

        // Vérifier si le tarif a déjà des mois de facturation
        if (TarifDifferencie::hasMoisFacturation($idConstante)) {
            throw new Exception('Impossible d\'ajouter un tarif différencié : ce tarif a déjà des mois de facturation associés.');
        }

        // Validation des intervalles
        if ($minConsommation < 0) {
            throw new Exception('La consommation minimale ne peut pas être négative');
        }
        if ($maxConsommation !== null && $maxConsommation <= $minConsommation) {
            throw new Exception('La consommation maximale doit être supérieure à la consommation minimale');
        }

        // Vérifier les chevauchements
        if (TarifDifferencie::checkOverlap($idConstante, $minConsommation, $maxConsommation)) {
            throw new Exception('Cet intervalle de consommation chevauche avec un tarif différencié existant');
        }

        // Validation des prix
        if ($prixMetreCube <= 0) {
            throw new Exception('Le prix par m³ doit être supérieur à 0');
        }
        if ($prixEntretien < 0) {
            throw new Exception('Le prix d\'entretien ne peut pas être négatif');
        }
        if ($prixTva < 0 || $prixTva > 100) {
            throw new Exception('La TVA doit être comprise entre 0 et 100%');
        }

        // Créer le tarif différencié
        $tarifDiff = new TarifDifferencie(
            0,
            $idConstante,
            $prixMetreCube,
            $prixEntretien,
            $prixTva,
            $minConsommation,
            $maxConsommation,
            $dateCreation,
            $description
        );

        $resultat = $tarifDiff->ajouter();

        if ($resultat) {
            header('Location: ../?page=detail_tarif&id=' . $idConstante . '&success=tarif_differencie_added');
        } else {
            throw new Exception('Impossible d\'ajouter le tarif différencié');
        }

    } catch (Exception $e) {
        $idConstante = isset($_POST['id_constante_reseau']) ? (int) $_POST['id_constante_reseau'] : 0;
        header('Location: ../?page=detail_tarif&id=' . $idConstante . '&error=tarif_differencie_add_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

function supprimerTarifDifferencie()
{
    global $aepId;

    try {
        $tarifDiffId = (int) (isset($_POST['tarif_differencie_id']) ? $_POST['tarif_differencie_id'] : 0);
        $idConstante = (int) (isset($_POST['id_constante_reseau']) ? $_POST['id_constante_reseau'] : 0);

        if ($tarifDiffId <= 0 || $idConstante <= 0) {
            throw new Exception('ID invalide');
        }

        // Vérifier que le tarif différencié appartient bien au tarif de base
        $tarifDiff = Manager::prepare_query(
            'SELECT td.* FROM tarif_differencie td
             INNER JOIN constante_reseau cr ON cr.id = td.id_constante_reseau
             WHERE td.id = ? AND td.id_constante_reseau = ? AND cr.id_aep = ?',
            array($tarifDiffId, $idConstante, $aepId)
        )->fetch();

        if (!$tarifDiff) {
            throw new Exception('Tarif différencié introuvable ou non autorisé');
        }

        // Vérifier si le tarif a déjà des mois de facturation
        if (TarifDifferencie::hasMoisFacturation($idConstante)) {
            throw new Exception('Impossible de supprimer un tarif différencié : ce tarif a déjà des mois de facturation associés.');
        }

        // Supprimer le tarif différencié
        $resultat = Manager::prepare_query(
            'DELETE FROM tarif_differencie WHERE id = ?',
            array($tarifDiffId)
        );

        if ($resultat) {
            header('Location: ../?page=detail_tarif&id=' . $idConstante . '&success=tarif_differencie_deleted');
        } else {
            throw new Exception('Impossible de supprimer le tarif différencié');
        }

    } catch (Exception $e) {
        $idConstante = isset($_POST['id_constante_reseau']) ? (int) $_POST['id_constante_reseau'] : 0;
        header('Location: ../?page=detail_tarif&id=' . $idConstante . '&error=tarif_differencie_delete_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}
?>