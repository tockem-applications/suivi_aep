<?php

@include_once("../donnees/versements.php");
@include_once("donnees/versements.php");
@include_once("../donnees/redevance.php");
@include_once("donnees/redevance.php");

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
            if ($id_redevance <= 0) {
                return array('success' => false, 'message' => 'L\'ID de la redevance est invalide.');
            }

            // Vérifier que le montant total versé ne dépasse pas le montant estimatif total
            if ($id_mois_facturation == '' || $id_mois_facturation == null || $id_mois_facturation == 0) {
                // Versement global - vérifier le total
                $redevance = Manager::prepare_query(
                    "SELECT * FROM redevance WHERE id = ?",
                    array($id_redevance)
                )->fetch();
                
                if (!$redevance) {
                    return array('success' => false, 'message' => 'Redevance introuvable.');
                }

                // Calculer le montant total estimatif
                $moisFacturation = Manager::prepare_query(
                    "SELECT m.id 
                     FROM mois_facturation m 
                     INNER JOIN constante_reseau c ON m.id_constante = c.id 
                     WHERE c.id_aep = ? AND m.mois >= ?
                     ORDER BY m.mois DESC",
                    array($redevance['id_aep'], $redevance['mois_debut'] ? $redevance['mois_debut'] : '1900-01')
                )->fetchAll();

                $montantTotalEstimatif = 0;
                foreach ($moisFacturation as $mois) {
                    $montant_estimatif = Redevance::calculerMontantEstimatif($id_redevance, $mois['id']);
                    $montantTotalEstimatif += $montant_estimatif;
                }

                // Récupérer le montant total déjà versé
                $result = Manager::prepare_query(
                    "SELECT COALESCE(SUM(montant), 0) as total_verse 
                     FROM versements 
                     WHERE id_redevance = ?",
                    array($id_redevance)
                )->fetch();
                
                $montantTotalVerse = $result ? (float)$result['total_verse'] : 0;
                
                if ($montantTotalVerse + $montant > $montantTotalEstimatif) {
                    return array('success' => false, 'message' => 'Le montant total versé ne peut pas dépasser le montant estimatif total (' . number_format($montantTotalEstimatif, 0, ',', ' ') . ' FCFA). Reste à verser: ' . number_format($montantTotalEstimatif - $montantTotalVerse, 0, ',', ' ') . ' FCFA');
                }
                
                $id_mois_facturation = null; // Versement global
            } else {
                // Versement par mois - validation de l'ID
                if ($id_mois_facturation <= 0) {
                    return array('success' => false, 'message' => 'L\'ID du mois de facturation est invalide.');
                }
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
            
            // Récupérer les infos du versement pour la redirection
            $versement = Manager::prepare_query(
                "SELECT id_redevance, id_mois_facturation FROM versements WHERE id = ?",
                array($id)
            )->fetch();
            
            $result = VersementProcessor::deleteVersement($id);
            if ($result['success']) {
                // Rediriger vers la page de détails si id_mois est fourni, sinon vers la liste
                if ($versement && isset($_GET['id_mois'])) {
                    header('Location: ?page=redevance_versements_detail&id_redevance=' . $versement['id_redevance'] . '&id_mois=' . $versement['id_mois_facturation'] . '&success=versement_deleted');
                } else {
                    header('Location: ?page=redevance_versements&id_redevance=' . ($versement ? $versement['id_redevance'] : 0) . '&success=versement_deleted');
                }
            } else {
                if ($versement && isset($_GET['id_mois'])) {
                    header('Location: ?page=redevance_versements_detail&id_redevance=' . $versement['id_redevance'] . '&id_mois=' . $versement['id_mois_facturation'] . '&error=delete_failed&message=' . urlencode($result['message']));
                } else {
                    header('Location: ?page=redevance_versements&id_redevance=' . ($versement ? $versement['id_redevance'] : 0) . '&error=delete_failed&message=' . urlencode($result['message']));
                }
            }
            exit;
        }
        elseif ($_POST['action'] === 'add_versement' || (isset($_GET['action']) && $_GET['action'] === 'add_versement')) {
            $montant = isset($_POST['montant']) ? (float)$_POST['montant'] : 0;
            $date_versement = isset($_POST['date_versement']) ? $_POST['date_versement'] : date('Y-m-d');
            $id_mois_facturation = isset($_POST['id_mois_facturation']) ? (int)$_POST['id_mois_facturation'] : 0;
            $id_redevance = isset($_POST['id_redevance']) ? (int)$_POST['id_redevance'] : 0;

            $result = VersementProcessor::addVersement($montant, $date_versement, $id_mois_facturation, $id_redevance);
            if ($result['success']) {
                // Rediriger vers la page de gestion des versements de la redevance
                header('Location: ?page=redevance_versements&id_redevance=' . $id_redevance . '&success=versement_added');
            } else {
                header('Location: ?page=redevance_versements&id_redevance=' . $id_redevance . '&error=add_failed&message=' . urlencode($result['message']));
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