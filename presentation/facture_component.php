<?php
include_once("traitement/facture_t.php");


function display_tab_facture_by_month($id_reseau=0){

    require_once("traitement/mois_facturation_t.php");
    $id_mois_listing = 0;
    $insolvable = false;
    $bon_payeurs = false;
    $avanceur = false;
    $fin_titre = "recouvrements ";

    if (isset($_GET["id_selected_month"]))
        $id_mois_listing = $_GET['id_selected_month'];

    if (isset($_GET["insolvable"])) {
        $fin_titre = "Insolvables";
        $insolvable = (int)$_GET['insolvable'];
    }
    elseif (isset($_GET["bon_payeurs"])) {
        $fin_titre = "Bons Payeurs ";
        $bon_payeurs = (int)$_GET['bon_payeurs'];
    }
    elseif (isset($_GET["avanceur"])) {
        $fin_titre = "Avanceurs ";
        $avanceur = (int)$_GET['avanceur'];
    }
    if($id_mois_listing == 0)
        $id_mois_listing =MoisFacturation::getIdMoisFacturationActive($_SESSION['id_aep']);

    ?>
    <div class="container mt-3 d-flex justify-content-center">
        <div class="row">
            <form action="?" method="GET" class="">
                <input type="hidden" name="list" value="recouvrement">
                <div class="input-group">
                    <span class="input-group-text">Mois de facturation</span>
                    <select name="id_selected_month" class="form-select" id="">
<!--                        <option value="">Veuillez choisir un mois</option>-->
                        <?php
                        MoisFacturation_t::getOnlyOption($id_mois_listing);
                        ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Afficher</button>
                    <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing?>&bon_payeurs=1" class="btn btn-success <?php echo $bon_payeurs?"disabled":""?>">Solvables</a>
                    <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing?>&avanceur=1" class="btn btn-warning <?php echo $avanceur?"disabled":""?>">Avances</a>
                    <a href="?list=recouvrement&id_selected_month=<?php echo $id_mois_listing?>&insolvable=1" class="btn btn-danger <?php echo $insolvable?"disabled":""?>">Insolvables</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    //echo $id_mois_listing;
    echo "<div class='container-fluid'>";
    $id_mois_listing = Facture_t::getTableauFactureByMoisId($_SESSION['id_aep'],$id_mois_listing, 'Liste des '.$fin_titre ,$id_reseau, $insolvable, $bon_payeurs, $avanceur);
    echo "</div>";
}
