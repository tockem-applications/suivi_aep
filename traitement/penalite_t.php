<?php
/**
 * Traitement des actions pénalités : appliquer / retirer
 * Redirige vers page=penalites après traitement.
 */
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/facture.php");
@include_once("donnees/facture.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}
if (!isset($_SESSION['id_aep'])) {
    header('Location: ../index.php?page=penalites&error=no_aep');
    exit;
}

$base_redirect = '../index.php?page=penalites';

// Conserver le mois en GET ou POST pour la redirection
$id_mois_redirect = isset($_GET['id_mois']) ? (int) $_GET['id_mois'] : (isset($_POST['id_mois']) ? (int) $_POST['id_mois'] : 0);
$id_mois_param = $id_mois_redirect > 0 ? '&id_mois=' . $id_mois_redirect : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'apply_penalite') {
        $id_compteur = (int) $_POST['id_compteur'];
        $id_mois = (int) $_POST['id_mois'];
        $penalite_montant = isset($_POST['penalite_montant']) ? (int) $_POST['penalite_montant'] : 0;

        if ($id_compteur > 0 && $id_mois > 0 && $penalite_montant >= 0) {
            try {
                $indexReq = Manager::prepare_query(
                    "SELECT i.id FROM indexes i WHERE i.id_compteur = ? AND i.id_mois_facturation = ?",
                    array($id_compteur, $id_mois)
                );
                $indexData = $indexReq->fetchAll();
                if (count($indexData) > 0) {
                    $id_indexes = (int) $indexData[0]['id'];
                    Manager::prepare_query("UPDATE facture SET penalite = ? WHERE id_indexes = ?", array($penalite_montant, $id_indexes));
                    header('Location: ' . $base_redirect . ($id_mois > 0 ? '&id_mois=' . $id_mois : '') . '&success=penalite_applied');
                    exit;
                }
            } catch (Exception $e) {
                header('Location: ' . $base_redirect . $id_mois_param . '&error=apply_failed&message=' . urlencode($e->getMessage()));
                exit;
            }
        }
        header('Location: ' . $base_redirect . $id_mois_param . '&error=invalid_data');
        exit;
    }

    if ($_POST['action'] === 'cancel_penalite') {
        $id_compteur = (int) $_POST['id_compteur'];
        $id_mois = (int) $_POST['id_mois'];

        if ($id_compteur > 0 && $id_mois > 0) {
            try {
                $indexReq = Manager::prepare_query(
                    "SELECT i.id FROM indexes i WHERE i.id_compteur = ? AND i.id_mois_facturation = ?",
                    array($id_compteur, $id_mois)
                );
                $indexData = $indexReq->fetchAll();
                if (count($indexData) > 0) {
                    $id_indexes = (int) $indexData[0]['id'];
                    Manager::prepare_query("UPDATE facture SET penalite = 0 WHERE id_indexes = ?", array($id_indexes));
                    header('Location: ' . $base_redirect . ($id_mois > 0 ? '&id_mois=' . $id_mois : '') . '&success=penalite_removed');
                    exit;
                }
            } catch (Exception $e) {
                header('Location: ' . $base_redirect . $id_mois_param . '&error=cancel_failed&message=' . urlencode($e->getMessage()));
                exit;
            }
        }
        header('Location: ' . $base_redirect . $id_mois_param . '&error=invalid_data');
        exit;
    }
}

header('Location: ' . $base_redirect);
exit;
