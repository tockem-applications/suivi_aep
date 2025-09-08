<?php
//include_once("donnees/tarif.php");

@include_once("../donnees/mois_facturation.php");
@include_once("donnees/mois_facturation.php");
@include_once("../donnees/constante_reseau.php");
@include_once("donnees/constante_reseau.php");
@include_once("traitement/facture_t.php");
@include_once("traitement/backup_t.php");
@include_once("backup_t.php");
@include_once("../donnees/impaye.php");
@include_once("donnees/impaye.php");
@include_once("../donnees/aep.php");
@include_once("donnees/aep.php");

/*$lettreMonth = array(
    '01'=>'Janvier',
    '02'=>'Fevrier',
    '03'=>'Mars',
    '04'=>'Avril',
    '05'=>'Mai',
    '06'=>'Juin',
    '07'=>'juiller',
    '08'=>'Aout',
    '09'=>'Septembre',
    '10'=>'Octobre',
    '11'=>'Novembre',
    '12'=>'Decembre'
    
);*/



function generateOptions($options, $selectedValue) {
    $html = '';

    foreach ($options as $key => $value) {
        $isSelected = ($key === $selectedValue) ? ' selected' : '';

        $html .= "<option value='$key' $isSelected>$value</option>";
    }

    return $html;
}

function generateOptionGraphique( $selectedValue)
{
    $listeGraphiques = array(
        'line' => 'Graphique en ligne',
        'bar' => 'Graphique à barres',
        'column' => 'Graphique à colonnes',
        'pie' => 'Graphique en secteurs',
        'doughnut' => 'Graphique en anneau',
        'area' => 'Graphique en surface',
        'bubble' => 'Graphique à bulles',
        'scatter' => 'Graphique à scatter',
//        'candlestick' => 'Graphique à candlestick',
//        'radar' => 'Graphique à radar',
//        'histogram' => 'Graphique à histogramme',
//        'stackedArea100' => 'Graphique à stackedArea100',
//        'stackedBar' => 'Graphique à stackedBar',
//        'rangeSplineArea' => 'Graphique à rangeSplineArea',
//        'ohlc' => 'Graphique à ohlc',
//        'waterfall' => 'Graphique à waterfall'
    );
    return generateOptions($listeGraphiques, $selectedValue);
}


class MoisFacturation_t
{

    public static function ajout()
    {
        if (isset($_GET['ajout'])) {
            if (!isset($_FILES['fichier_index'])) {
                header("location: ../index.php?page=releves&operation=error&message=Veillez selectionner le fichier des index");
            }
            echo 'ppppppppppppppppppppppppppp' . $_POST['description'];
            if (isset($_POST['mois'], $_POST['id_constante'], $_POST['description'])) {
                $mois = htmlspecialchars($_POST['mois']);
                $id_constante = htmlspecialchars($_POST['id_constante']);
                $description = htmlspecialchars($_POST['description']);
                $mois_en_lettre = getLetterMonth($mois);
                $est_dernier = MoisFacturation::mois_est_dernier($mois, $_SESSION['id_aep']);
                if (!$est_dernier) {
                    header("location: ../index.php?page=releves&operation=error&message=le mois << $mois_en_lettre >> à déjà été traversé.");
                    exit();
                }
                $mois_exist = MoisFacturation::mois_exist($mois, $_SESSION['id_aep']);
                if (!$mois_exist) {
                    header("location: ../index.php?page=releves&operation=error&message=le mois de << $mois_en_lettre >> est deja present.");
                    exit();
                }
                $nouveau_mois_facturation = new MoisFacturation(0, $mois
                    , date('d/m/Y'), date('d/m/Y'),
                    $id_constante,
                    $description, 1);
                $file_path = MoisFacturation::uploadImage('fichier_index');
                if ($file_path != '') {
                    $file_content = file_get_contents($file_path);
                    $data = json_decode($file_content, true);
//                    var_dump($data['releve']['data']);
                    $id_constante = ConstanteReseau::getIdConstanteActive($_SESSION['id_aep']);
                    var_dump($data['releve'][0]['data']);
                    if(isset($data['info_reseau']['id_reseau'], $data['info_reseau']['nom_reseau'])){
                        $id_reseau_input = $data['info_reseau']['id_reseau'];
                        $nom_reseau_input = $data['info_reseau']['nom_reseau'];
                        $aep_value = Aep::getOne($id_reseau_input, 'aep');
                        $aep_value = $aep_value->fetchAll();
                        if(count($aep_value) != 1 || $id_reseau_input != $_SESSION['id_aep']){
                            header("location: ../index.php?page=releves&operation=error&message=Les données que vous souhaitez enregistrer ne sont pas celles de ce reseau");
                            exit();
                        }                    
                    }else{
                        header("location: ../index.php?page=releves&operation=error&message=Veuillez importer un fichier de releve valide");
                        exit();
                    }
                    
                    $res = $nouveau_mois_facturation->ajouternouvelleListeFacture($data['releve'][0]['data'], $_SESSION['id_aep']);
                    var_dump($id_constante);
                    if (!$res)
                        header("location: ../index.php?page=releves&operation=error&message=le mois << $mois_en_lettre >> a deja été ajouté");
                    else
                        header("location: ../index.php?page=releves&operation=succes&id_selected_month=$mois&id_constante=$id_constante&id_mois=$res");

                }
            }
        }
    }


