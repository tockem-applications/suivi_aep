<?php
session_start();

@include_once("../donnees/constante_reseau.php");
@include_once("../donnees/manager.php");
@include_once("donnees/constante_reseau.php");
@include_once("donnees/manager.php");

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
    <?php

    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Erreur lors du chargement des détails : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>