<?php
/**
 * Traitement des actions sur les Bornes Fontaines
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/borne_fontaine.php");
@include_once("donnees/borne_fontaine.php");
@include_once("../donnees/bf_gerant.php");
@include_once("donnees/bf_gerant.php");

session_start();

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'convertir':
        convertirAboneEnBF();
        break;
    case 'ajouter_gerant':
        ajouterGerant();
        break;
    case 'terminer_gerant':
        terminerGerant();
        break;
    case 'modifier_gerant':
        modifierGerant();
        break;
    case 'supprimer_gerant':
        supprimerGerant();
        break;
    default:
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
}

function convertirAboneEnBF()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
    }

    $id_abone = isset($_POST['id_abone']) ? (int)$_POST['id_abone'] : 0;
    $numero_borne = isset($_POST['numero_borne']) ? trim($_POST['numero_borne']) : '';
    $localisation = isset($_POST['localisation']) ? trim($_POST['localisation']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($id_abone <= 0) {
        header('Location: ../index.php?page=borne_fontaine&error=add_failed&message=' . urlencode('ID abonné invalide'));
        exit;
    }

    // Vérifier si l'abonné existe et n'est pas déjà une BF
    $abone = Manager::prepare_query("SELECT id, type_abone FROM abone WHERE id = ?", array($id_abone))->fetch();
    if (!$abone) {
        header('Location: ../index.php?page=borne_fontaine&error=add_failed&message=' . urlencode('Abonné introuvable'));
        exit;
    }

    if ($abone['type_abone'] == 'BF') {
        // Vérifier si une BF existe déjà
        $bf = Manager::prepare_query("SELECT id FROM borne_fontaine WHERE id_abone = ?", array($id_abone))->fetch();
        if ($bf) {
            header('Location: ../index.php?page=borne_fontaine&success=bf_added');
            exit;
        }
    }

    // Convertir l'abonné en BF
    $id_bf = BorneFontaine::convertirAboneEnBF($id_abone, $numero_borne, $localisation, $description);

    if ($id_bf) {
        header('Location: ../index.php?page=borne_fontaine&success=bf_added');
    } else {
        header('Location: ../index.php?page=borne_fontaine&error=add_failed&message=' . urlencode('Erreur lors de la conversion'));
    }
    exit;
}

function ajouterGerant()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
    }

    $id_borne_fontaine = isset($_POST['id_borne_fontaine']) ? (int)$_POST['id_borne_fontaine'] : 0;
    $nom_gerant = isset($_POST['nom_gerant']) ? trim($_POST['nom_gerant']) : '';
    $numero_telephone = isset($_POST['numero_telephone']) ? trim($_POST['numero_telephone']) : '';
    $numero_piece_identite = isset($_POST['numero_piece_identite']) ? trim($_POST['numero_piece_identite']) : '';
    $type_piece_identite = isset($_POST['type_piece_identite']) ? trim($_POST['type_piece_identite']) : '';
    $date_debut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : date('Y-m-d');
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($id_borne_fontaine <= 0 || empty($nom_gerant) || empty($date_debut)) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=add_failed&message=' . urlencode('Données invalides'));
        exit;
    }

    // Vérifier les chevauchements avec les autres gérants actifs
    $gerants = BFGerant::getGerantsByBF($id_borne_fontaine)->fetchAll();
    $date_debut_ts = strtotime($date_debut);
    
    foreach ($gerants as $g) {
        if (!$g['est_actif']) continue; // On vérifie seulement les gérants actifs
        
        $g_debut = strtotime($g['date_debut']);
        $g_fin = $g['date_fin'] ? strtotime($g['date_fin']) : PHP_INT_MAX;
        
        // Vérifier le chevauchement
        if ($date_debut_ts >= $g_debut && $date_debut_ts <= $g_fin) {
            header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=add_failed&message=' . urlencode('La date de début chevauche avec le gérant actif: ' . $g['nom_gerant']));
            exit;
        }
    }

    $gerant = new BFGerant(0, $id_borne_fontaine, $nom_gerant, $numero_telephone, 
                           $numero_piece_identite, $type_piece_identite, $date_debut, 
                           null, true, $notes);
    
    $id_gerant = $gerant->ajouterGerant();

    if ($id_gerant) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&success=gerant_added');
    } else {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=add_failed&message=' . urlencode('Erreur lors de l\'ajout du gérant'));
    }
    exit;
}

function terminerGerant()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
    }

    $id_gerant = isset($_POST['id_gerant']) ? (int)$_POST['id_gerant'] : 0;
    $date_fin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : date('Y-m-d');
    $id_borne_fontaine = isset($_POST['id_borne_fontaine']) ? (int)$_POST['id_borne_fontaine'] : 0;

    if ($id_gerant <= 0) {
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
    }

    $success = BFGerant::terminerPeriodeGerant($id_gerant, $date_fin);

    if ($success) {
        $redirect = $id_borne_fontaine > 0 ? 
            '../index.php?page=info_bf&id=' . $id_borne_fontaine . '&success=gerant_terminated' :
            '../index.php?page=borne_fontaine&success=gerant_terminated';
        header('Location: ' . $redirect);
    } else {
        header('Location: ../index.php?page=borne_fontaine&error=update_failed&message=' . urlencode('Erreur lors de la mise à jour'));
    }
    exit;
}

function modifierGerant()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
    }

    $id_gerant = isset($_POST['id_gerant']) ? (int)$_POST['id_gerant'] : 0;
    $id_borne_fontaine = isset($_POST['id_borne_fontaine']) ? (int)$_POST['id_borne_fontaine'] : 0;
    $nom_gerant = isset($_POST['nom_gerant']) ? trim($_POST['nom_gerant']) : '';
    $numero_telephone = isset($_POST['numero_telephone']) ? trim($_POST['numero_telephone']) : '';
    $numero_piece_identite = isset($_POST['numero_piece_identite']) ? trim($_POST['numero_piece_identite']) : '';
    $type_piece_identite = isset($_POST['type_piece_identite']) ? trim($_POST['type_piece_identite']) : '';
    $date_debut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
    $date_fin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($id_gerant <= 0 || $id_borne_fontaine <= 0 || empty($nom_gerant) || empty($date_debut)) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=update_failed&message=' . urlencode('Données invalides'));
        exit;
    }

    // Vérifier la cohérence des dates
    if (!empty($date_fin) && strtotime($date_debut) > strtotime($date_fin)) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=update_failed&message=' . urlencode('La date de début doit être antérieure à la date de fin'));
        exit;
    }

    // Vérifier les chevauchements avec les autres gérants
    $gerantActuel = BFGerant::getGerantById($id_gerant);
    if (!$gerantActuel) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=update_failed&message=' . urlencode('Gérant introuvable'));
        exit;
    }

    // Vérifier les chevauchements (sauf avec lui-même)
    $gerants = BFGerant::getGerantsByBF($id_borne_fontaine)->fetchAll();
    foreach ($gerants as $g) {
        if ($g['id'] == $id_gerant) continue;
        
        $g_debut = strtotime($g['date_debut']);
        $g_fin = $g['date_fin'] ? strtotime($g['date_fin']) : PHP_INT_MAX;
        $new_debut = strtotime($date_debut);
        $new_fin = !empty($date_fin) ? strtotime($date_fin) : PHP_INT_MAX;
        
        // Vérifier le chevauchement
        if (($new_debut >= $g_debut && $new_debut <= $g_fin) || 
            ($new_fin >= $g_debut && $new_fin <= $g_fin) ||
            ($new_debut <= $g_debut && $new_fin >= $g_fin)) {
            header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=update_failed&message=' . urlencode('Les dates chevauchent avec un autre gérant: ' . $g['nom_gerant']));
            exit;
        }
    }

    // Mettre à jour le gérant
    $gerant = new BFGerant($id_gerant, $id_borne_fontaine, $nom_gerant, $numero_telephone, 
                           $numero_piece_identite, $type_piece_identite, $date_debut, 
                           !empty($date_fin) ? $date_fin : null, 
                           empty($date_fin), $notes);
    
    $success = $gerant->update();

    if ($success) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&tab=gerants&success=gerant_updated');
    } else {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&tab=gerants&error=update_failed&message=' . urlencode('Erreur lors de la modification'));
    }
    exit;
}

function supprimerGerant()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
    }

    $id_gerant = isset($_POST['id_gerant']) ? (int)$_POST['id_gerant'] : 0;
    $id_borne_fontaine = isset($_POST['id_borne_fontaine']) ? (int)$_POST['id_borne_fontaine'] : 0;

    if ($id_gerant <= 0) {
        header('Location: ../index.php?page=borne_fontaine&error=invalid_request');
        exit;
    }

    // Vérifier que le gérant existe
    $gerant = BFGerant::getGerantById($id_gerant);
    if (!$gerant) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=delete_failed&message=' . urlencode('Gérant introuvable'));
        exit;
    }

    // Ne pas permettre la suppression d'un gérant actif
    if ($gerant['est_actif']) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&error=delete_failed&message=' . urlencode('Impossible de supprimer un gérant actif. Terminez d\'abord sa période.'));
        exit;
    }

    // Supprimer le gérant
    $gerantObj = new BFGerant();
    $gerantObj->id = $id_gerant;
    $success = $gerantObj->supprimer();

    if ($success) {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&tab=gerants&success=gerant_deleted');
    } else {
        header('Location: ../index.php?page=info_bf&id=' . $id_borne_fontaine . '&tab=gerants&error=delete_failed&message=' . urlencode('Erreur lors de la suppression'));
    }
    exit;
}