    /**
     * Met à jour un mois de facturation à partir des données du formulaire.
     * @param int $id ID du mois à mettre à jour
     * @param string $mois_input Mois au format YYYY-MM
     * @param string $description Nouvelle description
     * @return array Résultat avec succès et message
     */
    public static function update()
    {
        //traitement/mois_facturation_t.php?update_mois=true&id_update=<?php echo $id;
//        var_dump($_POST);
//        var_dump(isset($_GET['update_mois'], $_GET['id_update_mois'], $_POST['mois'], $_POST['description']));
        if (!isset($_GET['update_mois'], $_GET['id_update_mois'], $_POST['mois'], $_POST['description']))
            return;
        $id = $_GET['id_update_mois'];
        $mois_input = htmlspecialchars($_POST['mois']);
        $description = htmlspecialchars($_POST['description']);

        try {
            // Validation des paramètres
            if (!is_numeric($id) || $id <= 0) {
                header("location: ../index.php?list=mois_facturation&operation=error&message=ID du mois invalide.");
//                throw new Exception("ID du mois invalide.");
            }
            if (empty($mois_input) || !preg_match('/^\d{4}-\d{2}$/', $mois_input)) {
                header("location: ../index.php?list=mois_facturation&operation=error&message=Format du mois invalide (attendu : YYYY-MM).");
//                throw new Exception("Format du mois invalide (attendu : YYYY-MM).");
            }
            if (empty($description)) {
                header("location: ../index.php?list=mois_facturation&operation=error&message=La description ne peut pas être vide.");
//                throw new Exception("La description ne peut pas être vide.");
            }


            // Nettoyer la description
            $description = trim(strip_tags($description));
            if (strlen($description) > 255) {
                header("location: ../index.php?list=mois_facturation&operation=error&message=La description ne doit pas dépasser 255 caractères.");
//                throw new Exception("La description ne doit pas dépasser 255 caractères.");
            }

            $res = MoisFacturation::updateMois($id, $mois_input, $description, $_SESSION['id_aep']);


            if (!$res)
                header("location: ../index.php?list=mois_facturation&operation=error&message=le mois  a deja été ajouté");
            else
                header("location: ../index.php?list=mois_facturation&operation=succes&message=modification effectué avec succes");

        } catch (Exception $e) {


        }
    }

    public static function delete()
    {
        if (isset($_GET['id_delete'])) {
            $id = $_GET['id_delete'];
            /*$res = (new Tarif($id, '', '' ))->delete($id);
            if (!$res)
                header("location: ../presentation/index.php?list=tarif&operation=error");
            else
                header("location: ../presentation/index.php?list=tarif&operation=succes");
            */
        }
    }

    public static function findUpadate()
    {
        if (isset($_GET['id_update'])) {
            $id = $_GET['id_update'];
            header("location: ../presentation/index.php?form=tarif&id=$id");
        }
    }

