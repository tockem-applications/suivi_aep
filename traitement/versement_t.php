<?php

@include_once("../donnees/versements.php");
@include_once("donnees/versements.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier si l'AEP est défini
if (!isset($_SESSION['id_aep'])) {
    header('Location: manage_versements.php?error=no_aep');
    exit;
}

class VersementProcessor
{
    public static function deleteVersement($id)
    {
        try {
            $query = Manager::prepare_query(
                "DELETE FROM versements WHERE id = ?",
                array($id)
            );
            return array('success' => true);
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    public static function addVersement($montant, $date_versement, $id_mois_facturation, $id_redevance)
    {
        try {
            // Validation des données
            if ($montant <= 0) {
                return array('success' => false, 'message' => 'Le montant doit être supérieur à 0.');
            }
            if (!strtotime($date_versement)) {
                return array('success' => false, 'message' => 'La date de versement est invalide.');
            }
            if ($id_mois_facturation <= 0) {
                return array('success' => false, 'message' => 'L’ID du mois de facturation est invalide.');
            }
            if ($id_redevance <= 0) {
                return array('success' => false, 'message' => 'L’ID de la redevance est invalide.');
            }

            $query = Manager::prepare_query(
                "INSERT INTO versements (montant, date_versement, id_mois_facturation, id_redevance) VALUES (?, ?, ?, ?)",
                array($montant, $date_versement, $id_mois_facturation, $id_redevance)
            );
            return array('success' => true);
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    public static function validateVersement($id)
    {
        try {
            $query = Manager::prepare_query(
                "UPDATE versements SET est_valide = 1 WHERE id = ?",
                array($id)
            );
            return array('success' => true);
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_versement') {
            $id = (int)$_POST['id'];
            $result = VersementProcessor::deleteVersement($id);
            if ($result['success']) {
                header('Location: ..?page=versement&success=versement_deleted');
            } else {
                header('Location: ..?page=versement&error=delete_failed&message=' . urlencode($result['message']));
            }
            exit;
        }
        elseif ($_POST['action'] === 'add_versement') {
            $montant = (float)$_POST['montant'];
            $date_versement = $_POST['date_versement'];
            $id_mois_facturation = (int)$_POST['id_mois_facturation'];
            $id_redevance = (int)$_POST['id_redevance'];

            $result = VersementProcessor::addVersement($montant, $date_versement, $id_mois_facturation, $id_redevance);
            if ($result['success']) {
                header('Location: ..?page=versement&success=versement_added');
            } else {
                header('Location: ..?page=versement&error=add_failed&message=' . urlencode($result['message']));
            }
            exit;
        }
        elseif ($_POST['action'] === 'validate_versement') {
            $id = (int)$_POST['id'];
            $result = VersementProcessor::validateVersement($id);
            if ($result['success']) {
                header('Location: ..?page=versement&success=versement_validated');
            } else {
                header('Location: ..?page=versement&error=validate_failed&message=' . urlencode($result['message']));
            }
            exit;
        }
    }
}

// Si la méthode n'est pas POST, rediriger