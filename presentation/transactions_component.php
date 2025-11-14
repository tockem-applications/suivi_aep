<?php
display_printing_button('', 'Imprimer la liste des entree sortie')

    ?>
<div class="container mt-4" id="a_imprimer">


    <h2 class="mb-3 text-primary fw-bold">Flux Financiers</h2>
    <div class="row">
        <div class="col-12">

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <!-- Bouton pour ouvrir le modal de création -->
                    <button type="button" class="btn btn-primary mb-3 shadow-sm" data-bs-toggle="modal"
                        data-bs-target="#createFluxModal">
                        <i class="fas fa-plus me-2"></i>Ajouter un flux
                    </button>



                    <!-- Formulaire de recherche -->
                    <form action="?page=transaction" method="post" class="mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="month" name="mois" class="form-control shadow-sm"
                                        value="<?php echo isset($_POST['mois']) ? htmlspecialchars($_POST['mois']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-filter"></i></span>
                                    <select name="type" class="form-select shadow-sm">
                                        <option value="">Tous les types</option>
                                        <option value="sortie" <?php echo isset($_POST['type']) && $_POST['type'] === 'sortie' ? 'selected' : ''; ?>>Sortie</option>
                                        <option value="entree" <?php echo isset($_POST['type']) && $_POST['type'] === 'entree' ? 'selected' : ''; ?>>Entrée</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="fas fa-money-bill-wave"></i></span>
                                    <input type="number" name="montant_min" class="form-control shadow-sm"
                                        placeholder="Montant min"
                                        value="<?php echo isset($_POST['montant_min']) ? htmlspecialchars($_POST['montant_min']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary shadow-sm"><i
                                        class="fas fa-search me-2"></i>Rechercher</button>
                            </div>
                            <div class="col-auto">
                                <a href="?list=transaction" class="btn btn-warning shadow-sm"><i
                                        class="fas fa-eraser me-2"></i>Vider</a>
                            </div>
                        </div>
                    </form>

                    <!-- Tableau des flux financiers -->
                    <div class="table-responsive">
                        <table class="table_searching table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
