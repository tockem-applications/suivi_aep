<?php
session_start();

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
            $pourcentage = floatval($_POST['pourcentage']);
            $mois_debut = trim($_POST['mois_debut']);
            $type_redevance= trim($_POST['type']);
            $description = trim($_POST['description']);
            $id_aep = (int)$_SESSION['id_aep'];

            // Validation
            if (empty($libele) || strlen($libele) > 64) {
                header('Location: ..?page=redevance&error=invalid_libele');
                exit;
            }
            if (empty($mois_debut) || strlen($mois_debut) > 8) {
                header('Location: ..?page=redevance&error=invalid_mois_debut');
                exit;
            }
            if (empty($type_redevance) || !(strcmp($type_redevance, 'sortie') != 0 || strcmp($type_redevance, 'entree') != 0)) {
                header('Location: ..?page=redevance&error=invalid_type');
                exit;
            }
            if ($pourcentage <= 0 || $pourcentage > 100) {
                header('Location: ..?page=redevance&error=invalid_pourcentage');
                exit;
            }

            try {
                $query = Manager::prepare_query(
                    "INSERT INTO redevance (libele, pourcentage, description, id_aep, type, mois_debut) VALUES (?, ?, ?, ?, ?, ?)",
                    array($libele, $pourcentage, $description, $id_aep, $type_redevance, $mois_debut)
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
            $pourcentage = floatval($_POST['pourcentage']);
            $description = trim($_POST['description']);
            $mois_debut = trim($_POST['mois_debut']);
            $type_redevance= trim($_POST['type']);
            $id_aep = (int)$_SESSION['id_aep'];

            // Validation
            if (empty($libele) || strlen($libele) > 64) {
                header('Location: ..?page=redevance&error=invalid_libele');
                exit;
            }
            if ($pourcentage <= 0 || $pourcentage > 100) {
                header('Location: ..?page=redevance&error=invalid_pourcentage');
                exit;
            }

            if (empty($mois_debut) || strlen($mois_debut) > 8) {
                header('Location: ..?page=redevance&error=invalid_mois_debut');
                exit;
            }
            if (empty($type_redevance) || !(strcmp($type_redevance, 'sortie') != 0 || strcmp($type_redevance, 'entree') != 0)) {
                header('Location: ..?page=redevance&error=invalid_type');
                exit;
            }

            try {
                $query = Manager::prepare_query(
                    "UPDATE redevance SET libele = ?, pourcentage = ?, description = ?, id_aep = ?, type = ?, mois_debut = ? WHERE id = ?",
                    array($libele, $pourcentage, $description, $id_aep, $type_redevance, $mois_debut, $id)
                );
                header('Location: ..?page=redevance&success=redevance_updated');
                exit;
            } catch (Exception $e) {
                header('Location: ..?page=redevance&error=update_failed&message=' . urlencode($e->getMessage()));
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