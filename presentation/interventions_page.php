<?php

@include_once("traitement/intervention_t.php");
@include_once("traitement/ressource_humaine_t.php");
@include_once("traitement/ressource_materielle_t.php");

$interventions = Intervention_t::getAll();
$rhs = RessourceHumaine_t::getAll();
$rms = RessourceMaterielle_t::getAll();

?>
<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h2">Interventions</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_interv">+ Nouvelle
            intervention</button>
    </div>

    <div class="table-responsive mt-3">
        <table class="table table-sm table-striped align-middle">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Coût estimé</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interventions as $i) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($i['titre']); ?></td>
                        <td><?php echo htmlspecialchars($i['type']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($i['statut']); ?></span></td>
                        <td><?php echo htmlspecialchars($i['date_debut_prevue']); ?></td>
                        <td><?php echo htmlspecialchars($i['date_fin_prevue']); ?></td>
                        <td><?php echo number_format((float) $i['cout_estime'], 2, ',', ' '); ?></td>
                        <td class="text-end">
                            <form method="post" action="traitement/intervention_t.php" class="d-inline">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?php echo (int) $i['id']; ?>">
                                <select name="statut" class="form-select form-select-sm d-inline w-auto">
                                    <option value="planifiee" <?php echo $i['statut'] == 'planifiee' ? 'selected' : ''; ?>>
                                        planifiée</option>
                                    <option value="en_cours" <?php echo $i['statut'] == 'en_cours' ? 'selected' : ''; ?>>en cours
                                    </option>
                                    <option value="terminee" <?php echo $i['statut'] == 'terminee' ? 'selected' : ''; ?>>terminée
                                    </option>
                                    <option value="annulee" <?php echo $i['statut'] == 'annulee' ? 'selected' : ''; ?>>annulée
                                    </option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary ms-1">OK</button>
                            </form>
                            <form method="post" action="traitement/intervention_t.php" class="d-inline ms-2">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $i['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Supprimer ?');">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <!-- Modal création intervention -->
    <div class="modal fade" id="modal_interv" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="traitement/intervention_t.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Nouvelle intervention</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-2"><label class="form-label">Titre</label><input name="titre"
                                        class="form-control" required></div>
                                <div class="mb-2"><label class="form-label">Type</label><input name="type"
                                        class="form-control" placeholder="Ex: Réparation fuite" required></div>
                                <div class="mb-2"><label class="form-label">Localisation</label><input
                                        name="localisation" class="form-control"></div>
                                <div class="mb-2"><label class="form-label">Dates prévues</label>
                                    <div class="d-flex gap-2">
                                        <input type="datetime-local" name="date_debut_prevue" class="form-control">
                                        <input type="datetime-local" name="date_fin_prevue" class="form-control">
                                    </div>
                                </div>
                                <div class="mb-2"><label class="form-label">Description</label><textarea
                                        name="description" class="form-control"></textarea></div>
                                <div class="mb-2"><label class="form-label">Coût estimé</label><input type="number"
                                        step="0.01" name="cout_estime" class="form-control" value="0"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label">Ressources humaines</label>
                                    <div class="border p-2 rounded" style="max-height: 220px; overflow:auto;">
                                        <?php foreach ($rhs as $r) {
                                            $rid = (int) $r['id']; ?>
                                            <div class="d-flex align-items-center mb-1">
                                                <input type="checkbox" class="form-check-input me-2" name="rh_ids[]"
                                                    value="<?php echo $rid; ?>">
                                                <span class="me-2"><?php echo htmlspecialchars($r['nom']); ?>
                                                    (<?php echo number_format((float) $r['cout_horaire'], 2, ',', ' '); ?>/h)</span>
                                                <input type="number" step="0.1" min="0"
                                                    name="heures_prevues[<?php echo $rid; ?>]"
                                                    class="form-control form-control-sm w-25" placeholder="h">
                                                <input type="hidden" name="cout_horaire[<?php echo $rid; ?>]"
                                                    value="<?php echo (float) $r['cout_horaire']; ?>">
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Ressources matérielles</label>
                                    <div class="border p-2 rounded" style="max-height: 220px; overflow:auto;">
                                        <?php foreach ($rms as $m) {
                                            $mid = (int) $m['id']; ?>
                                            <div class="d-flex align-items-center mb-1">
                                                <input type="checkbox" class="form-check-input me-2" name="rm_ids[]"
                                                    value="<?php echo $mid; ?>">
                                                <span class="me-2"><?php echo htmlspecialchars($m['libelle']); ?>
                                                    (<?php echo number_format((float) $m['cout_unitaire'], 2, ',', ' '); ?>/<?php echo htmlspecialchars($m['unite']); ?>)</span>
                                                <input type="number" step="0.01" min="0"
                                                    name="quantite_prevue[<?php echo $mid; ?>]"
                                                    class="form-control form-control-sm w-25" placeholder="Qté">
                                                <input type="hidden" name="cout_unitaire[<?php echo $mid; ?>]"
                                                    value="<?php echo (float) $m['cout_unitaire']; ?>">
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
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