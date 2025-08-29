<?php

@include_once("../donnees/ressource_materielle.php");
@include_once("donnees/ressource_materielle.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class RessourceMaterielle_t
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
            $rm = new RessourceMaterielle(
                0,
                $aep_id,
                trim($_POST['libelle']),
                isset($_POST['categorie']) ? trim($_POST['categorie']) : '',
                isset($_POST['reference']) ? trim($_POST['reference']) : '',
                isset($_POST['quantite_totale']) ? (float) $_POST['quantite_totale'] : 0,
                isset($_POST['quantite_disponible']) ? (float) $_POST['quantite_disponible'] : 0,
                isset($_POST['unite']) ? trim($_POST['unite']) : 'u',
                isset($_POST['cout_unitaire']) ? (float) $_POST['cout_unitaire'] : 0,
                isset($_POST['statut']) ? trim($_POST['statut']) : 'disponible',
                isset($_POST['actif']) ? (int) $_POST['actif'] : 1
            );
            try {
                $rm->ajouter();
                $success = 'created';
            } catch (Exception $e) {
                $success = 'create_failed';
            }
        } elseif ($action == 'update') {
            $id = (int) $_POST['id'];
            $rm = new RessourceMaterielle(
                $id,
                $aep_id,
                trim($_POST['libelle']),
                isset($_POST['categorie']) ? trim($_POST['categorie']) : '',
                isset($_POST['reference']) ? trim($_POST['reference']) : '',
                isset($_POST['quantite_totale']) ? (float) $_POST['quantite_totale'] : 0,
                isset($_POST['quantite_disponible']) ? (float) $_POST['quantite_disponible'] : 0,
                isset($_POST['unite']) ? trim($_POST['unite']) : 'u',
                isset($_POST['cout_unitaire']) ? (float) $_POST['cout_unitaire'] : 0,
                isset($_POST['statut']) ? trim($_POST['statut']) : 'disponible',
                isset($_POST['actif']) ? (int) $_POST['actif'] : 1
            );
            try {
                $rm->update();
                $success = 'updated';
            } catch (Exception $e) {
                $success = 'update_failed';
            }
        } elseif ($action == 'delete') {
            $id = (int) $_POST['id'];
            try {
                Manager::delete_by_id('ressources_materielles', $id);
                $success = 'deleted';
            } catch (Exception $e) {
                $success = 'delete_failed';
            }
        }
        header('Location: ..?page=ressources&success=rm_' . $success);
        exit();
    }

    public static function getAll()
    {
        $aep_id = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
        if ($aep_id <= 0)
            return array();
        $req = RessourceMaterielle::getAllByAep($aep_id);
        return $req ? $req->fetchAll() : array();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RessourceMaterielle_t::handlePost();
}


