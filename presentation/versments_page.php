<?php

// Inclure la classe Manager et le modèle Versement
include_once("traitement/versement_t.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login.php');
    exit;
}

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
    $versements = array();
} else {
    // Récupérer les versements pour l'AEP
    $versements = Manager::prepare_query(
        "SELECT v.*, r.libele as redevance_libele, m.mois as mois_facturation
         FROM versements v
         INNER JOIN redevance r ON v.id_redevance = r.id
         INNER JOIN mois_facturation m ON v.id_mois_facturation = m.id
         WHERE r.id_aep = ?",
        array($aepId)
    )->fetchAll();
    $message = '';
}

// Gérer les messages de retour
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'versement_deleted':
            $message = '<div class="alert alert-success">Versement supprimé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_aep':
            $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
            break;
        case 'delete_failed':
            $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
            $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
            break;
        case 'invalid_request':
            $message = '<div class="alert alert-danger">Requête invalide.</div>';
            break;
    }
}

// Appeler la fonction CalculeVersement
$results = Versement::CalculeVersement($aepId)->fetchAll();
$message = '';

$groupedResults = array();
foreach ($results as $row) {
    $mois = $row['mois'];
    if (!isset($groupedResults[$mois])) {
        $groupedResults[$mois] = array();
    }
    $groupedResults[$mois][] = $row;
}


?>

<div class="container mt-5">
    <h2 class="mb-4">Calcul des Versements pour l'AEP <?php echo htmlspecialchars($aepId); ?></h2>
    <?php echo $message; ?>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>

    <!-- Tableau des résultats -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
            <tr>
                <!--                <th>ID Mois Facturation</th>-->
                <th>Mois Facturation</th>
                <th>Consommation (m³)</th>
                <th>Redevances</th>
                <th>Montant à Verser</th>
                <th>Montant Versé</th>
                <th>Prix m³ Eau</th>
                <th>Pourcentages</th>
                <!--                <th>Nombre de redevance</th>-->
                <th>Nombre de Factures</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($groupedResults) > 0): ?>
                <?php foreach ($groupedResults as $mois => $rows): ?>
                    <?php $rowspan = count($rows); ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php
                        $montant = Versement::getMontantVerse($row['id_redevance'], $row['id_mois_facturation']);
                        $montant_a_verser = ($row['montant_verse'] - $row['nombre'] * $row['prix_metre_cube_eau']) * $row['pourcentage'] / 100 - $montant;
                        $formHtml = "";
                        if ($montant_a_verser <= 0) {
                            $formHtml = '<button type="submit" class="btn btn-secondary disabled" disabled>enregistré</button>';
                        } else {
                            $formHtml = '<form id="addVersementForm" method="POST" action="traitement/versement_t.php" style="display: inline;">
                                            <input type="hidden" name="montant" value="' . htmlspecialchars($montant_a_verser) . '">
                                            <input type="hidden" name="date_versement" value="' . date('Y-m-d', strtotime("now")) . '">
                                            <input type="hidden" name="id_mois_facturation" value="' . $row['id_mois_facturation'] . '">
                                            <input type="hidden" name="id_redevance" value="' . $row['id_redevance'] . '">
                                            <input type="hidden" name="action" value="add_versement">
                                            <button type="submit" class="btn btn-primary">enregistré</button>
                                        </form>';
                        }
                        ?>
                        <tr>
                            <?php if ($index === 0): ?>
                                <!-- Cellule Mois avec rowspan -->
                                <td rowspan="<?php echo $rowspan; ?>" class="mois-cell">
                                    <a href="?page=redevance_details&id_mois=<?php echo htmlspecialchars($row['id_mois_facturation']); ?>">
                                        <?php echo getLetterMonth(htmlspecialchars($mois)); ?>
                                    </a>
                                    <br> <?php echo htmlspecialchars((int)($row['montant_verse'] + 0.00001)); ?> perçu
                                </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($row['conso']); ?></td>
                            <td><?php echo htmlspecialchars($row['libele']); ?></td>
                            <td><?php echo htmlspecialchars($montant_a_verser); ?></td>
                            <td><?php echo htmlspecialchars($montant); ?></td>
                            <td><?php echo htmlspecialchars($row['prix_metre_cube_eau']); ?></td>
                            <td><?php echo htmlspecialchars($row['pourcentage']); ?></td>
                            <!--                            <td>-->
                            <?php //echo htmlspecialchars($row['nombre_redevance']); ?><!--</td>-->
                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                            <td>
                                <?php echo $formHtml; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">Aucun résultat trouvé.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="container mt-5">
    <h2 class="mb-4">Gestion des Versements</h2>
    <?php echo $message; ?>
    <!--    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>-->

    <!-- Tableau des versements -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Montant</th>
                <th>Date de Versement</th>
                <th>Mois de Facturation</th>
                <th>Redevance</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($versements) > 0): ?>
                <?php foreach ($versements as $versement): ?>
                    <?php
//                    var_dump($versement);
                    if($versement['id_mois_facturation'] != 0){

                    }
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($versement['id']); ?></td>
                        <td><?php echo htmlspecialchars($versement['montant']); ?> </td>
                        <td><?php echo htmlspecialchars($versement['date_versement']); ?></td>
                        <td><?php echo htmlspecialchars($versement['mois_facturation']); ?></td>
                        <td><?php echo htmlspecialchars($versement['redevance_libele']); ?></td>
                        <td>
                            <?php if(htmlspecialchars($versement['est_valide']) == '0'):?>
                            <button type="button" class="btn btn-danger btn-sm action-btn" data-bs-toggle="modal"
                                    data-bs-target="#deleteVersementModal" data-id="<?php echo $versement['id']; ?>"
                                    data-montant="<?php echo htmlspecialchars($versement['montant']); ?>">Supprimer
                            </button>
                            <?php else:?>
                                <button type="button" class="btn btn-warning btn-sm action-btn"
                                         data-id="<?php echo $versement['id']; ?>"
                                        data-montant="<?php echo htmlspecialchars($versement['montant']); ?>">Desacriver
                                </button>
                            <?php endif;?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">Aucun versement trouvé.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal pour supprimer un versement -->
<div class="modal fade" id="deleteVersementModal" tabindex="-1" aria-labelledby="deleteVersementModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteVersementModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Veuillez confirmer la suppression du versement :</p>
                <p><strong>Montant : <span id="delete_montant"></span> </strong></p>
                <form id="deleteVersementForm" method="POST" action="traitement/versement_t.php">
                    <input type="hidden" name="id" id="delete_id">
                    <input type="hidden" name="action" value="delete_versement">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger" form="deleteVersementForm">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Remplir le modal de suppression
    document.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-bs-target="#deleteVersementModal"]');
        if (button) {
            const id = button.getAttribute('data-id');
            const montant = button.getAttribute('data-montant');
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_montant').textContent = montant;
        }
    });
</script>
