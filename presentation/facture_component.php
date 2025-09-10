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
                <div class="input-group">
                    <span class="input-group-text">Mois de facturation</span>
                    <select name="id_selected_month" class="form-select" id="select_month_form_tag">
                        <!--                        <option value="">Veuillez choisir un mois</option>-->
                        <?php
                        MoisFacturation_t::getOnlyOption($id_mois_listing);
                        ?>
                    </select>
                </div>
            </form>

            <script>
                document.getElementById('select_month_form_tag').addEventListener('change', function () {
                    // Soumettre le formulaire lorsque la sélection change
                    document.getElementById('select_month_form').submit();
                });
            </script>

            <div class="d-flex justify-content-center pt-3">
                <div class=" d-flex" role="group" aria-label="Options de recouvrement">
                    <div class="m-1">
                        <div class="pb-1">
                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&select_option=insolvable"
                               class="custom-btn btn-insolvables <?php echo $insolvable ? 'disabled' : '' ?> ">Insolvables</a>

                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&select_option=paiement_partiel"
                               class="custom-btn btn-paiement-partiel <?php echo $select_option == 'paiement_partiel' ? 'disabled' : '' ?>">Paiement
                                partiel</a>
                        </div>
                        <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&select_option=pas_en_regle"
                           class="custom-btn btn-pas-en-regle w-100 px-2 <?php echo $select_option == 'pas_en_regle' ? 'disabled' : '' ?> ">
                            Pas en règle</a>
                    </div>

                    <div class="m-1">
                        <div class="pb-1">
                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&select_option=solvable"
                               class="custom-btn btn-solvables <?php echo $select_option == 'solvable' ? 'disabled' : '' ?>">Solvables</a>

                            <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&select_option=anticipation"
                               class="custom-btn btn-anticipation <?php echo $select_option == 'anticipation' ? 'disabled' : '' ?>">Anticipation</a>
                        </div>
                        <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>&select_option=en_regle"
                           class="custom-btn btn-en-regle w-100 <?php echo $select_option == 'en_regle' ? 'disabled' : '' ?>">En
                            règle</a>
                    </div>

                    <div class="m-1">
                        <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing ?>"
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
    $id_mois_listing = Facture_t::getTableauFactureByMoisId($_SESSION['id_aep'], $id_mois_listing, 'Liste des ' . $fin_titre, $id_reseau, $select_option);
    echo "</div>";
}
