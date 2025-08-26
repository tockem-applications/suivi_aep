<?php
require_once("traitement/reseau_t.php");
require_once("presentation/compteur_component.php");
include_once("facture_component.php");
include_once("tools.php");


function affichergraphiquesReseau($data)
{
    genererGraphiques($data);
    //    var_dump($data);
}

function afficherCompteursReseau($id_reseau){
    display_compteur_list($id_reseau);
    // makeFullCompteurFomrForReseau($id_reseau);
}

function afficherPageReseau($id_reseau, $data, $code_html)
{
    ob_start();
        $selected_reseau = display_reseau_list($id_reseau);
    $display_reseau_list = ob_get_clean();
    
    ?>
        <div class="w-100 bg-secondary d-flex justify-content-between" >
            <button class="btn btn-primary d-flex justify-content-between" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTop" aria-controls="offcanvasTop">Liste des reseaux</button>
            <?php $selected_reseau == null? :displayReseauDetails($selected_reseau);   ?>
            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasTop" aria-labelledby="offcanvasTopLabel">
            <div class="offcanvas-header">
                <!-- <h5 class="offcanvas-title" id="offcanvasTopLabel">Offcanvas top</h5> -->
                <h3 class='text-center'>Liste des sous reseaux</h3>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <?php echo $display_reseau_list ?>
            </div>  
            </div>
        </div>
    <?php
    // echo "<div class='row m-0 '>";
    // echo "<div class=' col-12 col-sm-4 col-md-3 bg-white p-2 me-0'>
    //             <h3 class='text-center'>Liste des sous reseaux</h3>
    //             ";
    
    // echo "</div>";
    // echo "<div class='col-12 col-sm-8 col-md-9 '>";
    // $selected_reseau == null ?: displayReseauDetails($selected_reseau);
    echo "<div class='row d-flex px-1'> ";
    echo "<div class='col-12 col-md-7 mx-auto position-relative'>";
    if($id_reseau != 0 && isset($_GET['compteur'])){
        afficherCompteursReseau($id_reseau);
    }else{
        affichergraphiquesReseau($data);
    }
    echo "</div>";
    echo "<div class='col-12 col-md-5 overflow-y-scroll bg-dark-subtle' style='height: 650px'>".$code_html. "</div>";
    echo "</div>";

    
    echo "</div>";
    echo "</div>";
}

function afficherStatistiqueReseau($id_reseau)
{
    $res = MoisFacturation::getAllMois(null, null, $_SESSION['id_aep'], $id_reseau);
    //    var_dump($reseau->id);
    $output = array();
    $res = $res->fetchAll();
    foreach ($res as $ligne) {
        //        var_dump();
        $prix_metre_cube_eau = $ligne['prix_metre_cube_eau'];
        $prix_entretient_compteur = (int) $ligne['prix_entretient_compteur'];
        $conso = (float) $ligne['conso'];
        $tva = (float) $ligne['prix_tva'];
        $nombre = (int) $ligne['nombre'];
        $montant_versee = (int) $ligne['montant_versee'];
        //indifférencié (recherche et professionnel) Sciences, technologies et santé mention Informatique parcours Data Engineer
        $montant_facture = (1 + $tva / 100) * $conso * $prix_metre_cube_eau + $prix_entretient_compteur*$nombre;
        $taux_recouvrement = $montant_facture == 0 ? "-" : substr(100 * $montant_versee / $montant_facture, 0, 5) . ' %';
        $consommation_moyenne = $conso / $nombre;
        $mois = $ligne['mois'];
        $tmp = array(
            "consommation" => $conso . ' m<sup>3</sup>',
            "nombre de factures" => $nombre . ' ',
            "montant facturé" => $montant_facture . ' F',
            "consomation moyenne" => number_format($consommation_moyenne, 2) . ' m<sup>3</sup>',
            "Taux de recouvrement" => $taux_recouvrement,
            "montant recouvert" => $montant_versee . ' F',
        );
        $tranform_tmp = array();
        foreach ($tmp as $key => $value) {
            $valeur = explode(' ', $value);
            //            var_dump($valeur);
            $tranform_tmp[$key] = (float) $valeur[0];
        }
        $output[] = array('data' => $tranform_tmp, 'month' => $mois);
        displayComponent(
            $tmp,
            getLetterMonth($mois)
        );
    }
    return $output;
}

function make_formulaire_reseau_colapse($reseau = null, $id_collapse = 'create_reseau_form_colapse')
{
    ?>
    <p class="d-inline-flex gap-1">
        <a class="" data-bs-toggle="collapse" href="#<?php echo $id_collapse ?>" role="button" aria-expanded="false"
            aria-controls="collapseExample">
            ajouter un reseau
        </a>
        <!--        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">-->
        <!--            Button with data-bs-target-->
        <!--        </button>-->
    </p>
    <div class="collapse" id="<?php echo $id_collapse ?>">
        <div class="text-start">
            <form action="traitement/reseau_t.php?ajout=true" method="post">
                <?php make_formulaire_reseau($reseau) ?>
                <div class='d-flex justify-content-end p-2 btn-group'>
                    <button class='btn btn-danger col-6 ' type='reset'>Vider</button>
                    <button class='btn btn-success col-6' type='submit'>Enregistrer</button>
                </div>
            </form>
        </div>

    </div>
    <?php


}

