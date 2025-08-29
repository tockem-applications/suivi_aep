<?php

@include_once("../donnees/ressource_humaine.php");
@include_once("donnees/ressource_humaine.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class RessourceHumaine_t
{
    public static function handlePost()
    {
        if (!isset($_POST['action']))
            return;
        $action = $_POST['action'];
        $aep_id = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
        if ($aep_id <= 0)
            return;

        $success = 'ok';
        if ($action == 'create') {
            $rh = new RessourceHumaine(
                0,
                $aep_id,
                trim($_POST['nom']),
                isset($_POST['fonction']) ? trim($_POST['fonction']) : '',
                isset($_POST['competences']) ? trim($_POST['competences']) : '',
                isset($_POST['telephone']) ? trim($_POST['telephone']) : '',
                isset($_POST['statut']) ? trim($_POST['statut']) : 'disponible',
                isset($_POST['cout_horaire']) ? (float) $_POST['cout_horaire'] : 0,
                isset($_POST['actif']) ? (int) $_POST['actif'] : 1
            );
            try {
                $rh->ajouter();
                $success = 'created';
            } catch (Exception $e) {
                $success = 'create_failed';
            }
        } elseif ($action == 'update') {
            $id = (int) $_POST['id'];
            $rh = new RessourceHumaine(
                $id,
                $aep_id,
                trim($_POST['nom']),
                isset($_POST['fonction']) ? trim($_POST['fonction']) : '',
                isset($_POST['competences']) ? trim($_POST['competences']) : '',
                isset($_POST['telephone']) ? trim($_POST['telephone']) : '',
                isset($_POST['statut']) ? trim($_POST['statut']) : 'disponible',
                isset($_POST['cout_horaire']) ? (float) $_POST['cout_horaire'] : 0,
                isset($_POST['actif']) ? (int) $_POST['actif'] : 1
            );
            try {
                $rh->update();
                $success = 'updated';
            } catch (Exception $e) {
                $success = 'update_failed';
            }
        } elseif ($action == 'delete') {
            $id = (int) $_POST['id'];
            try {
                Manager::delete_by_id('ressources_humaines', $id);
                $success = 'deleted';
            } catch (Exception $e) {
                $success = 'delete_failed';
            }
        }
        header('Location: ..?page=ressources&success=rh_' . $success);
        exit();
    }

    public static function getAll()
    {
        $aep_id = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
        if ($aep_id <= 0)
            return array();
        $req = RessourceHumaine::getAllByAep($aep_id);
        return $req ? $req->fetchAll() : array();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RessourceHumaine_t::handlePost();
}


