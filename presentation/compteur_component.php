<?php


function make_formulaire_compteur_colapse($compteur=null, $id_collapse='create_compteur_form_colapse'){
    ?>
    <p class="d-inline-flex gap-1">
        <a class="" data-bs-toggle="collapse" href="#<?php echo $id_collapse?>" role="button" aria-expanded="false" aria-controls="collapseExample">
            ajouter un compteur
        </a>
<!--        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">-->
<!--            Button with data-bs-target-->
<!--        </button>-->
    </p>
    <div class="collapse" id="<?php echo $id_collapse?>">
        <div class="text-start">
            <?php  make_formulaire_compteur($compteur)?>
        </div>

    </div>
    <?php


}

function  display_compteur_list($id_selected_reseau){
    $req = Compteur_t::getAllCompteurFromIdReseau($id_selected_reseau);
    echo "<div class='list-group'>";
    $selected_reseau = null;
    if(count($req) == 0){
        makeFullCompteurFomrForReseau($id_selected_reseau);

    }else {
        foreach ($req as $line) {
            echo $line['numero_compteur']." <br>";
//        $reseau = new Reseau($line['id'], $line['nom'], $line['abreviation'], $line['date_creation'], $line['description_reseau'], $line['id_aep']);
//
//        $selected_reseau_class = $reseau->id == $id_selected_reseau?"disabled active":"";
//        $selected_reseau_class == ""? :$selected_reseau =$reseau;
//        echo "<a class='list-group-item list-group-item-action list-group-item-primary $selected_reseau_class' href='?page=reseau&id_reseau=$reseau->id'>$reseau->nom ($reseau->abreviation)</a>";
        }
        echo "</div>";
        return $selected_reseau;
//    var_dump($selected_reseau);
    }
}

function makeFullCompteurFomrForReseau($id_reseau)
{
    echo "<form class='col-12 col-md-6' method='post' action='traitement/compteur_t.php?ajouter_compteur_reseau=true&id_reseau=$id_reseau'>
            <h2 class='text-center'>Ajouter un compteur</h2>
        ";

    make_formulaire_compteur();
    echo "<div class='d-flex justify-content-end p-2 btn-group'>
            <button class='btn btn-danger col-6 ' type='reset'>Vider</button> 
            <button class='btn btn-success col-6' type='submit'>Enregistrer</button>
        </div>
            </form>";
}

function make_formulaire_compteur($compteur=null){
    ?>
    <div class="input-group d-flex mb-3">
        <input type="text" placeholder="numero" class="form-control" id="numero_compteur" name="numero_compteur" maxlength="16" required>
    </div>
    <div class="input-group d-flex mb-3">
        <input type="number" step="0.01"  placeholder="Index"  class="form-control" id="derniers_index" name="derniers_index" required>
    </div>

    <div class="input-group d-flex mb-3">
        <input type=    "number"  placeholder="Longitude"  step="0.000001" class="form-control" id="longitude" name="longitude">
        <span class="input-group-text">@</span>
        <input type="number"  placeholder="Latitude"  step="0.000001" class="form-control" id="latitude" name="latitude">

    </div>



    <div class="form-group">
<!--        <label for="description">Description</label>-->
        <textarea class="form-control" id="description" placeholder="Decrivez ce cmpteur" name="description" rows="5" maxlength="1000"></textarea>
    </div>
    <?php


}
