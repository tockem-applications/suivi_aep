<?php
/**
 * Endpoint AJAX : réordonnancement des catégories flux manuels (drag & drop).
 * Réponse JSON uniquement (pas de layout index.php).
 */
header('Content-Type: application/json; charset=utf-8');

@include_once(__DIR__ . '/../donnees/manager.php');
@include_once(__DIR__ . '/../donnees/categorie_flux_manuel.php');

$response = array('ok' => false, 'ordres' => array());

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action !== 'reorder_ordre') {
    echo json_encode($response);
    exit;
}

$type_flux = isset($_POST['type_flux']) ? $_POST['type_flux'] : '';
$ids = isset($_POST['categorie_ids']) && is_array($_POST['categorie_ids'])
    ? $_POST['categorie_ids'] : array();

CategorieFluxManuel::ensureOrdreAffichageColumn();
$ok = CategorieFluxManuel::reorderOrdreAffichage($ids, $type_flux, $id_aep);

if ($ok) {
    $ordre = 10;
    foreach ($ids as $raw_id) {
        $response['ordres'][(int) $raw_id] = $ordre;
        $ordre += 10;
    }
}
$response['ok'] = $ok;

echo json_encode($response);
exit;
