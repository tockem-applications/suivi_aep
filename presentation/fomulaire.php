<?php

$is_aep_in_session = Aep_t::isAepIdInSession();
if (isset($_GET['form'])) {
    $nom_formulaire = htmlspecialchars($_GET['form']);
    $valide = true;
    $traitement = 'traitement/';
    $titre = '';
    ob_start();


    if ($nom_formulaire == 'abone' && $is_aep_in_session) {

        $titre = 'Nouvel Aboné';
        $id = 0;
        //require_once ("../traitement/proprietaire_t.php");
        if (isset($_GET['id'])) {
            $re = Abone_t::getOne(htmlspecialchars($_GET['id']));
            $re = $re->fetch();

            if ($re['Id']) {
                $id = $re['Id'];
                $nom = $re['Nom'];
                $numero_compteur = $re['numero_compteur'];
                $numero_telephone = $re['numero_telephone'];
                $numero_compte_anticipation = $re['numero_compte_anticipation'];
                $etat = $re['etat'];
                $rang = $re['rang'];
                $derniers_index = $re['derniers_index'];
                $traitement = $traitement . "abone_t.php?update=true&id_updates=$id";
                $titre = "Modifier L'abone";
            }
        } else {
            $traitement = $traitement . "abone_t.php?ajout=true";
        }
        ?>

        <div style="display: flex;">

            <div class="input-group mb-3">
                <span class="input-group-text w-25" id="inputGroup-sizing-default">Reseau</span>
                <select class="form-select" name="id_reseau" aria-label="Sizing example input"
                        aria-describedby="inputGroup-sizing-default" id="">
                    <?php
                    require_once("/traitement/reseau_t.php");
                    Reseau_t::getoption($id);
                    ?>
                    <!-- <option value="non_actif">Non Actif</option> -->
                </select>
                <div>
                    <a href="?form=reseau" class="form-control input-group-text p-0 fs-5 pb-2 px-2"
                       data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Ajouter un reseau">+</a>
                </div>
            </div>
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">type</span>
            <select class="form-select" name="type_compteur" aria-label="Sizing example input"
                    aria-describedby="inputGroup-sizing-default" id="">
                <option value="distribution">Distribution</option>
                <option value="production">Production</option>
                <!-- <option value="non_actif">Non Actif</option> -->
            </select>
        </div>

        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Nom</span>
            <input type="text" class="form-control" required name="nom" value="<?php echo isset($nom) ? $nom : '' ?>"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">N° Compteur</span>
            <input type="text" required class="form-control"
                   value="<?php echo isset($numero_compteur) ? $numero_compteur : '' ?>" name="numero_compteur"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-50" id="inputGroup-sizing-default">N° Telephone</span>
            <input type="text" required class="form-control" value="<?php echo isset($prenom) ? $prenom : '' ?>"
                   name="numero_telephone" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>

        <div class="input-group mb-3">
            <span class="input-group-text w-50" id="inputGroup-sizing-default">N°Compte anticipation</span>
            <input type="text" class="form-control"
                   value="<?php echo isset($numero_compte_anticipation) ? $numero_compte_anticipation : '' ?>"
                   name="numero_compte_anticipation" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-50" id="inputGroup-sizing-default">Dernier index</span>
            <input type="number" step="0.01" class="form-control"
                   value="<?php echo isset($derniers_index) ? $derniers_index : '' ?>" name="derniers_index"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-50" id="inputGroup-sizing-default">etat</span>
            <select name="etat" class="form-select " aria-label="Sizing example input"
                    aria-describedby="inputGroup-sizing-default" id="">
                <option value="actif">Actif</option>
                <option value="non_actif">Non Actif</option>
                <!-- <option value="non_actif">Non Actif</option> -->
            </select>
            <!-- <input type="text" class="form-control" value="<?php echo isset($etat) ? $etat : '' ?>" name="etat" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default"> -->
        </div>

        <?php
//        ob_start();
//        Proprietaire_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'trying' && $is_aep_in_session) {

        $titre = 'We are juste trying';
        //require_once ("../traitement/tarif_t.php");
        if (isset($_GET['id'])) {
            $re = tarif_t::getOne(htmlspecialchars($_GET['id']));
            $re = $re->fetch();
            if ($re['Id']) {
                $id = $re['Id'];
                $PrixsemHS = $re['PrixsemHS'];
                $PrixsemBS = $re['PrixsemBS'];
                $traitement = $traitement . "tarif_t.php?update=true&id_updates=$id";
                $titre = 'Modifier Le Tarif';
            }
        } else {
            $traitement = $traitement . "abone_t.php?ajout=true";
        }
        ?>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemHS</span>
            <input type="month" class="form-control" value="2024-10" name="prixsemHS" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemBS</span>
            <input type="number" class="form-control" value="<?= $PrixsemBS ?? '' ?>" name="prixSemBS"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'login' && $is_aep_in_session) {

        $titre = 'Connectez vous';
        //require_once ("../traitement/tarif_t.php");

            $traitement = $traitement . "abone_t.php?connextion=true";

        ?>
        <div class="">
            <div class="form-group">
                <label for="email">Nom d'utilisateur</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group mb-3">
                <label for="password">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
        </div>
        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    }elseif ($nom_formulaire == 'aep') {

        $titre = 'Nouvel Aep';
        //require_once ("../traitement/tarif_t.php");
        if (isset($_GET['id'])) {
            $re = tarif_t::getOne(htmlspecialchars($_GET['id']));
            $re = $re->fetch();
            if ($re['Id']) {
                $id = $re['Id'];
                $PrixsemHS = $re['PrixsemHS'];
                $PrixsemBS = $re['PrixsemBS'];
                $traitement = $traitement . "aep_t.php?update=true&id_updates=$id";
                $titre = 'Modifier Le Tarif';
            }
        } else {
            $traitement = $traitement . "aep_t.php?ajout=true";
        }
        ?>
        <div class="text-start m-3">
            Ce formulaire vous permet de créer un Aep. Une fois sa creation terminee, vous pourez y ajouter des reseaux, des abonés et les facturer.
        </div>
        <div class="input-group mb-2">
            <label for="libele" class="input-group-text w-25">Libelé:</label>
            <input type="text" class="form-control" id="libele" value="aep" name="libele" required>
        </div>
        <div class="input-group mb-2">
            <label for="date" class="input-group-text w-25">Date:</label>
            <input type="date" class="form-control" id="date" value="2024-12-12" name="date" required>
        </div>
        <div class="form-group mb-2">
<!--            <label for="description" class="input-group-text">Description:</label>-->
            <textarea class="form-control" placeholder="@Description" id="description" name="description" rows="3" required>Mon Aep</textarea>
        <div class="form-group">
                <p class="m-2">Selectionnez le model de facture que vous souhaitez pour votre Aep</p>
                <div class="form-check ">
                    <input class="form-check-input" type="radio" name="fichier_facture" id="facture1" value="model_fokoue" required>
                    <label class="image-radio text-start my-2" for="facture1">
                        <span>Model de Fokoue</span>
                        <img src="presentation/assets/images/model_fokoue.png"  alt="Image image du model de facture de Fokoue" class="img-fluid my-2">
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="fichier_facture" id="facture2" value="model_nkongzem">
                    <label class="image-radio text-start" for="facture2">
                        <span>Model de Nkongzem</span>
                        <img src="presentation/assets/images/model_nkongzem.png" alt="Image du model de facture de Nkongzem" class="img-fluid my-2">
                    </label>
                </div>
        </div>
        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'login') {

        $titre = 'Connectez vous';
        //require_once ("../traitement/tarif_t.php");

            $traitement = $traitement . "abone_t.php?connextion=true";

        ?>
        <div class="">
            <div class="form-group">
                <label for="email">Nom d'utilisateur</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group mb-3">
                <label for="password">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
        </div>
        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'liste_facture' && $is_aep_in_session) {

        $titre = 'We are juste trying';
        //require_once ("../traitement/tarif_t.php");
        if (isset($_GET['id'])) {
            $re = tarif_t::getOne(htmlspecialchars($_GET['id']));
            $re = $re->fetch();
            if ($re['Id']) {
                $id = $re['Id'];
                $PrixsemHS = $re['PrixsemHS'];
                $PrixsemBS = $re['PrixsemBS'];
                $traitement = $traitement . "tarif_t.php?update=true&id_updates=$id";
                $titre = 'Modifier Le Tarif';
            }
        } else {
            $traitement = $traitement . "abone_t.php?ajout=true";
        }
        ?>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemHS</span>
            <input type="month" class="form-control" value="2024-10" name="prixsemHS" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemBS</span>
            <input type="number" class="form-control" value="<?= $PrixsemBS ?? '' ?>" name="prixSemBS"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'finance' && $is_aep_in_session) {

        $titre = 'Nouvelle Transactioon';
        require_once ("donnees/flux_financier.php");
        $libele = '';
        $date = date('Y-m-d');
        $mois = date('Y-m');
        $prix = '';
        $type = '';
        $description = '';
        if (isset($_GET['id'])) {
            $re = FluxFinancier::getFluxById($_GET['id']);
            $re = $re->fetch();
//            var_dump($re, $_GET['id']);
            if ($re['id']) {
                $id = $re['id'];
                $libele = $re['libele'];
                $date = $re['date'];
                $mois = $re['mois'];
                $prix = $re['prix'];
                $type = $re['type'];
                $description = $re['description'];
//                $mois = $re['mois'];
                $traitement = $traitement . "flux_financier_t.php?update=true&id_updates_flux=$id";
                $titre = 'Modifier la transaction';
            }
        } else {
            $traitement = $traitement . "flux_financier_t.php?ajout=true";
        }
        ?>
        <div class="input-group  mb-3">
            <label for="date" class="input-group-text w-25">Date</label>
            <input type="date" class="form-control" id="date" value="<?php echo $date;?>" name="date" required>
        </div>
        <div class="input-group mb-3">
            <label for="mois" class="input-group-text w-25">Mois</label>
            <input type="month" class="form-control" id="mois" value="<?php echo $mois;?>" name="mois" maxlength="7" required>
        </div>
        <div class="input-group mb-3">
            <label for="libele" class="input-group-text w-25">Libelé</label>
            <input type="text" class="form-control" id="libele" name="libele" value="<?php echo $libele;?>" maxlength="128"
                   required>
        </div>
        <div class="input-group mb-3">
            <label for="prix" class="input-group-text w-25">Prix</label>
            <input type="number" class="form-control" id="prix" name="prix" value="<?php echo $prix;?>" min="0" max="9999999"
                   required>
        </div>
        <div class="input-group mb-3">
            <label for="type" class="input-group-text w-25">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="sortie" <?php echo $type=='sortie'?'selected':''?>>Sortie</option>
                <option value="entree" <?php echo $type=='entree'?'selected':''?>>Entrée</option>
            </select>
        </div>
        <div class="input-group mb-3">
            <textarea class="form-control" id="description" placeholder="Description" name="description" rows="3"
                      maxlength="1000"><?php echo $description;?></textarea>
        </div>
        <div>
            <a href="?list=transaction"></a>
        </div>
        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'reseau' && $is_aep_in_session) {

        $titre = 'Nouveau reseau';
        //require_once ("../traitement/tarif_t.php");
        if (isset($_GET['id'])) {
            $re = tarif_t::getOne(htmlspecialchars($_GET['id']));
            $re = $re->fetch();
            if ($re['Id']) {
                $id = $re['Id'];
                $nom = $re['nom'];
                $abreviation = $re['abreviation'];
                $date_creation = $re['date_creation'];
                $description_reseau = $re['description_reseau'];
                $traitement = $traitement . "tarif_t.php?update=true&id_updates=$id";
                $titre = 'Modifier Le Tarif';
            }
        } else {
            $traitement = $traitement . "reseau_t.php?ajout=true";
        }
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
                   value="<?php echo isset($PrixsemBS) ? $PrixsemBS : '' ?>" name="abreviation"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <!-- <span class="input-group-text w-50"  id="inputGroup-sizing-default">Date de cration</span> -->
            <input type="date" required class="form-control" id="date_creation" placeholder="Date de creation"
                   value="<?php echo isset($date_creation) ? $date_creation : date('Y-m-d') ?>" name="date_creation"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <!-- <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemBS</span> -->
            <textarea type="number" class="form-control"
                      value="<?php isset($description_reseau) ? $description_reseau : '' ?>" name="description_reseau"
                      aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default"
                      placeholder="decrivez le reseau"></textarea>
        </div>

        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'import_index' && $is_aep_in_session) {

        $titre = 'Preciser les informations mensuelles';
        //require_once ("../traitement/tarif_t.php");
        if (isset($_GET['id'])) {
            $re = mois_facturation_t::getOne(htmlspecialchars($_GET['id']));
            $re = $re->fetch();
            if ($re['Id']) {
                $id = $re['Id'];
                $mois = $re['mois'];
                $id_constante = $re['id_constante'];
                $description_reseau = $re['description_reseau'];
                $traitement = $traitement . "tarif_t.php?update=true&id_updates=$id";
                $titre = 'Modifier Le Tarif';
            }
        } else {
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
            $traitement = $traitement . "mois_facturation_t.php?ajout=true";
        }
        ?>
        <div class="input-group mb-3">
            <span class="input-group-text w-50 new-width" id="inputGroup-sizing-default">Mois</span>
            <input type="month" required size="32" class="form-control" value="<?php echo isset($mois) ? $mois : '' ?>"
                   name="mois" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-0" id="inputGroup-sizing-default">Fichier mobile</span>
            <input type="file" class="form-control" value="" id="fichier_index" name="fichier_index"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
            <!-- <input type="hidden" class="form-control" value="" id="ma_photo" name="Photo2" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default"> -->
        </div>
        <div class="input-group mb-3">
            <!-- <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemBS</span> -->
            <textarea type="number" class="form-control"
                      value="<?php isset($description_reseau) ? $description_reseau : '' ?>" name="description"
                      aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default"
                      placeholder="decrivez le reseau"></textarea>
        </div>
        <input type="hidden" required size="32" class="form-control"
               value="<?php echo isset($constante_reseau_id) ? $constante_reseau_id : '' ?>" name="id_constante"
               aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        <div class="input-group mb-3">
            <?php if (!isset($constante_reseau_id))
                header("location: ?form=constante_reseau&operation=error&message=Vous devez d'abord entrer des tarifs du reseau");
            ?>
            <a class="" data-bs-toggle="collapse" href="#collapseExample" role="" aria-expanded="false"
               aria-controls="collapseExample">
                voir les tarifs ...
            </a>
            <div class="collapse" id="collapseExample">
                <div class="card" style="padding: 15px 35px 0px 0;">
                    <ul>
                        <li class="list-group-item fw-bold jc-" style="justify-content: space-around;"><span
                                    style="width: 50%;">Prix de l'eau:</span> <span
                                    style="width: 50%;"><?php echo $prix_metre_cube_eau ?> FCFA/m3</span></li>
                        <li class="list-group-item fw-bold">entretient compteur: <?php echo $prix_entretient_compteur ?>
                            FCFA/mois
                        </li>
                        <li class="list-group-item fw-bold ta-left">TVA: <?php echo $prix_tva ?> %</li>
                        <li class="list-group-item fw-bold ta-left"><a
                                    href="?form=constante_reseau&operation=error&message=Vous devez d'abord entrer des tarifs du reseau">Modifier
                                ces tarifs</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    } elseif ($nom_formulaire == 'constante_reseau' && $is_aep_in_session) {

        $titre = 'Nouveaux tarifs';
        //require_once ("../traitement/tarif_t.php");
        if (isset($_GET['id'])) {
            $re = ConstanteReseau_t::getOne(htmlspecialchars($_GET['id']));
            $re = $re->fetch();
            if ($re['Id']) {
                $id = $re['Id'];
                $nom = $re['nom'];
                $abreviation = $re['abreviation'];
                $date_creation = $re['date_creation'];
                $description_reseau = $re['description_reseau'];
                $traitement = $traitement . "constante_reseau_t.php?update=true&id_updates=$id";
                $titre = 'Modifier Le Tarif';
            }
        } else {
            $traitement = $traitement . "constante_reseau_t.php?ajout=true";
            include_once('traitement/constante_reseau_t.php');
            $ex_constante_reseau = ConstanteReseau_t::getConstanteActive();
            if ($ex_constante_reseau != null) {
                $id = $ex_constante_reseau['id'];
                $prix_metre_cube_eau = $ex_constante_reseau['prix_metre_cube_eau'];
                $prix_entretient_compteur = $ex_constante_reseau['prix_entretient_compteur'];
                $prix_tva = $ex_constante_reseau['prix_tva'];
                $date_creation = $ex_constante_reseau['date_creation'];
                $est_actif = $ex_constante_reseau['est_actif'];
                $description = $ex_constante_reseau['description'];
                $titre = 'Ajourner les tarifs reseau';

            }

        }
        ?>
        <div class="input-group mb-3">
            <span class="input-group-text w-50 new-width" id="inputGroup-sizing-default">Metre cube</span>
            <input type="number" required class="form-control"
                   value="<?php echo isset($prix_metre_cube_eau) ? $prix_metre_cube_eau : '' ?>"
                   name="prix_metre_cube_eau" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-50" id="inputGroup-sizing-default">Entretient compteur</span>
            <input type="number" class="form-control"
                   value="<?php echo isset($prix_entretient_compteur) ? $prix_entretient_compteur : '' ?>"
                   name="prix_entretient_compteur" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-50" id="inputGroup-sizing-default">TVA</span>
            <input type="" step="0.01" min="0" required class="form-control" id="date_creation"
                   value="<?php echo isset($prix_tva) ? $prix_tva : '' ?>" name="prix_tva"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <!-- <span class="input-group-text w-25" id="inputGroup-sizing-default">PrixsemBS</span> -->
            <textarea type="text" class="form-control" value="<?php echo isset($description) ? $description : '' ?>"
                      name="description" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default"
                      placeholder="Faites un commentaire"></textarea>
        </div>

        <?php
//        ob_start();
//        tarif_t::getAll('Liste des Tarifs');
//        $liste = ob_get_clean();
    }  elseif ($nom_formulaire == 'logup') {
        $traitement = $traitement . "admin_t.php?ajout=true";
        $titre = 'Nouveau Compte';
        ?>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Nom<span
                        style="color: red; font-weight: bold">*</span></span>
            <input type="text" class="form-control" value="<?= $NomLocataire ?? '' ?>" name="nom"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Prenom<span
                        style="color: red; font-weight: bold">*</span></span>
            <input type="text" class="form-control" value="<?= $PrenomLocataire ?? '' ?>" name="prenom"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Email<span
                        style="color: red; font-weight: bold">*</span></span>
            <input type="email" class="form-control" value="<?= $CodePostalLocataire ?? '' ?>" name="email"
                   placeholder="email@gmail.com" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="d-flex">
            <div class="input-group mb-3">
                <span class="input-group-text w-25" id="inputGroup-sizing-default">Cle admin<span
                            style="color: red; font-weight: bold">*</span></span>
                <input type="text" class="form-control" value="<?= $NumTel1Locataire ?? '' ?>" required
                       placeholder="Cle Unique" name="cle_admin" aria-label="Sizing example input"
                       aria-describedby="inputGroup-sizing-default">
            </div>
        </div>

        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Password<span
                        style="color: red; font-weight: bold">*</span></span>
            <input type="password" class="form-control" value="<?= $NumTel2Locataire ?? '' ?>" placeholder=""
                   name="confirm" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Confirm<span
                        style="color: red; font-weight: bold">*</span></span>
            <input type="password" class="form-control" value="<?= $VilleLocataire ?? '' ?>" name="password"
                   aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <a href="index.php?form=login">J'ai deja un compte</a>
        <?php

    } elseif ($nom_formulaire == 'login') {
        $traitement = $traitement . "admin_t.php?login=true";
        $titre = 'Connectez Vous';
        ?>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Email<span
                        style="color: red; font-weight: bold">*</span></span>
            <input type="email" class="form-control" value="<?= $CodePostalLocataire ?? '' ?>" name="email"
                   placeholder="email@gmail.com" aria-label="Sizing example input"
                   aria-describedby="inputGroup-sizing-default">
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text w-25" id="inputGroup-sizing-default">Password<span
                        style="color: red; font-weight: bold">*</span></span>
            <input type="password" class="form-control" value="<?= $NumTel2Locataire ?? '' ?>" placeholder=""
                   name="password" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-default">
        </div>
        <a href="index.php?form=logup">Je n'ai pas de compte</a>
        <?php
    } else {
        $valide = false;
        echo "<p>
        erreur 404: le formulaire que vous recherchez n'existe pas
        </p>";
    }
    $codeHtml = ob_get_clean();

    if ($valide) {
        ?>

        <div class=" d-flex align-items-center justify-content-center pt-5 ">
            <div class="text-center col-12 col-md-7 col-lg-6 col-xl-5 ">
                <form method="post" action="<?php echo $traitement ?>" enctype="multipart/form-data">
                    <h2><?php echo $titre ?></h2>
                    <hr>
                    <?php
                    echo $codeHtml;
                    ?>
                    <div class="d-flex justify-content-end">
                        <button type="reset" class="btn btn-danger me-3">Vider</button>
                        <button type="submit" class="btn btn-primary">Valider</button>
                    </div>
                </form>
            </div>
        </div>


        <?php
    }


//    echo  $codeHtml;

}
