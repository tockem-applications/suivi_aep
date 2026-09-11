<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();

/**
 * Enregistrement d'une campagne d'avis de coupure : paramètres (responsable,
 * date, en-tête, seuil) et sélection des abonnés retenus pour le mois.
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/avis_coupure.php");
@include_once("donnees/avis_coupure.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}
if (!isset($_SESSION['id_aep']) || (int) $_SESSION['id_aep'] <= 0) {
    header('Location: ../index.php?page=avis_coupure&error=no_aep&message=' . urlencode('Aucun AEP sélectionné.'));
    exit;
}

$aepId = (int) $_SESSION['id_aep'];
$base_redirect = '../index.php?page=avis_coupure';

/** Reconstruit l'URL de retour avec le contexte d'affichage de la page. */
function avis_coupure_retour($id_mois)
{
    $parts = array();
    if ((int) $id_mois > 0) {
        $parts[] = 'id_mois=' . (int) $id_mois;
    }
    if (isset($_POST['seuil']) && (int) $_POST['seuil'] > 0) {
        $parts[] = 'seuil=' . (int) $_POST['seuil'];
    }
    if (isset($_POST['reseau_id']) && (int) $_POST['reseau_id'] > 0) {
        $parts[] = 'reseau_id=' . (int) $_POST['reseau_id'];
    }
    if (isset($_POST['q']) && trim($_POST['q']) !== '') {
        $parts[] = 'q=' . urlencode(trim($_POST['q']));
    }
    return count($parts) ? '&' . implode('&', $parts) : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ' . $base_redirect);
    exit;
}

$id_mois = isset($_POST['id_mois']) ? (int) $_POST['id_mois'] : 0;
$retour = avis_coupure_retour($id_mois);

if ($id_mois <= 0) {
    header('Location: ' . $base_redirect . '&error=invalid_data&message=' . urlencode('Mois de facturation manquant.'));
    exit;
}

if ($_POST['action'] === 'save_selection') {
    $abones = isset($_POST['abones']) && is_array($_POST['abones']) ? $_POST['abones'] : array();
    $visibles = isset($_POST['visibles']) && is_array($_POST['visibles']) ? $_POST['visibles'] : array();
    $id_responsable = isset($_POST['id_responsable']) ? (int) $_POST['id_responsable'] : 0;
    $date_avis = isset($_POST['date_avis']) ? trim($_POST['date_avis']) : '';
    $entete = isset($_POST['entete']) ? trim($_POST['entete']) : '';
    $seuil = isset($_POST['seuil']) ? (int) $_POST['seuil'] : AvisCoupure::SEUIL_MOIS_DEFAUT;

    try {
        AvisCoupure::enregistrerCampagne($aepId, $id_mois, $seuil, $id_responsable, $entete, $date_avis);
        $resultat = AvisCoupure::enregistrerSelection(
            $aepId,
            $id_mois,
            $abones,
            $id_responsable,
            $date_avis,
            $visibles
        );
        header('Location: ' . $base_redirect . $retour . '&success=selection_saved'
            . '&a=' . (int) $resultat['ajoutes'] . '&r=' . (int) $resultat['retires']);
        exit;
    } catch (Exception $e) {
        header('Location: ' . $base_redirect . $retour . '&error=save_failed&message=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ' . $base_redirect . $retour);
exit;
