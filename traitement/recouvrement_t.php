<?php
session_start();

@include_once("../donnees/mois_facturation.php");
@include_once("../donnees/constante_reseau.php");
@include_once("../donnees/manager.php");
@include_once("donnees/mois_facturation.php");
@include_once("donnees/constante_reseau.php");
@include_once("donnees/manager.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../?page=login');
    exit;
}

// Vérifier si un AEP est sélectionné
if (!isset($_SESSION['id_aep'])) {
    header('Location: ../?page=recouvrement&error=no_aep');
    exit;
}

$aepId = (int) $_SESSION['id_aep'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add_mois':
            ajouterMois();
            break;
        case 'activate_mois':
            activerMois();
            break;
        case 'delete_mois':
            supprimerMois();
            break;
        default:
            header('Location: ../?page=recouvrement&error=invalid_request');
            exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'get_details':
            getDetailsMois();
            break;
        default:
            header('Location: ../?page=recouvrement&error=invalid_request');
            exit;
    }
}

function ajouterMois()
{
    global $aepId;

    try {
        // Validation des données
        $mois = isset($_POST['mois']) ? trim($_POST['mois']) : '';
        $dateFacturation = isset($_POST['date_facturation']) ? $_POST['date_facturation'] : '';
        $dateDepot = isset($_POST['date_depot']) ? $_POST['date_depot'] : '';
        $idConstante = (int) (isset($_POST['id_constante']) ? $_POST['id_constante'] : 0);
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
        $estActif = isset($_POST['est_actif']);

        // Validation
        if (empty($mois) || !preg_match('/^\d{4}-\d{2}$/', $mois)) {
            throw new Exception('Format de mois invalide. Utilisez le format YYYY-MM');
        }
        if (empty($dateFacturation)) {
            throw new Exception('Date de facturation requise');
        }
        if (empty($dateDepot)) {
            throw new Exception('Date de dépôt requise');
        }
        if ($idConstante <= 0) {
            throw new Exception('Tarif invalide');
        }

        // Vérifier que le tarif appartient à l'AEP
        $tarif = Manager::prepare_query(
            'SELECT * FROM constante_reseau WHERE id = ? AND id_aep = ?',
            array($idConstante, $aepId)
        )->fetch();

        if (!$tarif) {
            throw new Exception('Tarif introuvable ou non autorisé');
        }

        // Vérifier que le mois n'existe pas déjà
        $moisExistant = Manager::prepare_query(
            'SELECT * FROM mois_facturation mf 
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id 
             WHERE mf.mois = ? AND cr.id_aep = ?',
            array($mois, $aepId)
        )->fetch();

        if ($moisExistant) {
            throw new Exception('Un mois de facturation existe déjà pour ' . $mois);
        }

        // Si on veut activer le mois, désactiver tous les autres
        if ($estActif) {
            Manager::prepare_query(
                'UPDATE mois_facturation mf 
                 INNER JOIN constante_reseau cr ON mf.id_constante = cr.id 
                 SET mf.est_actif = false 
                 WHERE cr.id_aep = ?',
                array($aepId)
            );
        }

        // Créer le nouveau mois
        $nouveauMois = new MoisFacturation(
            0, // ID sera généré automatiquement
            $mois,
            $dateFacturation,
            $dateDepot,
            $idConstante,
            $description,
            $estActif
        );

        $resultat = $nouveauMois->ajouter();

        if ($resultat) {
            header('Location: ../?page=recouvrement&success=mois_added');
        } else {
            throw new Exception('Impossible d\'ajouter le mois de facturation');
        }

    } catch (Exception $e) {
        header('Location: ../?page=recouvrement&error=add_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

function activerMois()
{
    global $aepId;

    try {
        $moisId = (int) (isset($_POST['mois_id']) ? $_POST['mois_id'] : 0);

        if ($moisId <= 0) {
            throw new Exception('ID de mois invalide');
        }

        // Vérifier que le mois appartient bien à l'AEP
        $mois = Manager::prepare_query(
            'SELECT mf.* FROM mois_facturation mf 
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id 
             WHERE mf.id = ? AND cr.id_aep = ?',
            array($moisId, $aepId)
        )->fetch();

        if (!$mois) {
            throw new Exception('Mois introuvable ou non autorisé');
        }

        // Désactiver tous les mois de l'AEP
        Manager::prepare_query(
            'UPDATE mois_facturation mf 
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id 
             SET mf.est_actif = false 
             WHERE cr.id_aep = ?',
            array($aepId)
        );

        // Activer le mois sélectionné
        $resultat = Manager::prepare_query(
            'UPDATE mois_facturation SET est_actif = true WHERE id = ?',
            array($moisId)
        );

        if ($resultat) {
            header('Location: ../?page=recouvrement&success=mois_activated');
        } else {
            throw new Exception('Impossible d\'activer le mois');
        }

    } catch (Exception $e) {
        header('Location: ../?page=recouvrement&error=mois_activation_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

function supprimerMois()
{
    global $aepId;

    try {
        $moisId = (int) (isset($_POST['mois_id']) ? $_POST['mois_id'] : 0);

        if ($moisId <= 0) {
            throw new Exception('ID de mois invalide');
        }

        // Vérifier que le mois appartient bien à l'AEP
        $mois = Manager::prepare_query(
            'SELECT mf.* FROM mois_facturation mf 
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id 
             WHERE mf.id = ? AND cr.id_aep = ?',
            array($moisId, $aepId)
        )->fetch();

        if (!$mois) {
            throw new Exception('Mois introuvable ou non autorisé');
        }

        // Vérifier qu'il n'y a pas de factures liées
        $nbFactures = Manager::prepare_query(
            'SELECT COUNT(*) as nb FROM vue_abones_facturation WHERE id_mois = ?',
            array($moisId)
        )->fetch()['nb'];

        if ($nbFactures > 0) {
            throw new Exception('Impossible de supprimer ce mois : ' . $nbFactures . ' facture(s) y sont liées');
        }

        // Supprimer le mois
        $resultat = Manager::prepare_query(
            'DELETE FROM mois_facturation WHERE id = ?',
            array($moisId)
        );

        if ($resultat) {
            header('Location: ../?page=recouvrement&success=mois_deleted');
        } else {
            throw new Exception('Impossible de supprimer le mois');
        }

    } catch (Exception $e) {
        header('Location: ../?page=recouvrement&error=delete_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

function getDetailsMois()
{
    global $aepId;

    $moisId = (int) (isset($_GET['id']) ? $_GET['id'] : 0);

    if ($moisId <= 0) {
        echo '<div class="alert alert-danger">ID de mois invalide</div>';
        return;
    }

    try {
        // Récupérer les détails du mois
        $mois = Manager::prepare_query(
            'SELECT mf.*, cr.prix_metre_cube_eau, cr.prix_entretient_compteur, cr.prix_tva 
             FROM mois_facturation mf
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
             WHERE mf.id = ? AND cr.id_aep = ?',
            array($moisId, $aepId)
        )->fetch();

        if (!$mois) {
            echo '<div class="alert alert-danger">Mois introuvable</div>';
            return;
        }

        // Récupérer les abonnés facturés pour ce mois
        $abonesFactures = Manager::prepare_query(
            'SELECT vaf.*, 
                    (vaf.montant_total - vaf.montant_verse) as reste_a_payer
             FROM vue_abones_facturation vaf 
             WHERE vaf.id_mois = ?
             ORDER BY vaf.nom_abone',
            array($moisId)
        )->fetchAll();

        // Calculer les statistiques
        $totalFacture = array_sum(array_column($abonesFactures, 'montant_total'));
        $totalVerse = array_sum(array_column($abonesFactures, 'montant_verse'));
        $totalReste = array_sum(array_column($abonesFactures, 'reste_a_payer'));
        $nbAbones = count($abonesFactures);
        $nbImpayes = count(array_filter($abonesFactures, function ($a) {
            return $a['reste_a_payer'] > 0;
        }));

        // Afficher les détails
        ?>
        <div class="row">
            <div class="col-md-5">
                <h6 class="text-primary">Informations du Mois</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Mois :</strong></td>
                        <td><?php echo getLetterMonth($mois['mois']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date facturation :</strong></td>
                        <td><?php echo date('d/m/Y', strtotime($mois['date_facturation'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date dépôt :</strong></td>
                        <td><?php echo date('d/m/Y', strtotime($mois['date_depot'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date relevé :</strong></td>
                        <td><?php echo $mois['date_releve'] ? date('d/m/Y', strtotime($mois['date_releve'])) : 'Non défini'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Statut :</strong></td>
                        <td>
                            <?php if ($mois['est_actif']): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Description :</strong></td>
                        <td><?php echo htmlspecialchars($mois['description'] ?: 'Aucune description'); ?></td>
                    </tr>
                </table>

                <h6 class="text-info mt-4">Tarif Utilisé</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Prix par m³ :</strong></td>
                        <td><?php echo number_format($mois['prix_metre_cube_eau'], 0, ',', ' '); ?> FCFA</td>
                    </tr>
                    <tr>
                        <td><strong>Entretien compteur :</strong></td>
                        <td><?php echo number_format($mois['prix_entretient_compteur'], 0, ',', ' '); ?> FCFA</td>
                    </tr>
                    <tr>
                        <td><strong>TVA :</strong></td>
                        <td><?php echo number_format($mois['prix_tva'], 2, ',', ' '); ?>%</td>
                    </tr>
                </table>
            </div>

            <div class="col-md-7">
                <h6 class="text-info">Statistiques de Recouvrement</h6>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <h5><?php echo $nbAbones; ?></h5>
                                <small>Abonnés</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h5><?php echo number_format($totalFacture, 0, ',', ' '); ?></h5>
                                <small>Total Facturé</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h5><?php echo number_format($totalVerse, 0, ',', ' '); ?></h5>
                                <small>Total Versé</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card <?php echo $totalReste > 0 ? 'bg-warning' : 'bg-success'; ?> text-white text-center">
                            <div class="card-body">
                                <h5><?php echo number_format($totalReste, 0, ',', ' '); ?></h5>
                                <small>Reste à Payer</small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (count($abonesFactures) > 0): ?>
                    <h6>Détail par Abonné</h6>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Abonné</th>
                                    <th>Consommation</th>
                                    <th>Montant Total</th>
                                    <th>Montant Versé</th>
                                    <th>Reste</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($abonesFactures as $abone): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($abone['nom_abone']); ?></strong><br>
                                            <small class="text-muted"><?php echo $abone['numero_telephone']; ?></small>
                                        </td>
                                        <td><?php echo number_format($abone['consommation'], 2, ',', ' '); ?> m³</td>
                                        <td>
                                            <strong><?php echo number_format($abone['montant_total'], 0, ',', ' '); ?> FCFA</strong>
                                        </td>
                                        <td>
                                            <span class="text-success">
                                                <?php echo number_format($abone['montant_verse'], 0, ',', ' '); ?> FCFA
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $reste = $abone['reste_a_payer'];
                                            $classReste = $reste > 0 ? 'text-danger' : 'text-success';
                                            ?>
                                            <span class="<?php echo $classReste; ?>">
                                                <strong><?php echo number_format($reste, 0, ',', ' '); ?> FCFA</strong>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($reste > 0): ?>
                                                <span class="badge bg-warning">Impayé</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Payé</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Aucun abonné n'a encore été facturé pour ce mois
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$mois['est_actif']): ?>
            <div class="mt-3 text-center">
                <button type="button" class="btn btn-warning" onclick="activerMois(<?php echo $mois['id']; ?>)">
                    <i class="bi bi-exclamation-triangle"></i> Activer ce Mois
                </button>
            </div>
        <?php endif; ?>
    <?php

    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Erreur lors du chargement des détails : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// La fonction getLetterMonth() est déjà définie dans manager.php
?>
