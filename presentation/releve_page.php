<div class="container-fluid">
    <div class="row">
        <!-- Menu à gauche -->
        <div class="col-md-3 bg-light sidebar p-3">
            <div class="d-flex justify-content-between"><h4 class="mb-3 text-primary fw-bold">Mois de Facturation</h4>
                <div class="d-flex">
                    <button type="button" class="btn btn-primary mb-3 shadow-sm me-1" data-bs-toggle="modal"
                            data-bs-target="#importIndexModal">
                        <i class="bi bi-file-earmark-arrow-up"></i>
                    </button>
                    <a href="?page=download_index&action=export_index" type="button"
                       class="btn btn-success mb-3 shadow-sm" data-bs-toggle="tooltip" data-bs-placement="top"
                       data-bs-title="exporter les index compteurs du derniers mois"
                    >
                        <i class="bi bi-file-earmark-arrow-down"></i>
                    </a>
                    <!--                <li><a class="dropdown-item" href="" target="_blank">Exporter vers mobile</a></li>-->
                </div>
            </div>
            <ul class="list-group">
                <?php
                $id = '';
                $mois = '';
                @include_once("../donnees/mois_facturation.php");
                @include_once("donnees/mois_facturation.php");
                $result = MoisFacturation::getAllMois('', '', $_SESSION['id_aep']); // Assumer que cette méthode existe
//                if(!count($result->fetchAll()))
//                    exit();
//                var_dump(count($result->fetchAll()));
                $selected_moi_id = '';
                $first_mois_selected = true;

                if (isset($_GET['mois_facturation'])) {
                    $selected_moi_id = $_GET['mois_facturation'];
                }

                while ($row = $result->fetch()) {
                    $mois_selected = '';
                    if ($selected_moi_id != '') {
                        if ($selected_moi_id == $row['id']) {
                            $mois_selected = 'bg-success-subtle';
                            $id = $row['id'];
                            $mois = htmlspecialchars(getLetterMonth($row['mois']));
                        }
                    } else {
                        if ($first_mois_selected) {
                            $mois_selected = 'bg-success-subtle';
                            $first_mois_selected = false;
                            $id = $row['id'];
                            $mois = htmlspecialchars(getLetterMonth($row['mois']));
                        }
                    }
                    $mois_lettre = getLetterMonth($row['mois']);
                    echo '<a href="?page=releves&mois_facturation=' . $row['id'] . '" class="text-decoration-none text-dark p-0 m-0"> <li class="list-group-item ' . $mois_selected . '">' . htmlspecialchars($mois_lettre) . '</li> </a>';
                }
                ?>
            </ul>
        </div>

        <!-- Contenu principal -->
        <div class="col-md-9 p-4 overflow-y-auto">
            <div class="d-flex justify-content-between"><h2 class="mb-4 text-primary fw-bold">Relevés d'index
                    compteur</h2>
                <a href="#" class="text-decoration-none text-primary" data-bs-toggle="modal"
                   data-bs-target="#distributionModal<?php echo $id; ?>">
                    <i class="fas fa-calendar-check me-1"></i>Facturer
                </a></div>

            <?php
            @include_once("../traitement/facture_t.php");
            @include_once("traitement/facture_t.php");
            $id_current_mois = Facture_t::getTableauFactureactiveForReleve(isset($_GET['mois_facturation']) ? $_GET["mois_facturation"] : 0, $titre = '');

            include('traitement/constante_reseau_t.php');
            $constante_reseau = ConstanteReseau_t::getConstanteActive();
            if ($constante_reseau != null) {
                $constante_reseau_id = $constante_reseau['id'];
                $prix_metre_cube_eau = $constante_reseau['prix_metre_cube_eau'];
                $prix_entretient_compteur = $constante_reseau['prix_entretient_compteur'];
                $prix_tva = $constante_reseau['prix_tva'];
                $date_creation = $constante_reseau['date_creation'];
                $constante_reseau_idest_actif = $constante_reseau['est_actif'];
                $constante_reseau_iddescription = $constante_reseau['description'];
            }

            $curentMoisQuery = MoisFacturation::getMoisById($id_current_mois);
            $currentMoisData = $curentMoisQuery->fetchAll();
