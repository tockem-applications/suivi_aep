<?php

/**
 * Banc d'essai du moteur .docx.
 *
 * Navigateur : index.php étant hors circuit, l'accès direct est protégé par
 *   l'authentification, comme les scripts de donnees/bd/.
 *     http://localhost/fokoue/suivi_reseau/donnees/docx/test_docx.php
 * CLI (sans garde) :
 *     php donnees/docx/test_docx.php [chemin_de_sortie.docx]
 */

require_once __DIR__ . '/docx.php';

$estCli = (php_sapi_name() === 'cli');

if (!$estCli) {
    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'web_guard.php';
    WebGuard::setRedirectPrefix('../../');
    WebGuard::loadCore();
    WebGuard::enforceAuth(false);
}

/**
 * Graphique en barres minimal, pour vérifier l'intégration d'images.
 *
 * @param array<string,int> $valeurs
 * @return string|false PNG binaire
 */
function test_docx_graphique(array $valeurs)
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $largeur = 900;
    $hauteur = 380;
    $margeGauche = 70;
    $margeBas = 50;
    $margeHaut = 30;

    $image = imagecreatetruecolor($largeur, $hauteur);
    $blanc = imagecolorallocate($image, 255, 255, 255);
    $grille = imagecolorallocate($image, 222, 226, 230);
    $texte = imagecolorallocate($image, 90, 90, 90);
    $barre = imagecolorallocate($image, 46, 116, 181);
    imagefilledrectangle($image, 0, 0, $largeur, $hauteur, $blanc);

    $max = max(1, max($valeurs));
    $zoneHaut = $hauteur - $margeBas;
    $zoneHauteur = $zoneHaut - $margeHaut;

    for ($i = 0; $i <= 4; $i++) {
        $y = (int) ($zoneHaut - $zoneHauteur * $i / 4);
        imageline($image, $margeGauche, $y, $largeur - 20, $y, $grille);
        imagestring($image, 2, 10, $y - 7, (string) (int) round($max * $i / 4), $texte);
    }

    $nb = count($valeurs);
    $pas = (int) (($largeur - $margeGauche - 30) / max(1, $nb));
    $x = $margeGauche + 10;
    foreach ($valeurs as $libelle => $valeur) {
        $h = (int) ($zoneHauteur * $valeur / $max);
        imagefilledrectangle($image, $x, $zoneHaut - $h, $x + $pas - 20, $zoneHaut, $barre);
        imagestring($image, 2, $x, $zoneHaut + 8, substr($libelle, 0, 8), $texte);
        $x += $pas;
    }

    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    return $png;
}

$docx = new Docx(array(
    'titre' => "Rapport d'exploitation",
    'auteur' => 'Tockem SPE',
    'sujet' => 'Banc d\'essai du moteur .docx',
));

$docx->entete('Tockem SPE — AEP de Fokoué', 'Exercice 2026');
$docx->piedDePage('Document généré automatiquement', true);

$docx->pageDeGarde(
    "Rapport d'exploitation",
    'AEP de Fokoué — Janvier 2026',
    array(
        'Période' => 'du 01/01/2026 au 31/01/2026',
        'Édité le' => date('d/m/Y à H:i'),
        'Édité par' => 'Banc d\'essai',
    )
);

$docx->sommaire('Sommaire');
$docx->sautDePage();

$docx->titre('Résumé exécutif', 1);
$docx->paragraphe(
    "Ce document ne contient aucune donnée réelle : il sert à vérifier que chaque "
    . "primitive du moteur produit un fichier Word valide. Accents éprouvés : à é è ê ï ô ù ç « » — °C m³.",
    array('align' => 'justifie')
);

$docx->paragraphe('Texte gras.', array('gras' => true));
$docx->paragraphe('Texte italique et souligné.', array('italique' => true, 'souligne' => true));
$docx->paragraphe('Texte coloré en 14 points.', array('couleur' => 'C00000', 'taille' => 14));
$docx->paragraphe("Première ligne.\nSeconde ligne après un saut forcé.", array('align' => 'centre'));
$docx->paragraphe('Paragraphe sur fond gris.', array('fond' => 'F2F2F2', 'espace_avant' => 120));

$docx->titre('Listes', 2);
$docx->listePuces(array(
    'Rendement de distribution en baisse de 4 points.',
    'Trois relevés manquants sur le réseau secondaire.',
    'Taux de recouvrement stable à 87 %.',
));
$docx->listeNumerotee(array(
    'Relancer les abonnés impayés de plus de trois mois.',
    'Contrôler le compteur de distribution DN100.',
));

$docx->titre('Tableaux', 1);

$docx->titre('Tableau simple', 2);
$docx->tableau(
    array('Mois', 'Volume produit (m³)', 'Volume facturé (m³)', 'Rendement'),
    array(
        array('Janvier', '12 400', '9 920', '80,0 %'),
        array('Février', '11 850', '9 006', '76,0 %'),
        array('Mars', '13 100', '10 873', '83,0 %'),
    ),
    array(
        'largeurs' => array(2, 2, 2, 1),
        'alignements' => array('gauche', 'droite', 'droite', 'droite'),
    )
);

$docx->titre('Tableau avec fusion et ligne de total', 2);
$docx->tableau(
    array('Rubrique', 'Code', 'Quantité', 'Montant (FCFA)'),
    array(
        array(array('texte' => 'RECETTES', 'fusion' => 4, 'gras' => true, 'fond' => 'E8F5E9')),
        array('Facturation eau', '7052', '9 920', '4 960 000'),
        array('Branchements', '7051', '12', '360 000'),
        array(
            array('texte' => 'TOTAL RECETTES', 'gras' => true, 'fond' => 'D9E2F3', 'fusion' => 3),
            array('texte' => '5 320 000', 'gras' => true, 'fond' => 'D9E2F3', 'align' => 'droite'),
        ),
    ),
    array(
        'largeurs' => array(4, 1, 2, 2),
        'alignements' => array('gauche', 'centre', 'droite', 'droite'),
        'zebra' => false,
    )
);

$docx->titre('Graphique', 1);
$png = test_docx_graphique(array(
    'Janv' => 12400, 'Févr' => 11850, 'Mars' => 13100,
    'Avr' => 12900, 'Mai' => 14200, 'Juin' => 13650,
));
if ($png !== false) {
    $docx->image($png, array(
        'largeur' => Docx::largeurUtilePx(),
        'legende' => 'Figure 1 — Volumes produits mensuels (données fictives)',
    ));
} else {
    $docx->paragraphe('GD indisponible : test image ignoré.', array('italique' => true, 'couleur' => 'C00000'));
}

$docx->separateur();
$docx->titre('Conclusion', 1);
$docx->paragraphe('Si ce document s\'ouvre sans message de récupération, le moteur est fonctionnel.');

if ($estCli) {
    $sortie = isset($argv[1]) ? $argv[1] : (__DIR__ . DIRECTORY_SEPARATOR . 'test_docx.docx');
    if ($docx->enregistrer($sortie)) {
        echo 'Document écrit : ' . $sortie . ' (' . filesize($sortie) . " octets)\n";
        exit(0);
    }
    echo "Échec de l'écriture du document.\n";
    exit(1);
}

$docx->telecharger('test_moteur_docx.docx');
