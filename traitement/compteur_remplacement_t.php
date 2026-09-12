<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();

/**
 * Remplacement du compteur d'un abonné depuis sa fiche : le nouveau compteur
 * est créé et rattaché, l'ancien est clos sur son index de dépose.
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/compteur_remplacement.php");
@include_once("donnees/compteur_remplacement.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$id_abone = isset($_POST['id_abone']) ? (int) $_POST['id_abone'] : 0;
$retour = '../index.php?page=info_abone&id=' . $id_abone;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'remplacer_compteur' || $id_abone <= 0) {
    header('Location: ../index.php?page=abonne');
    exit;
}

// L'abonné doit appartenir à l'AEP de la session : l'identifiant vient du formulaire.
$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$situation = CompteurRemplacement::getSituation($id_abone);
if ($situation === null || ($aepId > 0 && (int) $situation['id_aep'] !== $aepId)) {
    $_SESSION['error_message'] = 'Abonné introuvable pour cet AEP.';
    header('Location: ' . $retour);
    exit;
}

try {
    $resultat = CompteurRemplacement::remplacer($id_abone, array(
        'numero_compteur' => isset($_POST['numero_compteur']) ? $_POST['numero_compteur'] : '',
        'index_initial' => isset($_POST['index_initial']) ? $_POST['index_initial'] : 0,
        'index_depose' => isset($_POST['index_depose']) ? $_POST['index_depose'] : '',
        'date_remplacement' => isset($_POST['date_remplacement']) ? $_POST['date_remplacement'] : '',
        'latitude' => isset($_POST['latitude']) ? $_POST['latitude'] : '',
        'longitude' => isset($_POST['longitude']) ? $_POST['longitude'] : '',
        'motif' => isset($_POST['motif']) ? $_POST['motif'] : '',
    ), (int) $_SESSION['user_id']);
} catch (Exception $e) {
    $resultat = $e->getMessage();
}

if (!is_array($resultat)) {
    $_SESSION['error_message'] = (string) $resultat;
    header('Location: ' . $retour);
    exit;
}

$message = 'Compteur remplacé : n° ' . $resultat['ancien_numero'] . ' déposé à l\'index '
    . CompteurRemplacement::formatIndex($resultat['index_depose']) . ', nouveau compteur n° '
    . $resultat['nouveau_numero'] . ' posé à l\'index ' . CompteurRemplacement::formatIndex($resultat['index_initial']) . '.';
if ($resultat['releve_mis_a_jour']) {
    $message .= ' Le relevé du mois en cours (' . (function_exists('getLetterMonth') ? getLetterMonth($resultat['mois']) : $resultat['mois'])
        . ') a été arrêté à l\'index de dépose.';
}
$_SESSION['success_message'] = $message;
header('Location: ' . $retour . '&section=index');
exit;
