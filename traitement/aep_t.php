<?php

@include_once("../donnees/aep.php");
@include_once("donnees/aep.php");


class Aep_t
{

    public static function ajout()
    {
        if (isset($_GET['ajout'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                var_dump($_POST);
                // Récupérer et sécuriser les données
                $libele = htmlspecialchars(trim($_POST['libele']));
                $numero_compte = htmlspecialchars(trim($_POST['numero_compte']));
                $nom_banque = htmlspecialchars(trim($_POST['nom_banque']));
                $date = htmlspecialchars(trim($_POST['date']));
                $description = htmlspecialchars(trim($_POST['description']));
                $fichier_facture = htmlspecialchars(trim($_POST['fichier_facture']));

                // Valider les données (ajoutez d'autres validations si nécessaire)
                if (empty($libele) || empty($date) || empty($description) || empty($fichier_facture)) {
//                    die("Tous les champs sont requis.");
                    header("location: ../index.php?form=aep&operation=error&message=veuillez saisir tout les champs");
                }
                $nouvel_aep = new Aep('', $libele, $fichier_facture, $date, $description, $nom_banque, $numero_compte);
                var_dump($date);
                var_dump($nouvel_aep->getDonnee());
                $res = $nouvel_aep->ajouter();
                if (!$res)
                    header("location: ../index.php?form=aep&operation=error&message=Une erreur es survenu lors de l'enregidtrement");
                else
                    header("location: ../index.php?page=home&operation=succes");

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

    public static function getAll(){
        $res = Aep::getAll('aep');
        return $res->fetchAll();
    }

    public static function select_aep(){
        //<a href="traitement/aep_t.php?select_ape=true&id_aep=<?php echo $aep['id'];" class="btn btn-primary">Selectionner</a>
//        var_dump($_SESSION);
        if(!isset($_GET['select_aep']))
            return;
        if (isset($_GET['id_aep'])) {
            var_dump($_GET);
            $id_aep = htmlspecialchars(trim($_GET['id_aep'])); // Sécuriser l'entrée
            unset($_SESSION['id_aep']);
            unset($_SESSION['libele_aep']);
            if($id_aep == 0){
                header("Location: ../index.php?page=home&operation=succes"); // Changez ceci pour l'URL que vous souhaitez
                exit();
            }
            $res = Aep::getOne($id_aep, 'aep');
            $res = $res->fetch();
            if($res) {
                $_SESSION['id_aep'] = $id_aep; // Placer l'ID dans la session
                $_SESSION['libele_aep'] = $res['libele'];
                $_SESSION['PREVIOUS_REQUEST_HEADER'] = isset($_SESSION['PREVIOUS_REQUEST_HEADER'])?$_SESSION['PREVIOUS_REQUEST_HEADER']:'';
                header('Location: ../index.php?page=aep_dashboard&aep_id='.$id_aep); // Changez ceci pour l'URL que vous souhaitez
                return true;
            }else
                header("Location: ../index.php?page=home&operation=error&message=Cet Aep est innexistant"); // Changez ceci pour l'URL que vous souhaitez

            // Rediriger vers une page de confirmation ou vers la liste des AEP

            exit();
        } else {
            // Gérer le cas où l'ID n'est pas présent
            echo "ID AEP non spécifié.";
        }
    }

    public static function isAepIdInSession() {
        //session_start(); // Démarrer la session si ce n'est pas déjà fait
        if( isset($_SESSION['id_aep'], $_SESSION['libele_aep'])) {
            if($_SESSION['libele_aep'] != '' && $_SESSION['id_aep'] != '' )
                return true;
            else{
                unset($_SESSION['libele_aep']);
                return self::isAepIdInSession();
            }
        }
        else if( isset($_SESSION['id_aep'])) // Retourne true si l'id_aep est présent, sinon false
        {
            $id_aep = ((int)$_SESSION['id_aep']).'';
            $res = Aep::getOne($id_aep, 'aep');
            $res = $res->fetch();
            if($res) {
                $_SESSION['libele_aep'] = $res['libele'];
                return true;
            } else {
                return false;
            }
        }
        $res = Aep::getAll('aep');
        $res = $res->fetchAll();
        if(count($res)==1) {
            $_SESSION['libele_aep'] = $res[0]['libele'];
            $_SESSION['id_aep'] = $res[0]['id'];
            return true;
        }
        return false;
    }

    public static function findUpadate()
    {
        if (isset($_GET['id_update'])) {
            $id = $_GET['id_update'];
            header("location: ../presentation/index.php?form=tarif&id=$id");
        }
    }
}

Aep_t::ajout();
Aep_t::select_aep();