    public static function getOne($id)
    {
        return MoisFacturation::getOne('Tarif', $id);
    }

    public static function getAll($titre = "Liste", $id_name = 'Id', $debut = 0, $action = false)
    {

        $req = MoisFacturation::getAll('Tarif');
        $req = $req->fetchAll();
        if (isset($req[0])) {
            ?>

            <!--            ceation de l'entete du tableau      -->
            <table class="table table-striped">
                <thead>
                <h3 style="text-align: center; margin-top: 20px;">
                    <?= $titre ?>
                </h3>
                </thead>
                <tr>
                    <?php
                    $i = 0;
                    foreach ($req[0] as $cle => $val) {
                        if ($i % 2 == 1) {
                            $i++;
                            continue;
                        }
                        $i++;
                        echo "<th>$cle</th>";
                        //<script>alert('$val')</script>
                    }
                    echo "</tr>";
                    foreach ($req as $donnees) {
                        $id = $donnees[$id_name];
                        echo "<tr onclick='affiche($id)'> <a href=?id='.$donnees[$id_name].'>";
                        $i = 0;
                        foreach ($donnees as $valeur) {
                            if ($i % 2 == 1) {
                                $i++;
                                continue;
                            }
                            $i++;
                            echo "<td>$valeur</td>";
                        }
                        if (!$action) {
                            ?>
                            <td><a href="../traitement/tarif_t.php?id_delete=<?= $id ?>">delete</a></td>
                            <td><a href="../traitement/tarif_t.php?id_update=<?= $id ?>">update</a></td>
                            <?php
                        }
//                echo "<td><a href='../presentation/traitement/t_news.php?id_new=$id'>Supprimer</a></td> <td><a href='?id_update=$id'>Modifier</a></td> <td><a alt='ajuter au panier' title='ajuter au panier' href='../presentation/traitement/produit.php?ajouter_panier=$id'>Add</a></td>";
                        echo '</a></tr>';
                    }
                    ?>
            </table>
            <?php
        } else {
            ?>
            <div style="text-align: center">
                <h3 style="text-align: center; margin-top: 20px;">
                    Fin de Liste
                </h3>
            </div>
            <?php
            return 0;
        }
        return 1;
    }

    public static function createTable($htmlTableCode, $titre = 'liste', $autre_entete = '')
    {
        ?>
        <table class="table table-striped table-bordered">
            <thead>
            <h3 style="text-align: center; margin-top: 20px;">
                <?php echo $titre ?>
            </h3>
            <?php echo $autre_entete ?>
            </thead>
            <tbody>
            <?php echo $htmlTableCode; ?>
            </tbody>
        </table>
        <?php
    }

    public static function getListeMoisFacturationimple()
    {
        $req = MoisFacturation::getAll('mois_facturation');
        $req = $req->fetchAll();
        ob_start();
        ?>
        <tr>
            <th>Id</th>
            <th>Nom et Prenom</th>
            <th>N° Telephone</th>
            <th>N° Compteur</th>
            <th>Reseau</th>
            <th>Index</th>
            <th>Etat</th>

        </tr>
        <?php
        foreach ($req as $data) {
            ?>
            <tr <?php ?>>
                <td> <?php echo $data['id'] ?></td>
                <td class="table_link"><a href="?page=info_abone&id=<?php echo $data['id'] ?>"
                                          style="color:black;"><?php echo $data['nom'] ?></a></td>
                <td> <?php echo $data['numero_telephone'] ?></td>
                <td> <?php echo $data['numero_compteur'] ?></td>
                <td> <?php echo $data['reseau'] ?></td>
                <td> <?php echo $data['derniers_index'] ?></td>
                <td class="<?php echo $data['etat'] == 'actif' ? '' : 'bg-danger' ?>"> <?php echo $data['etat'] == 'actif' ? 'ACTIF' : 'NON ACTIF' ?></td>
            </tr>
            <?php
        }
        echo '<a class=dropdown-item" href="?form=abone"> Ajouter un aboné</a>';
        $codeHtml = ob_get_clean();
        self::createTable($codeHtml, 'Liste des MoisFacturation', "<a href='traitement/moisfacturation_t.php?action=export_index' target='_blank'>Telecharger les index</a><br>");
    }


