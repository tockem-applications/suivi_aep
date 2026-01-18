<?php
/**
 * Page de détails d'une Borne Fontaine avec gestion des gérants
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/borne_fontaine.php");
@include_once("donnees/borne_fontaine.php");
@include_once("../donnees/bf_gerant.php");
@include_once("donnees/bf_gerant.php");
@include_once("../traitement/abone_t.php");
@include_once("traitement/abone_t.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_bf = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'details';

if ($id_bf <= 0) {
    header('Location: index.php?page=borne_fontaine&error=invalid_request');
    exit;
}

// Récupérer les informations de la BF
$bf = BorneFontaine::getBFById($id_bf);
if (!$bf) {
    header('Location: index.php?page=borne_fontaine&error=not_found');
    exit;
}

// Récupérer l'historique des gérants
$historiqueGerants = BorneFontaine::getHistoriqueGerants($id_bf)->fetchAll();
$gerantActif = BorneFontaine::getGerantActif($id_bf);

// Récupérer les informations de facturation (utiliser la même fonction que pour les abonnés)
$id_abone = $bf['id_abone'];
// La fonction afficheInfoAbone affiche directement le HTML, on capture sa sortie
ob_start();
$aboneInfoResult = Abone_t::afficheInfoAbone($id_abone);
$aboneInfo = ob_get_clean();

// Messages
$message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'gerant_added':
            $message = '<div class="alert alert-success">Gérant ajouté avec succès.</div>';
            break;
        case 'gerant_terminated':
            $message = '<div class="alert alert-success">Période du gérant terminée avec succès.</div>';
            break;
        case 'gerant_updated':
            $message = '<div class="alert alert-success">Gérant modifié avec succès.</div>';
            break;
        case 'gerant_deleted':
            $message = '<div class="alert alert-success">Gérant supprimé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
    $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <a href="?page=borne_fontaine" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- En-tête de la BF -->
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">
                        <i class="bi bi-droplet"></i> <?php echo htmlspecialchars($bf['nom']); ?>
                        <?php if ($bf['numero_borne']): ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($bf['numero_borne']); ?></span>
                        <?php endif; ?>
                    </h4>
                    <div class="mt-2">
                        <span class="badge bg-secondary">Réseau: <?php echo htmlspecialchars($bf['nom_reseau']); ?></span>
                        <?php if ($bf['localisation']): ?>
                            <span class="badge bg-info ms-2"><?php echo htmlspecialchars($bf['localisation']); ?></span>
                        <?php endif; ?>
                        <?php 
                        $etatClass = ($bf['etat'] === 'actif') ? 'bg-success' : (($bf['etat'] === 'suspendu') ? 'bg-warning' : 'bg-secondary');
                        ?>
                        <span class="badge <?php echo $etatClass; ?> ms-2"><?php echo ucfirst(htmlspecialchars($bf['etat'])); ?></span>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#ajouterGerantModal">
                        <i class="bi bi-person-plus"></i> Ajouter un gérant
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglets -->
    <style>
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
            padding: 0 10px;
            border-radius: 8px 8px 0 0;
        }
        .nav-tabs .nav-link {
            font-size: 1rem;
            font-weight: 500;
            color: #212529 !important;
            padding: 12px 20px;
            border: none;
            border-bottom: 3px solid transparent;
            background-color: #e9ecef;
            transition: all 0.3s ease;
            margin-right: 4px;
            border-radius: 8px 8px 0 0;
        }
        .nav-tabs .nav-link:hover {
            color: #0d6efd !important;
            background-color: #dee2e6;
            border-bottom-color: #0d6efd;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd !important;
            font-weight: 600;
            background-color: #fff !important;
            border-bottom-color: #0d6efd;
            border-bottom-width: 3px;
        }
        .nav-tabs .nav-link i {
            margin-right: 6px;
            font-size: 1.1rem;
        }
        .nav-tabs .badge {
            margin-left: 8px;
            font-size: 0.85rem;
            padding: 4px 8px;
            font-weight: 600;
        }
    </style>
    <ul class="nav nav-tabs mb-3" id="bfTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $tab === 'details' ? 'active' : ''; ?>" 
                    id="details-tab" data-bs-toggle="tab" data-bs-target="#details" 
                    type="button" role="tab">
                <i class="bi bi-info-circle"></i> Détails
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $tab === 'gerants' ? 'active' : ''; ?>" 
                    id="gerants-tab" data-bs-toggle="tab" data-bs-target="#gerants" 
                    type="button" role="tab">
                <i class="bi bi-people"></i> Gérants 
                <span class="badge bg-primary"><?php echo count($historiqueGerants); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $tab === 'facturation' ? 'active' : ''; ?>" 
                    id="facturation-tab" data-bs-toggle="tab" data-bs-target="#facturation" 
                    type="button" role="tab">
                <i class="bi bi-receipt"></i> Facturation
            </button>
        </li>
    </ul>

    <!-- Contenu des onglets -->
    <div class="tab-content" id="bfTabContent">
        <!-- Onglet Détails -->
        <div class="tab-pane fade <?php echo $tab === 'details' ? 'show active' : ''; ?>" 
             id="details" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Informations de la Borne Fontaine</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Numéro de borne</th>
                                    <td><?php echo $bf['numero_borne'] ? htmlspecialchars($bf['numero_borne']) : '<span class="text-muted">Non défini</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Localisation</th>
                                    <td><?php echo $bf['localisation'] ? htmlspecialchars($bf['localisation']) : '<span class="text-muted">Non définie</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Date de création</th>
                                    <td><?php echo date('d/m/Y H:i', strtotime($bf['date_creation'])); ?></td>
                                </tr>
                                <?php if ($bf['date_modification']): ?>
                                <tr>
                                    <th>Dernière modification</th>
                                    <td><?php echo date('d/m/Y H:i', strtotime($bf['date_modification'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($bf['description']): ?>
                                <tr>
                                    <th>Description</th>
                                    <td><?php echo nl2br(htmlspecialchars($bf['description'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Informations de l'abonné</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Nom</th>
                                    <td><?php echo htmlspecialchars($bf['nom']); ?></td>
                                </tr>
                                <tr>
                                    <th>Téléphone</th>
                                    <td><?php echo htmlspecialchars($bf['numero_telephone']); ?></td>
                                </tr>
                                <tr>
                                    <th>Numéro compte anticipation</th>
                                    <td><?php echo htmlspecialchars($bf['numero_compte_anticipation']); ?></td>
                                </tr>
                                <tr>
                                    <th>Réseau</th>
                                    <td><?php echo htmlspecialchars($bf['nom_reseau']); ?></td>
                                </tr>
                                <tr>
                                    <th>État</th>
                                    <td>
                                        <span class="badge <?php echo $etatClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($bf['etat'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Gérants -->
        <div class="tab-pane fade <?php echo $tab === 'gerants' ? 'show active' : ''; ?>" 
             id="gerants" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0" style="font-weight: 600; color: #212529; font-size: 1.1rem;">
                        <i class="bi bi-people"></i> Historique des gérants
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($historiqueGerants) > 0): ?>
                        <style>
                            .table-responsive-gerants {
                                max-height: 600px;
                                overflow-y: auto;
                                border: 1px solid #dee2e6;
                                border-radius: 8px;
                                background-color: #fff;
                            }
                            .table-responsive-gerants thead th {
                                position: sticky;
                                top: 0;
                                background-color: #212529 !important;
                                z-index: 10;
                                color: #fff !important;
                                font-weight: 600;
                                padding: 14px 16px;
                                font-size: 0.95rem;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                border-bottom: 2px solid #495057;
                            }
                            .table-responsive-gerants tbody td {
                                padding: 12px 16px;
                                font-size: 1rem;
                                vertical-align: middle;
                                color: #212529;
                                border-bottom: 1px solid #e9ecef;
                            }
                            .table-responsive-gerants tbody td:first-child {
                                font-weight: 600;
                                max-width: 220px;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                color: #0d6efd;
                            }
                            .table-responsive-gerants tbody td {
                                white-space: nowrap;
                                max-width: 200px;
                                overflow: hidden;
                                text-overflow: ellipsis;
                            }
                            .table-responsive-gerants tbody tr:hover {
                                background-color: #f8f9fa;
                            }
                            .table-responsive-gerants tbody tr.table-success {
                                background-color: #d1e7dd !important;
                            }
                            .table-responsive-gerants .badge {
                                font-size: 0.85rem;
                                padding: 6px 10px;
                                font-weight: 600;
                            }
                            .table-responsive-gerants .btn-group .btn {
                                font-size: 0.9rem;
                                padding: 6px 10px;
                            }
                        </style>
                        <div class="table-responsive table-responsive-gerants">
                            <table class="table table-striped table-hover table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nom du gérant</th>
                                        <th>Téléphone</th>
                                        <th>Pièce d'identité</th>
                                        <th>Date début</th>
                                        <th>Date fin</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historiqueGerants as $gerant): ?>
                                        <tr class="<?php echo $gerant['est_actif'] ? 'table-success' : ''; ?>">
                                            <td><strong><?php echo htmlspecialchars($gerant['nom_gerant']); ?></strong></td>
                                            <td><?php echo $gerant['numero_telephone'] ? htmlspecialchars($gerant['numero_telephone']) : '-'; ?></td>
                                            <td>
                                                <?php if ($gerant['numero_piece_identite']): ?>
                                                    <?php echo htmlspecialchars($gerant['type_piece_identite'] ? $gerant['type_piece_identite'] . ': ' : ''); ?>
                                                    <?php echo htmlspecialchars($gerant['numero_piece_identite']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($gerant['date_debut'])); ?></td>
                                            <td>
                                                <?php if ($gerant['date_fin']): ?>
                                                    <?php echo date('d/m/Y', strtotime($gerant['date_fin'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-success">En cours</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($gerant['est_actif']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Terminé</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="modifierGerant(<?php echo $gerant['id']; ?>)"
                                                            title="Modifier">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($gerant['est_actif']): ?>
                                                        <button type="button" class="btn btn-sm btn-warning" 
                                                                onclick="terminerGerant(<?php echo $gerant['id']; ?>, '<?php echo htmlspecialchars($gerant['nom_gerant']); ?>')"
                                                                title="Terminer">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="supprimerGerant(<?php echo $gerant['id']; ?>, '<?php echo htmlspecialchars($gerant['nom_gerant']); ?>')"
                                                            title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php if ($gerant['notes']): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted small">
                                                <i class="bi bi-info-circle"></i> Notes: <?php echo nl2br(htmlspecialchars($gerant['notes'])); ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-people fs-1"></i>
                            <p class="mt-2">Aucun gérant enregistré pour cette Borne Fontaine</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Onglet Facturation -->
        <div class="tab-pane fade <?php echo $tab === 'facturation' ? 'show active' : ''; ?>" 
             id="facturation" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php 
                    // Afficher les informations de facturation (même format que pour les abonnés)
                    if (!empty($aboneInfo) && $aboneInfoResult !== 0) {
                        echo $aboneInfo;
                    } else {
                        echo '<div class="alert alert-warning">Aucune information de facturation disponible</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un gérant -->
<div class="modal fade" id="ajouterGerantModal" tabindex="-1" aria-labelledby="ajouterGerantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ajouterGerantModalLabel">Ajouter un gérant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="traitement/borne_fontaine_t.php?action=ajouter_gerant">
                <input type="hidden" name="id_borne_fontaine" value="<?php echo $id_bf; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom_gerant" class="form-label">Nom du gérant <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom_gerant" name="nom_gerant" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="numero_telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="numero_telephone" name="numero_telephone" 
                                   placeholder="Ex: 654190514">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_piece_identite" class="form-label">Type de pièce d'identité</label>
                            <select class="form-select" id="type_piece_identite" name="type_piece_identite">
                                <option value="">-- Choisir --</option>
                                <option value="CNI">CNI</option>
                                <option value="Passeport">Passeport</option>
                                <option value="Permis">Permis de conduire</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="numero_piece_identite" class="form-label">Numéro de pièce d'identité</label>
                            <input type="text" class="form-control" id="numero_piece_identite" name="numero_piece_identite">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Notes additionnelles sur le gérant"></textarea>
                    </div>
                    <?php if ($gerantActif): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            Un gérant actif existe déjà (<?php echo htmlspecialchars($gerantActif['nom_gerant']); ?>). 
                            Il sera automatiquement désactivé à la date de début du nouveau gérant.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter le gérant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour terminer un gérant -->
<div class="modal fade" id="terminerGerantModal" tabindex="-1" aria-labelledby="terminerGerantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="terminerGerantModalLabel">Terminer la période du gérant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="traitement/borne_fontaine_t.php?action=terminer_gerant" id="terminerGerantForm">
                <input type="hidden" name="id_gerant" id="terminer_id_gerant">
                <input type="hidden" name="id_borne_fontaine" value="<?php echo $id_bf; ?>">
                <div class="modal-body">
                    <p>Vous êtes sur le point de terminer la période de gestion de <strong id="terminer_nom_gerant"></strong>.</p>
                    <div class="mb-3">
                        <label for="date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Terminer la période</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour modifier un gérant -->
<div class="modal fade" id="modifierGerantModal" tabindex="-1" aria-labelledby="modifierGerantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modifierGerantModalLabel">Modifier le gérant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="traitement/borne_fontaine_t.php?action=modifier_gerant" id="modifierGerantForm">
                <input type="hidden" name="id_gerant" id="modifier_id_gerant">
                <input type="hidden" name="id_borne_fontaine" value="<?php echo $id_bf; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modifier_nom_gerant" class="form-label">Nom du gérant <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modifier_nom_gerant" name="nom_gerant" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modifier_numero_telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="modifier_numero_telephone" name="numero_telephone">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modifier_type_piece_identite" class="form-label">Type de pièce d'identité</label>
                            <select class="form-select" id="modifier_type_piece_identite" name="type_piece_identite">
                                <option value="">-- Choisir --</option>
                                <option value="CNI">CNI</option>
                                <option value="Passeport">Passeport</option>
                                <option value="Permis">Permis de conduire</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modifier_numero_piece_identite" class="form-label">Numéro de pièce d'identité</label>
                            <input type="text" class="form-control" id="modifier_numero_piece_identite" name="numero_piece_identite">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modifier_date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="modifier_date_debut" name="date_debut" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modifier_date_fin" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="modifier_date_fin" name="date_fin">
                            <small class="form-text text-muted">Laissez vide si toujours actif</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modifier_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="modifier_notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Les dates seront vérifiées pour éviter les chevauchements avec les autres gérants.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour supprimer un gérant -->
<div class="modal fade" id="supprimerGerantModal" tabindex="-1" aria-labelledby="supprimerGerantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="supprimerGerantModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="traitement/borne_fontaine_t.php?action=supprimer_gerant" id="supprimerGerantForm">
                <input type="hidden" name="id_gerant" id="supprimer_id_gerant">
                <input type="hidden" name="id_borne_fontaine" value="<?php echo $id_bf; ?>">
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le gérant <strong id="supprimer_nom_gerant"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Attention :</strong> Cette action est irréversible. Les données du gérant seront définitivement supprimées.
                    </div>
                    <p class="text-muted small">Note : Un gérant actif ne peut pas être supprimé. Terminez d'abord sa période.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer définitivement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
<?php
// Préparer les données des gérants en JSON pour JavaScript
$gerantsJson = array();
foreach ($historiqueGerants as $g) {
    $gerantsJson[$g['id']] = $g;
}
?>
var gerants = <?php echo json_encode($gerantsJson); ?>;

function terminerGerant(idGerant, nomGerant) {
    document.getElementById('terminer_id_gerant').value = idGerant;
    document.getElementById('terminer_nom_gerant').textContent = nomGerant;
    var modal = new bootstrap.Modal(document.getElementById('terminerGerantModal'));
    modal.show();
}

function modifierGerant(idGerant) {
    var gerant = gerants[idGerant];
    
    if (gerant) {
        document.getElementById('modifier_id_gerant').value = gerant.id;
        document.getElementById('modifier_nom_gerant').value = gerant.nom_gerant || '';
        document.getElementById('modifier_numero_telephone').value = gerant.numero_telephone || '';
        document.getElementById('modifier_type_piece_identite').value = gerant.type_piece_identite || '';
        document.getElementById('modifier_numero_piece_identite').value = gerant.numero_piece_identite || '';
        document.getElementById('modifier_date_debut').value = gerant.date_debut || '';
        document.getElementById('modifier_date_fin').value = gerant.date_fin || '';
        document.getElementById('modifier_notes').value = gerant.notes || '';
        
        var modal = new bootstrap.Modal(document.getElementById('modifierGerantModal'));
        modal.show();
    }
}

function supprimerGerant(idGerant, nomGerant) {
    document.getElementById('supprimer_id_gerant').value = idGerant;
    document.getElementById('supprimer_nom_gerant').textContent = nomGerant;
    var modal = new bootstrap.Modal(document.getElementById('supprimerGerantModal'));
    modal.show();
}

// Validation des dates dans le formulaire de modification
var modifierForm = document.getElementById('modifierGerantForm');
if (modifierForm) {
    modifierForm.addEventListener('submit', function(e) {
        var dateDebut = document.getElementById('modifier_date_debut').value;
        var dateFin = document.getElementById('modifier_date_fin').value;
        
        if (dateFin && dateDebut && new Date(dateDebut) > new Date(dateFin)) {
            e.preventDefault();
            alert('La date de début doit être antérieure à la date de fin.');
            return false;
        }
    });
}
</script>
