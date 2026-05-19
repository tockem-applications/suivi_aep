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

function penalite_redirect_suffix($id_mois = 0)
{
    $parts = array();
    if ($id_mois > 0) {
        $parts[] = 'id_mois=' . (int) $id_mois;
    }
    if (isset($_POST['tab']) && preg_match('/^[a-z_]+$/', $_POST['tab'])) {
        $parts[] = 'tab=' . urlencode($_POST['tab']);
    } elseif (isset($_GET['tab']) && preg_match('/^[a-z_]+$/', $_GET['tab'])) {
        $parts[] = 'tab=' . urlencode($_GET['tab']);
    }
    if (isset($_POST['reseau_id']) && (int) $_POST['reseau_id'] > 0) {
        $parts[] = 'reseau_id=' . (int) $_POST['reseau_id'];
    } elseif (isset($_GET['reseau_id']) && (int) $_GET['reseau_id'] > 0) {
        $parts[] = 'reseau_id=' . (int) $_GET['reseau_id'];
    }
    if (isset($_POST['q']) && trim($_POST['q']) !== '') {
        $parts[] = 'q=' . urlencode(trim($_POST['q']));
    } elseif (isset($_GET['q']) && trim($_GET['q']) !== '') {
        $parts[] = 'q=' . urlencode(trim($_GET['q']));
    }
    return count($parts) ? '&' . implode('&', $parts) : '';
}

// Conserver le mois en GET ou POST pour la redirection
$id_mois_redirect = isset($_GET['id_mois']) ? (int) $_GET['id_mois'] : (isset($_POST['id_mois']) ? (int) $_POST['id_mois'] : 0);
$id_mois_param = penalite_redirect_suffix($id_mois_redirect);

function penalite_apply_to_compteur_mois($id_compteur, $id_mois, $montant)
{
    $indexReq = Manager::prepare_query(
        "SELECT i.id FROM indexes i WHERE i.id_compteur = ? AND i.id_mois_facturation = ?",
        array($id_compteur, $id_mois)
    );
    $indexData = $indexReq ? $indexReq->fetchAll() : array();
    if (count($indexData) === 0) {
        return false;
    }
    $id_indexes = (int) $indexData[0]['id'];
    Manager::prepare_query("UPDATE facture SET penalite = ? WHERE id_indexes = ?", array($montant, $id_indexes));
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'apply_penalite') {
        $id_compteur = (int) $_POST['id_compteur'];
        $id_mois = (int) $_POST['id_mois'];
        $penalite_montant = isset($_POST['penalite_montant']) ? (int) $_POST['penalite_montant'] : 0;

        if ($id_compteur > 0 && $id_mois > 0 && $penalite_montant >= 0) {
            try {
                if (penalite_apply_to_compteur_mois($id_compteur, $id_mois, $penalite_montant)) {
                    header('Location: ' . $base_redirect . penalite_redirect_suffix($id_mois) . '&success=penalite_applied');
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
                if (penalite_apply_to_compteur_mois($id_compteur, $id_mois, 0)) {
                    header('Location: ' . $base_redirect . penalite_redirect_suffix($id_mois) . '&success=penalite_removed');
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

    if ($_POST['action'] === 'bulk_apply_penalite') {
        $penalite_montant = isset($_POST['penalite_montant']) ? (int) $_POST['penalite_montant'] : 2500;
        $compteurs = isset($_POST['bulk_id_compteur']) && is_array($_POST['bulk_id_compteur']) ? $_POST['bulk_id_compteur'] : array();
        $mois_list = isset($_POST['bulk_id_mois']) && is_array($_POST['bulk_id_mois']) ? $_POST['bulk_id_mois'] : array();
        $ok = 0;
        if ($penalite_montant > 0 && count($compteurs) === count($mois_list)) {
            try {
                for ($i = 0; $i < count($compteurs); $i++) {
                    $idc = (int) $compteurs[$i];
                    $idm = (int) $mois_list[$i];
                    if ($idc > 0 && $idm > 0 && penalite_apply_to_compteur_mois($idc, $idm, $penalite_montant)) {
                        $ok++;
                    }
                }
                header('Location: ' . $base_redirect . penalite_redirect_suffix($id_mois_redirect) . '&success=bulk_applied&n=' . $ok);
                exit;
            } catch (Exception $e) {
                header('Location: ' . $base_redirect . $id_mois_param . '&error=apply_failed&message=' . urlencode($e->getMessage()));
                exit;
            }
        }
        header('Location: ' . $base_redirect . $id_mois_param . '&error=invalid_data');
        exit;
    }
}

header('Location: ' . $base_redirect);
exit;
