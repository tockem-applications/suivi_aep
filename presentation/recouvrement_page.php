<?php

// Inclure les classes nécessaires
@include_once("../donnees/mois_facturation.php");
@include_once("../donnees/constante_reseau.php");
@include_once("donnees/mois_facturation.php");
@include_once("donnees/constante_reseau.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
    $moisRecouvrement = array();
} else {
    // Récupérer tous les mois de facturation pour l'AEP avec statistiques
    $moisRecouvrement = Manager::prepare_query(
        "SELECT mf.*, 
                cr.prix_metre_cube_eau,
                cr.prix_entretient_compteur,
                cr.prix_tva,
                (SELECT COUNT(DISTINCT vaf.id_abone) FROM vue_abones_facturation vaf WHERE vaf.id_mois = mf.id) as nb_abones,
                (SELECT SUM(vaf.montant_total) FROM vue_abones_facturation vaf WHERE vaf.id_mois = mf.id) as montant_total_facture,
                (SELECT SUM(vaf.montant_verse) FROM vue_abones_facturation vaf WHERE vaf.id_mois = mf.id) as montant_total_verse,
                (SELECT SUM(vaf.montant_restant) FROM vue_abones_facturation vaf WHERE vaf.id_mois = mf.id) as montant_restant,
                (SELECT COUNT(CASE WHEN vaf.montant_restant > 0 THEN 1 END) FROM vue_abones_facturation vaf WHERE vaf.id_mois = mf.id) as nb_abones_impayes
         FROM mois_facturation mf
         INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
         WHERE cr.id_aep = ? 
         ORDER BY mf.mois DESC",
        array($aepId)
    )->fetchAll();
    
    $message = '';
}


// Gérer les messages de retour
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'mois_activated':
            $message = '<div class="alert alert-success">Mois de facturation activé avec succès.</div>';
            break;
        case 'mois_added':
            $message = '<div class="alert alert-success">Nouveau mois de facturation ajouté avec succès.</div>';
            break;
        case 'mois_updated':
            $message = '<div class="alert alert-success">Mois de facturation mis à jour avec succès.</div>';
            break;
        case 'mois_deleted':
            $message = '<div class="alert alert-success">Mois de facturation supprimé avec succès.</div>';
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
        case 'mois_activation_failed':
            $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
            $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
            break;
        case 'invalid_request':
            $message = '<div class="alert alert-danger">Requête invalide.</div>';
            break;
    }
}

// La fonction getLetterMonth() est déjà définie dans manager.php
?>

