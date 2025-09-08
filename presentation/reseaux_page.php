<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("donnees/constante_reseau.php");

// Sécurité: utilisateur connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$message = '';

if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
    $reseaux = array();
} else {
    $reseaux = Manager::prepare_query(
        "SELECT * FROM reseau WHERE id_aep = ? ORDER BY date_creation DESC, nom ASC",
        array($aepId)
    )->fetchAll();
    $constanteActive = ConstanteReseau::getConstanteActive($aepId);
    $tarifActif = $constanteActive ? $constanteActive->fetch() : false;
}
?>

<div class="container-fluid mt-5">
    <h2 class="mb-4">Gestion des Réseaux</h2>
    <?php echo $message; ?>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>

    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-diagram-3"></i> Réseaux</h4>
            <div class="btn-group">
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addReseauModal"
                    <?php echo $aepId ? '' : 'disabled'; ?>>
                    <i class="bi bi-plus-circle"></i> Nouveau Réseau
                </button>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addMoisModal"
                    <?php echo $aepId ? '' : 'disabled'; ?>>
                    <i class="bi bi-calendar-plus"></i> Nouveau Mois
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($reseaux) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Nom</th>
                                <th>Abréviation</th>
                                <th>Date de création</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reseaux as $reseau): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($reseau['nom']); ?></strong></td>
                                    <td><span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($reseau['abreviation']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($reseau['date_creation']); ?></td>
                                    <td><?php echo htmlspecialchars(substr(isset($reseau['description_reseau']) ? $reseau['description_reseau'] : '', 0, 80)); ?><?php echo strlen(isset($reseau['description_reseau']) ? $reseau['description_reseau'] : '') > 80 ? '…' : ''; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?page=reseau_detail&id=<?php echo $reseau['id']; ?>"
                                                class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i> Détails</a>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                data-bs-target="#editReseauModal_<?php echo $reseau['id']; ?>">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </button>
                                            <form method="post" action="traitement/reseau_t.php"
                                                onsubmit="return confirm('Supprimer ce réseau et toutes ses données associées ?');"
                                                class="d-inline">
                                                <input type="hidden" name="action" value="delete_reseau">
                                                <input type="hidden" name="reseau_id" value="<?php echo $reseau['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i
                                                        class="bi bi-trash"></i> Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal édition réseau -->
                                <div class="modal fade" id="editReseauModal_<?php echo $reseau['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Modifier le Réseau</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post" action="traitement/reseau_t.php">
                                                <input type="hidden" name="action" value="update_reseau">
                                                <input type="hidden" name="reseau_id" value="<?php echo $reseau['id']; ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nom *</label>
                                                        <input type="text" class="form-control" name="nom"
                                                            value="<?php echo htmlspecialchars($reseau['nom']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Abréviation</label>
                                                        <input type="text" class="form-control" name="abreviation"
                                                            value="<?php echo htmlspecialchars($reseau['abreviation']); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Date de création *</label>
                                                        <input type="date" class="form-control" name="date_creation"
                                                            value="<?php echo htmlspecialchars($reseau['date_creation']); ?>"
                                                            required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea class="form-control"
                                                            name="description_reseau"><?php echo htmlspecialchars(isset($reseau['description_reseau']) ? $reseau['description_reseau'] : ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-info-circle fs-1"></i>
                    <p class="mt-2">Aucun réseau pour cet AEP</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal création mois de facturation -->
    <div class="modal fade" id="addMoisModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau Mois de Facturation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="traitement/mois_facturation_t.php">
                    <input type="hidden" name="action" value="create_month_auto">
                    <input type="hidden" name="id_constante"
                        value="<?php echo $tarifActif ? (int) $tarifActif['id'] : 0; ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Mois *</label>
                                <input type="month" class="form-control" name="mois" value="<?php echo date('Y-m'); ?>"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date de facturation *</label>
                                <input type="date" class="form-control" name="date_facturation"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date de dépôt *</label>
                                <input type="date" class="form-control" name="date_depot"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"
                                    placeholder="Description du mois..."></textarea>
                            </div>
                        </div>

                        <div class="mt-3 p-3 bg-light border rounded">
                            <strong>Tarif appliqué</strong>
                            <?php if ($tarifActif): ?>
                                <ul class="mb-0">
                                    <li>Prix eau: <?php echo htmlspecialchars($tarifActif['prix_metre_cube_eau']); ?>
                                        FCFA/m³</li>
                                    <li>Entretien compteur:
                                        <?php echo htmlspecialchars($tarifActif['prix_entretient_compteur']); ?> FCFA/mois
                                    </li>
                                    <li>TVA: <?php echo htmlspecialchars($tarifActif['prix_tva']); ?> %</li>
                                    <li>Créé le: <?php echo htmlspecialchars($tarifActif['date_creation']); ?></li>
                                </ul>
                            <?php else: ?>
                                <div class="text-danger">Aucun tarif actif pour cet AEP. Veuillez activer un tarif.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" <?php echo $tarifActif ? '' : 'disabled'; ?>>Créer
                            le mois</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal création réseau -->
    <div class="modal fade" id="addReseauModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau Réseau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="traitement/reseau_t.php">
                    <input type="hidden" name="action" value="add_reseau">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Abréviation</label>
                            <input type="text" class="form-control" name="abreviation">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date de création *</label>
                            <input type="date" class="form-control" name="date_creation"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description_reseau"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>