    public static function readJson()
    {
        $req = json_decode(file_get_contents('../donnees/mbou.json'));
        var_dump($req);
    }

    public static function handelGetListmoisFacturation()
    {
        if (isset($_GET["get_mois_facturation"], $_POST["mois_facturation"], $_POST['date_depot'], $_POST['date_releve'])) {

            $month = htmlspecialchars($_POST["mois_facturation"]);
            $date_depot = htmlspecialchars($_POST["date_depot"]);
            $date_releve = htmlspecialchars($_POST["date_releve"]);
            if($date_depot < $date_releve) {
                header("location: ".$_SERVER['HTTP_REFERER']."&operation=error&message=la dade depot est anterieur a celle de releve");
            }
//            exit();
            $tab = explode("-", $month);
            $id_mois = (int)$tab[0];
            $id_constante = (int)$tab[1];
            //var_dump($_POST);
            if ($date_depot != '')
                MoisFacturation::updateDateDepot($id_mois, $date_depot, $date_releve);
            //header("location: ../index.php?list=facture_month&operation=succes&id_mois=$id_mois&id_constante=$id_constante&id_selected_month=$id_mois");
            header("location: ../index.php?list=liste_facture_month&id_mois=$id_mois&id_constante=$id_constante&id_selected_month=$id_mois");

        }
    }


    public static function getoption($item = 0)
    {

        $req = MoisFacturation::getOrderedMonthList($_SESSION['id_aep']);
        $req = $req->fetchAll();
        foreach ($req as $ligne) {
            $id = $ligne['id'];
            //var_dump($ligne);
            $mois = $ligne['mois'];
            $id_constante = $ligne['id_constante'];
            $mois_en_lettre = getLetterMonth($mois);
            echo "<option value='$id-$id_constante' " . ($id == $item ? 'selected' : '') . " >$mois_en_lettre</option>";

        }
    }

    public static function getOnlyOption($item = 0)
    {

        $req = MoisFacturation::getOrderedMonthList($_SESSION['id_aep']);
        $req = $req->fetchAll();
        foreach ($req as $ligne) {
            $id = $ligne['id'];
            //var_dump($ligne);
            $mois = $ligne['mois'];
            $id_constante = $ligne['id_constante'];
            $mois_en_lettre = getLetterMonth($mois);
            echo "<option value='$id' " . ($id == $item ? 'selected' : '') . " >$mois_en_lettre</option>";

        }
    }

    public static function getAllMonth($item = 0)
    {

        $req = MoisFacturation::getOrderedMonthList($_SESSION['id_aep']);
        $req = $req->fetchAll();
        foreach ($req as $ligne) {
            $id = $ligne['id'];
            //var_dump($ligne);
            $mois = $ligne['mois'];
            $id_constante = $ligne['id_constante'];
            $mois_en_lettre = getLetterMonth($mois);
            echo "<option value='$id-$id_constante' " . ($id == $item ? 'selected' : '') . " >$mois_en_lettre</option>";
//            echo "<option value='$id' ".($id == $item?'selected':''). " >$prenom * $nom</option>";

        }
    }

    public static function getoptionXml($item = 0)
    {

        $req = MoisFacturation::getObject2('mois_facturation');
        foreach ($req as $ligne) {
            $id = $ligne['id'];
            $nom = $ligne->PrixsemHS;
            $prenom = $ligne->PrixSemBS;
//            if ($photot != null){
            echo "<option value='$id' " . ($id == $item ? 'selected' : '') . " >$prenom de $nom</option>";
        }
    }

