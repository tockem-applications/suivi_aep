<?php
//exit();
@include_once("../donnees/Abones.php");
@include_once("donnees/Abones.php");
@include_once("../donnees/facture.php");
@include_once("donnees/facture.php");
@include_once("traitement/reseau_t.php");
@include_once("resau_t.php");

class Abone_t
{

    public static function ajout()
    {
        if (isset($_GET['ajout_abone'])) {
            ob_start();
//            var_dump($_POST);
            echo $_POST['nom'], $_POST['numero_compteur'], $_POST['numero_telephone'], $_POST['id_reseau'], $_POST['derniers_index'], $_POST['etat'];
            if (isset($_POST['nom'], $_POST['numero_compteur'], $_POST['numero_telephone'], $_POST['id_reseau'], $_POST['derniers_index'], $_POST['etat'])) {
                echo "nnnnnnnnnnnnnnnnnnnnnnnnn";
                echo "ooooooooooo";
                /*if ($_POST['nom'] == '' || $_POST['prenom'] == '' || $_POST['numero'] == '') {
                    $_POST['operation_message'] = 'Veuillez saisir tout les champs';
                    header("location: ../index.php?form=abone&operation=error&message=Veuillez saisir tout les champs");
                }*/
                $nom = htmlspecialchars($_POST['nom']);
                $numero_compteur = htmlspecialchars($_POST['numero_compteur']);
                $numero_telephone = htmlspecialchars($_POST['numero_telephone']);
                $numero_compte_anticipation = 100;// htmlspecialchars($_POST['numero_compte_anticipation']);
                $derniers_index = (float) htmlspecialchars($_POST['derniers_index']);
                $id_reseau = htmlspecialchars($_POST['id_reseau']);
                //                $type_compteur = htmlspecialchars($_POST['type_compteur']);
                $etat = htmlspecialchars($_POST['etat']);
                if (empty($nom)) {
                    throw new Exception('Le nom est requis');
                }
                if (strlen($nom) > 128) {
                    throw new Exception('Le nom est trop long (max 128 caractères)');
                }

                if (strlen($numero_compteur) > 16) {
                    throw new Exception('Le numero de compteur est trop long (max 16 caractères)');
                }

                if ($derniers_index < 0) {
                    throw new Exception('Le dernier index ne peu etre inferieur à 0');
                }

                if (empty($numero_telephone)) {
                    throw new Exception('Le numéro de téléphone est requis');
                }
                if (strlen($numero_telephone) > 16) {
                    throw new Exception('Le numéro de téléphone est trop long (max 16 caractères)');
                }

                if (!in_array($etat, array('actif', 'inactif', 'suspendu'))) {
                    throw new Exception('État invalide');
                }

                if ($id_reseau <= 0) {
                    throw new Exception('Réseau invalide');
                }

                // Vérifier que le réseau appartient à l'AEP
                $reseau = Manager::prepare_query(
                    'SELECT * FROM reseau WHERE id = ? AND id_aep = ?',
                    array($id_reseau, $_SESSION['id_aep'])
                )->fetch();

                if (!$reseau) {
                    throw new Exception('Réseau introuvable ou non autorisé');
                }



                $nouvel_abone = new Abones(
                    0,
                    $nom
                    ,
                    $numero_compteur,
                    $numero_telephone,
                    $numero_compte_anticipation,
                    $etat,
                    0,
                    $id_reseau,
                    $derniers_index
                );

                $res = $nouvel_abone->save_abone();
                var_dump($nouvel_abone);
                $nouvel_abone->getAboneIdBy();
                var_dump($nouvel_abone);
                //                exit();
                $text = ob_get_clean();
                if (!$res)
                    header("location: ../index.php?page=abonne&operation=error");
                else
                    header("location: ../index.php?page=info_abone&id=$nouvel_abone->id");
            }
        }
    }

