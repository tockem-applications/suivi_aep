<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();

/**
 * Téléchargement au format PDF des avis de coupure retenus pour un mois de
 * facturation : deux avis par page A4, prêts à être imprimés et découpés.
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/avis_coupure.php");
@include_once("donnees/avis_coupure.php");
@include_once("../donnees/avis_coupure_pdf.php");
@include_once("donnees/avis_coupure_pdf.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}
if (!isset($_SESSION['id_aep']) || (int) $_SESSION['id_aep'] <= 0) {
    header('Location: ../index.php?page=avis_coupure&error=no_aep&message=' . urlencode('Aucun AEP sélectionné.'));
    exit;
}

$aepId = (int) $_SESSION['id_aep'];
$libeleAep = isset($_SESSION['libele_aep']) ? trim($_SESSION['libele_aep']) : '';
$id_mois = isset($_GET['id_mois']) ? (int) $_GET['id_mois'] : 0;

if ($id_mois <= 0) {
    header('Location: ../index.php?page=avis_coupure&error=invalid_data&message=' . urlencode('Mois de facturation manquant.'));
    exit;
}

// Le mois doit appartenir à l'AEP courant : l'identifiant vient de l'URL.
$moisAutorise = Manager::prepare_query(
    "SELECT m.mois FROM mois_facturation m
       INNER JOIN constante_reseau c ON c.id = m.id_constante
      WHERE m.id = ? AND c.id_aep = ?",
    array($id_mois, $aepId)
)->fetch(PDO::FETCH_ASSOC);

if (!$moisAutorise) {
    header('Location: ../index.php?page=avis_coupure&error=invalid_data&message=' . urlencode('Mois de facturation inconnu pour cet AEP.'));
    exit;
}

$libelleMois = function_exists('getLetterMonth') ? getLetterMonth($moisAutorise['mois']) : (string) $moisAutorise['mois'];
$campagne = AvisCoupure::getCampagne($aepId, $id_mois);
$avis = AvisCoupure::getAvis($aepId, $id_mois);

// Le responsable est commun à la campagne : son nom et son téléphone figurent
// sur chaque avis.
$responsableNom = '';
$responsableTel = '';
foreach ($avis as $ligne) {
    if (!empty($ligne['responsable_nom']) || !empty($ligne['responsable_prenom'])) {
        $responsableNom = AvisCoupure::nomComplet(array(
            'nom' => isset($ligne['responsable_nom']) ? $ligne['responsable_nom'] : '',
            'prenom' => isset($ligne['responsable_prenom']) ? $ligne['responsable_prenom'] : '',
        ));
        $responsableTel = isset($ligne['responsable_telephone']) ? $ligne['responsable_telephone'] : '';
        break;
    }
}

$pdf = AvisCoupurePdf::generer($avis, array(
    'entete' => $campagne['entete'],
    'reference' => $libeleAep !== ''
        ? 'AEP ' . (function_exists('mb_strtoupper') ? mb_strtoupper($libeleAep, 'UTF-8') : strtoupper($libeleAep))
        : 'AEP',
    'libelle_mois' => $libelleMois,
    'responsable' => $responsableNom,
    'contact' => $responsableTel,
));

$nomFichier = 'avis_coupure_' . ($libeleAep !== '' ? $libeleAep . '_' : '') . str_replace(' ', '_', $libelleMois);
$pdf->telecharger($nomFichier);
exit;
