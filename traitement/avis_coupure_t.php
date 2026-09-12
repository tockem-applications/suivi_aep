<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();

/**
 * Avis de coupure : paramètres de la campagne (responsable, date, en-tête,
 * seuil, frais de remise en service), logo de l'AEP, sélection des abonnés
 * retenus pour le mois et rétablissement des abonnés coupés après règlement.
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

/**
 * Reconstruit l'URL de retour avec le contexte d'affichage de la page.
 *
 * @param string $onglet section à rouvrir : parametrage, avis ou paiements
 */
function avis_coupure_retour($id_mois, $onglet = '')
{
    $parts = array();
    if ((int) $id_mois > 0) {
        $parts[] = 'id_mois=' . (int) $id_mois;
    }
    if ($onglet !== '') {
        $parts[] = 'onglet=' . $onglet;
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
$retour = avis_coupure_retour($id_mois, 'avis');
$retourParametrage = avis_coupure_retour($id_mois, 'parametrage');
$retourPaiements = avis_coupure_retour($id_mois, 'paiements');

if ($id_mois <= 0) {
    header('Location: ' . $base_redirect . '&error=invalid_data&message=' . urlencode('Mois de facturation manquant.'));
    exit;
}

// Le logo est propre à l'AEP : il reste en place d'un mois sur l'autre.
if ($_POST['action'] === 'save_logo') {
    $fichier = isset($_FILES['logo']) ? $_FILES['logo'] : null;
    $erreur = '';
    if ($fichier === null || !isset($fichier['error']) || $fichier['error'] === UPLOAD_ERR_NO_FILE) {
        $erreur = 'Choisissez un fichier image.';
    } elseif ($fichier['error'] === UPLOAD_ERR_INI_SIZE || $fichier['error'] === UPLOAD_ERR_FORM_SIZE) {
        $erreur = 'Le logo dépasse la taille autorisée.';
    } elseif ($fichier['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($fichier['tmp_name'])) {
        $erreur = 'Échec du téléversement (code ' . (int) $fichier['error'] . ').';
    }
    if ($erreur === '') {
        try {
            $resultat = AvisCoupure::enregistrerLogo(
                $aepId,
                file_get_contents($fichier['tmp_name']),
                isset($fichier['name']) ? $fichier['name'] : ''
            );
            if ($resultat !== true) {
                $erreur = (string) $resultat;
            }
        } catch (Exception $e) {
            $erreur = $e->getMessage();
        }
    }
    if ($erreur !== '') {
        header('Location: ' . $base_redirect . $retourParametrage . '&error=logo_failed&message=' . urlencode($erreur));
        exit;
    }
    header('Location: ' . $base_redirect . $retourParametrage . '&success=logo_saved');
    exit;
}

if ($_POST['action'] === 'delete_logo') {
    try {
        AvisCoupure::supprimerLogo($aepId);
        header('Location: ' . $base_redirect . $retourParametrage . '&success=logo_deleted');
    } catch (Exception $e) {
        header('Location: ' . $base_redirect . $retourParametrage . '&error=logo_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

// Paramètres de la campagne du mois. Les frais de remise en service fixés ici
// sont repris par défaut sur les mois suivants.
if ($_POST['action'] === 'save_campagne') {
    $id_responsable = isset($_POST['id_responsable']) ? (int) $_POST['id_responsable'] : 0;
    $date_avis = isset($_POST['date_avis']) ? trim($_POST['date_avis']) : '';
    $entete = isset($_POST['entete']) ? trim($_POST['entete']) : '';
    $seuil = isset($_POST['seuil']) ? (int) $_POST['seuil'] : AvisCoupure::SEUIL_MOIS_DEFAUT;
    $frais = isset($_POST['frais_remise']) ? (float) str_replace(array(' ', ','), array('', '.'), $_POST['frais_remise']) : 0.0;

    try {
        AvisCoupure::enregistrerCampagne($aepId, $id_mois, $seuil, $id_responsable, $entete, $date_avis, $frais);
        header('Location: ' . $base_redirect . $retourParametrage . '&success=campagne_saved');
    } catch (Exception $e) {
        header('Location: ' . $base_redirect . $retourParametrage . '&error=save_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

// Rétablissement d'un abonné coupé : montant versé et date, enregistrés sur sa
// dernière facture ; l'avis reste en place pour la traçabilité.
if ($_POST['action'] === 'retablir') {
    $id_abone = isset($_POST['id_abone']) ? (int) $_POST['id_abone'] : 0;
    $montant = isset($_POST['montant_verse']) ? (float) str_replace(array(' ', ','), array('', '.'), $_POST['montant_verse']) : -1;
    $frais = isset($_POST['frais_remise']) ? (float) str_replace(array(' ', ','), array('', '.'), $_POST['frais_remise']) : 0.0;
    $date = isset($_POST['date_reglement']) ? trim($_POST['date_reglement']) : '';

    if ($id_abone <= 0 || $date === '' || !isset($_POST['montant_verse']) || trim($_POST['montant_verse']) === '') {
        header('Location: ' . $base_redirect . $retourPaiements . '&error=invalid_data&message='
            . urlencode('Renseignez le montant versé et la date du règlement.'));
        exit;
    }
    try {
        $resultat = AvisCoupure::retablir($aepId, $id_abone, $montant, $date, $frais, (int) $_SESSION['user_id']);
        if (!is_array($resultat)) {
            header('Location: ' . $base_redirect . $retourPaiements . '&error=retablir_failed&message=' . urlencode((string) $resultat));
            exit;
        }
        header('Location: ' . $base_redirect . $retourPaiements . '&success=retabli'
            . '&nom=' . urlencode($resultat['nom']) . '&m=' . urlencode((string) $resultat['montant'])
            . '&mois=' . urlencode((string) $resultat['mois']));
    } catch (Exception $e) {
        header('Location: ' . $base_redirect . $retourPaiements . '&error=retablir_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

if ($_POST['action'] === 'save_selection') {
    $abones = isset($_POST['abones']) && is_array($_POST['abones']) ? $_POST['abones'] : array();
    $visibles = isset($_POST['visibles']) && is_array($_POST['visibles']) ? $_POST['visibles'] : array();

    try {
        // Le responsable et la date viennent du paramétrage de la campagne ;
        // une campagne jamais paramétrée est créée avec ses valeurs par défaut.
        $campagne = AvisCoupure::getCampagne($aepId, $id_mois);
        $id_responsable = (int) $campagne['id_responsable'] > 0 ? (int) $campagne['id_responsable'] : (int) $_SESSION['user_id'];
        $date_avis = $campagne['date_avis'] !== '' ? $campagne['date_avis'] : date('Y-m-d');
        if (!$campagne['enregistree']) {
            AvisCoupure::enregistrerCampagne(
                $aepId,
                $id_mois,
                $campagne['seuil_mois'],
                $id_responsable,
                $campagne['entete'],
                $date_avis,
                $campagne['frais_remise']
            );
        }
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
