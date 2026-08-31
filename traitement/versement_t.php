<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();

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

                $montantTotalEstimatif = 0;

                // Calculer le montant total estimatif selon le base_calcul
                if ($redevance['base_calcul'] == 'branchements') {
                    // Pour les branchements, calculer à partir des branchements
                    $mois_debut = $redevance['mois_debut'] ? $redevance['mois_debut'] : '1900-01';
                    $moisBranchements = Manager::prepare_query(
                        "SELECT 
                            b.mois, 
                            SUM(IFNULL(b.versement_fcfa, 0)) as total_facture, 
                            COUNT(b.mois) as nombre_branchement
                         FROM branchement_abonne b
                         INNER JOIN abone a ON b.id_abone = a.id
                         INNER JOIN reseau r ON a.id_reseau = r.id
                         WHERE b.mois >= ? AND r.id_aep = ?
                         GROUP BY b.mois
                         ORDER BY b.mois DESC",
                        array($mois_debut, $redevance['id_aep'])
                    )->fetchAll();

                    foreach ($moisBranchements as $moisBranchement) {
                        $total_facture = (float) $moisBranchement['total_facture'];
                        $nombre_branchement = (int) $moisBranchement['nombre_branchement'];

                        if ($redevance['type_calcul'] == 'montant_fixe') {
                            $montantTotalEstimatif += $nombre_branchement * (float) $redevance['montant_par_m3'];
                        } else {
                            $montantTotalEstimatif += $total_facture * (float) $redevance['pourcentage'] / 100;
                        }
                    }
                } else {
                    // Pour vente_eau, utiliser les mois de facturation
                    $moisFacturation = Manager::prepare_query(
                        "SELECT m.id 
                         FROM mois_facturation m 
                         INNER JOIN constante_reseau c ON m.id_constante = c.id 
                         WHERE c.id_aep = ? AND m.mois >= ?
                         ORDER BY m.mois DESC",
                        array($redevance['id_aep'], $redevance['mois_debut'] ? $redevance['mois_debut'] : '1900-01')
                    )->fetchAll();

                    foreach ($moisFacturation as $mois) {
                        $montant_estimatif = Redevance::calculerMontantEstimatif($id_redevance, $mois['id']);
                        $montantTotalEstimatif += $montant_estimatif;
                    }
                }

                // Récupérer le montant total déjà versé
                $result = Manager::prepare_query(
                    "SELECT COALESCE(SUM(montant), 0) as total_verse 
                     FROM versements 
                     WHERE id_redevance = ?",
                    array($id_redevance)
                )->fetch();

                $montantTotalVerse = $result ? (float) $result['total_verse'] : 0;

                // Ne valider que si le montant estimatif est > 0
                if ($montantTotalEstimatif > 0 && $montantTotalVerse + $montant > $montantTotalEstimatif) {
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
            $id = (int) $_POST['id'];

            // Récupérer les infos du versement pour la redirection
            $versement = Manager::prepare_query(
                "SELECT id_redevance, id_mois_facturation FROM versements WHERE id = ?",
                array($id)
            )->fetch();

            $result = VersementProcessor::deleteVersement($id);
            if ($result['success']) {
                // Rediriger vers la page de détails si id_mois est fourni, sinon vers la liste
                if ($versement && isset($_GET['id_mois'])) {
                    header('Location: ../index.php?page=redevance_versements_detail&id_redevance=' . $versement['id_redevance'] . '&id_mois=' . $versement['id_mois_facturation'] . '&success=versement_deleted');
                } else {
                    header('Location: ../index.php?page=redevance_versements&id_redevance=' . ($versement ? $versement['id_redevance'] : 0) . '&success=versement_deleted');
                }
            } else {
                if ($versement && isset($_GET['id_mois'])) {
                    header('Location: ../index.php?page=redevance_versements_detail&id_redevance=' . $versement['id_redevance'] . '&id_mois=' . $versement['id_mois_facturation'] . '&error=delete_failed&message=' . urlencode($result['message']));
                } else {
                    header('Location: ../index.php?page=redevance_versements&id_redevance=' . ($versement ? $versement['id_redevance'] : 0) . '&error=delete_failed&message=' . urlencode($result['message']));
                }
            }
            exit;
        } elseif ($_POST['action'] === 'add_versement' || (isset($_GET['action']) && $_GET['action'] === 'add_versement')) {
            $montant = isset($_POST['montant']) ? (float) $_POST['montant'] : 0;
            $date_versement = isset($_POST['date_versement']) ? $_POST['date_versement'] : date('Y-m-d');
            $id_mois_facturation = isset($_POST['id_mois_facturation']) && $_POST['id_mois_facturation'] != '' ? (int) $_POST['id_mois_facturation'] : 0;
            $id_redevance = isset($_POST['id_redevance']) ? (int) $_POST['id_redevance'] : 0;

            // Validation basique avant traitement
            if ($id_redevance <= 0) {
                header('Location: ../index.php?page=redevance_versements&id_redevance=0&error=add_failed&message=' . urlencode('ID de redevance invalide.'));
                exit;
            }

            if ($montant <= 0) {
                header('Location: ../index.php?page=redevance_versements&id_redevance=' . $id_redevance . '&error=add_failed&message=' . urlencode('Le montant doit être supérieur à 0.'));
                exit;
            }

            $result = VersementProcessor::addVersement($montant, $date_versement, $id_mois_facturation, $id_redevance);
            if ($result['success']) {
                // Rediriger vers la page de gestion des versements de la redevance (tous types: vente_eau, branchements)
                header('Location: ../index.php?page=redevance_versements&id_redevance=' . $id_redevance . '&success=versement_added');
            } else {
                header('Location: ../index.php?page=redevance_versements&id_redevance=' . $id_redevance . '&error=add_failed&message=' . urlencode($result['message']));
            }
            exit;
        } elseif ($_POST['action'] === 'validate_versement') {
            $id = (int) $_POST['id'];
            $result = VersementProcessor::validateVersement($id);
            if ($result['success']) {
                header('Location: ../index.php?page=versement&success=versement_validated');
            } else {
                header('Location: ../index.php?page=versement&error=validate_failed&message=' . urlencode($result['message']));
            }
            exit;
        }
    }
}

// Si la méthode n'est pas POST, rediriger