<!--                                    <th>ID</th>-->
                                    <th>Date</th>
                                    <th>Mois</th>
                                    <th>Libellé</th>
                                    <th>Prix</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                @include_once("../donnees/flux_financier.php");
                                @include_once("donnees/flux_financier.php");

                                $mois = isset($_POST['mois']) ? $_POST['mois'] : '';
                                $type = isset($_POST['type']) ? $_POST['type'] : '';
                                $montant_min = isset($_POST['montant_min']) ? (int) $_POST['montant_min'] : 0;
                                $id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : 1;

                                // Récupérer les flux financiers
                                $res = FluxFinancier::getFinanceData($mois, $type, $montant_min, $id_aep);
                                $fluxFinanciers = $res->fetchAll(PDO::FETCH_ASSOC);

                                // Récupérer tous les versements
                                $resVersements = FluxFinancier::getAllVersements($id_aep);
                                $versements = $resVersements->fetchAll(PDO::FETCH_ASSOC);

                                // Filtrer les versements selon les critères de recherche
                                $versementsFiltres = array();
                                foreach ($versements as $versement) {
                                    $moisMatch = empty($mois) || strpos($versement['mois'], $mois) !== false;
                                    $typeMatch = empty($type) || $versement['type'] === $type;
                                    $montantMatch = $versement['prix'] >= $montant_min;

                                    if ($moisMatch && $typeMatch && $montantMatch) {
                                        $versementsFiltres[] = $versement;
                                    }

                                }

                                // Combiner les flux financiers et les versements
                                $tousFlux = array_merge($fluxFinanciers, $versementsFiltres);

                                // Trier par date décroissante
                                usort($tousFlux, function ($a, $b) {
                                    return strtotime($b['date']) - strtotime($a['date']);
                                });

                                create_csv_exportation_button(
                                    $tousFlux,
                                    'flux_financier-' . $_SESSION["libele_aep"] . '-' . $mois . '.csv',
                                    "Vous allez exporter les donnees financiere de " . $mois . 'au format csv'
                                );
                                $somme_algebrique = 0;
                                $somme_sortie = 0;
                                $somme_entree = 0;

                                foreach ($tousFlux as $flux) {
//                                    var_dump($flux);
                                    $no_actions = false;
                                    if(isset($flux['actions'])){
                                        $no_actions = true;
                                    }
                                    $class = $flux['type'] === 'sortie' ? 'table-danger' : 'table-success';
                                    echo '<tr class="' . $class . '">';
//                                    echo '<td>' . htmlspecialchars($flux['id']) . '</td>';
                                    echo '<td>' . htmlspecialchars($flux['date']) . '</td>';
                                    echo '<td>' . htmlspecialchars(getLetterMonth($flux['mois'])) . '</td>';
                                    echo '<td data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($flux['description']) . '">' . htmlspecialchars($flux['libele']) . '</td>';
                                    echo '<td class="text-end">' . htmlspecialchars($flux['prix']) . ' FCFA</td>';
                                    echo '<td class="text-center">' . htmlspecialchars($flux['type']) . '</td>';
                                    echo '<td>';
                                    // Afficher les boutons d'action seulement pour les flux financiers (pas pour les paiements)
                                    if (!$no_actions) {
                                        echo '<button class="btn btn-danger btn-sm shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#deleteFluxModal' . $flux['id'] . '"><i class="bi bi-trash"></i></button>';
                                        echo '<button class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#editFluxModal' . $flux['id'] . '"><i class="bi bi-pencil"></i></button>';
                                    } else {
                                        echo '<span class="text-muted small">Paiement</span>';
                                    }
                                    echo '</td>';
                                    echo '</tr>';
                                    $somme_algebrique += ($flux['type'] == 'sortie' ? -1 : 1) * (int) $flux['prix'];
                                    $somme_sortie += ($flux['type'] == 'sortie' ? -1 : 0) * (int) $flux['prix'];
                                    $somme_entree += ($flux['type'] == 'sortie' ? 0 : 1) * (int) $flux['prix'];
                                }
                                ?>
                                <tr><td colspan="6" class="table-dark"></td></tr>
                                <tr class="bg-light fw-bold ">
                                    <td colspan="3" class="px-5 text-danger">Total soties</td>
                                    <td colspan="1" class="text-end text-danger" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Cumule des sorties"><?php echo $somme_sortie; ?> FCFA</td>
                                    <td colspan="2" class="text-center">Sorties</td>
                                </tr>
                                <tr class="bg-light fw-bold" >
                                    <td colspan="3" class="px-5 text-success">Total entrées</td>
                                    <td colspan="1" class="text-end text-success"data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Cumule des entrées"><?php echo $somme_algebrique; ?> FCFA</td>
                                    <td colspan="2" class="text-center">Entrées</td>
                                </tr>

                                <tr class="table-dark fw-bold" >
                                    <td colspan="3" class="px-5">Total</td>
                                    <td colspan="4" class="text-center px-5" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Cumule des entrées et des sorties"><?php echo $somme_entree; ?> FCFA</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de création -->
    <div class="modal fade" id="createFluxModal" tabindex="-1" aria-labelledby="createFluxModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createFluxModalLabel">Ajouter un nouveau flux</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="traitement/flux_financier_t.php?ajout=true" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label fw-bold">Date <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control shadow-sm" id="date" name="date"
                                        value="2025-05-27" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="mois" class="form-label fw-bold">Mois <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-calendar-month"></i></span>
                                    <input type="month" class="form-control shadow-sm" id="mois" name="mois"
                                        value="2025-05" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="libele" class="form-label fw-bold">Libellé <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-tag"></i></span>
                                    <input type="text" class="form-control shadow-sm" id="libele" name="libele"
                                        placeholder="Ex: Paiement fournisseur" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prix" class="form-label fw-bold">Prix (FCFA) <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="fas fa-money-bill-wave"></i></span>
                                    <input type="number" class="form-control shadow-sm" id="prix" name="prix"
                                        placeholder="Ex: 50000" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label fw-bold">Type <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-exchange-alt"></i></span>
                                <select class="form-select shadow-sm" id="type" name="type" required>
                                    <option value="sortie" selected>Sortie</option>
                                    <option value="entree">Entrée</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold">Description</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-comment"></i></span>
                                <textarea class="form-control shadow-sm" id="description" name="description" rows="3"
                                    placeholder="Détails supplémentaires..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 shadow-sm">Ajouter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de modification (dynamique par flux) -->
    <?php foreach ($tousFlux as $flux): ?>
        <?php if (strpos($flux['libele'], 'Paiement -') === false): ?>
            <div class="modal fade" id="editFluxModal<?php echo $flux['id']; ?>" tabindex="-1"
                aria-labelledby="editFluxModalLabel<?php echo $flux['id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="editFluxModalLabel<?php echo $flux['id']; ?>">Modifier le flux
                                <?php echo htmlspecialchars($flux['libele']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form
                                action="traitement/flux_financier_t.php?update=true&id_updates_flux=<?php echo $flux['id']; ?>"
                                method="post">
                                <input type="hidden" name="id" value="<?php echo $flux['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="date_<?php echo $flux['id']; ?>" class="form-label fw-bold">Date <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                                            <input type="date" class="form-control shadow-sm"
                                                id="date_<?php echo $flux['id']; ?>" name="date"
                                                value="<?php echo htmlspecialchars($flux['date']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="mois_<?php echo $flux['id']; ?>" class="form-label fw-bold">Mois <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-calendar-month"></i></span>
                                            <input type="month" class="form-control shadow-sm"
                                                id="mois_<?php echo $flux['id']; ?>" name="mois"
                                                value="<?php echo htmlspecialchars($flux['mois']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="libele_<?php echo $flux['id']; ?>" class="form-label fw-bold">Libellé <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-tag"></i></span>
                                            <input type="text" class="form-control shadow-sm"
                                                id="libele_<?php echo $flux['id']; ?>" name="libele"
                                                value="<?php echo htmlspecialchars($flux['libele']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="prix_<?php echo $flux['id']; ?>" class="form-label fw-bold">Prix (FCFA)
                                            <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                    class="fas fa-money-bill-wave"></i></span>
                                            <input type="number" class="form-control shadow-sm"
                                                id="prix_<?php echo $flux['id']; ?>" name="prix"
                                                value="<?php echo htmlspecialchars($flux['prix']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="type_<?php echo $flux['id']; ?>" class="form-label fw-bold">Type <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-exchange-alt"></i></span>
                                        <select class="form-select shadow-sm" id="type_<?php echo $flux['id']; ?>" name="type"
                                            required>
                                            <option value="entree" <?php echo $flux['type'] === 'entree' ? 'selected' : ''; ?>>
                                                Entrée</option>
                                            <option value="sortie" <?php echo $flux['type'] === 'sortie' ? 'selected' : ''; ?>>
                                                Sortie</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="description_<?php echo $flux['id']; ?>"
                                        class="form-label fw-bold">Description</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-comment"></i></span>
                                        <textarea class="form-control shadow-sm" id="description_<?php echo $flux['id']; ?>"
                                            name="description"
                                            rows="3"><?php echo htmlspecialchars($flux['description']); ?></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100 shadow-sm">Modifier</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de suppression -->
            <div class="modal fade" id="deleteFluxModal<?php echo $flux['id']; ?>" tabindex="-1"
                aria-labelledby="deleteFluxModalLabel<?php echo $flux['id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteFluxModalLabel<?php echo $flux['id']; ?>">Supprimer le flux
                                <?php echo htmlspecialchars($flux['libele']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted">Voulez-vous vraiment supprimer ce flux de type
                                <strong><?php echo htmlspecialchars($flux['type']); ?></strong> ?
                            </p>
                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>Montant</strong></td>
                                    <td><?php echo htmlspecialchars($flux['prix']); ?> FCFA</td>
                                </tr>
                                <tr>
                                    <td><strong>Date</strong></td>
                                    <td><?php echo htmlspecialchars(strftime('%d %B %Y', strtotime($flux['date']))); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <strong>Description</strong><br><?php echo htmlspecialchars($flux['description']); ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary shadow-sm" data-bs-dismiss="modal">Annuler</button>
                            <a href="traitement/flux_financier_t.php?delete=true&id_flux=<?php echo $flux['id']; ?>"
                                class="btn btn-danger shadow-sm">Confirmer la suppression</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>