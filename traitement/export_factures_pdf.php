<?php
/**
 * Export PDF des factures du mois : une page PDF par facture, reproduction du layout
 * des factures actuelles (model_fokoue = Fokoue2, model_nkongzem = Nkongzem).
 * Compatible PHP 5. Utilise FPDF (utils/fpdf.php).
 */
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/facture.php");
@include_once("donnees/facture.php");
@include_once("../donnees/aep.php");
@include_once("donnees/aep.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}
if (!isset($_SESSION['id_aep'])) {
    header('Location: ../index.php?list=liste_facture_month&error=no_aep');
    exit;
}

$id_mois = isset($_GET['id_mois']) ? (int) $_GET['id_mois'] : 0;
if ($id_mois <= 0) {
    header('Location: ../index.php?list=liste_facture_month&error=no_month');
    exit;
}

$id_aep = (int) $_SESSION['id_aep'];
$req = Facture::getMonthAllFactureData($id_mois, $id_aep);
if (!$req) {
    header('Location: ../index.php?list=liste_facture_month&id_mois=' . $id_mois . '&error=no_data');
    exit;
}
$factures = $req->fetchAll(PDO::FETCH_ASSOC);

$mois_query = Facture::prepare_query("SELECT mois FROM mois_facturation WHERE id = ?", array($id_mois));
$mois_row = $mois_query ? $mois_query->fetch(PDO::FETCH_ASSOC) : null;
$mois_lettre = $mois_row && function_exists('getLetterMonth') ? getLetterMonth($mois_row['mois']) : (string) $id_mois;
$libele_aep = isset($_SESSION['libele_aep']) ? $_SESSION['libele_aep'] : 'AEP';

// Modèle de facture (model_fokoue => Fokoue2, model_nkongzem => Nkongzem)
$model_facture = 'model_nkongzem';
$aep_row = Aep::getOne($id_aep, 'aep');
if ($aep_row) {
    $aep_fetch = $aep_row->fetch(PDO::FETCH_ASSOC);
    if (!empty($aep_fetch['fichier_facture'])) {
        $model_facture = $aep_fetch['fichier_facture'];
    }
}

$fpdf_path = dirname(__FILE__) . '/../utils/fpdf.php';
if (!file_exists($fpdf_path)) {
    header('Location: ../index.php?list=liste_facture_month&id_mois=' . $id_mois . '&error=no_fpdf');
    exit;
}
require_once($fpdf_path);

if (!class_exists('FPDF')) {
    header('Location: ../index.php?list=liste_facture_month&id_mois=' . $id_mois . '&error=no_fpdf');
    exit;
}

// --- Helpers (alignés sur facture_t.php) ---
function pdf_moneyFormatter($montant)
{
    return number_format((float) $montant, 0, ',', ' ');
}
function pdf_addDaysAndFormat($string_date, $days = 10)
{
    if (empty($string_date))
        return '';
    try {
        $date = new DateTime($string_date);
        $date->modify("+$days days");
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return (string) $string_date;
    }
}
function pdf_formatDateOnly($string_date)
{
    if (empty($string_date))
        return '';
    try {
        $date = new DateTime($string_date);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return (string) $string_date;
    }
}
function pdf_addZeros($chaine, $nomber_of_zero = 5)
{
    return str_pad((string) $chaine, $nomber_of_zero, '0', STR_PAD_LEFT);
}

// Encodage pour FPDF (Latin1)
function pdf_enc($s)
{
    $s = (string) $s;
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    }
    return $s;
}