    public static function update()
    {
        if (isset($_GET['update'])) {
            if (isset($_POST['prixsemHS'], $_GET['id_updates'], $_POST['prixSemBS'])) {
                $prixsemHS = htmlspecialchars($_POST['prixsemHS']);
                $id = htmlspecialchars($_GET['id_updates']);
                $prixSemBS = htmlspecialchars($_POST['prixSemBS']);
                /*$res = (new Abones($id, $prixsemHS, $prixSemBS))->update();
                if (!$res)
                    header("location: ../presentation/index.php?list=tarif&operation=error");
                else
                    header("location: ../presentation/index.php?list=tarif&operation=succes");
*/
                // TODO verification d'erreur sur $res

            }
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
        return Abones::getOne($id);
    }

    public static function getAll($titre = "Liste", $id_name = 'Id', $debut = 0, $action = false)
    {

        $req = Abones::getAll('Tarif');
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
                    echo "</table>";
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

    public static function afficheInfoAbone($id_abone)
    {
        $res = Abones::getAllAboneInfoByid($id_abone);
        if (!$res) {
            echo "<h1>Erreur !</h1>";
            return 0;
        }
        $res = $res->fetchAll();
        if (!count($res)) {
            echo "<h1>Aucun Abone Retrouvé </h1/";
            return 0;
        }
        $data = $res[0];
        $idCompteur = $data['id_compteur'];
        //var_dump($data);
        ?>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <h5 class="mb-0 me-2"><?php echo htmlspecialchars($data['nom']); ?></h5>
                            <span class="badge bg-light text-dark">Réseau:
                                <?php echo htmlspecialchars($data['reseau']); ?></span>
                            <?php $etatClass = ($data['etat'] === 'actif') ? 'bg-success' : (($data['etat'] === 'suspendu') ? 'bg-warning' : 'bg-secondary'); ?>
                            <span
                                class="badge <?php echo $etatClass; ?> text-uppercase"><?php echo htmlspecialchars($data['etat']); ?></span>
                        </div>
                        <div>
                            <span class="badge bg-info">Tél: <a class="text-white text-decoration-none"
                                    href="https://wa.me/237<?php echo htmlspecialchars($data['numero_telephone']); ?>"
                                    target="_blank"><?php echo htmlspecialchars($data['numero_telephone']); ?></a></span>
                        </div>
                    </div>
                </div>

                <!-- <div class="fs-4">reseau de <span>Mbou</span></div>
                <div class="fs-4">telephone: <a href="https://wa.me/237654190514">655784982</a></div> -->
                <table class="table table-bordered">
                    <h1 class="text-center text-dark my-3 h1"><?php echo $data['nom'] ?></h1>
                    <thead class="text-center">
                        <tr>
                            <th>Attribut</th>
                            <th>Valeur</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>Reseau</th>
                            <th><?php echo $data['reseau'] ?></th>
                            <th><select name="" class="form-select"
                                    onchange="HandleAboneUpdate(<?php echo $id_abone ?>, 'id_reseau', this.value)" id="">
                                    <option value="0">changer ?</option>
                                    <?php
                                    Reseau_t::getoption($data['id_reseau']);
                                    ?>
                                </select></th>
                        </tr>
                        <tr>
                            <th>Nº compteur</th>
                            <th> <?php echo $data['numero_compteur'] ?> </th>
                            <th> <input type="number" step="0.01" placeholder="modifier le Nº compteur"
                                    onkeyup="HandleAboneUpdateKeyPressedEnter(event, <?php echo $id_abone ?>, 'numero_compteur', this.value)"
                                    class="form-control">
                            </th>
                        </tr>
                        <tr>
                            <th>Numero</th>
                            <th><a data-bs-toggle="tooltip" data-bs-placement="top"
                                    data-bs-title="cliquer pour envoyer un message sur mobile"
                                    href="https://wa.me/237<?php echo $data['numero_telephone'] ?>"><?php echo $data['numero_telephone'] ?></a>
                            </th>
                            <th> <input type="tel" placeholder="modifier le numero" class="form-control"
                                    onkeyup="HandleAboneUpdateKeyPressedEnter(event, <?php echo $id_abone ?>, 'numero_telephone', this.value)">
                            </th>
                        </tr>
                        <tr>
                            <th>Index</th>
                            <th><?php echo $data['derniers_index'] ?></th>
                            <th> <input type="number" step="0.01" placeholder="nouvel index" class="form-control"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    data-bs-title="Il est déconseillé de modifier cet index si l'aboné a déja fait l'objet d'une relève"
                                    onkeyup="HandleAboneUpdateKeyPressedEnter(event, <?php echo $id_abone ?>, 'derniers_index', this.value)">
                            </th>
                        </tr>
                        <tr>
                            <th>Etat</th>
                            <th><?php echo $data['etat'] ?></th>
                            <th> <a href="traitement/abone_t.php?single_update_abone=true&key=etat&value=<?php echo $data['etat'] == 'actif' ? 'non actif' : 'actif' ?>&id_abone=<?php echo $id_abone ?>"
                                    class="btn form-control <?php echo $data['etat'] == 'actif' ? 'btn-danger' : 'btn-primary' ?>">
                                    <?php echo 'Rendre ' . ($data['etat'] == 'actif' ? 'non actif' : 'actif') ?></a></th>
                        </tr>
                        <!--                        <tr>-->
                        <!--                            <th>Type</th>-->
                        <!--                            <th>--><?php //echo strtoupper($data['type_compteur']) ?><!--</th>-->
                        <!--                            <th> <a href="traitement/abone_t.php?single_update_abone=true&key=type_compteur&value=--><?php //echo $data['type_compteur']=='distribution'?'production':'distribution' ?><!--&id_abone=--><?php //echo $id_abone ?><!--" class="btn form-control --><?php //echo $data['type_compteur'] == 'distribution' ? 'btn-success' : 'btn-primary' ?><!--"> --><?php //echo 'Mettre en '.($data['type_compteur']=='production'?'distribution':'production') ?><!--</a></th>-->
                        <!--                        </tr>-->
                        <tr>
                            <th colspan="3" class=""> <input type="text" placeholder="modifier le nom" class="form-control m-0"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    data-bs-title="Modifiez le nom de l'aboné"
                                    onkeyup="HandleAboneUpdateKeyPressedEnter(event, <?php echo $id_abone ?>, 'nom', this.value)">
                            </th>
                        </tr>
                        <tr>
                            <th>Consommation</th>
                            <th colspan="2" class="text-end px-5"><?php echo $data['consommation'] ?> M<sup>3</sup></th>
                        </tr>
                        <tr>
                            <th>Paiement</th>
                            <th colspan="2" class="text-end px-5"><?php echo (int) $data['montant_verse'] ?> FCFA</sub></th>

                        </tr>
                        <!-- <tr>
                            <th>Impayé</th>
                            <th colspan="2" class="text-end px-5"><?php echo ((int) $data['impaye'] - (int) $data['montant_verse']) ?> FCFA</sub></th>
                        </tr> -->
                        <tr>
                            <th colspan="2">Derniers paiement</th>
                            <th class="text-end px-5"><?php echo $data['date_paiement'] ?></sub></th>
                        </tr>
                        <tr>
                            <th colspan="2">nombre de factures</th>
                            <th class="text-end px-5"><?php echo $data['duree'] ?></sub></th>
                        </tr>
                        <tr>
                            <th colspan="3" class=""> <a
                                    href="traitement/abone_t.php?abone_deleting=true&id_abone=<?php echo $id_abone ?>"
                                    placeholder="modifier le nom" class="form-control m-0 bg-danger text-center"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    data-bs-title="Cette action va suprimer definitivement l'abone de la liste"> Suprimer</a>
                            </th>
                        </tr>
                    </tbody>
                </table>
                <?php
                return $idCompteur;
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

    public static function handleSingleFielAboneUpdate()
    {
        if (isset($_GET['single_update_abone']) || isset($_GET['abone_deleting'])) {
            echo "bonjour<br>";
            echo $_SERVER['REQUEST_METHOD'];
            $sendedData = null;
            if ($_SERVER['REQUEST_METHOD'] == 'GET')
                $sendedData = $_GET;
            else
                $sendedData = json_decode(file_get_contents('php://input'), true);
            //self::writeToFile('tito.txt',  '555555555555555555555555');
            if (isset($_GET['abone_deleting'])) {
                if (isset($sendedData['id_abone'])) {
                    $id_abone = (int) htmlspecialchars($sendedData['id_abone']);
                    $res = Abones::deleteAbone($id_abone);
                    if ($res) {
                        header("location: ../index.php?list=abone_simple&message='aboné suprimé'");
                    } else {
                        header("location: ../index.php?page=info_abone&id=$id_abone&operation=error&message=echec de supression de l'aboné");
                    }
                    return;
                }
            } else if (!isset($sendedData['id_abone'], $sendedData['key'], $sendedData['value'])) {
                //                self::writeToFile('tito.txt',  '6666666666666666666666');
                return false;
            }



            $id_abone = (int) htmlspecialchars($sendedData['id_abone']);
            $key = htmlspecialchars($sendedData['key']);
            $value = htmlspecialchars($sendedData['value']);
            if ($key == 'id_reseau')
                $value = (int) $value;
            else if ($key == 'derniers_index')
                $value = (float) $value;

            $res = Abones::updateSingleValue($id_abone, $key, $value);
            if (!$res)
                header("location: ../index.php?page=info_abone&id=$id_abone&operation=error&message=");
            else
                header("location: ../index.php?page=info_abone&id=$id_abone&operation=success");

        }
    }
    public static function getListeAboneSimple($type_compteur = '')
    {
        $req = Abones::getSimpleAbone($_SESSION['id_aep']);
        $req = $req->fetchAll(PDO::FETCH_ASSOC);
        //        var_dump($req);

        if ($type_compteur == 'compteur_reseau') {
            $titre_page = 'Liste de tout les compteurs reseau';
            $req = Abones::getSimpleCompteurReseau($_SESSION['id_aep']);
            $req = $req->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($type_compteur == 'distribution')
            $titre_page = "Liste des abonés";
        elseif ($type_compteur == 'production')
            $titre_page = "Liste des compteurs de production";
        ob_start();
        create_csv_exportation_button(
            $req,
            "liste_abones.csv",
            "Exporter la liste des abonés au format csv"
        );
        ?>
            <tr class="mt-3">
                <!--                <th>Id</th>-->
                <th>Nom et Prenom</th>
                <th>N° Telephone</th>
                <th>N° Compteur</th>
                <th>Reseau</th>
                <th>Index</th>
                <th>Etat</th>
                <!--                <th>Type</th>-->

            </tr>
            <?php
            foreach ($req as $data) {
                ?>
                <tr <?php ?> class=<?php echo $data['etat'] == 'actif' ? '' : 'bg-danger' ?>>
                    <!--                    <td> --><?php //echo $data['id'] ?><!--</td>-->
                    <td class="table_link"> <a
                            href="<?php echo $type_compteur == 'distribution' ? '?page=info_abone&id=' . $data['id'] : '#' ?>"
                            style="color:black;"><?php echo $data['nom'] ?></a></td>
                    <td> <?php echo $data['numero_telephone'] ?></td>
                    <td> <?php echo $data['numero_compteur'] ?></td>
                    <td> <?php echo $data['reseau'] ?></td>
                    <td> <?php echo $data['derniers_index'] ?></td>
                    <td class="<?php echo $data['etat'] == 'actif' ? '' : 'bg-danger' ?>">
                        <?php echo $data['etat'] == 'actif' ? 'ACTIF' : 'NON ACTIF' ?>
                        <!-- <a href="traitement/abone_t.php?single_update_abone=true&key=etat&value=<?php echo $data['etat'] == 'actif' ? 'non actif' : 'actif' ?>&id_abone=<?php echo $data['id'] ?>" class="btn form-control m-0 p-0"> <?php echo 'Rendre ' . ($data['etat'] == 'actif' ? 'non actif' : 'actif') ?></a> -->
                    </td>
                    <!--                    <td> --><?php //echo strtoupper($data['type_compteur']) ?><!--</td>-->
                </tr>
                <?php
            }
            $codeHtml = ob_get_clean();
            self::createTable($codeHtml, $titre_page, "");
    }

    public static function writeToFile($fileName, $content)
    {
        return file_put_contents($fileName, $content);
    }

    public static function readJson()
    {
        $req = json_decode(file_get_contents('../donnees/mbou.json'));
        var_dump($req);
    }

    public static function getoption($item = 0)
    {

        $req = Abones::getAll('Tarif');
        $req = $req->fetchAll();
        foreach ($req as $ligne) {
            $id = $ligne['Id'];
            var_dump($ligne);
            $nom = $ligne['PrixsemHS'];
            $prenom = $ligne['PrixSemBS'];
            //            echo "<option value='$id'>$nom fCFA -> $prenom FCFA</option>";
            echo "<option value='$id' " . ($id == $item ? 'selected' : '') . " >$nom -> $prenom</option>";
            //            echo "<option value='$id' ".($id == $item?'selected':''). " >$prenom * $nom</option>";

        }
    }

    public static function getAllAboneInfoByid($id = 0)
    {

        $req = Abones::getAllAboneInfoByid();
        $req = $req->fetchAll();
        foreach ($req as $ligne) {
            $id = $ligne['Id'];
            var_dump($ligne);
            $nom = $ligne['PrixsemHS'];
            $prenom = $ligne['PrixSemBS'];
            //            echo "<option value='$id'>$nom fCFA -> $prenom FCFA</option>";
            echo "<option value='$id' " . ($id == $id ? 'selected' : '') . " >$nom -> $prenom</option>";
            //            echo "<option value='$id' ".($id == $item?'selected':''). " >$prenom * $nom</option>";

        }
    }

    public static function getoptionXml($item = 0)
    {

        $req = Abones::getObject2('Tarif');
        foreach ($req as $ligne) {
            $id = $ligne['id'];
            $nom = $ligne->PrixsemHS;
            $prenom = $ligne->PrixSemBS;
            //            if ($photot != null){
            echo "<option value='$id' " . ($id == $item ? 'selected' : '') . " >$prenom de $nom</option>";
        }
    }



    public static function getData()
    {
        $file_name = 'donnees/data.csv';
        $handle = fopen($file_name, 'r');
        $tab = array();
        $i = 0;
        while (($donnee = fgetcsv($handle, 1000, ';')) !== false) {
            $i++;

            $a = $donnee;
            $data = array();
            // Convertir l'encodage des données
            foreach ($donnee as &$cellule) {
                $data[] = mb_convert_encoding($cellule, 'UTF-8', 'ISO-8859-1'); // Ajustez l'encodage d'origine si nécessaire
            }
            //            var_dump($data);
            if ($i == 1) {
                continue;
            }
            $abone = new Abones(
                '',
                '' . $data[1],
                '' . $data[0],
                '' . $data[4],
                '' . $data[3],
                'actif',
                '' . $data[6],
                '' . $data[5],
                '' . $data[8]
            ); //$donnee;
            $abone->ajouter();
            //          $facture = new Facture('','','','','','','','','');

        }
        return $tab;
    }

    public static function getJsonDataToExport()
    {
        //        var_dump($_GET);
        if (!isset($_GET['action'], $_GET['id_mois']))
            return;
        elseif ($_GET['action'] != 'export_index')
            return;
        //        echo "<br><br><br><br>ooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooo<br><br><br><br>";
        $req = Abones::getJsonDataFromIdMois($_GET['id_mois']);
        //        $req2 = Abones::getLastmonthIndex($_SESSION['id_aep']);
        //var_dump($req);
        $date_export = new DateTime();
        $data = json_encode($req);
        $all = array(
            "releve" => array(array("nom_feuille" => "nom_aep", "data" => $req)),
            "info_reseau" => array(
                "nom_reseau" => $_SESSION['libele_aep'],
                "id_reseau" => $_SESSION['id_aep'],
                "agent_export" => "Non Disponible",
                "date_export" => $date_export->format('d/m/Y:H/i/s')
            )
        );

        //$all = var_dump($all);
        $data = json_encode($all, 128);
        //echo $data;
        //echo $date_export->format('d/m/Y:H/i/s');
        //$boo = json_decode($data, true);
        //var_dump($boo);
        $fileName = '../donnees/exports/export_index_nom_AEP_' . $date_export->format('d-m-Y_H-i-s') . '.json';
        Abones::writeToFile($fileName, $data);
        Abones::telecharger($fileName);
        header('location: ' . $_SESSION['PREVIOUS_REQUEST_HEADER']);
        exit;
        //        unlink($fileName);
    }

    public static function afficheInputRecouvrementAbone($id_compteur)
    {
        ob_start();
        $req = Abones::getRecouvrementData($id_compteur, $_SESSION['id_aep']);
        $resultats = $req->fetchAll(PDO::FETCH_ASSOC);

        // Préparer données pour graphiques
        $labels = array();
        $facturesArr = array();
        $versesArr = array();
        $restantsArr = array();
        $resteCumuleArr = array();
        $resteCumule = 0;
        foreach ($resultats as $row) {
            $libMois = getLetterMonth($row['mois']);
            $labels[] = $libMois;
            $mt = isset($row['montant_total']) ? (float) $row['montant_total'] : 0;
            $mv = isset($row['montant_verse']) ? (float) $row['montant_verse'] : 0;
            $mr = isset($row['montant_restant']) ? (float) $row['montant_restant'] : max(0, $mt - $mv);
            $facturesArr[] = $mt;
            $versesArr[] = $mv;
            $restantsArr[] = $mr;
            $resteCumule += $mr;
            $resteCumuleArr[] = $resteCumule;
        }

        create_csv_exportation_button(
            $resultats,
            'facturation-abone-' . $_SESSION["libele_aep"] . '.csv',
            "Vous allez exporter les donnees de facturation d'un aboné au format csv"
        );

        // Graphiques (au-dessus du tableau)
        // echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        // echo '<script src="https://unpkg.com/chart.js@4.4.1/dist/chart.umd.js"></script>';
        echo '<div class="card mb-3 col-md-12"><div class="card-header bg-primary text-white"><strong>Comportement de paiement</strong></div><div class="card-body">';
        echo '<div class="row g-3">';
        echo '<div class="col-md-6"><div style="height:320px"><canvas id="abonne_bar_recouvrement' . $id_compteur . '"></canvas></div></div>';
        echo '<div class="col-md-6"><div style="height:320px"><canvas id="abonne_line_cumule' . $id_compteur . '"></canvas></div></div>';
        echo '</div>';
        if (!count($labels)) {
            echo '<div class="text-muted small mt-2">Aucune donnée de recouvrement disponible pour afficher les graphiques.</div>';
        }
        echo '</div></div>';

        // Section pénalité
        include_once ("donnees/mois_facturation.php");
        $moisActif = MoisFacturation::getMoisFacturationActive($_SESSION['id_aep']);
        $moisActifData = $moisActif->fetchAll();
        $moisActifId = count($moisActifData) > 0 ? $moisActifData[0]['id'] : 0;
        $moisActifLibelle = count($moisActifData) > 0 ? getLetterMonth($moisActifData[0]['mois']) : 'Aucun mois actif';

        // Récupérer la pénalité actuelle pour ce compteur et ce mois
        $penaliteActuelle = 0;
        if ($moisActifId > 0) {
            $penaliteReq = Manager::prepare_query("
                SELECT f.penalite 
                FROM facture f 
                INNER JOIN indexes i ON f.id_indexes = i.id 
                WHERE i.id_compteur = ? AND i.id_mois_facturation = ?
            ", array($id_compteur, $moisActifId));
            $penaliteData = $penaliteReq->fetchAll();
            if (count($penaliteData) > 0) {
                $penaliteActuelle = (int) $penaliteData[0]['penalite'];
            }
        }

        echo '<div class="card mb-3">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Gestion des pénalités</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h6 class="text-muted mb-1">Mois actuel</h6>
                            <span class="badge bg-primary fs-6">' . $moisActifLibelle . '</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h6 class="text-muted mb-1">Pénalité actuelle</h6>
                            <span class="badge ' . ($penaliteActuelle > 0 ? 'bg-danger' : 'bg-success') . ' fs-6">' . Facture::formatFinancier($penaliteActuelle) . '</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <form method="POST" action="traitement/abone_t.php" class="d-flex gap-2">
                            <input type="hidden" name="action" value="apply_penalite">
                            <input type="hidden" name="id_compteur" value="' . $id_compteur . '">
                            <input type="hidden" name="id_mois" value="' . $moisActifId . '">
                            <input type="number" name="penalite_montant" class="form-control" value="2500" min="0" step="100" required>
                            <button type="submit" class="btn btn-warning btn-sm" ' . ($moisActifId == 0 ? 'disabled' : '') . '>
                                <i class="fas fa-plus"></i> Pénaliser
                            </button>
                        </form>
                    </div>
                </div>';

        // Bouton d'annulation de pénalité si pénalité > 0 et mois actif
        if ($penaliteActuelle > 0 && $moisActifId > 0) {
            echo '<div class="row mt-3">
                <div class="col-12 text-center">
                    <form method="POST" action="traitement/abone_t.php" class="d-inline">
                        <input type="hidden" name="action" value="cancel_penalite">
                        <input type="hidden" name="id_compteur" value="' . $id_compteur . '">
                        <input type="hidden" name="id_mois" value="' . $moisActifId . '">
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(\'Êtes-vous sûr de vouloir annuler la pénalité ?\')">
                            <i class="fas fa-times"></i> Annuler la pénalité
                        </button>
                    </form>
                </div>
            </div>';
        }

        echo '</div></div>';

        echo '<script>
(function(){
    window.addEventListener("load", function(){
        try {
            if (!window.Chart) {
                var cont = document.getElementById("abonne_bar_recouvrement");
                if (cont) {
                    var p = document.createElement("div");
                    p.className = "text-danger small mt-2";
                    p.textContent = "Chart.js non chargé.";
                    cont.parentNode.appendChild(p);
                }
                return;
            }
            var labels = ' . json_encode($labels) . ';
            var factures = ' . json_encode($facturesArr) . ';
            var verses = ' . json_encode($versesArr) . ';
            var consommations = ' . json_encode(array_map(function ($row) {
            return isset($row["consommation"]) ? (float) $row["consommation"] : 0;
        }, $resultats)) . ';
            if (!Array.isArray(labels) || !labels.length) {
                labels = ["-"]; factures=[0]; verses=[0]; consommations=[0];
            }
            var cA = document.getElementById("abonne_bar_recouvrement' . $id_compteur . '");
            if (cA) {
                var ctxA = cA.getContext("2d");
                new Chart(ctxA, {
                    type: "bar",
                    data: {
                        labels: labels,
                        datasets: [
                            { label: "Montant facturé", data: factures, backgroundColor: "rgba(255,99,132,0.7)", borderColor: "rgba(255,0,0,1)", borderWidth: 1 },
                            { label: "Montant versé", data: verses, backgroundColor: "rgba(50,205,50,0.7)", borderColor: "rgba(0,128,0,1)", borderWidth: 1 }
                        ]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: "bottom" } } }
                });
            }
            var cB = document.getElementById("abonne_line_cumule' . $id_compteur . '");
            if (cB) {
                var ctxB = cB.getContext("2d");
                new Chart(ctxB, {
                    type: "line",
                    data: {
                        labels: labels,
                        datasets: [
                            { label: "Consommation (m³)", data: consommations, borderColor: "rgba(54,162,235,1)", backgroundColor: "rgba(54,162,235,0.15)", tension: 0.2, fill: true }
                        ]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: "bottom" } } }
                });
            }
        } catch (e) {
            console.error(e);
            var root = document.getElementById("abonne_bar_recouvrement' . $id_compteur . '");
            if (root) {
                var p2 = document.createElement("div");
                p2.className = "text-danger small mt-2";
                p2.textContent = e.message;
                root.parentNode.appendChild(p2);
            }
        }
    });
})();
</script>';

