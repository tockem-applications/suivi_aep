<?php

@include_once("traitement/ressource_humaine_t.php");
@include_once("traitement/ressource_materielle_t.php");

$rhs = RessourceHumaine_t::getAll();
$rms = RessourceMaterielle_t::getAll();

?>
<div class="container-fluid mt-3">
    <h2 class="h2">Gestion des ressources</h2>
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="rh-tab" data-bs-toggle="tab" data-bs-target="#rh" type="button"
                role="tab">Humaines</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="rm-tab" data-bs-toggle="tab" data-bs-target="#rm" type="button"
                role="tab">Matérielles</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="rh" role="tabpanel" aria-labelledby="rh-tab">
            <div class="d-flex justify-content-end my-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_rh">Ajouter</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Fonction</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Coût/h</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rhs as $r) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['nom']); ?></td>
                                <td><?php echo htmlspecialchars($r['fonction']); ?></td>
                                <td><?php echo htmlspecialchars($r['telephone']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['statut']); ?></span>
                                </td>
                                <td><?php echo number_format((float) $r['cout_horaire'], 2, ',', ' '); ?></td>
                                <td class="text-end">
                                    <form method="post" action="traitement/ressource_humaine_t.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Supprimer ?');">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="rm" role="tabpanel" aria-labelledby="rm-tab">
            <div class="d-flex justify-content-end my-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_rm">Ajouter</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Catégorie</th>
                            <th>Quantité</th>
                            <th>Unité</th>
                            <th>Coût unitaire</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rms as $m) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['libelle']); ?></td>
                                <td><?php echo htmlspecialchars($m['categorie']); ?></td>
                                <td><?php echo number_format((float) $m['quantite_disponible'], 2, ',', ' '); ?>/<?php echo number_format((float) $m['quantite_totale'], 2, ',', ' '); ?>
                                </td>
                                <td><?php echo htmlspecialchars($m['unite']); ?></td>
                                <td><?php echo number_format((float) $m['cout_unitaire'], 2, ',', ' '); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($m['statut']); ?></span>
                                </td>
                                <td class="text-end">
                                    <form method="post" action="traitement/ressource_materielle_t.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Supprimer ?');">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal RH -->
    <div class="modal fade" id="modal_rh" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="traitement/ressource_humaine_t.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter une ressource humaine</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-2"><label class="form-label">Nom</label><input name="nom" class="form-control"
                                required></div>
                        <div class="mb-2"><label class="form-label">Fonction</label><input name="fonction"
                                class="form-control"></div>
                        <div class="mb-2"><label class="form-label">Téléphone</label><input name="telephone"
                                class="form-control"></div>
                        <div class="mb-2"><label class="form-label">Compétences</label><textarea name="competences"
                                class="form-control"></textarea></div>
                        <div class="mb-2"><label class="form-label">Coût horaire</label><input name="cout_horaire"
                                type="number" step="0.01" class="form-control" value="0"></div>
                        <div class="mb-2"><label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="disponible">disponible</option>
                                <option value="occupe">occupe</option>
                                <option value="indisponible">indisponible</option>
                            </select>
                        </div>
                        <input type="hidden" name="actif" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal RM -->
    <div class="modal fade" id="modal_rm" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="traitement/ressource_materielle_t.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter une ressource matérielle</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-2"><label class="form-label">Libellé</label><input name="libelle"
                                class="form-control" required></div>
                        <div class="mb-2"><label class="form-label">Catégorie</label><input name="categorie"
                                class="form-control"></div>
                        <div class="mb-2"><label class="form-label">Référence</label><input name="reference"
                                class="form-control"></div>
                        <div class="mb-2"><label class="form-label">Quantité totale</label><input name="quantite_totale"
                                type="number" step="0.01" class="form-control" value="0"></div>
                        <div class="mb-2"><label class="form-label">Quantité disponible</label><input
                                name="quantite_disponible" type="number" step="0.01" class="form-control" value="0">
                        </div>
                        <div class="mb-2"><label class="form-label">Unité</label><input name="unite"
                                class="form-control" value="u"></div>
                        <div class="mb-2"><label class="form-label">Coût unitaire</label><input name="cout_unitaire"
                                type="number" step="0.01" class="form-control" value="0"></div>
                        <div class="mb-2"><label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="disponible">disponible</option>
                                <option value="occupe">occupe</option>
                                <option value="panne">panne</option>
                                <option value="hors_service">hors_service</option>
                            </select>
                        </div>
                        <input type="hidden" name="actif" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>