<?php

function renderReseauUpdateModalComponent($reseau, $reseaux, $modalId)
{
    $reseauId = isset($reseau['id']) ? (int) $reseau['id'] : 0;
    $nom = isset($reseau['nom']) ? $reseau['nom'] : '';
    $abreviation = isset($reseau['abreviation']) ? $reseau['abreviation'] : '';
    $dateCreation = isset($reseau['date_creation']) ? $reseau['date_creation'] : date('Y-m-d');
    $description = isset($reseau['description_reseau']) ? $reseau['description_reseau'] : '';
    $idReseauParent = !empty($reseau['id_reseau_parent']) ? (int) $reseau['id_reseau_parent'] : 0;
    ?>
    <div class="modal fade" id="<?php echo htmlspecialchars($modalId); ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content form-card">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier le réseau</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="traitement/reseau_t.php">
                    <input type="hidden" name="action" value="update_reseau">
                    <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nom du réseau *</label>
                                <input type="text" class="form-control" name="nom" value="<?php echo htmlspecialchars($nom); ?>"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Abréviation</label>
                                <input type="text" class="form-control" name="abreviation"
                                    value="<?php echo htmlspecialchars($abreviation); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de création *</label>
                                <input type="date" class="form-control" name="date_creation"
                                    value="<?php echo htmlspecialchars($dateCreation); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Réseau parent (optionnel)</label>
                                <select name="id_reseau_parent" class="form-select">
                                    <option value="">Aucun (réseau racine)</option>
                                    <?php foreach ($reseaux as $candidate): ?>
                                        <?php if ((int) $candidate['id'] !== $reseauId): ?>
                                            <option value="<?php echo (int) $candidate['id']; ?>"
                                                <?php echo ($idReseauParent === (int) $candidate['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($candidate['nom']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" rows="3"
                                    name="description_reseau"><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function renderReseauDeleteModalComponent($reseau, $modalId, $nbAbonnes, $nbEnfants)
{
    $reseauId = isset($reseau['id']) ? (int) $reseau['id'] : 0;
    $nom = isset($reseau['nom']) ? $reseau['nom'] : '';
    $hasAbonnes = (int) $nbAbonnes > 0;
    $hasChildren = (int) $nbEnfants > 0;
    $deleteDisabled = $hasAbonnes || $hasChildren;
    ?>
    <div class="modal fade" id="<?php echo htmlspecialchars($modalId); ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content form-card">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Supprimer le réseau</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($deleteDisabled): ?>
                        <div class="alert alert-warning mb-3">
                            <?php if ($hasAbonnes): ?>
                                Ce réseau contient <strong><?php echo (int) $nbAbonnes; ?></strong> abonné(s).
                            <?php endif; ?>
                            <?php if ($hasAbonnes && $hasChildren): ?>
                                <br>
                            <?php endif; ?>
                            <?php if ($hasChildren): ?>
                                Ce réseau a <strong><?php echo (int) $nbEnfants; ?></strong> réseau(x) fils.
                            <?php endif; ?>
                            <br>La suppression est désactivée.
                        </div>
                    <?php else: ?>
                        <p class="mb-0">
                            Confirmer la suppression du réseau <strong><?php echo htmlspecialchars($nom); ?></strong> ?
                        </p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="post" action="traitement/reseau_t.php" class="d-inline">
                        <input type="hidden" name="action" value="delete_reseau">
                        <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                        <button type="submit" class="btn btn-danger" <?php echo $deleteDisabled ? 'disabled' : ''; ?>>
                            <i class="bi bi-trash me-1"></i>Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}