function make_formulaire_reseau($reseau = null)
{
    ?>
    <div class="input-group mb-3">
        <!-- <span class="input-group-text w-50 new-width" id="inputGroup-sizing-default">Nom</span> -->
        <input type="text" required size="32" class="form-control" placeholder="Nom"
            value="<?php echo isset($nom) ? $nom : '' ?>" name="nom" aria-label="Sizing example input"
            aria-describedby="inputGroup-sizing-default">
    </div>
    <div class="input-group mb-3">
        <!-- <span class="input-group-text w-50" id="inputGroup-sizing-default">Abbreviation</span> -->
        <input type="text" class="form-control" placeholder="Abbreviation"
            value="<?php echo isset($PrixsemBS) ? $PrixsemBS : '' ?>" name="abreviation" aria-label="Sizing example input"
            aria-describedby="inputGroup-sizing-default">
    </div>
    <div class="input-group mb-3">
        <!-- <span class="input-group-text w-50"  id="inputGroup-sizing-default">Date de cration</span> -->
        <input type="date" required class="form-control" id="date_creation" placeholder="Date de creation"
            value="<?php echo isset($date_creation) ? $date_creation : date('Y-m-d') ?>" name="date_creation"
            aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
    </div>
    <div class="input-group mb-3">
        <!-- <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemBS</span> -->
        <textarea type="number" class="form-control" value="<?php ?>" name="description_reseau"
            aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default"
            placeholder="decrivez le reseau"></textarea>
    </div>
    <?php

}

function displayComponent($array, $mois)
{
    echo "<div class='card mb-5 mt-2'>
        <div class='card-header text-center h3'>$mois</div>
        <style>
                .my-card {
                    transition: transform 0.3s, box-shadow 0.3s;
                }
                
                .my-card:hover {
                    transform: scale(1.05); /* Agrandit légèrement la carte */
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); /* Ajoute une ombre */
                }
</style>
    <div class=' card-body row justify-content-center'>";
    foreach ($array as $key => $value) {
        echo elementInfoResesauComponent($key, $value);
    }
    echo "</div></div>";
}

function elementInfoResesauComponent($key, $value)
{
    $htmlComponent = "
        <div class=' my-card card text-center m-2 col-11 col-md-5' >
            <div class='card-body'>
                    $key
                
                <p class='card-text display-4' id='counter' >
                    <h5 class='card-title incrementing-card'>$value </h5>
                </p>
            </div>
            
        </div>";
    return $htmlComponent;
}


function displayReseauDetails(Reseau $reseau)
{
    if($reseau == null){
        return;
    }
    echo " <h1 class='h3'>$reseau->nom ($reseau->abreviation)</h1>
            <div class='d-flex justify-content-between'>
            
            <div class='btn-group' role='group' aria-label='Basic mixed styles example'>
              <a href='index.php?page=reseau&id_reseau=$reseau->id&compteur=true' class='btn btn-primary align-content-center' data-bs-toggle='tooltip'><i class='bi bi-speedometer'></i></a>
              <a href='index.php?page=reseau&id_reseau=$reseau->id' class='btn btn-warning align-content-center'><i class='bi bi-pencil-square '></i></a>
              <button type='button' class='btn btn-danger align-content-center' data-bs-toggle='modal' data-bs-target='#delete_reseau_modal'><i class='bi bi-trash'></i></button>
            </div>
        </div>";


    //    displayComponent(array("consommation"=>$ligne['conso'], "nombre de facture"=>$ligne['nombre'], "montant recouvert"=>$ligne['montant_versee']));
//    display_tab_facture_by_month($reseau->id);

    /*display_delete_modal(
        "Supression du reseau $reseau->nom ($reseau->abreviation)",
        'les abonés, les compteurs, et les ainsi que toutes les factures de ce reseau seront suprimés',
        'traitement/reseau_t.php?id_delete=' . $reseau->id,
        'delete_reseau_modal');*/

    //$tab = Compteur_t::getAllCompteurFromIdReseau($reseau->id);
//    display_compteur_list($reseau->id);
}

function display_reseau_list($id_selected_reseau)
{
    $req = Reseau_t::getAllReseauFromAepId();
    echo "<div class='list-group overflow-y-auto'>";
    $selected_reseau = null;
    foreach ($req as $line) {
        $reseau = new Reseau($line['id'], $line['nom'], $line['abreviation'], $line['date_creation'], $line['description_reseau'], $line['id_aep']);

        $selected_reseau_class = $reseau->id == $id_selected_reseau ? "disabled active" : "";
        $selected_reseau_class == "" ?: $selected_reseau = $reseau;
        echo "<a class='list-group-item list-group-item-action  $selected_reseau_class' href='?page=reseau&id_reseau=$reseau->id'>$reseau->id $reseau->nom ($reseau->abreviation)</a>";
    }
    make_formulaire_reseau_colapse();
    ;
    echo "</div>";
    //    var_dump($selected_reseau);
    return $selected_reseau;
}
