<?php

@include_once("../donnees/Abones.php");
@include_once("donnees/Abones.php");
@include_once("../donnees/facture.php");
@include_once("donnees/facture.php");
@include_once("../donnees/mois_facturation.php");
@include_once("donnees/mois_facturation.php");


function moneyFormatter($montant)
{
    return number_format($montant, 0, ',', ' ');
}

function addDaysAndFormat($string_date, $days = 10)
{
    $date = new DateTime($string_date);
    $date->modify("+$days days");
    return $date->format('d/m/Y');
}


?>
<div class="container-fluid">
    <div class="row">
        <!-- Menu à gauche -->
        <div class="col-md-3 bg-light sidebar p-3">
            <div class="d-flex justify-content-between">
                <h4 class="mb-3 text-primary fw-bold">Mois de Facturation</h4>
                <div class="d-flex">
                    <div class="p-0 ms-1" data-bs-toggle="tooltip" data-bs-placement="top"
                         data-bs-title="Créer un nouveau mois de facturation et initialiser les index">
                        <button type="button" class="btn btn-warning mb-3 shadow-sm" data-bs-toggle="modal"
                                data-bs-target="#createMonthModal">
                            <i class="bi bi-calendar-plus"></i>
                        </button>
                    </div>
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
        <div id="a_imprimer" class="col-md-9 p-4 overflow-y-auto">
            <div class="d-flex justify-content-around">
                <h2 class="mb-4 text-primary fw-bold">Relevés d'index
                    compteur</h2>
                <a href="#" class="text-decoration-none text-primary" data-bs-toggle="modal"
                   data-bs-target="#distributionModal<?php echo $id; ?>">
                    <i class="bi bi-file-earmark-text me-1"> Facturer</i>
                    <!--                    <i class="bi bi-currency-dollar me-1"> Facturer</i>-->
                </a>
            </div>

            <div class="d-flex">
                <div class="p-0 m-0" data-bs-toggle="tooltip" data-bs-placement="top"
                     data-bs-title="Importer les index de votre machine dans l'application">
                    <button type="button" class="btn btn-primary mb-3 shadow-sm me-1" data-bs-toggle="modal"
                            data-bs-target="#importIndexModal">
                        <i class="bi bi-file-earmark-arrow-up"></i>
                    </button>
                </div>
                <div class="m-0 p-0"><a href="?page=download_index&action=export_index&id_mois=<?php echo $id ?>"
                                        type="button" class="btn btn-success mb-3 shadow-sm" data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="telecharger les index dans votre machine">
                        <i class="bi bi-file-earmark-arrow-down"></i>
                    </a>
                </div>
                <!--                <li><a class="dropdown-item" href="" target="_blank">Exporter vers mobile</a></li>-->
            </div>


            <?php
            $id_mois = isset($_GET['mois_facturation']) ? $_GET["mois_facturation"] : 0;
            $id_mois = $id;
            ob_start()
            ?>

            <?php
            $actions_button_html = ob_get_clean();

            //            <?php
            $mois_lettre = '';
            $id_mois_actif = MoisFacturation::getIdMoisFacturationActive($_SESSION['id_aep']);
            $editable = $id_mois == $id_mois_actif || $id_mois == 0;

            if ($id_mois == 0) {
                $mois_data = MoisFacturation::getMoisFacturationActive($_SESSION['id_aep'])->fetchAll();
                if (count($mois_data)) {
                    $id_mois = (int)$mois_data[0]['id'];
                    $mois_lettre = getLetterMonth($mois_data[0]['mois']);
                }
            } else {
                $mois_data = MoisFacturation::getOneById((int)$id_mois)->fetchAll();
                $mois_lettre = getLetterMonth($mois_data[0]['mois']);
            }

            $req2 = Facture::getMonthIndexes((int)$id_mois, $_SESSION['id_aep'])->fetchAll(PDO::FETCH_ASSOC);


            $titre_table = " $mois_lettre";

            ?>

            <style>
                /* Masquer les flèches d'incrémentation dans les navigateurs modernes */
                input[type='number']::-webkit-inner-spin-button,
                input[type='number']::-webkit-outer-spin-button {
                    -webkit-appearance: none;
                    margin: 0;
                }

                /* Masquer les flèches dans Firefox */
                input[type='number'] {
                    -moz-appearance: textfield;
                }
            </style>
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-graph-up"></i> <?php echo $titre_table; ?>
                        <span class="badge bg-secondary ms-2"><?php echo count($req2); ?></span>
                    </h4>
                    <?php create_csv_exportation_button($req2,
                        'Releve-' . $_SESSION["libele_aep"] . '-' . $mois_lettre . '.csv',
                        'Vous allez exporter les donnees de releve de ' . $mois_lettre . 'au format csv');
                    ?>
                </div>

                <table class="table table-striped table-bordered table-hover">
                    <thead>
                    </thead>
                    <tbody>
                    <tr>
                        <th>N° compteur</th>
                        <th>Nom et Prenom</th>
                        <th>Ancien index</th>
                        <th>nouvel index</th>
                    </tr>
                    <?php
                    // Intégration du code de creerLigneTableauReleveManuelle
                    foreach ($req2 as $data) {
                        $circle_bg_color = '';
                        if ((float)$data['ancien_index'] > (float)$data['nouvel_index'])
                            $circle_bg_color = 'bg-danger';
                        elseif (((float)$data['ancien_index'] == (float)$data['nouvel_index']))
                            $circle_bg_color = 'bg-warning';
                        elseif ((float)$data['ancien_index'] < (float)$data['nouvel_index'])
                            $circle_bg_color = 'bg-success';
                        ?>

                        <tr class="p-0 m-0">
                            <td><?php echo $data['numero_compteur'] ?> </td>
                            <td> <?php echo $data['nom'] ?> </td>
                            <td id="ancien_index<?php echo $data['id'] ?>"> <?php echo $data['ancien_index'] ?></td>
                            <td class="w-auto d-flex justify-content-between align-items-center">
                                <input type="number" class="form-control w-50 border-0 p-0 ps-2 "
                                       style="background-color: rgba(0, 0, 0, 0)"
                                       id="nouvel_index<?php echo $data['id'] ?>"
                                       min="<?php echo $data['ancien_index'] ?>"
                                       onclick="this.select()"

                                       onchange="handleReleve(this.value, <?php echo $data['id'] ?>, <?php echo $data['id_compteur'] ?>)"
                                       step="0.01"
                                       value="<?php echo((float)$data['nouvel_index'] == 0 || (float)$data['nouvel_index'] == (float)$data['ancien_index'] ? '' : $data['nouvel_index']) ?>"
                                       aria-label="Sizing example input" aria-describedby="inputGroup-sizing-lg">
                                <div class="color-circle <?php echo $circle_bg_color ?>"></div>
                                <input type="hidden" value="<?php echo $data['nouvel_index'] ?>"
                                       id="ex_nouvel_index<?php echo $data['id'] ?>">
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <?php
            echo '<a class=dropdown-item" href="?form=abone"> Ajouter un aboné</a>';
            ?>
            <br>

            <?php
            $id_current_mois = $id_mois;


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
                                        <?php //echo htmlspecialchars($id) ?><!--">-->
                                        <span class="input-group-text bg-light"><i
                                                    class="fas fa-calendar-day"></i></span>
                                        <input type="date" class="form-control shadow-sm"
                                               value="<?php echo isset($currentMoisData[0]['date_releve']) ? htmlspecialchars($currentMoisData[0]['date_releve']) : ''; ?>"
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
                                        <input type="date" class="form-control shadow-sm"
                                               value="<?php echo isset($currentMoisData[0]['date_depot']) ? htmlspecialchars($currentMoisData[0]['date_depot']) : ''; ?>"
                                               id="distribution_date_<?php echo $id; ?>" name="date_depot" required>
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
                            <form
                                    action="traitement/mois_facturation_t.php?update_indexes_mois=true&id_mois=<?php echo $id; ?>"
                                    method="post" enctype="multipart/form-data">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="fichier_index" class="form-label fw-bold">Fichier des index <span
                                                    class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                        class="fas fa-file-upload"></i></span>
                                            <input type="file" class="form-control shadow-sm" id="fichier_index"
                                                   name="fichier_index" accept=".json,.csv" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aperçu du fichier importé -->
                                <div id="preview_section" class="mt-3 d-none">
                                    <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
                                        <div>
                                            <label class="form-label mb-1">Filtrer</label>
                                            <select id="preview_filter" class="form-select form-select-sm">
                                                <option value="all">Tous</option>
                                                <option value="ok">Coherent (nouvel ≥ ancien)</option>
                                                <option value="identique">Identique (nouvel = ancien)</option>
                                                <option value="error">Incohérent (nouvel < ancien)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1">Recherche</label>
                                            <input id="preview_search" type="text" class="form-control form-control-sm"
                                                   placeholder="Libellé ou N° compteur">
                                        </div>
                                        <div class="ms-auto small text-muted" id="preview_count"></div>
                                    </div>
                                    <div class="table-responsive border rounded">
                                        <table class="table table-sm table-hover mb-0" id="preview_table">
                                            <thead class="table-light">
                                            <tr>
                                                <th>Libellé</th>
                                                <th>Numéro compteur</th>
                                                <th class="text-end">Ancien index</th>
                                                <th class="text-end">Nouvel index</th>
                                                <th>Statut</th>
                                            </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Champs supprimés: mois/description/tarifs → la prévisualisation suffit avant import -->
                                <button type="submit" class="btn btn-primary w-100 shadow-sm">Importer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de création d'un nouveau mois -->
            <div class="modal fade" id="createMonthModal" tabindex="-1" aria-labelledby="createMonthModalLabel"
                 aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createMonthModalLabel">Créer un nouveau mois</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form action="traitement/mois_facturation_t.php" method="post">
                                <input type="hidden" name="action" value="create_month_auto">
                                <input type="hidden" name="id_constante"
                                       value="<?php echo isset($constante_reseau_id) ? (int)$constante_reseau_id : 0; ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="mois_new" class="form-label fw-bold">Mois <span
                                                    class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                        class="fas fa-calendar-month"></i></span>
                                            <input type="month" class="form-control shadow-sm" id="mois_new" name="mois"
                                                   value="<?php echo date('Y-m'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date_depot_new" class="form-label fw-bold">Date de dépôt <span
                                                    class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                        class="fas fa-calendar-day"></i></span>
                                            <input type="date" class="form-control shadow-sm" id="date_depot_new"
                                                   name="date_depot" value="<?php echo date('Y-m-28'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="description_new" class="form-label fw-bold">Description</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i
                                                        class="fas fa-info-circle"></i></span>
                                            <textarea class="form-control shadow-sm" id="description_new"
                                                      name="description" rows="4"
                                                      placeholder="Décrivez le mois..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 mt-3">
                                    <label class="form-label fw-bold">Tarif appliqué</label>
                                    <div class="card card-body bg-light">
                                        <?php if (isset($constante_reseau_id) && $constante_reseau_id): ?>
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between"><span>Prix de
                                                        l'eau
                                                        :</span><span><?php echo isset($prix_metre_cube_eau) ? $prix_metre_cube_eau . ' FCFA/m³' : 'N/A'; ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between"><span>Entretien
                                                        compteur
                                                        :</span><span><?php echo isset($prix_entretient_compteur) ? $prix_entretient_compteur . ' FCFA/mois' : 'N/A'; ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between">
                                                    <span>TVA
                                                        :</span><span><?php echo isset($prix_tva) ? $prix_tva . ' %' : 'N/A'; ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between">
                                                    <span>Créé le
                                                        :</span><span><?php echo isset($date_creation) ? $date_creation : 'N/A'; ?></span>
                                                </li>
                                            </ul>
                                        <?php else: ?>
                                            <div class="text-danger">Aucun tarif actif pour cet AEP. Veuillez activer un
                                                tarif.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler
                                    </button>
                                    <button type="submit"
                                            class="btn btn-primary" <?php echo isset($constante_reseau_id) && $constante_reseau_id ? '' : 'disabled'; ?>>
                                        Créer le mois
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                (function () {
                    var moisInput = document.getElementById('mois_new');
                    var depotInput = document.getElementById('date_depot_new');
                    if (!moisInput || !depotInput) return;

                    function updateDepotDate() {
                        var value = moisInput.value; // format attendu: YYYY-MM
                        if (!value || value.length < 7) return;
                        var parts = value.split('-');
                        if (parts.length >= 2) {
                            var yyyy = parts[0];
                            var mm = parts[1];
                            depotInput.value = yyyy + '-' + mm + '-28';
                        }
                    }

                    moisInput.addEventListener('change', updateDepotDate, false);
                    moisInput.addEventListener('input', updateDepotDate, false);
                })();
            </script>
            <script>
                (function () {
                    var fileInput = document.getElementById('fichier_index');
                    var previewSection = document.getElementById('preview_section');
                    var previewTableBody = document.querySelector('#preview_table tbody');
                    var previewFilter = document.getElementById('preview_filter');
                    var previewSearch = document.getElementById('preview_search');
                    var previewCount = document.getElementById('preview_count');
                    var rawRows = [];

                    function buildRowClass(row) {
                        var ancien = parseFloat(row.ancien_index);
                        var nouvel = parseFloat(row.nouvel_index);
                        if (isNaN(ancien) || isNaN(nouvel)) return 'table-warning';
                        if (nouvel < ancien) return 'table-danger';
                        if (nouvel === ancien) return 'table-secondary';
                        return 'table-success';
                    }

                    function matchFilter(row) {
                        var cls = buildRowClass(row);
                        var f = previewFilter.value;
                        if (f === 'ok') return cls === 'table-success';
                        if (f === 'identique') return cls === 'table-secondary';
                        if (f === 'error') return cls === 'table-danger';
                        return true;
                    }

                    function matchSearch(row) {
                        var q = (previewSearch.value || '').toLowerCase();
                        if (!q) return true;
                        var lib = (row.libelle || '').toLowerCase();
                        var num = (row.numero_compteur || '').toLowerCase();
                        return lib.indexOf(q) !== -1 || num.indexOf(q) !== -1;
                    }

                    function render() {
                        previewTableBody.innerHTML = '';
                        var shown = 0;
                        for (var i = 0; i < rawRows.length; i++) {
                            var r = rawRows[i];
                            if (!matchFilter(r) || !matchSearch(r)) continue;
                            var cls = buildRowClass(r);
                            var tr = document.createElement('tr');
                            tr.className = cls;
                            tr.innerHTML = '' +
                                '<td>' + (r.libelle || '') + '</td>' +
                                '<td>' + (r.numero_compteur || '') + '</td>' +
                                '<td class="text-end">' + (r.ancien_index || 0) + '</td>' +
                                '<td class="text-end">' + (r.nouvel_index || 0) + '</td>' +
                                '<td>' + (cls === 'table-success' ? 'Coherent' : (cls === 'table-secondary' ? 'Identique' : (cls === 'table-danger' ? 'Incohérent' : 'Vérifier'))) + '</td>';
                            previewTableBody.appendChild(tr);
                            shown++;
                        }
                        previewCount.textContent = shown + ' / ' + rawRows.length + ' lignes';
                    }

                    function parseIncomingJson(data) {
                        if (data && data.releve && data.releve[0] && data.releve[0].data) {
                            // Map fields to expected keys
                            var arr = data.releve[0].data;
                            var out = [];
                            for (var i = 0; i < arr.length; i++) {
                                var row = arr[i] || {};
                                out.push({
                                    libelle: row.libele || row.libelle || '',
                                    numero_compteur: row.numero || row.numero_compteur || '',
                                    ancien_index: row.ancien_index,
                                    nouvel_index: row.nouvel_index
                                });
                            }
                            return out;
                        }
                        if (Object.prototype.toString.call(data) === '[object Array]') return data;
                        return [];
                    }

                    function parseCsv(text) {
                        var lines = text.split(/\r?\n/);
                        var rows = [];
                        if (!lines.length) return rows;
                        // detect header
                        var header = lines[0].split(',');
                        var hasHeader = header.length >= 4 &&
                            /lib/i.test(header[0]) && /compteur|numero/i.test(header[1]);
                        for (var i = hasHeader ? 1 : 0; i < lines.length; i++) {
                            var line = lines[i].trim();
                            if (!line) continue;
                            var cols = line.split(',');
                            rows.push({
                                libelle: (cols[0] || '').trim(),
                                numero_compteur: (cols[1] || '').trim(),
                                ancien_index: parseFloat(cols[2] || '0'),
                                nouvel_index: parseFloat(cols[3] || '0')
                            });
                        }
                        return rows;
                    }

                    function handleFile(file) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            try {
                                var ext = (file.name || '').toLowerCase();
                                if (ext.indexOf('.csv') !== -1) {
                                    rawRows = parseCsv(e.target.result);
                                } else {
                                    var json = JSON.parse(e.target.result);
                                    rawRows = parseIncomingJson(json);
                                }
                                previewSection.classList.remove('d-none');
                                render();
                            } catch (err) {
                                previewSection.classList.add('d-none');
                                rawRows = [];
                                previewTableBody.innerHTML = '';
                                previewCount.textContent = '';
                                alert('Fichier invalide: ' + err);
                            }
                        };
                        reader.readAsText(file);
                    }

                    if (fileInput) {
                        fileInput.addEventListener('change', function () {
                            if (fileInput.files && fileInput.files[0]) {
                                handleFile(fileInput.files[0]);
                            }
                        });
                    }
                    if (previewFilter) previewFilter.addEventListener('change', render);
                    if (previewSearch) previewSearch.addEventListener('input', render);
                })();
            </script>
        </div>
    </div>
</div>