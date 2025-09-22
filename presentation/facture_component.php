<?php
include_once("traitement/facture_t.php");


function display_tab_facture_by_month($id_reseau = 0)
{

    require_once("traitement/mois_facturation_t.php");
    $id_mois_listing = 0;
    $insolvable = false;
    $bon_payeurs = false;
    $avanceur = false;
    $fin_titre = "recouvrements ";

    if (isset($_GET["id_selected_month"]))
        $id_mois_listing = $_GET['id_selected_month'];

    // Récupérer le filtre réseau depuis l'URL
    if (isset($_GET["id_reseau_filter"]))
        $id_reseau = (int) $_GET['id_reseau_filter'];

    $select_option = 'vide';
    if (isset($_GET['select_option'])) {
        $select_option = $_GET['select_option'];
        if ($select_option == "insolvable") {
            $fin_titre = "Insolvables";
            $insolvable = 1;
        } elseif ($select_option == "paiement_partiel") {
            $fin_titre = "Paiements partiels ";
            $bon_payeurs = 1;
        } elseif ($select_option == "solvable") {
            $fin_titre = "Avanceurs ";
            $avanceur = 1;
        } elseif ($select_option == "solvable") {
            $fin_titre = "Solvables ";
            $avanceur = 1;
        }
    }
    if ($id_mois_listing == 0)
        $id_mois_listing = MoisFacturation::getIdMoisFacturationActive($_SESSION['id_aep']);

    ?>
    <div class="container mt-3 d-flex justify-content-center">
        <div class="row">
            <form action="?" method="GET" class="" id="select_month_form">
                <input type="hidden" name="list" value="recouvrement">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">Mois de facturation</span>
                            <select name="id_selected_month" class="form-select" id="select_month_form_tag">
                                <!--                        <option value="">Veuillez choisir un mois</option>-->
                                <?php
                                MoisFacturation_t::getOnlyOption($id_mois_listing);
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">Réseau</span>
                            <select name="id_reseau_filter" class="form-select" id="select_reseau_form_tag">
                                <option value="0">Tous les réseaux</option>
                                <?php
                                // Récupérer la liste des réseaux pour l'AEP actuelle
                                include_once("traitement/reseau_t.php");
                                $reseaux = Manager::prepare_query("SELECT id, nom FROM reseau WHERE id_aep = ? ORDER BY nom", array($_SESSION['id_aep']));
                                $reseauxData = $reseaux->fetchAll();
                                $selectedReseau = isset($_GET['id_reseau_filter']) ? (int) $_GET['id_reseau_filter'] : 0;

                                foreach ($reseauxData as $reseau) {
                                    $selected = ($reseau['id'] == $selectedReseau) ? 'selected' : '';
                                    echo "<option value='{$reseau['id']}' $selected>{$reseau['nom']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search_nom_input"
                                placeholder="Rechercher par nom..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="clear_search_btn"
                                title="Effacer la recherche">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <script>
                // Gérer les changements des deux sélecteurs
                document.getElementById('select_month_form_tag').addEventListener('change', function () {
                    document.getElementById('select_month_form').submit();
                });

                document.getElementById('select_reseau_form_tag').addEventListener('change', function () {
                    document.getElementById('select_month_form').submit();
                });

                // Fonctionnalité de recherche par nom
                function initSearch() {
                    const searchInput = document.getElementById('search_nom_input');
                    const clearBtn = document.getElementById('clear_search_btn');
                    
                    if (!searchInput || !clearBtn) return;
                    
                    // Attendre que le tableau soit chargé
                    function waitForTable() {
                        const table = document.querySelector('.table tbody');
                        if (!table) {
                            setTimeout(waitForTable, 100); // Réessayer dans 100ms
                            return;
                        }
                        
                        console.log('Tableau trouvé, initialisation de la recherche...');
                        
                        // Fonction de recherche
                        function filterTable() {
                            const searchTerm = searchInput.value.toLowerCase().trim();
                            const rows = table.querySelectorAll('tr');
                            let visibleCount = 0;
                            
                            rows.forEach(function (row) {
                                // Chercher dans toutes les colonnes qui contiennent du texte
                                const cells = row.querySelectorAll('td');
                                let found = false;
                                
                                cells.forEach(function (cell) {
                                    const cellText = cell.textContent.toLowerCase();
                                    if (cellText.includes(searchTerm) && searchTerm !== '') {
                                        found = true;
                                    }
                                });
                                
                                if (searchTerm === '') {
                                    row.style.display = '';
                                    visibleCount++;
                                } else {
                                    row.style.display = found ? '' : 'none';
                                    if (found) visibleCount++;
                                }
                            });
                            
                            // Afficher un message si aucun résultat
                            showNoResultsMessage(visibleCount === 0 && searchTerm !== '');
                        }
                        
                        // Fonction pour afficher le message "aucun résultat"
                        function showNoResultsMessage(show) {
                            let noResultsRow = document.getElementById('no-results-row');
                            
                            if (show && !noResultsRow) {
                                noResultsRow = document.createElement('tr');
                                noResultsRow.id = 'no-results-row';
                                noResultsRow.innerHTML = '<td colspan="100%" class="text-center text-muted py-4"><i class="fas fa-search"></i> Aucun résultat trouvé pour "' + searchInput.value + '"</td>';
                                table.appendChild(noResultsRow);
                            } else if (!show && noResultsRow) {
                                noResultsRow.remove();
                            }
                        }
                        
                        // Événements
                        searchInput.addEventListener('input', function () {
                            clearBtn.style.display = this.value ? 'block' : 'none';
                            filterTable();
                        });
                        
                        clearBtn.addEventListener('click', function () {
                            searchInput.value = '';
                            clearBtn.style.display = 'none';
                            filterTable();
                        });
                        
                        // Raccourci clavier pour effacer (Escape)
                        searchInput.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') {
                                this.value = '';
                                clearBtn.style.display = 'none';
                                filterTable();
                            }
                        });
                        
                        // Initialiser l'état du bouton clear
                        clearBtn.style.display = searchInput.value ? 'block' : 'none';
                    }
                    
                    waitForTable();
                }
                
                // Initialiser la recherche
                document.addEventListener('DOMContentLoaded', initSearch);
                
                // Réinitialiser après chargement AJAX ou changement de contenu
                setTimeout(initSearch, 500);
            </script>

            <div class="d-flex justify-content-center pt-3">
                <div class=" d-flex" role="group" aria-label="Options de recouvrement">
                    <div class="m-1">
                        <div class="pb-1">
                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&id_reseau_filter=<?php echo $id_reseau ?>&select_option=insolvable"
                                class="custom-btn btn-insolvables <?php echo $insolvable ? 'disabled' : '' ?> ">Insolvables</a>

                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&id_reseau_filter=<?php echo $id_reseau ?>&select_option=paiement_partiel"
                                class="custom-btn btn-paiement-partiel <?php echo $select_option == 'paiement_partiel' ? 'disabled' : '' ?>">Paiement
                                partiel</a>
                        </div>
                        <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&id_reseau_filter=<?php echo $id_reseau ?>&select_option=pas_en_regle"
                            class="custom-btn btn-pas-en-regle w-100 px-2 <?php echo $select_option == 'pas_en_regle' ? 'disabled' : '' ?> ">
                            Pas en règle</a>
                    </div>

                    <div class="m-1">
                        <div class="pb-1">
                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&id_reseau_filter=<?php echo $id_reseau ?>&select_option=solvable"
                                class="custom-btn btn-solvables <?php echo $select_option == 'solvable' ? 'disabled' : '' ?>">Solvables</a>

                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&id_reseau_filter=<?php echo $id_reseau ?>&select_option=anticipation"
                                class="custom-btn btn-anticipation <?php echo $select_option == 'anticipation' ? 'disabled' : '' ?>">Anticipation</a>
                        </div>
                        <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&id_reseau_filter=<?php echo $id_reseau ?>&select_option=en_regle"
                            class="custom-btn btn-en-regle w-100 <?php echo $select_option == 'en_regle' ? 'disabled' : '' ?>">En
                            règle</a>
                    </div>

                    <div class="m-1">
                        <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&id_reseau_filter=<?php echo $id_reseau ?>"
                            class="custom-btn btn-vider <?php echo $select_option == 'vide' ? 'disabled' : '' ?>">Vider</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>


    <?php
    //echo $id_mois_listing;
    echo "<div class='container-fluid'>";
    $id_mois_listing = Facture_t::getTableauFactureByMoisId($_SESSION['id_aep'], $id_mois_listing, 'Liste des ' . $fin_titre, $id_reseau, $select_option, $selectedReseau);
    echo "</div>";
    
    // Script de débogage pour la recherche
    echo '<script>
    console.log("Script de débogage chargé");
    
    // Vérifier si les éléments existent
    setTimeout(function() {
        const searchInput = document.getElementById("search_nom_input");
        const clearBtn = document.getElementById("clear_search_btn");
        const table = document.querySelector(".table tbody");
        
        console.log("Éléments trouvés:");
        console.log("- searchInput:", searchInput);
        console.log("- clearBtn:", clearBtn);
        console.log("- table:", table);
        
        if (table) {
            const rows = table.querySelectorAll("tr");
            console.log("Nombre de lignes dans le tableau:", rows.length);
            if (rows.length > 0) {
                console.log("Première ligne:", rows[0]);
                const cells = rows[0].querySelectorAll("td");
                console.log("Cellules de la première ligne:", cells.length);
            }
        }
    }, 1000);
    </script>';
}
