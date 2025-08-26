<?php
@include_once("../donnees/constante_reseau.php");
@include_once("donnees/constante_reseau.php");
//echo "merci";
class ConstanteReseau_t
{

    public static function ajout()
    {
        if (isset($_GET['ajout'])) {
            if (isset($_POST['prix_metre_cube_eau'], $_POST['prix_entretient_compteur'], $_POST['prix_tva'], $_POST['description'])) {
                echo "ooooooooooo";
                $prix_metre_cube_eau = htmlspecialchars($_POST['prix_metre_cube_eau']);
                $prix_entretient_compteur = htmlspecialchars($_POST['prix_entretient_compteur']);
                $prix_tva = htmlspecialchars($_POST['prix_tva']);
                $date_creation = date('d/m/Y');
                $est_actif = true;
                $description = htmlspecialchars($_POST['description']);
                echo $_POST['prix_tva'];
                $nouveau_constante_reseau = new ConstanteReseau(0, $prix_metre_cube_eau
                    , $prix_entretient_compteur, $prix_tva
                    , $date_creation, $est_actif, $description, $_SESSION['id_aep']);
                var_dump($nouveau_constante_reseau);
                $res = $nouveau_constante_reseau->ajouterEtActiver();
                if (!$res)
                    header("location: ../index.php?form=constante_reseau&operation=error");
                else
                    header("location: ../index.php?form=constante_reseau&operation=succes");

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
            $reseau = new Reseau($id, '', '', '', '');
            $res = $reseau->delete();
            if (!$res)
                header("location: ../presentation/index.php?list=tarif&operation=error");
            else
                header("location: ../presentation/index.php?list=tarif&operation=succes");
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
        return ConstanteReseau::getOne('Reseau', $id);
    }

    public static function getConstanteActive()
    {
        $res = ConstanteReseau::getConstanteActive($_SESSION['id_aep']);
        $data = $res->fetchAll();
        if ($res->rowCount() == 0) {
            return null;
        }
        return $data[0];
    }

}
ConstanteReseau_t::ajout();
ConstanteReseau_t::update();
ConstanteReseau_t::delete();
ConstanteReseau_t::findUpadate();
//Reseau_t::getoption();
//tarif_t::getAll();


    