        $sum = 0;
        // Affichage des résultats dans un tableau HTML
        if ($resultats) {
            echo '<table class="table table-bordered table-hover">';
            echo '<thead> <div class="d-flex justify-content55.json-center"><h3 class="">Liste des recouvrements <hr></h3> </div>';
            echo '<tr>';
            echo '<th>Mois</th>';
            echo '<th>Penalité</th>';
            echo '<th>Total</th>';
            echo '<th>Montant Versé</th>';
            echo '<th>Reste</th>';
            echo '</tr>';

            //        var_dump($resultats);
            foreach ($resultats as $row) {
                $mois = getLetterMonth($row['mois']);
                $montant_verse = $row['montant_verse'] != '0' ? (int) $row['montant_verse'] : '';
                $impaye = (int) $row['impaye'];
                $prix_tva = $row['prix_tva'];
                $prix_entretient_compteur = $row['prix_entretient_compteur'];
                $avance = $row['montant_restant'];// ($row['montant_restant'])<0?$row['montant_restant']:'0';
                $sum += $montant_verse;
                $prix_metre_cube_eau = $row['prix_metre_cube_eau'];
                $montant_factue = $row['montant_total'];
                $montant_restant = $row['montant_restant'];

                $placeholder = "";
                $bg = "";
                $desabled = '';
                if ($montant_factue == (int) ($montant_verse + 0.00000001)) {
                    $bg = "bg-success-subtle text-success";
                    //                $desabled = 'disabled';
                }
                if ((int) $row['montant_restant'] > 0) {
                    //                    $desabled = 'disabled';
                    $bg = "bg-danger-subtle text-danger";
                    $placeholder = "";
                }
                //echo $montant_factue.'<br>';

                echo "<tr class='  border border-dark' >";
                echo '<td>' . htmlspecialchars($mois) . '</td>';
                echo '<td class="text-end"> ' . htmlspecialchars(Facture::formatFinancier((int) $row['penalite'])) . '</td>';
                echo '<td class="text-end">' . htmlspecialchars(Facture::formatFinancier($montant_factue)) . '</td>';
                //            echo '<td>';

                if ((int) $row['mois_actif']) {
                    echo '<td><input class="form-control border-0 m-0 py-0 ' . $bg . '" ' . $desabled . '
                            value="' . ($placeholder == "" ? htmlspecialchars($montant_verse) : $placeholder) . '" 
                            onchange="handleRecouvrement(this.value, ' . ($placeholder == "" ? $row['id'] : 0) . ' , this.id)" 
                            id="montant_verse2' . $row['id'] . '" type="text"> 
                            <input type="datetime" id="date_releve_facture_' . $row['id'] . 'class="form-control mb-0 " hidden value="' . date('d/m/Y') . '"></td>';
                } else
                    echo '<td class="text-end ' . $bg . '">' . htmlspecialchars(Facture::formatFinancier($montant_verse)) . '</td>';

                echo '<td class="text-end">' . htmlspecialchars(Facture::formatFinancier($avance)) . '</td>';
                echo '</tr>';
                //            onkeyup="handleRecouvrement_pressed_enter(event, this.value, '. $row['id'].')"
            }
            echo '<tr class="  border border-dark" style="font-weight: bold"><td colspan="2">Total</td><td colspan="3" class="text-center">' . htmlspecialchars($sum) . '</td></tr>';
            echo '</table>';
        }
        return ob_get_clean();
    }

    public static function applyPenalite()
    {
        if (isset($_POST['action']) && $_POST['action'] === 'apply_penalite') {
            $id_compteur = (int) $_POST['id_compteur'];
            $id_mois = (int) $_POST['id_mois'];
            $penalite_montant = (int) $_POST['penalite_montant'];

            if ($id_compteur > 0 && $id_mois > 0 && $penalite_montant > 0) {
                try {
                    // Récupérer l'ID de l'index pour ce compteur et ce mois
                    $indexReq = Manager::prepare_query("
                        SELECT i.id 
                        FROM indexes i 
                        WHERE i.id_compteur = ? AND i.id_mois_facturation = ?
                    ", array($id_compteur, $id_mois));
                    $indexData = $indexReq->fetchAll();

                    if (count($indexData) > 0) {
                        $id_indexes = $indexData[0]['id'];

                        // Mettre à jour la pénalité dans la facture
                        $updateReq = Manager::prepare_query("
                            UPDATE facture 
                            SET penalite = ? 
                            WHERE id_indexes = ?
                        ", array($penalite_montant, $id_indexes));

                        if ($updateReq) {
                            $_SESSION['success_message'] = "Pénalité de " . Facture::formatFinancier($penalite_montant) . " appliquée avec succès.";
                            header("location: ../index.php?list=recouvrement&operation=succes");
//                            header("location: ../index.php?list=tarif&operation=error");
                        } else {
                            $_SESSION['error_message'] = "Erreur lors de l'application de la pénalité.";
                            header("location: ../index.php?list=recouvrement&operation=error&message=Erreur lors de l'application de la pénalité");
                        }
                    } else {
                        $_SESSION['error_message'] = "Aucune facture trouvée pour ce compteur et ce mois.";
                        header("location: ../index.php?list=recouvrement&operation=error&message=Aucune facture trouvée pour ce compteur et ce mois.");
                    }
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
                }
            } else {
                $_SESSION['error_message'] = "Données invalides pour l'application de la pénalité.";
                header("location: ../index.php?list=recouvrement&operation=error&message=Données invalides pour l'application de la pénalité.");
            }
        }
    }

    public static function cancelPenalite()
    {
        if (isset($_POST['action']) && $_POST['action'] === 'cancel_penalite') {
            $id_compteur = (int) $_POST['id_compteur'];
            $id_mois = (int) $_POST['id_mois'];

            if ($id_compteur > 0 && $id_mois > 0) {
                try {
                    // Récupérer l'ID de l'index pour ce compteur et ce mois
                    $indexReq = Manager::prepare_query("
                        SELECT i.id 
                        FROM indexes i 
                        WHERE i.id_compteur = ? AND i.id_mois_facturation = ?
                    ", array($id_compteur, $id_mois));
                    $indexData = $indexReq->fetchAll();

                    if (count($indexData) > 0) {
                        $id_indexes = $indexData[0]['id'];

                        // Annuler la pénalité (mettre à 0)
                        $updateReq = Manager::prepare_query("
                            UPDATE facture 
                            SET penalite = 0 
                            WHERE id_indexes = ?
                        ", array($id_indexes));

                        if ($updateReq) {
                            $_SESSION['success_message'] = "Pénalité annulée avec succès.";
                            header("location: ../index.php?list=recouvrement&operation=succes");
                        } else {
                            $_SESSION['error_message'] = "Erreur lors de l'annulation de la pénalité.";
                            header("location: ../index.php?list=recouvrement&operation=error&message=Erreur lors de l'annulation de la pénalité");
                        }
                    } else {
                        $_SESSION['error_message'] = "Aucune facture trouvée pour ce compteur et ce mois.";
                        header("location: ../index.php?list=recouvrement&operation=error&message=Aucune facture trouvée pour ce compteur et ce mois.");
                    }
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
                    header("location: ../index.php?list=recouvrement&operation=error&message=".$e->getMessage());
                }
            } else {
                $_SESSION['error_message'] = "Données invalides pour l'annulation de la pénalité.";
                header("location: ../index.php?list=recouvrement&operation=error&message=Données invalides pour l'annulation de la pénalité.");
            }
        }
    }

}

//var_dump($_POST);
Abone_t::ajout();
Abone_t::update();
Abone_t::delete();
Abone_t::findUpadate();
Abone_t::handleSingleFielAboneUpdate();
Abone_t::getJsonDataToExport();
Abone_t::applyPenalite();
Abone_t::cancelPenalite();
//tarif_t::getAll();


