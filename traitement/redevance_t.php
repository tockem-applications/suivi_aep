<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();
//session_start();

@include_once("../donnees/redevance.php");
@include_once("donnees/redevance.php");


// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ..?page=login');
    exit;
}

// Vérifier si l'AEP est défini
if (!isset($_SESSION['id_aep'])) {
    header('Location: ..?page=redevance&error=no_aep');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_redevance') {
            $libele = trim($_POST['libele']);
            $pourcentage = isset($_POST['pourcentage']) ? floatval($_POST['pourcentage']) : 0;
            $mois_debut = trim($_POST['mois_debut']);
            $type_redevance = 'sortie'; // Toutes les redevances sont des sorties
            $description = trim($_POST['description']);
            $id_aep = (int)$_SESSION['id_aep'];
            $base_calcul = isset($_POST['base_calcul']) ? trim($_POST['base_calcul']) : 'vente_eau';
            $type_calcul = isset($_POST['type_calcul']) ? trim($_POST['type_calcul']) : 'pourcentage';
            $montant_par_m3 = isset($_POST['montant_par_m3']) && $_POST['montant_par_m3'] != '' ? floatval($_POST['montant_par_m3']) : null;

            // Validation
            if (empty($libele) || strlen($libele) > 64) {
                header('Location: ..?page=redevance&error=invalid_libele');
                exit;
            }
            if (empty($mois_debut) || strlen($mois_debut) > 8) {
                header('Location: ..?page=redevance&error=invalid_mois_debut');
                exit;
            }
            if ($base_calcul != 'vente_eau' && $base_calcul != 'branchements') {
                header('Location: ..?page=redevance&error=invalid_base_calcul');
                exit;
            }
            if ($type_calcul != 'pourcentage' && $type_calcul != 'montant_fixe') {
                header('Location: ..?page=redevance&error=invalid_type_calcul');
                exit;
            }
            if ($type_calcul == 'pourcentage' && ($pourcentage <= 0 || $pourcentage > 100)) {
                header('Location: ..?page=redevance&error=invalid_pourcentage');
                exit;
            }
            if ($type_calcul == 'montant_fixe' && ($montant_par_m3 === null || $montant_par_m3 <= 0)) {
                header('Location: ..?page=redevance&error=invalid_montant_par_m3');
                exit;
            }

            try {
                $query = Manager::prepare_query(
                    "INSERT INTO redevance (libele, pourcentage, description, id_aep, type, mois_debut, base_calcul, type_calcul, montant_par_m3, est_sortie) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    array($libele, $pourcentage, $description, $id_aep, $type_redevance, $mois_debut, $base_calcul, $type_calcul, $montant_par_m3)
                );
                header('Location: ..?page=redevance&success=redevance_added');
                exit;
            } catch (Exception $e) {
                header('Location: ..?page=redevance&error=add_failed&message=' . urlencode($e->getMessage()));
                exit;
            }
        } elseif ($_POST['action'] === 'update_redevance') {
            $id = (int)$_POST['id'];
            $libele = trim($_POST['libele']);
            $pourcentage = isset($_POST['pourcentage']) ? floatval($_POST['pourcentage']) : 0;
            $description = trim($_POST['description']);
            $mois_debut = trim($_POST['mois_debut']);
            $type_redevance = 'sortie'; // Toutes les redevances sont des sorties
            $id_aep = (int)$_SESSION['id_aep'];
            $base_calcul = isset($_POST['base_calcul']) ? trim($_POST['base_calcul']) : 'vente_eau';
            $type_calcul = isset($_POST['type_calcul']) ? trim($_POST['type_calcul']) : 'pourcentage';
            $montant_par_m3 = isset($_POST['montant_par_m3']) && $_POST['montant_par_m3'] != '' ? floatval($_POST['montant_par_m3']) : null;

            // Validation
            if (empty($libele) || strlen($libele) > 64) {
                header('Location: ..?page=redevance&error=invalid_libele');
                exit;
            }
            if ($base_calcul != 'vente_eau' && $base_calcul != 'branchements') {
                header('Location: ..?page=redevance&error=invalid_base_calcul');
                exit;
            }
            if ($type_calcul != 'pourcentage' && $type_calcul != 'montant_fixe') {
                header('Location: ..?page=redevance&error=invalid_type_calcul');
                exit;
            }
            if ($type_calcul == 'pourcentage' && ($pourcentage <= 0 || $pourcentage > 100)) {
                header('Location: ..?page=redevance&error=invalid_pourcentage');
                exit;
            }
            if ($type_calcul == 'montant_fixe' && ($montant_par_m3 === null || $montant_par_m3 <= 0)) {
                header('Location: ..?page=redevance&error=invalid_montant_par_m3');
                exit;
            }
            if (empty($mois_debut) || strlen($mois_debut) > 8) {
                header('Location: ..?page=redevance&error=invalid_mois_debut');
                exit;
            }

            try {
                $query = Manager::prepare_query(
                    "UPDATE redevance SET libele = ?, pourcentage = ?, description = ?, id_aep = ?, type = ?, mois_debut = ?, base_calcul = ?, type_calcul = ?, montant_par_m3 = ?, est_sortie = 1 WHERE id = ?",
                    array($libele, $pourcentage, $description, $id_aep, $type_redevance, $mois_debut, $base_calcul, $type_calcul, $montant_par_m3, $id)
                );
                header('Location: ..?page=redevance&success=redevance_updated&id=' . $id);
                exit;
            } catch (Exception $e) {
                header('Location: ..?page=redevance&error=update_failed&id=' . $id . '&message=' . urlencode($e->getMessage()));
                exit;
            }
        } elseif ($_POST['action'] === 'delete_redevance') {
            $id = (int)$_POST['id'];
            try {
                $query = Manager::prepare_query(
                    "DELETE FROM redevance WHERE id = ?",
                    array($id)
                );
                header('Location: ..?page=redevance&success=redevance_deleted');
                exit;
            } catch (Exception $e) {
                header('Location: ..?page=redevance&error=delete_failed&message=' . urlencode($e->getMessage()));
                exit;
            }
        }
    }
}

// Si la méthode n'est pas POST, rediriger
//header('Location: manage_redevances.php?error=invalid_request');
//exit;