// Répertoire des images (logos) pour le PDF
$GLOBALS['pdf_images_dir'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'presentation' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;

// Couleurs texte (alignées sur le HTML : text-success, text-danger, #5B9BD5, #2F5496)
$GLOBALS['pdf_color_success'] = array(25, 135, 84);   // vert Bootstrap
$GLOBALS['pdf_color_danger'] = array(220, 53, 69);   // rouge Bootstrap
$GLOBALS['pdf_color_blue'] = array(91, 155, 213);  // #5B9BD5
$GLOBALS['pdf_color_blue_dark'] = array(47, 84, 150); // #2F5496
$GLOBALS['pdf_color_border_fokoue'] = array(108, 117, 125); // #6c757d border-heavy

/**
 * Une page PDF = une facture au format Fokoue2 (AMGEEA, paysage) - calquée sur le HTML
 */
function drawFactureFokoue2(FPDF $pdf, $data, $mois_lettre, $libele_aep)
{
    $img_dir = isset($GLOBALS['pdf_images_dir']) ? $GLOBALS['pdf_images_dir'] : '';
    $nom = isset($data['nom_abone']) ? trim($data['nom_abone']) : (isset($data['nom']) ? trim($data['nom']) : '');
    $numero_compteur = isset($data['numero_compteur']) ? $data['numero_compteur'] : '';
    $reseau = isset($data['reseau']) ? $data['reseau'] : '';
    $ancien_index = isset($data['ancien_index']) ? $data['ancien_index'] : '';
    $nouvel_index = isset($data['nouvel_index']) ? $data['nouvel_index'] : '';
    $conso_mois = Facture::calculeConso((float) $nouvel_index, (float) $ancien_index);
    $impaye = isset($data['impayer_cumule']) ? (int) $data['impayer_cumule'] : 0;
    $penalite = isset($data['penalite']) ? (int) $data['penalite'] : 0;
    $prix_eau = isset($data['prix_metre_cube_eau']) ? $data['prix_metre_cube_eau'] : '';
    $prix_entretient = isset($data['prix_entretient_compteur']) ? $data['prix_entretient_compteur'] : '';
    $totalFacture = isset($data['total_cumule']) ? (float) $data['total_cumule'] : 0;
    $facture_mois = isset($data['montant_conso']) ? (float) $data['montant_conso'] : 0;
    $tva = isset($data['prix_tva']) ? $data['prix_tva'] : '';
    $date_depot = pdf_formatDateOnly(isset($data['date_depot']) ? $data['date_depot'] : '');
    $date_max_paiement = pdf_addDaysAndFormat(isset($data['date_depot']) ? $data['date_depot'] : '', 10);
    $id_facture = pdf_addZeros(isset($data['id_facture']) ? $data['id_facture'] : 0, 6);
    $impaye_str = pdf_moneyFormatter(abs($impaye));

    $pdf->AddPage('L', 'A4');
    $marge = 8;
    $page_w = 297 - 2 * $marge;
    $page_h = 210 - 2 * $marge;
    $pdf->SetMargins($marge, $marge, $marge);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetTextColor(0, 0, 0);

    // Bordure container (angles arrondis, bordure noire)
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(1);
    $pdf->RoundedRect($marge, $marge, $page_w, $page_h, 4, '1234', 'D');

    $y = 12;
    $logo_w = 40;
    $logo_h = 32;
    $center_w = $page_w - 2 * $logo_w;

    // Ligne 1 : logo gauche | titre 2 lignes centre | logo droite
    if ($img_dir !== '') {
        if (file_exists($img_dir . 'logo_commune_fokoue.png')) {
            $pdf->Image($img_dir . 'logo_commune_fokoue.png', $marge + 2, $y, 0, $logo_h, 'png');
        }
        if (file_exists($img_dir . 'logo_amgeea.png')) {
            $pdf->Image($img_dir . 'logo_amgeea.png', 297 - $marge - $logo_w - 2, $y, 0, $logo_h, 'png');
        }
    }
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetXY($marge + $logo_w + 2, $y + 4);
    $pdf->Cell($center_w, 8, pdf_enc('Agence Municipale de la Gestion de l\'Energie, de l\'Eau et de'), 0, 1, 'C');
    $pdf->SetX($marge + $logo_w + 2);
    $pdf->Cell($center_w, 8, pdf_enc('l\'Assainissement - Commune de Fokoue (AMGEEA)'), 0, 1, 'C');
    $y = $pdf->GetY() + 5;

    // Email | B.P - sur une ligne, 2 colonnes centrées
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY($marge, $y);
    $pdf->Cell($center_w / 2 + $logo_w, 7, pdf_enc('Email: amgeeafokoue@gmail.com'), 0, 0, 'C');
    $pdf->Cell($center_w / 2 + $logo_w, 7, pdf_enc('B.P 02 Fokoue'), 0, 1, 'C');
    $y = $pdf->GetY();
    $pdf->SetXY($marge, $y);
    $pdf->Cell($page_w, 7, pdf_enc('Tel: 656 16 16 82 / 699 35 25 11 / 699 82 01 49 / 677 03 58 09'), 0, 1, 'C');
    $y = $pdf->GetY() + 6;

    // FACTURE D'EAU / WATER BILL N° (h2)
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->SetXY($marge, $y);
    $pdf->Cell($page_w, 11, pdf_enc('FACTURE D\'EAU / WATER BILL N° ' . $id_facture), 0, 1, 'C');
    $y = $pdf->GetY();
    $pdf->SetFont('Helvetica', 'I', 11);
    $pdf->SetXY($marge, $y);
    $pdf->MultiCell($page_w, 6, pdf_enc('Tous ensemble pour un accès durable à l\'eau, à l\'énergie et à l\'assainissement dans la commune de Fokoue'), 0, 'C');
    $y = $pdf->GetY() + 5;

    // Row : Date de dépôt (vert) | Date limite (rouge)
    $col_w = $page_w / 2;
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY($marge, $y);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($col_w / 2, 8, pdf_enc('Date de depot: '), 0, 0, 'C');
    $pdf->SetTextColor($GLOBALS['pdf_color_success'][0], $GLOBALS['pdf_color_success'][1], $GLOBALS['pdf_color_success'][2]);
    $pdf->Cell($col_w / 2, 8, $date_depot, 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($col_w / 2, 8, pdf_enc('Date limite de paiement: '), 0, 0, 'C');
    $pdf->SetTextColor($GLOBALS['pdf_color_danger'][0], $GLOBALS['pdf_color_danger'][1], $GLOBALS['pdf_color_danger'][2]);
    $pdf->Cell($col_w / 2, 8, $date_max_paiement, 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $y = $pdf->GetY();

    // Row : Période | Impayés (rouge) / Compte anticipation (vert) | Pénalité
    $col3 = $page_w / 3;
    $pdf->SetXY($marge, $y);
    $pdf->Cell($col3, 8, pdf_enc('Periode de facturation: ') . $mois_lettre, 0, 0, 'C');
    if ($impaye < 0) {
        $pdf->Cell($col3 / 2, 8, pdf_enc('Compte anticipation: '), 0, 0, 'C');
        $pdf->SetTextColor($GLOBALS['pdf_color_success'][0], $GLOBALS['pdf_color_success'][1], $GLOBALS['pdf_color_success'][2]);
        $pdf->Cell($col3 / 2, 8, $impaye_str, 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    } elseif ($impaye > 0) {
        $pdf->Cell($col3 / 2, 8, pdf_enc('Impayes: '), 0, 0, 'C');
        $pdf->SetTextColor($GLOBALS['pdf_color_danger'][0], $GLOBALS['pdf_color_danger'][1], $GLOBALS['pdf_color_danger'][2]);
        $pdf->Cell($col3 / 2, 8, $impaye_str, 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->Cell($col3, 8, pdf_enc('Impayes: ') . $impaye_str, 0, 0, 'C');
    }
    $pdf->Cell($col3, 8, pdf_enc('Penalite: ') . pdf_moneyFormatter($penalite), 0, 1, 'C');
    $y = $pdf->GetY() + 4;

    // Nom du client | N° compteur | Réseau (justify-around)
    $pdf->SetXY($marge, $y);
    $pdf->Cell($col3, 8, pdf_enc('Nom du client: ') . $nom, 0, 0, 'L');
    $pdf->Cell($col3, 8, pdf_enc('N° compteur: ') . $numero_compteur, 0, 0, 'C');
    $pdf->Cell($col3, 8, pdf_enc('Reseau: ') . $reseau, 0, 1, 'R');
    $y = $pdf->GetY() + 5;

    // Tableau facture - largeur pleine page, bordures #6c757d (border-heavy), séparations nettes
    $w = array(82, 32, 32, 28, 32, 42, 32);
    $bc = $GLOBALS['pdf_color_border_fokoue'];
    $pdf->SetDrawColor($bc[0], $bc[1], $bc[2]);
    $pdf->SetLineWidth(0.5);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetXY($marge, $y);
    foreach (array(pdf_enc('Rubrique Facture'), pdf_enc('Ancien index'), pdf_enc('Nouvel index'), pdf_enc('Consommation'), pdf_enc('Tarif'), pdf_enc('Montant HT'), pdf_enc('TVA')) as $i => $h) {
        $pdf->Cell($w[$i], 10, $h, 1, 0, $i === 0 ? 'L' : 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->Cell($w[0], 7, '', 1, 0);
    $pdf->Cell($w[1], 7, '', 1, 0);
    $pdf->Cell($w[2], 7, '', 1, 0);
    $pdf->Cell($w[3], 7, 'm3', 1, 0, 'C');
    $pdf->Cell($w[4], 7, 'Fcfa', 1, 0, 'C');
    $pdf->Cell($w[5], 7, 'Fcfa', 1, 0, 'C');
    $pdf->Cell($w[6], 7, 'Fcfa', 1, 1, 'C');
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->Cell($w[0], 10, pdf_enc('Consommation facturee'), 1, 0, 'L');
    $pdf->Cell($w[1], 10, $ancien_index, 1, 0, 'C');
    $pdf->Cell($w[2], 10, $nouvel_index, 1, 0, 'C');
    $pdf->Cell($w[3], 10, $conso_mois, 1, 0, 'C');
    $pdf->Cell($w[4], 10, $prix_eau, 1, 0, 'C');
    $pdf->Cell($w[5], 10, pdf_moneyFormatter($facture_mois), 1, 0, 'C');
    $pdf->Cell($w[6], 10, $tva, 1, 1, 'C');
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->Cell($w[0], 10, pdf_enc('Entretien compteur'), 1, 0, 'L');
    $pdf->Cell($w[1], 10, '', 1, 0);
    $pdf->Cell($w[2], 10, '', 1, 0);
    $pdf->Cell($w[3], 10, '', 1, 0);
    $pdf->Cell($w[4], 10, $prix_entretient, 1, 0, 'C');
    $pdf->Cell($w[5], 10, $prix_entretient, 1, 0, 'C');
    $pdf->Cell($w[6], 10, '', 1, 1, 'C');
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], 12, pdf_enc('Montant total facture'), 1, 0, 'L', true);
    $pdf->Cell($w[4] + $w[5], 12, pdf_moneyFormatter($totalFacture), 1, 0, 'C', true);
    $pdf->Cell($w[6], 12, '', 1, 1);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    $y = $pdf->GetY() + 6;

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetXY($marge, $y);
    $pdf->MultiCell($page_w, 7, pdf_enc('ATTENTION: Tout paiement après la date limite est augmente des frais de penalite (2500 Fcfa).'), 0, 'C');
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->MultiCell($page_w, 7, pdf_enc('Any payment after the date above will be increased with penalty (2500 Fcfa).'), 0, 'C');
}

/**
 * Une page PDF = une facture au format Nkongzem (TOCKEM / Nkong Zem) - calquée sur le HTML
 */
function drawFactureNkongzem(FPDF $pdf, $data, $mois_lettre, $libele_aep)
{
    $img_dir = isset($GLOBALS['pdf_images_dir']) ? $GLOBALS['pdf_images_dir'] : '';
    $nom = isset($data['nom_abone']) ? trim($data['nom_abone']) : (isset($data['nom']) ? trim($data['nom']) : '');
    if (strlen($nom) > 40)
        $nom = substr($nom, 0, 40) . '..';
    $numero_compteur = isset($data['numero_compteur']) ? $data['numero_compteur'] : '';
    $reseau = isset($data['reseau']) ? $data['reseau'] : '';
    $ancien_index = isset($data['ancien_index']) ? $data['ancien_index'] : '';
    $nouvel_index = isset($data['nouvel_index']) ? $data['nouvel_index'] : '';
    $conso_mois = Facture::calculeConso((float) $nouvel_index, (float) $ancien_index);
    $impaye = isset($data['impayer_cumule']) ? (int) $data['impayer_cumule'] : 0;
    $prix_eau = isset($data['prix_metre_cube_eau']) ? $data['prix_metre_cube_eau'] : '';
    $prix_entretient = isset($data['prix_entretient_compteur']) ? $data['prix_entretient_compteur'] : '';
    $totalFacture = isset($data['total_cumule']) ? (float) $data['total_cumule'] : 0;
    $facture_mois = isset($data['montant_conso']) ? (float) $data['montant_conso'] : 0;
    $date_depot = pdf_formatDateOnly(isset($data['date_depot']) ? $data['date_depot'] : '');
    $date_releve = pdf_formatDateOnly(isset($data['date_releve']) ? $data['date_releve'] : '');
    $id_facture = pdf_addZeros(isset($data['id_facture']) ? $data['id_facture'] : 0, 6);
    $numero_compte_banque = isset($data['numero_compte']) ? $data['numero_compte'] : '---------------';
    $nom_banque = isset($data['nom_banque']) ? $data['nom_banque'] : '';

    $pdf->AddPage('L', 'A4');
    $marge = 6;
    $page_w = 297 - 2 * $marge;
    $pdf->SetMargins($marge, $marge, $marge);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetTextColor(0, 0, 0);

    $y = 8;
    $logo_w = 44;
    $logo_h = 36;
    $center_w = $page_w - 2 * $logo_w;

    // Ligne 1 : [Logo Tockem + Mois] | [FACTURE N°, N° Compte, Merci..., ATTENTION, résilié 3] | [Logo Nkongzem]
    if ($img_dir !== '') {
        if (file_exists($img_dir . 'logo_tockem.png')) {
            $pdf->Image($img_dir . 'logo_tockem.png', $marge, $y, 0, $logo_h, 'png');
        }
        if (file_exists($img_dir . 'logo_nkongzem.png')) {
            $pdf->Image($img_dir . 'logo_nkongzem.png', 297 - $marge - $logo_w, $y, 0, $logo_h, 'png');
        }
    }
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetXY($marge + $logo_w + 2, $y + 6);
    $pdf->SetTextColor($GLOBALS['pdf_color_success'][0], $GLOBALS['pdf_color_success'][1], $GLOBALS['pdf_color_success'][2]);
    $pdf->Cell(35, 7, $mois_lettre, 0, 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($marge + $logo_w, $y);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell($center_w, 9, pdf_enc('FACTURE D\'EAU POTABLE. N° ' . $id_facture), 0, 1, 'C');
    $pdf->SetX($marge + $logo_w);
    $pdf->SetFont('Helvetica', 'I', 11);
    $pdf->Cell($center_w, 7, pdf_enc('N° de Compte ' . $nom_banque . ' : ' . $numero_compte_banque), 0, 1, 'C');
    $pdf->SetX($marge + $logo_w);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor($GLOBALS['pdf_color_blue'][0], $GLOBALS['pdf_color_blue'][1], $GLOBALS['pdf_color_blue'][2]);
    $pdf->Cell($center_w, 7, pdf_enc('Merci de payer dans les delais'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetX($marge + $logo_w);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell($center_w, 6, pdf_enc('ATTENTION !!!: VOUS RISQUEZ UNE COUPURE POUR FACTURES IMPAYEES'), 0, 1, 'C');
    $pdf->SetX($marge + $logo_w);
    $pdf->SetTextColor($GLOBALS['pdf_color_blue_dark'][0], $GLOBALS['pdf_color_blue_dark'][1], $GLOBALS['pdf_color_blue_dark'][2]);
    $pdf->Cell($center_w, 6, pdf_enc('Votre abonnement sera resilie au-dela de 3 factures impayees'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $y = $pdf->GetY() + 5;

    // Bandeau 3 colonnes : bordures #5B9BD5 (2px), angles arrondis
    $c1 = $page_w * 0.25;
    $c2 = $page_w * 0.50;
    $c3 = $page_w * 0.25;
    $bandeau_h = 16;
    $r_bandeau = 3;
    $bord = $GLOBALS['pdf_color_blue'];
    $pdf->SetDrawColor($bord[0], $bord[1], $bord[2]);
    $pdf->SetLineWidth(0.6);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY($marge, $y);
    $pdf->MultiCell($c1 - 2, 4, pdf_enc('Association TOCKEM - Siege Bureau d\'exploitation : Nkong Zem 1er etage immeuble derriere la place des fetes. BP 62 DSCHANG (Cameroun)'), 0, 'L');
    $pdf->SetFillColor(47, 85, 151);
    $pdf->RoundedRect($marge + $c1 + 2, $y, $c2 - 4, $bandeau_h, $r_bandeau, '1234', 'FD');
    $pdf->RoundedRect($marge + $c1 + $c2 + 2, $y, $c3 - 4, $bandeau_h, $r_bandeau, '1234', 'FD');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY($marge + $c1 + 2, $y + 2);
    $pdf->MultiCell($c2 - 4, 6, pdf_enc("Pour régler votre facture rendez vous aux Bureau de la régie\ncommunale de l'eau de Nkong-Zem entre 9h30 et 15h"), 0, 'C', false);
    $pdf->SetXY($marge + $c1 + $c2 + 2, $y + 4);
    $pdf->MultiCell($c3 - 4, 6, pdf_enc('Date limite de paiement - 10 Jours des reception'), 0, 'C', false);
    $pdf->SetTextColor(0, 0, 0);
    $y = $y + $bandeau_h + 5;

    // Deux blocs : bordures #5B9BD5, séparations de cellules visibles
    $bloc1_w = $page_w * 0.65;
    $bloc2_w = $page_w * 0.35;
    $lh = 10;
    $blocs_h = 3 * $lh;
    $r_bloc = 3;
    $pdf->SetFillColor(143, 170, 220);
    $pdf->RoundedRect($marge, $y, $bloc1_w, $blocs_h, $r_bloc, '1234', 'FD');
    $pdf->SetFillColor(47, 85, 151);
    $pdf->RoundedRect($marge + $bloc1_w, $y, $bloc2_w, $blocs_h, $r_bloc, '1234', 'FD');
    $pdf->SetDrawColor($bord[0], $bord[1], $bord[2]);
    $pdf->SetLineWidth(0.35);
    $pdf->SetFillColor(143, 170, 220);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY($marge, $y);
    $pdf->Cell($bloc1_w * 0.22, $lh, pdf_enc('Adresse'), 1, 0, 'L', true);
    $pdf->Cell($bloc1_w * 0.28, $lh, $reseau, 1, 0, 'L');
    $pdf->Cell($bloc1_w * 0.22, $lh, pdf_enc('N° Compteur'), 1, 0, 'L', true);
    $pdf->Cell($bloc1_w * 0.28, $lh, $numero_compteur, 1, 0, 'L');
    $pdf->SetFillColor(47, 85, 151);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($bloc2_w * 0.45, $lh, pdf_enc('Impayes'), 1, 0, 'L', true);
    $pdf->Cell($bloc2_w * 0.55, $lh, pdf_moneyFormatter($impaye) . ' FCFA', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->SetFillColor(143, 170, 220);
    $pdf->Cell($bloc1_w * 0.22, $lh, pdf_enc('Nom/Prenom'), 1, 0, 'L', true);
    $pdf->Cell($bloc1_w * 0.28, $lh, $nom, 1, 0, 'L');
    $pdf->Cell($bloc1_w * 0.22, $lh, pdf_enc('Date de releve'), 1, 0, 'L', true);
    $pdf->Cell($bloc1_w * 0.28, $lh, $date_releve, 1, 0, 'L');
    $pdf->SetFillColor(47, 85, 151);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($bloc2_w * 0.45, $lh, pdf_enc('Facture du mois'), 1, 0, 'L', true);
    $pdf->Cell($bloc2_w * 0.55, $lh, pdf_moneyFormatter($facture_mois + (int) $prix_entretient) . ' FCFA', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->SetFillColor(143, 170, 220);
    $pdf->Cell($bloc1_w * 0.22, $lh, pdf_enc('Reseau AEP'), 1, 0, 'L', true);
    $pdf->Cell($bloc1_w * 0.28, $lh, $libele_aep, 1, 0, 'L');
    $pdf->Cell($bloc1_w * 0.22, $lh, pdf_enc('Date facturation'), 1, 0, 'L', true);
    $pdf->Cell($bloc1_w * 0.28, $lh, $date_depot, 1, 0, 'L');
    $pdf->SetFillColor(47, 85, 151);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($bloc2_w * 0.45, $lh, pdf_enc('Dette totale'), 1, 0, 'L', true);
    $pdf->Cell($bloc2_w * 0.55, $lh, pdf_moneyFormatter($totalFacture) . ' FCFA', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $y = $pdf->GetY() + 5;

    // Tableau détail - bordures #5B9BD5, séparations de cellules nettes
    $w = array(91, 32, 32, 26, 38, 22, 42);
    $pdf->SetDrawColor($bord[0], $bord[1], $bord[2]);
    $pdf->SetLineWidth(0.4);
    $pdf->SetFillColor(91, 155, 213);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetXY($marge, $y);
    $pdf->Cell($w[0], 10, '', 1, 0);
    $pdf->Cell($w[1], 10, pdf_enc('Ancien index'), 1, 0, 'C', true);
    $pdf->Cell($w[2], 10, pdf_enc('Nouvel index'), 1, 0, 'C', true);
    $pdf->Cell($w[3], 10, pdf_enc('Quantite'), 1, 0, 'C', true);
    $pdf->Cell($w[4], 10, pdf_enc('Tarif unitaire/m3'), 1, 0, 'C', true);
    $pdf->Cell($w[5], 10, pdf_enc('Unite'), 1, 0, 'C', true);
    $pdf->Cell($w[6], 10, pdf_enc('Montant (FCFA)'), 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(210, 222, 239);
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->Cell($w[0], 11, pdf_enc('Conso. Compteur actuel'), 1, 0, 'L', true);
    $pdf->Cell($w[1], 11, $ancien_index, 1, 0, 'C');
    $pdf->Cell($w[2], 11, $nouvel_index, 1, 0, 'C');
    $pdf->Cell($w[3], 11, $conso_mois, 1, 0, 'C');
    $pdf->Cell($w[4], 11, $prix_eau, 1, 0, 'C');
    $pdf->Cell($w[5], 11, 'm3', 1, 0, 'C');
    $pdf->Cell($w[6], 11, pdf_moneyFormatter($facture_mois), 1, 1, 'C');
    $pdf->SetFillColor(234, 239, 247);
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->Cell($w[0], 11, pdf_enc('Entretient compteur'), 1, 0, 'L', true);
    $pdf->Cell($w[1], 11, '', 1, 0);
    $pdf->Cell($w[2], 11, '', 1, 0);
    $pdf->Cell($w[3], 11, '', 1, 0);
    $pdf->Cell($w[4], 11, '', 1, 0);
    $pdf->Cell($w[5], 11, '1 Mois', 1, 0, 'C');
    $pdf->Cell($w[6], 11, $prix_entretient, 1, 1, 'C');
    $pdf->SetFillColor(91, 155, 213);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3] + $w[4], 12, pdf_enc('Total TTC (en FCFA)'), 1, 0, 'L', true);
    $pdf->SetFillColor(210, 222, 239);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($w[5] + $w[6], 12, pdf_moneyFormatter($totalFacture), 1, 1, 'C', true);
    $y = $pdf->GetY() + 4;

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY($marge, $y);
    $pdf->MultiCell($page_w, 5, pdf_enc('Pour rapporter un dysfonctionnement sur le reseau, contactez le 690409882 ou le 695794780. Pour vous abonner au service public de l\'eau de Bassessa, contactez le 656256504 ou le 670905523.'), 0, 'C');
    $pdf->SetXY($marge, $pdf->GetY());
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->MultiCell($page_w, 5, pdf_enc('NB : Les mauvais usages concernent la revente de l\'eau au voisinage et les branchements frauduleux.'), 0, 'C');
    $y = $pdf->GetY() + 3;

    $pdf->SetFillColor(47, 85, 151);
    $pdf->SetDrawColor($bord[0], $bord[1], $bord[2]);
    $pdf->SetLineWidth(0.6);
    $pdf->RoundedRect($marge, $y, $page_w, 12, 3, '1234', 'FD');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetXY($marge, $y + 2);
    $pdf->Cell($page_w, 12, pdf_enc('Le paiement de votre facture dans les delais est le garant d\'un service d\'eau potable durable'), 0, 1, 'C', false);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
}

// --- Génération du PDF : une page par facture ---
$pdf = new FPDF();
$pdf->SetTitle(pdf_enc('Facturation ' . $mois_lettre . ' - ' . $libele_aep));
$pdf->SetAuthor('Suivi Reseau');

if (count($factures) === 0) {
    $pdf->AddPage('L', 'A4');
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, pdf_enc('Aucune facture pour ce mois.'), 0, 1, 'C');
}

foreach ($factures as $data) {
    if ($model_facture === 'model_fokoue') {
        drawFactureFokoue2($pdf, $data, $mois_lettre, $libele_aep);
    } else {
        drawFactureNkongzem($pdf, $data, $mois_lettre, $libele_aep);
    }
}

$filename = 'facturation_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $mois_lettre) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $libele_aep) . '.pdf';
$pdf->Output('D', $filename, false);
