<?php
@include_once("../donnees/reseau.php");
@include_once("donnees/reseau.php");
@include_once("../traitement/compteur_t.php");
@include_once("traitement/compteur_t.php");


class Reseau_t
{

    public static function ajout()
    {
        if (isset($_GET['ajout'])) {
            var_dump($_POST);
            if (isset($_POST['nom'], $_POST['abreviation'], $_POST['date_creation'], $_POST['description_reseau'])) {
                echo "ooooooooooo";
                $nom = htmlspecialchars($_POST['nom']);
                $abreviation = htmlspecialchars($_POST['abreviation']);
                $date_creation = $_POST['date_creation'] == ''? date('d/m/Y'):htmlspecialchars($_POST['date_creation']);
                $description_reseau = htmlspecialchars($_POST['description_reseau']);
                
                $nouveau_reseau = new Reseau(0, $nom, $abreviation, $date_creation, $description_reseau, $_SESSION['id_aep']);
                var_dump($nouveau_reseau);
//                $compteur = Compteur_t::createCompteurFromPost($_POST);
                $res = $nouveau_reseau->ajouter();
                if (!$res)
                    header("location: ../index.php?form=reseau&operation=error");
                else
                    header("location: ../index.php?page=reseau&id_reseau=$nouveau_reseau->id");
                
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
                $updatedReseau = new Reseau($id, '', '', '', '');
                $res = $updatedReseau->update();
                if (!$res)
                    header("location: ../presentation/index.php?form=reseau&operation=error");
                else
                    header("location: ../presentation/index.php?form=abone&operation=succes");

                // TODO verification d'erreur sur $res

            }
        }
    }
    public static function delete()
    {
        if (isset($_GET['id_delete'])) {
            $id = $_GET['id_delete'];
            $reseau = new Reseau($id, '', '' , '', '');
            $res = $reseau->deleteReseau();
            if (!$res)
                header("location: ../index.php?page=reseau&id_reseau=$id&operation=error");
            else
                header("location: ../index.php?page=reseau&operation=succes");
        }
    }
    public static function findUpadate()
    {
        if (isset($_GET['id_update'])) {
            $id = $_GET['id_update'];
            header("location: ../presentation/index.php?form=tarif&id=$id");
        }
    }

    public static function getOne($id){
        return Reseau::getOne('Reseau', $id);
    }

    public static function getAll($titre = "Liste", $id_name = 'Id', $debut = 0, $action=false)
    {

        $req = Reseau::getAll('Reseau');
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
                if (!$action){
                ?>
                <td><a href="../traitement/tarif_t.php?id_delete=<?=$id?>">delete</a></td>
                <td><a href="../traitement/tarif_t.php?id_update=<?=$id?>">update</a></td>
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

    public static function getoption($item=0)
    {
        $req = Reseau::getAllByIdReseau($_SESSION['id_aep']);
        $req = $req->fetchAll();
        $taille = count($req)/2;
        $i = 0;
        foreach ($req as $ligne){
            $id = $ligne['id'];
            $nom = $ligne['nom'];
            $abreviation = $ligne['abreviation'];
            echo "<option value='$id' ".($id == $item?'selected':''). " >$nom ($abreviation)</option>";
        }
    }

    public static function getoptionXml($item=0)
    {

        $req = Reseau::getObject2('reseau');
        foreach ($req as $ligne){
            $id = $ligne['id'];
            $nom = $ligne->PrixsemHS;
            $prenom = $ligne->PrixSemBS;
//            if ($photot != null){
            echo "<option value='$id' ".($id == $item?'selected':''). " >$prenom de $nom</option>";
        }
    }


    public static function getAllReseauFromAepId(){
        $req = Reseau::getAllByIdReseau($_SESSION['id_aep']);
        $tab = array();
        $req = $req->fetchAll();
        return $req;
    }
}

Reseau_t::ajout();
Reseau_t::update();
Reseau_t::delete();
Reseau_t::findUpadate();
//Reseau_t::getoption();
//tarif_t::getAll();


    