//            var_dump($currentMoisData, $selected_moi_id);
            ?>

            <div class="modal fade" id="distributionModal<?php echo $id; ?>" tabindex="-1"
                 aria-labelledby="distributionModalLabel<?php echo $id; ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="distributionModalLabel<?php echo $id; ?>">Distribution des
                                factures - <?php echo htmlspecialchars($mois); ?></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form action="traitement/mois_facturation_t.php?get_mois_facturation=true" method="post">
                                <div class="mb-3">
                                    <label for="distribution_date_<?php echo $id; ?>" class="form-label fw-bold">Date de
                                        Releve <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <!--                                        <input type="hidden" name="date_releve_mois_facturation" value="date_releve_mois_facturation_-->
                                        <?php //echo htmlspecialchars($id)?><!--">-->
                                        <span class="input-group-text bg-light"><i
                                                    class="fas fa-calendar-day"></i></span>
                                        <input type="date" class=
                                        "form-control shadow-sm" value="<?php echo isset($currentMoisData[0]['date_releve'])? htmlspecialchars($currentMoisData[0]['date_releve']):''; ?>"
                                               id="releve_date_<?php echo $id; ?>" name="date_releve" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="distribution_date_<?php echo $id; ?>" class="form-label fw-bold">Date de
                                        distribution <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="hidden" name="mois_facturation"
                                               value="<?php echo htmlspecialchars($id) ?>">
                                        <span class="input-group-text bg-light"><i
                                                    class="fas fa-calendar-day"></i></span>
                                        <input type="date" class="form-control shadow-sm" value="<?php echo isset($currentMoisData[0]['date_depot'])? htmlspecialchars($currentMoisData[0]['date_depot']):''; ?>"
                                               id="distribution_date_<?php echo $id; ?>" name="date_depot"
                                               required>
                                    </div>
                                </div>
<!--                                --><?php //echo htmlspecialchars($id); ?>
                                <input type="hidden" name="mois_id" value="<?php echo htmlspecialchars($id); ?>">
                                <button type="submit" class="btn btn-success w-100 shadow-sm">Facturer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bouton pour ouvrir le modal d'importation -->


            <!-- Modal d'importation des index -->
            <div class="modal fade" id="importIndexModal" tabindex="-1" aria-labelledby="importIndexModalLabel"
                 aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="importIndexModalLabel">Importer les index mensuels</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form action="traitement/mois_facturation_t.php?ajout=true" method="post"
                                  enctype="multipart/form-data">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="mois" class="form-label fw-bold">Mois <span
                                                    class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                        class="fas fa-calendar-month"></i></span>
                                            <input type="month" class="form-control shadow-sm" id="mois" name="mois"
                                                   value="<?php echo date('Y-m'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fichier_index" class="form-label fw-bold">Fichier des index <span
                                                    class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                        class="fas fa-file-upload"></i></span>
                                            <input type="file" class="form-control shadow-sm" id="fichier_index"
                                                   name="fichier_index" accept=".csv,.xls,.xlsx" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <label for="description" class="form-label fw-bold">Description du réseau</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i
                                                    class="fas fa-info-circle"></i></span>
                                        <textarea class="form-control shadow-sm" id="description" name="description"
                                                  rows="3" placeholder="Décrivez le réseau..."></textarea>
                                    </div>
                                </div>
                                <input type="hidden" name="id_constante"
                                       value="<?php echo isset($constante_reseau_id) ? $constante_reseau_id : ''; ?>">
                                <div class="mb-3">
                                    <a class="btn btn-outline-info btn-sm" data-bs-toggle="collapse"
                                       href="#tarifCollapse" role="button" aria-expanded="false"
                                       aria-controls="tarifCollapse">
                                        Voir les tarifs
                                    </a>
                                    <div class="collapse mt-2" id="tarifCollapse">
                                        <div class="card card-body bg-light">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between"><span>Prix de l'eau :</span><span><?php echo isset($prix_metre_cube_eau) ? $prix_metre_cube_eau . ' FCFA/m³' : 'N/A'; ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between"><span>Entretien compteur :</span><span><?php echo isset($prix_entretient_compteur) ? $prix_entretient_compteur . ' FCFA/mois' : 'N/A'; ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between">
                                                    <span>TVA :</span><span><?php echo isset($prix_tva) ? $prix_tva . ' %' : 'N/A'; ?></span>
                                                </li>
                                                <li class="list-group-item"><a href="?form=constante_reseau"
                                                                               class="text-decoration-none">Modifier les
                                                        tarifs</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 shadow-sm">Importer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>