    public static function getListeMoisFacture($id_reseau=0)
    {
        $mois_debut = '';
        $mois_fin = '';
        $type_graphique = 'line';
//        var_dump($_POST);
        if(isset($_GET['choix'], $_POST['mois_debut'], $_POST['mois_fin'] )) {

            $mois_debut = htmlspecialchars($_POST['mois_debut']);
            $mois_fin = htmlspecialchars($_POST['mois_fin']);
//            var_dump($_POST);
//            $type_graphique = htmlspecialchars($_POST['type_graphique']);
        }
            $req = MoisFacturation::getAllMois($mois_debut, $mois_fin, $_SESSION['id_aep'], $id_reseau);
//        $req = MoisFacturation::getOrderedMonthList();
        $req = $req->fetchAll();
        $mois_en_lettre_selectionne = "";
        $id_mois_selectionne = 0;
        $tab_chart = array();
        $expanded = 'false';
        ?>
        <div class="row mb-5">
        <div class=" col-lg-3 col-md-4 col-12 accordion p-0 m-0" id="accordionExample" >
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                    Preciser la periode
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <form method="post" action="index.php?list=mois_facturation&choix=true">
                        <div class="input-group my-1">
                            <span class="input-group-text w-25">Debut</span>
                            <input type="month" class="form-control" name="mois_debut"
                                   value="<?php echo $mois_debut; ?>">
                        </div>
                        <div class="input-group my-1">
                            <span class="input-group-text w-25">Fin</span>
                            <input type="month" class="form-control" name="mois_fin"
                                   value="<?php echo $mois_fin; ?>">
                        </div>

                        <div class=" btn-group my-1 d-flex justify-content-arround">
                            <button type="submit" class="btn btn-primary">Afficher</button>
                            <?php
                                if(isset($_POST['mois_fin'])){
                                    echo '<a target="_blank" href="traitement/facture_t.php?choix=true&mois_debut='.$mois_debut.'&mois_fin='.$mois_fin.'" id="" class="btn btn-success">Télécharger le CSV</a>';
//                                    include_once('traitement/facture_t.php');
                                    Facture_t::exportFactureByInterval();
                                }
//                            ?>
                        </div>
                    </form>
                </div>
            </div>
            <?php
            foreach ($req as $ligne) {
                $id = $ligne['id'];
                //var_dump($ligne);
//            var_dump($ligne);
                $nombre = $ligne['nombre'];
                $est_actif = $ligne['est_actif'];
                $mois = $ligne['mois'];
                $description = $ligne['description'];
                $conso = $ligne['conso'];
                $montant_versee = $ligne['montant_versee'];
                $id_constante = $ligne['id_constante'];
                $mois_en_lettre = getLetterMonth($mois);
                $tab_chart[] = array("mois" => $mois_en_lettre, "montant_verse" => $montant_versee, "conso" => $conso, "nombre" => $nombre, "attendu" => 5000);
                if ($est_actif == '1') {
//                $id_mois_selectionne = $id;
                    $expanded = 'false';
                }
                if (isset($_GET['id_mois'])) {
                    $id_mois_selectionne = $_GET['id_mois'];
                }
                if ($id_mois_selectionne == $id) {
                    $mois_en_lettre_selectionne = $mois_en_lettre;
                    $expanded = 'false';
                }
//            echo create_accordeon($mois_en_lettre, )
                ob_start();
                ?>

                <!--                <div class="card">-->
                <!--                  <div class="card-body">-->
                <p class="card-text"><?php echo htmlspecialchars($description) ?></p>
                <table class="table table table-striped table-active table-bordered">
                    <tr>
                        <th>element</th>
                        <th>Valeur</th>
                    </tr>
                    <tr>
                        <td>Nombre de facture</td>
                        <td><?php echo $nombre ?></td>
                    </tr>
                    <tr>
                        <td>Volume consommé</td>
                        <td><?php echo $conso ?></td>
                    </tr>
                    <tr>
                        <td>Montant versé</td>
                        <td><?php echo $montant_versee ?></td>
                    </tr>
                </table>
                <div class="btn-group">

                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#delete_<?php echo $id ?>">
                        Suprimer
                    </button>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#update_<?php echo $id ?>">
                        Modifier
                    </button>
                    <a href="?list=mois_facturation&id_mois=<?php echo $id ?>" class="btn btn-primary">Afficher</a>
                </div>

                <!-- Modal Bootstrap pour la modification d'un mois -->

                <!-- Modal pour modifier un mois -->
                <div class="modal fade" id="update_<?php echo $id; ?>" tabindex="-1" role="dialog" aria-labelledby="updateModalLabel_<?php echo $id; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="updateModalLabel_<?php echo $id; ?>">Modifier le mois de <?php echo htmlspecialchars($mois_en_lettre); ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="update_form_<?php echo $id; ?>" method="post" action="traitement/mois_facturation_t.php?update_mois=true&id_update=<?php echo $id; ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="mois_<?php echo $id; ?>" class="form-label fw-bold">Mois et Année <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-calendar-month"></i></span>
                                                <input type="month" class="form-control shadow-sm" id="mois_<?php echo $id; ?>" name="mois" value="<?php echo htmlspecialchars($mois); ?>" required>
                                                <!-- Solution de secours pour navigateurs anciens :
                                <select class="form-control shadow-sm" id="mois_<?php echo $id; ?>" name="mois">
                                    <?php
                                                $months = array(
                                                    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
                                                    7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                                                );
                                                foreach ($months as $value => $name) {
                                                    $selected = ($value == $mois) ? 'selected' : '';
                                                    echo "<option value=\"$value\" $selected>$name</option>";
                                                }
                                                ?>
                                </select>
                                -->
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="description_<?php echo $id; ?>" class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-info-circle"></i></span>
                                                <textarea class="form-control shadow-sm" id="description_<?php echo $id; ?>" name="description" rows="3" placeholder="Entrez une description" required><?php echo htmlspecialchars($description); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary w-100 shadow-sm mt-3">Modifier</button>
                                <button type="button" class="btn btn-secondary w-100 shadow-sm" data-bs-dismiss="modal">Annuler</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal pour supprimer un mois -->
                <div class="modal fade" id="delete_<?php echo $id; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel_<?php echo $id; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteModalLabel_<?php echo $id; ?>">Suppression de <?php echo htmlspecialchars($mois_en_lettre); ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="font-weight-bold">Voulez-vous vraiment supprimer le mois de <?php echo htmlspecialchars($mois_en_lettre); ?> ?</p>
                                <p class="text-danger font-weight-bold">Cette action sera irréversible.</p>
                                <div class="form-group">
                                    <label for="confirmation_text_<?php echo $id; ?>" class="form-label fw-bold text-danger">Veuillez taper <strong>SUPPRIMER</strong> pour confirmer :</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-exclamation-circle"></i></span>
                                        <input type="text" class="form-control shadow-sm" id="confirmation_text_<?php echo $id; ?>" placeholder="Tapez SUPPRIMER">
                                    </div>
                                </div>

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger w-100 shadow-sm" id="confirm_delete_<?php echo $id; ?>">Supprimer</button>
                                <button type="button" class="btn btn-secondary w-100 shadow-sm" data-bs-dismiss="modal">Annuler</button>
                            </div>
                            <script>
                                // JavaScript intégré pour gérer la confirmation
                                (function() {
                                    var confirmButton = document.getElementById('confirm_delete_<?php echo $id; ?>');
                                    var inputText = document.getElementById('confirmation_text_<?php echo $id; ?>');
                                    var modal = document.getElementById('delete_<?php echo $id; ?>');
                                    confirmButton.addEventListener('click', function() {
                                        if (inputText.value.trim().toUpperCase() === 'SUPPRIMER') {
                                            window.location.href = 'traitement/mois_facturation_t.php?delete_mois=true&id_delete=<?php echo $id; ?>';
                                        } else {
                                            alert('Veuillez taper exactement "SUPPRIMER" pour confirmer.');
                                            inputText.focus();
                                        }
                                    });
                                    modal.addEventListener('hidden.bs.modal', function() {
                                        inputText.value = '';
                                    });
                                })();
                            </script>
                        </div>
                    </div>
                </div>

                <?php
                echo make_form("traitement/mois_facturation_t.php?delete_mois=true&id_delete=$id",
                    'Suppression de ' . $mois_en_lettre,
                    '<p>voulez vous vraiment supprimer le mois de ' . $mois_en_lettre . '?</p> <p class="text-danger"> Cette action sera ireversible</p></p>',
                    '-1', "delete_$id", '');
                $html_body = ob_get_clean();
                echo create_accordeon($mois_en_lettre, $html_body, $expanded, 'mois_' . $id);
                $expanded = 'false';
            }
            $tab_chart = json_encode($tab_chart);
            //        echo $tab_chart;

            echo '</div>';
            echo '</div>';
            $mois_facturaation = '';
            if (isset($_GET['id_mois'])) {
                $id_mois_selectionne = (int)$_GET['id_mois'];
                $mois_facturaation = MoisFacturation_t::display_mois_facturation($id_mois_selectionne, $mois_en_lettre_selectionne);
            }
            ?>

            <div class="col-lg-9 col-md-8 col-12 my-3" style="min-height: 50vh">

                <?php if ($mois_facturaation == ''): ?>
                    <div class="input-group my-1">
                        <span class="input-group-text w-25" >Type</span>
                        <select type="month" class="form-select" onchange="handle_onchange_graphique(this.value)" name="type_graphique">
                            <?php echo generateOptionGraphique($type_graphique)?>
                        </select>
                    </div>
                    <div class="row">
                        <div id="container2" class="col-12 col-lg-6 mt-3" style="min-height: 250px"></div>
                        <div id="container1" class="col-12 col-lg-6 mt-3" style="min-height: 250px"></div>
                        <div id="container3" class="col-12 col-lg-6 mt-3" style="min-height: 250px"></div>
                        <div id="container4" class=""></div>
                    </div>
                <?php else: ?>
                    <?php echo $mois_facturaation ?>
                <?php endif; ?>
            </div>
        </div>

        <script>
            console.log(<?php echo $tab_chart?>);
            const tab_chart = <?php echo $tab_chart?>;
            //var mois_debut = <?php //echo $mois_debut?>//;
            //var mois_fin = <?php //echo $mois_fin?>//;
            var  type_graphique ="<?php echo $type_graphique?>";
            type_graphique = 'spline';
            displayAllFactureChart(tab_chart, type_graphique);
            const monSelect = document.getElementById('');
            function handle_onchange_graphique(graphique) {
                // alert(graphique)
                displayAllFactureChart(tab_chart, graphique);
            }
        </script>
        <?php
    }

