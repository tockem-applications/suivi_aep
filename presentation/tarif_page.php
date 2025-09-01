<?php

// Inclure les classes nécessaires
@include_once("../donnees/constante_reseau.php");
@include_once("../donnees/mois_facturation.php");
@include_once("donnees/constante_reseau.php");
@include_once("donnees/mois_facturation.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
    $tarifs = array();
} else {
    // Récupérer tous les tarifs (constantes réseau) pour l'AEP
    $tarifs = Manager::prepare_query(
        "SELECT cr.*, 
                (SELECT COUNT(*) FROM mois_facturation mf WHERE mf.id_constante = cr.id) as nb_mois_factures,
                (SELECT SUM(vaf.montant_conso_tva) FROM vue_abones_facturation vaf 
                 WHERE vaf.id_constante_reseau = cr.id) as total_facture
         FROM constante_reseau cr 
         WHERE cr.id_aep = ? 
         ORDER BY cr.date_creation DESC",
        array($aepId)
    )->fetchAll();

    $message = '';
}

// Gérer les messages de retour
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'tarif_activated':
            $message = '<div class="alert alert-success">Tarif activé avec succès.</div>';
            break;
        case 'tarif_added':
            $message = '<div class="alert alert-success">Nouveau tarif ajouté avec succès.</div>';
            break;
        case 'tarif_updated':
            $message = '<div class="alert alert-success">Tarif mis à jour avec succès.</div>';
            break;
        case 'tarif_deleted':
            $message = '<div class="alert alert-success">Tarif supprimé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_aep':
            $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
            break;
        case 'add_failed':
        case 'update_failed':
        case 'delete_failed':
        case 'tarif_activation_failed':
            $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
            $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
            break;
        case 'invalid_request':
            $message = '<div class="alert alert-danger">Requête invalide.</div>';
            break;
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4">Gestion des Tarifs de l'Eau</h2>
    <?php echo $message; ?>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>

    <!-- Section des Tarifs -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-currency-exchange"></i> Tarifs de l'Eau</h4>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addTarifModal"
                <?php echo $aepId ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> Nouveau Tarif
            </button>
        </div>
        <div class="card-body">
            <?php if (count($tarifs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                        <tr>
                            <th>Prix m³</th>
                            <th>Entretien Compteur</th>
                            <th>TVA (%)</th>
                            <th>Date Création</th>
                            <th>Statut</th>
                            <th>Mois Facturés</th>
                            <th>Total Facturé</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tarifs as $tarif): ?>
                            <tr class="<?php echo $tarif['est_actif'] ? 'table-success' : ''; ?>">
                                <td>
                                    <strong><?php echo number_format($tarif['prix_metre_cube_eau'], 0, ',', ' '); ?>
                                        FCFA</strong>
                                </td>
                                <td><?php echo number_format($tarif['prix_entretient_compteur'], 0, ',', ' '); ?>FCFA
                                </td>
                                <td><?php echo number_format($tarif['prix_tva'], 2, ',', ' '); ?>%</td>
                                <td><?php echo date('d/m/Y', strtotime($tarif['date_creation'])); ?></td>
                                <td>
                                    <?php if ($tarif['est_actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $tarif['nb_mois_factures']; ?> mois</span>
                                </td>
                                                                    <td>
                                        <strong><?php echo number_format(isset($tarif['total_facture']) ? $tarif['total_facture'] : 0, 0, ',', ' '); ?>
                                            FCFA</strong>
                                    </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick="voirDetailsTarif(<?php echo $tarif['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                                                                 <?php if (!$tarif['est_actif']): ?>
                                             <button type="button" class="btn btn-sm btn-outline-warning"
                                                     onclick="activerTarif(<?php echo $tarif['id']; ?>)"
                                                     title="Attention : Cette action désactivera le tarif actuel">
                                                 <i class="bi bi-exclamation-triangle"></i> Activer
                                             </button>
                                         <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                onclick="editTarif(<?php echo $tarif['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteTarif(<?php echo $tarif['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-info-circle fs-1"></i>
                    <p class="mt-2">Aucun tarif configuré pour cet AEP</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un nouveau tarif -->
<div class="modal fade" id="addTarifModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Tarif</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addTarifForm" method="post" action="traitement/tarif_t.php">
                    <input type="hidden" name="action" value="add_tarif">
                    <input type="hidden" name="id_aep" value="<?php echo $aepId; ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prix_metre_cube" class="form-label">Prix par m³ (FCFA)</label>
                                <input type="number" class="form-control" id="prix_metre_cube"
                                       name="prix_metre_cube_eau"
                                       required min="0" step="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prix_entretien" class="form-label">Prix entretien compteur (FCFA)</label>
                                <input type="number" class="form-control" id="prix_entretien"
                                       name="prix_entretient_compteur"
                                       required min="0" step="1">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prix_tva" class="form-label">TVA (%)</label>
                                <input type="number" class="form-control" id="prix_tva" name="prix_tva"
                                       required min="0" max="100" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_creation" class="form-label">Date de création</label>
                                <input type="date" class="form-control" id="date_creation" name="date_creation"
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Description du tarif..."></textarea>
                    </div>

                                         <div class="form-check mb-3">
                         <input class="form-check-input" type="checkbox" id="activer_immediatement"
                                name="activer_immediatement" checked>
                         <label class="form-check-label" for="activer_immediatement">
                             Activer ce tarif immédiatement (désactivera l'ancien)
                         </label>
                     </div>
                     
                     <div class="alert alert-warning" id="warningActivation" style="display: none;">
                         <i class="bi bi-exclamation-triangle"></i>
                         <strong>Attention :</strong> L'activation immédiate va désactiver le tarif actuellement actif. 
                         Assurez-vous que ce nouveau tarif est correct avant de continuer.
                     </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="addTarifForm" class="btn btn-primary">Créer le tarif</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les détails d'un tarif -->
<div class="modal fade" id="detailsTarifModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Tarif</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tarifDetailsContent">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    function voirDetailsTarif(tarifId) {
        // Charger les détails du tarif via AJAX
        fetch(`traitement/tarif_t.php?action=get_details&id=${tarifId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('tarifDetailsContent').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('detailsTarifModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des détails');
            });
    }

    function activerTarif(tarifId) {
        if (confirm('⚠️ ATTENTION : Cette action va désactiver le tarif actuellement actif et activer le nouveau tarif.\n\n' +
                   '• Le tarif actuel sera désactivé\n' +
                   '• Toutes les nouvelles factures utiliseront ce nouveau tarif\n' +
                   '• Cette action peut avoir un impact sur la facturation\n\n' +
                   'Êtes-vous vraiment sûr de vouloir continuer ?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'traitement/tarif_t.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'activate_tarif';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'tarif_id';
            idInput.value = tarifId;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function editTarif(tarifId) {
        // Implémenter l'édition des tarifs
        alert('Fonctionnalité d\'édition des tarifs à implémenter');
    }

         // Afficher/masquer l'avertissement d'activation
     document.addEventListener('DOMContentLoaded', function() {
         const checkbox = document.getElementById('activer_immediatement');
         const warning = document.getElementById('warningActivation');
         
         if (checkbox && warning) {
             checkbox.addEventListener('change', function() {
                 warning.style.display = this.checked ? 'block' : 'none';
             });
             
             // Afficher l'avertissement au chargement si la case est cochée
             if (checkbox.checked) {
                 warning.style.display = 'block';
             }
         }
     });
     
     function deleteTarif(tarifId) {
         if (confirm('Êtes-vous sûr de vouloir supprimer ce tarif ? Cette action est irréversible.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'traitement/tarif_t.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_tarif';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'tarif_id';
            idInput.value = tarifId;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
