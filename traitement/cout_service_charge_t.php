<?php
require_once __DIR__ . '/_guard.php';
traitement_guard(true);
/**
 * Endpoint AJAX : édition (par mois) et simulation des charges prises en compte
 * dans le calcul du coût du service (page analyse_financiere). Réponse JSON uniquement.
 */
header('Content-Type: application/json; charset=utf-8');

@include_once(__DIR__ . '/../donnees/manager.php');
@include_once(__DIR__ . '/../donnees/cout_service_charge.php');

CoutServiceCharge::ensureTable();

$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
if (!$id_aep) {
    echo json_encode(array('ok' => false, 'error' => 'Aucun AEP sélectionné.'));
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

function csc_mois_valide($mois)
{
    return is_string($mois) && preg_match('/^\d{4}-\d{2}$/', $mois);
}

if ($action === 'get_form') {
    $mois = isset($_GET['mois']) ? $_GET['mois'] : '';
    if (!csc_mois_valide($mois)) {
        echo json_encode(array('ok' => false, 'error' => 'Mois invalide.'));
        exit;
    }

    $selection = CoutServiceCharge::getSelectionEffective($id_aep, $mois);
    $categories = CoutServiceCharge::getCategoriesChargeDisponibles($id_aep);
    $redevances = CoutServiceCharge::getRedevancesDisponibles($id_aep);

    $categoriesOut = array();
    foreach ($categories as $c) {
        $categoriesOut[] = array(
            'id' => (int) $c['id'],
            'nom' => $c['nom'],
            'code_budgetaire' => isset($c['code_budgetaire']) ? $c['code_budgetaire'] : '',
            'actif' => !empty($c['est_actif']),
            'activite_associee' => isset($c['activite_associee']) ? $c['activite_associee'] : 'autre',
            'checked' => in_array((int) $c['id'], $selection['categories'], true),
        );
    }
    $redevancesOut = array();
    foreach ($redevances as $r) {
        $redevancesOut[] = array(
            'id' => (int) $r['id'],
            'libele' => $r['libele'],
            'checked' => in_array((int) $r['id'], $selection['redevances'], true),
        );
    }

    echo json_encode(array(
        'ok' => true,
        'mois' => $mois,
        'herite_de' => $selection['herite_de'],
        'sans_categorie_checked' => !empty($selection['sans_categorie']),
        'categories' => $categoriesOut,
        'redevances' => $redevancesOut,
    ));
    exit;
}

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('ok' => false, 'error' => 'Méthode non autorisée.'));
        exit;
    }
    Csrf::requireValid('Jeton CSRF invalide.', true);

    $mois = isset($_POST['mois']) ? $_POST['mois'] : '';
    if (!csc_mois_valide($mois)) {
        echo json_encode(array('ok' => false, 'error' => 'Mois invalide.'));
        exit;
    }
    $categories = isset($_POST['categories']) && is_array($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
    $redevances = isset($_POST['redevances']) && is_array($_POST['redevances']) ? array_map('intval', $_POST['redevances']) : array();
    $sans_categorie = !empty($_POST['sans_categorie']);

    CoutServiceCharge::saveSelection($id_aep, $mois, $categories, $redevances, $sans_categorie);
    $cout = CoutServiceCharge::calculerCoutMois($id_aep, $mois, array(
        'categories' => $categories,
        'redevances' => $redevances,
        'sans_categorie' => $sans_categorie,
    ));

    echo json_encode(array('ok' => true, 'mois' => $mois, 'cout' => $cout));
    exit;
}

// Simulation a partir d'une selection choisie a la main dans la modale : la meme
// selection est appliquee a tous les mois de la periode, sans rien enregistrer.
if ($action === 'simulate_selection') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('ok' => false, 'error' => 'Méthode non autorisée.'));
        exit;
    }
    Csrf::requireValid('Jeton CSRF invalide.', true);

    $mois_debut = isset($_POST['mois_debut']) ? $_POST['mois_debut'] : '';
    $mois_fin = isset($_POST['mois_fin']) ? $_POST['mois_fin'] : '';
    if (!csc_mois_valide($mois_debut) || !csc_mois_valide($mois_fin)) {
        echo json_encode(array('ok' => false, 'error' => 'Paramètres invalides.'));
        exit;
    }

    $selection = array(
        'categories' => isset($_POST['categories']) && is_array($_POST['categories']) ? array_map('intval', $_POST['categories']) : array(),
        'redevances' => isset($_POST['redevances']) && is_array($_POST['redevances']) ? array_map('intval', $_POST['redevances']) : array(),
        'sans_categorie' => !empty($_POST['sans_categorie']),
    );

    $moisList = CoutServiceCharge::getMoisListPeriode($id_aep, $mois_debut, $mois_fin);
    $mois = array();
    $coutParM3 = array();
    foreach ($moisList as $m) {
        $cout = CoutServiceCharge::calculerCoutMois($id_aep, $m, $selection);
        $mois[] = $m;
        $coutParM3[] = round($cout['cout_par_m3'], 2);
    }

    echo json_encode(array(
        'ok' => true,
        'mois' => $mois,
        'cout_par_m3' => $coutParM3,
        'nb_charges' => count($selection['categories']) + count($selection['redevances']) + ($selection['sans_categorie'] ? 1 : 0),
    ));
    exit;
}

if ($action === 'simulate') {
    $mois_source = isset($_GET['mois_source']) ? $_GET['mois_source'] : '';
    $mois_debut = isset($_GET['mois_debut']) ? $_GET['mois_debut'] : '';
    $mois_fin = isset($_GET['mois_fin']) ? $_GET['mois_fin'] : '';
    if (!csc_mois_valide($mois_source) || !csc_mois_valide($mois_debut) || !csc_mois_valide($mois_fin)) {
        echo json_encode(array('ok' => false, 'error' => 'Paramètres invalides.'));
        exit;
    }

    $selectionSource = CoutServiceCharge::getSelectionEffective($id_aep, $mois_source);
    $moisList = CoutServiceCharge::getMoisListPeriode($id_aep, $mois_debut, $mois_fin);

    $mois = array();
    $coutParM3 = array();
    foreach ($moisList as $m) {
        $cout = CoutServiceCharge::calculerCoutMois($id_aep, $m, $selectionSource);
        $mois[] = $m;
        $coutParM3[] = round($cout['cout_par_m3'], 2);
    }

    echo json_encode(array(
        'ok' => true,
        'mois_source' => $mois_source,
        'mois' => $mois,
        'cout_par_m3' => $coutParM3,
    ));
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'Action inconnue.'));
exit;
