<?php
require_once ("traitement/reseau_t.php");
require_once ("presentation/compteur_component.php");


function afficherPageReseau($id_reseau){
    echo "<div class='d-flex'>";
        echo "<div class='col-12 col-sm-4 col-md-3 bg-white p-2'>
                <h3 class='text-center'>Liste des sous reseaux</h3>
                ";
            $selected_reseau = display_reseau_list($id_reseau);
        echo "</div>";
        echo "<div class='col-12 col-sm-8 col-md-9'>";
            $selected_reseau == null? :displayReseauDetails($selected_reseau);
        echo "</div>";
    echo "</div>";
}



function displayReseauDetails(Reseau $reseau){
    echo "<div class='bg-danger'><h1>$reseau->nom ($reseau->abreviation)</h1></div>";
    //$tab = Compteur_t::getAllCompteurFromIdReseau($reseau->id);
    display_compteur_list($reseau->id);
}

function  display_reseau_list($id_selected_reseau){
    $req = Reseau_t::getAllReseauFromAepId();
    echo "<div class='list-group'>";
    $selected_reseau = null;
    foreach ($req as $line){
        $reseau = new Reseau($line['id'], $line['nom'], $line['abreviation'], $line['date_creation'], $line['description_reseau'], $line['id_aep']);

        $selected_reseau_class = $reseau->id == $id_selected_reseau?"disabled active":"";
        $selected_reseau_class == ""? :$selected_reseau =$reseau;
        echo "<a class='list-group-item list-group-item-action list-group-item-primary $selected_reseau_class' href='?page=reseau&id_reseau=$reseau->id'>$reseau->nom ($reseau->abreviation)</a>";
    }
    echo "</div>";
//    var_dump($selected_reseau);
    return $selected_reseau;
}