<div class="container-fluid mt-5">
    <h2 class="mb-4">Gestion des Mois de Recouvrement</h2>
    <?php echo $message; ?>
    <a href="?page=aep_dashboard&aep_id=<?php echo $_SESSION['id_aep']?>" class="btn btn-secondary mb-3">Retour au tableau de bord</a>

    <!-- Section des Mois de Recouvrement -->
    <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Mois de Recouvrement</h4>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addMoisModal"
                <?php echo $aepId ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> Nouveau Mois
            </button>
        </div>
        <div class="card-body">
            <?php if (count($moisRecouvrement) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Mois</th>
                                <th>Date Facturation</th>
                                <th>Date Dépôt</th>
                                <th>Tarif Actif</th>
                                <th>Abonnés</th>
                                <th>Montant Total</th>
                                <th>Montant Versé</th>
                                <th>Reste à Payer</th>
                                <th>Abonnés Impayés</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($moisRecouvrement as $mois): ?>
                                <tr class="<?php echo $mois['est_actif'] ? 'table-success' : ''; ?>">
                                    <td>
                                        <strong><?php echo getLetterMonth($mois['mois']); ?></strong>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($mois['date_facturation'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($mois['date_depot'])); ?></td>
                                    <td>
                                        <small>
                                            <strong><?php echo number_format($mois['prix_metre_cube_eau'], 0, ',', ' '); ?> FCFA/m³</strong><br>
                                            + <?php echo number_format($mois['prix_entretient_compteur'], 0, ',', ' '); ?> FCFA entretien<br>
                                            + <?php echo number_format($mois['prix_tva'], 2, ',', ' '); ?>% TVA
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $mois['nb_abones']; ?> abonnés</span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format(isset($mois['montant_total_facture']) ? $mois['montant_total_facture'] : 0, 0, ',', ' '); ?> FCFA</strong>
                                    </td>
                                    <td>
                                        <span class="text-success">
                                            <strong><?php echo number_format(isset($mois['montant_total_verse']) ? $mois['montant_total_verse'] : 0, 0, ',', ' '); ?> FCFA</strong>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $reste = isset($mois['montant_restant']) ? $mois['montant_restant'] : 0;
                                        $classReste = $reste > 0 ? 'text-danger' : 'text-success';
                                        ?>
                                        <span class="<?php echo $classReste; ?>">
                                            <strong><?php echo number_format($reste, 0, ',', ' '); ?> FCFA</strong>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $nbImpayes = isset($mois['nb_abones_impayes']) ? $mois['nb_abones_impayes'] : 0;
                                        $classImpayes = $nbImpayes > 0 ? 'bg-warning' : 'bg-success';
                                        ?>
                                        <span class="badge <?php echo $classImpayes; ?>">
                                            <?php echo $nbImpayes; ?> impayés
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($mois['est_actif']): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="voirDetailsMois(<?php echo $mois['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if (!$mois['est_actif']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        onclick="activerMois(<?php echo $mois['id']; ?>)"
                                                        title="Attention : Cette action désactivera le mois actuel">
                                                    <i class="bi bi-exclamation-triangle"></i> Activer
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="editMois(<?php echo $mois['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($mois['est_actif']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteMois(<?php echo $mois['id']; ?>)"
                                                        title="Supprimer le mois actif">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                        disabled title="Seuls les mois actifs peuvent être supprimés">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
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
                    <p class="mt-2">Aucun mois de facturation configuré pour cet AEP</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un nouveau mois -->
<div class="modal fade" id="addMoisModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Mois de Facturation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addMoisForm" method="post" action="traitement/recouvrement_t.php">
                    <input type="hidden" name="action" value="add_mois">
                    <input type="hidden" name="id_aep" value="<?php echo $aepId; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="mois" class="form-label">Mois (YYYY-MM)</label>
                                <input type="month" class="form-control" id="mois" name="mois" 
                                       value="<?php echo date('Y-m'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_facturation" class="form-label">Date de facturation</label>
                                <input type="date" class="form-control" id="date_facturation" name="date_facturation" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_depot" class="form-label">Date de dépôt</label>
                                <input type="date" class="form-control" id="date_depot" name="date_depot" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_constante" class="form-label">Tarif à utiliser</label>
                                <select class="form-control" id="id_constante" name="id_constante" required>
                                    <option value="">Sélectionner un tarif...</option>
                                    <?php
                                    if ($aepId) {
                                        $tarifs = Manager::prepare_query(
                                            "SELECT * FROM constante_reseau WHERE id_aep = ? ORDER BY date_creation DESC",
                                            array($aepId)
                                        )->fetchAll();
                                        
                                        foreach ($tarifs as $tarif) {
                                            $selected = $tarif['est_actif'] ? 'selected' : '';
                                            echo '<option value="' . $tarif['id'] . '" ' . $selected . '>';
                                            echo number_format($tarif['prix_metre_cube_eau'], 0, ',', ' ') . ' FCFA/m³';
                                            echo ' + ' . number_format($tarif['prix_entretient_compteur'], 0, ',', ' ') . ' FCFA entretien';
                                            echo ' + ' . number_format($tarif['prix_tva'], 2, ',', ' ') . '% TVA';
                                            echo '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Description du mois de facturation..."></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" checked>
                        <label class="form-check-label" for="est_actif">
                            Activer ce mois immédiatement (désactivera l'ancien)
                        </label>
                    </div>
                    
                    <div class="alert alert-warning" id="warningActivation" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Attention :</strong> L'activation immédiate va désactiver le mois actuellement actif. 
                        Assurez-vous que ce nouveau mois est correct avant de continuer.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="addMoisForm" class="btn btn-primary">Créer le mois</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les détails d'un mois -->
<div class="modal fade" id="detailsMoisModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Mois de Facturation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="moisDetailsContent">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un mois -->
<div class="modal fade" id="editMoisModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Mois de Facturation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editMoisForm" method="post" action="traitement/recouvrement_t.php">
                    <input type="hidden" name="action" value="update_mois">
                    <input type="hidden" name="mois_id" id="edit_mois_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_mois" class="form-label">Mois (YYYY-MM)</label>
                                <input type="month" class="form-control" id="edit_mois" name="mois" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_facturation" class="form-label">Date de facturation</label>
                                <input type="date" class="form-control" id="edit_date_facturation" name="date_facturation" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_depot" class="form-label">Date de dépôt</label>
                                <input type="date" class="form-control" id="edit_date_depot" name="date_depot" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_id_constante" class="form-label">Tarif à utiliser</label>
                                <select class="form-control" id="edit_id_constante" name="id_constante" required>
                                    <option value="">Sélectionner un tarif...</option>
                                    <?php
                                    if ($aepId) {
                                        $tarifs = Manager::prepare_query(
                                            "SELECT * FROM constante_reseau WHERE id_aep = ? ORDER BY date_creation DESC",
                                            array($aepId)
                                        )->fetchAll();
                                        foreach ($tarifs as $tarif) {
                                            echo '<option value="' . $tarif['id'] . '">' . 
                                                 htmlspecialchars($tarif['prix_metre_cube_eau']) . ' FCFA/m³ - ' . 
                                                 date('d/m/Y', strtotime($tarif['date_creation'])) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description (optionnel)</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3" 
                                  placeholder="Description du mois de facturation..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_est_actif" name="est_actif">
                            <label class="form-check-label" for="edit_est_actif">
                                Activer ce mois (désactivera le mois actuellement actif)
                            </label>
                        </div>
                        <div id="editWarningActivation" class="alert alert-warning mt-2" style="display: none;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Attention :</strong> Activer ce mois désactivera automatiquement le mois actuellement actif.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('editMoisForm').submit();">
                    <i class="bi bi-check-circle"></i> Mettre à jour
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation d'activation -->
<div class="modal fade" id="activateMoisModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle"></i> Confirmation d'activation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <h6><strong>⚠️ ATTENTION :</strong></h6>
                    <p>Cette action va désactiver le mois actuellement actif et activer le nouveau mois.</p>
                    <ul class="mb-0">
                        <li>Le mois actuel sera désactivé</li>
                        <li>Toutes les nouvelles factures utiliseront ce nouveau mois</li>
                        <li>Cette action peut avoir un impact sur la facturation</li>
                    </ul>
                </div>
                <p><strong>Êtes-vous vraiment sûr de vouloir continuer ?</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="post" action="traitement/recouvrement_t.php" style="display: inline;">
                    <input type="hidden" name="action" value="activate_mois">
                    <input type="hidden" name="mois_id" id="activate_mois_id">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-exclamation-triangle"></i> Oui, activer ce mois
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteMoisModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-trash"></i> Confirmation de suppression
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <h6><strong>⚠️ ATTENTION :</strong></h6>
                    <p>Cette action va supprimer définitivement le mois de facturation actif ainsi que toutes les données associées.</p>
                    <ul class="mb-0">
                        <li>Les anciens index des compteurs deviendront les derniers index</li>
                        <li>Le mois le plus récent deviendra automatiquement actif</li>
                        <li>Toutes les factures de ce mois seront supprimées</li>
                        <li>Cette action est irréversible</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_mois_lettre" class="form-label">
                        <strong>Pour confirmer la suppression, tapez le mois en lettres :</strong>
                    </label>
                    <input type="text" class="form-control" id="confirm_mois_lettre" 
                           placeholder="Ex: Janvier 2024" required>
                    <div class="form-text">
                        <span id="mois_attendu" class="text-muted"></span>
                    </div>
                </div>
                
                <p><strong>Êtes-vous vraiment sûr de vouloir continuer ?</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="post" action="traitement/recouvrement_t.php" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="action" value="delete_mois">
                    <input type="hidden" name="mois_id" id="delete_mois_id">
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="bi bi-trash"></i> Oui, supprimer définitivement
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    // Afficher/masquer l'avertissement d'activation
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('est_actif');
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
        
        // Gestion de l'avertissement pour le modal d'édition
        const editCheckbox = document.getElementById('edit_est_actif');
        const editWarning = document.getElementById('editWarningActivation');
        
        if (editCheckbox && editWarning) {
            editCheckbox.addEventListener('change', function() {
                editWarning.style.display = this.checked ? 'block' : 'none';
            });
        }
        
        // Validation de la saisie du mois pour la suppression
        const confirmInput = document.getElementById('confirm_mois_lettre');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        const moisAttendu = document.getElementById('mois_attendu');
        
        if (confirmInput && confirmBtn && moisAttendu) {
            confirmInput.addEventListener('input', function() {
                const expectedMois = moisAttendu.textContent.replace('Tapez: ', '');
                const inputValue = this.value.trim();
                
                if (inputValue === expectedMois) {
                    confirmBtn.disabled = false;
                    confirmBtn.classList.remove('btn-secondary');
                    confirmBtn.classList.add('btn-danger');
                } else {
                    confirmBtn.disabled = true;
                    confirmBtn.classList.remove('btn-danger');
                    confirmBtn.classList.add('btn-secondary');
                }
            });
        }
    });

    function voirDetailsMois(moisId) {
        // Charger les détails du mois via AJAX
        fetch(`traitement/recouvrement_t.php?action=get_details&id=${moisId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('moisDetailsContent').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('detailsMoisModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('moisDetailsContent').innerHTML = 
                    '<div class="alert alert-danger">Erreur lors du chargement des détails du mois.</div>';
                const modal = new bootstrap.Modal(document.getElementById('detailsMoisModal'));
                modal.show();
            });
    }

    function activerMois(moisId) {
        // Ouvrir le modal de confirmation d'activation
        document.getElementById('activate_mois_id').value = moisId;
        const modal = new bootstrap.Modal(document.getElementById('activateMoisModal'));
        modal.show();
    }

    function editMois(moisId) {
        // Charger les données du mois pour l'édition
        fetch(`traitement/recouvrement_t.php?action=get_mois_data&id=${moisId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remplir le formulaire d'édition
                    document.getElementById('edit_mois').value = data.mois.mois;
                    document.getElementById('edit_date_facturation').value = data.mois.date_facturation;
                    document.getElementById('edit_date_depot').value = data.mois.date_depot;
                    document.getElementById('edit_id_constante').value = data.mois.id_constante;
                    document.getElementById('edit_description').value = data.mois.description || '';
                    document.getElementById('edit_est_actif').checked = data.mois.est_actif == 1;
                    document.getElementById('edit_mois_id').value = moisId;
                    
                    // Afficher/masquer l'avertissement d'activation
                    const warning = document.getElementById('editWarningActivation');
                    if (warning) {
                        warning.style.display = data.mois.est_actif == 1 ? 'none' : 'block';
                    }
                    
                    // Ouvrir le modal d'édition
                    const modal = new bootstrap.Modal(document.getElementById('editMoisModal'));
                    modal.show();
                } else {
                    alert('Erreur lors du chargement des données: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des données');
            });
    }

    function deleteMois(moisId) {
        // Récupérer le mois en lettres pour validation
        fetch(`traitement/recouvrement_t.php?action=get_mois_lettre&id=${moisId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remplir les champs du modal
                    document.getElementById('delete_mois_id').value = moisId;
                    document.getElementById('mois_attendu').textContent = 'Tapez: ' + data.mois_lettre;
                    document.getElementById('confirm_mois_lettre').value = '';
                    document.getElementById('confirmDeleteBtn').disabled = true;
                    
                    // Ouvrir le modal
                    const modal = new bootstrap.Modal(document.getElementById('deleteMoisModal'));
                    modal.show();
                } else {
                    alert('Erreur lors du chargement des données: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des données');
            });
    }
</script>