    public static function delete_mois()
    {
        if (!isset($_GET['delete_mois'], $_GET['id_delete']))
            return;
        $mois = MoisFacturation::getMoisById($_GET['id_delete']);
        $mois = $mois->fetchAll(PDO::FETCH_ASSOC);
        if (empty($mois))
            return;
        $mois = $mois[0]['mois'];
        $mois = getLetterMonth($mois);
        $aep_name = $_SESSION['libele_aep'];
        Backup_t::phpSqlDump(Connexion::connect(), Connexion::$db_name ,__DIR__."/../backups/Backup_Avant_Aupression_mois_$aep_name-$mois.sql");
        var_dump($_GET);
        $id = htmlspecialchars($_GET['id_delete']);

        $res = MoisFacturation::deleteMonth($id, $_SESSION['id_aep']);
        var_dump($res);
        header("location: ../index.php?list=mois_facturation");
    }

    public static function display_mois_facturation($id_mois_selectionne, $mois_en_lettre)
    {
        ob_start();
        Facture_t::getTableauFactureByMoisId( $_SESSION['id_aep'], $id_mois_selectionne);
        $table_abone = ob_get_clean();
        $result = '
            <nav style="--bs-breadcrumb-divider: \'>\';" aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?list=mois_facturation">vider</a></li>
                <li class="breadcrumb-item active" aria-current="page">' . $mois_en_lettre . '</li>
              </ol>
            </nav>
            <h1>' . $mois_en_lettre . '</h1>
            ' . $table_abone . '
        ';
        return $result;
    }


}
//var_dump($_POST);
MoisFacturation_t::ajout();
MoisFacturation_t::update();
//exit();
MoisFacturation_t::delete();
MoisFacturation_t::delete_mois();
MoisFacturation_t::findUpadate();;
MoisFacturation_t::handelGetListmoisFacturation();;
//tarif_t::getAll();

    