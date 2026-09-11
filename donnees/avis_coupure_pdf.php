<?php

@include_once(__DIR__ . '/pdf/pdf.php');
@include_once('donnees/pdf/pdf.php');
@include_once(__DIR__ . '/avis_coupure.php');
@include_once('donnees/avis_coupure.php');

/**
 * Mise en page PDF des avis de coupure.
 *
 * Reproduit le formulaire papier : deux avis par page A4, séparés par un trait
 * de découpe. Le cachet et la signature sont apposés à la main après
 * impression, les emplacements correspondants sont donc laissés vides.
 */
class AvisCoupurePdf
{
    /** Marges latérales de la page, en points. */
    const MARGE = 45.0;

    /** Hauteur réservée à un avis (moitié d'une page A4). */
    const HAUTEUR_AVIS = 420.94;

    /** Interligne entre deux champs du formulaire. */
    const PAS_CHAMP = 25.0;

    /**
     * Construit le document.
     *
     * @param array<int,array<string,mixed>> $avis     lignes issues de AvisCoupure::getAvis()
     * @param array<string,mixed>            $contexte entete, reference, libelle_mois, responsable
     * @return Pdf
     */
    public static function generer(array $avis, array $contexte = array())
    {
        $entete = isset($contexte['entete']) ? (string) $contexte['entete'] : '';
        $libelleMois = isset($contexte['libelle_mois']) ? (string) $contexte['libelle_mois'] : '';

        $pdf = new Pdf(array(
            'titre' => 'Avis de coupure' . ($libelleMois !== '' ? ' — ' . $libelleMois : ''),
            'auteur' => 'Suivi AEP',
            'sujet' => 'Avis de coupure des abonnés retenus',
        ));

        if (empty($avis)) {
            $pdf->nouvellePage();
            $pdf->texteCentre($pdf->largeurPage() / 2, 120, 'Aucun avis de coupure à imprimer', 14, true);
            $pdf->texteCentre(
                $pdf->largeurPage() / 2,
                150,
                'Aucun abonné n\'a été retenu pour ' . ($libelleMois !== '' ? $libelleMois : 'ce mois de facturation') . '.',
                10
            );
            return $pdf;
        }

        $position = 0;
        foreach ($avis as $ligne) {
            if ($position % AvisCoupure::AVIS_PAR_PAGE === 0) {
                $pdf->nouvellePage();
            }
            $indexDansPage = $position % AvisCoupure::AVIS_PAR_PAGE;
            $haut = $indexDansPage * self::HAUTEUR_AVIS;
            self::dessinerAvis($pdf, $haut, $ligne, $contexte);
            if ($indexDansPage === 0) {
                // Trait de découpe entre les deux avis de la page.
                $pdf->ligne(
                    self::MARGE / 2,
                    self::HAUTEUR_AVIS,
                    $pdf->largeurPage() - self::MARGE / 2,
                    self::HAUTEUR_AVIS,
                    0.6,
                    array(5, 4),
                    array(0.55, 0.55, 0.55)
                );
            }
            $position++;
        }

        return $pdf;
    }

    /**
     * Dessine un avis dans le bloc dont le sommet est à $haut.
     *
     * @param array<string,mixed> $ligne
     * @param array<string,mixed> $contexte
     */
    private static function dessinerAvis(Pdf $pdf, $haut, array $ligne, array $contexte)
    {
        $gauche = self::MARGE;
        $droite = $pdf->largeurPage() - self::MARGE;
        $centre = $pdf->largeurPage() / 2;
        $gris = array(0.35, 0.35, 0.35);

        $entete = isset($contexte['entete']) ? trim((string) $contexte['entete']) : '';
        if ($entete !== '') {
            $pdf->texteCentre($centre, $haut + 22, $entete, 8.5, true);
        }
        $pdf->texteCentre($centre, $haut + 48, 'AVIS DE COUPURE / DISCONNECTION NOTICE', 11.5, true);

        $libelleMois = isset($contexte['libelle_mois']) ? trim((string) $contexte['libelle_mois']) : '';
        $nbMois = isset($ligne['nb_mois_impayes']) ? (int) $ligne['nb_mois_impayes'] : 0;
        $sousTitre = array();
        if ($libelleMois !== '') {
            $sousTitre[] = 'Facturation ' . $libelleMois;
        }
        if ($nbMois > 0) {
            $sousTitre[] = $nbMois . ' mois non réglé' . ($nbMois > 1 ? 's' : '');
        }
        if (!empty($sousTitre)) {
            $pdf->texteCentre($centre, $haut + 66, implode(' — ', $sousTitre), 8, false, $gris);
        }

        $y = $haut + 90;

        $nom = isset($ligne['nom_abone']) && trim((string) $ligne['nom_abone']) !== ''
            ? $ligne['nom_abone']
            : (isset($ligne['nom_actuel']) ? $ligne['nom_actuel'] : '');
        self::champ($pdf, $gauche, $y, $droite, "Nom de l'abonné :", $nom);
        $y += self::PAS_CHAMP;

        $reference = isset($contexte['reference']) ? (string) $contexte['reference'] : '';
        if (!empty($ligne['nom_reseau'])) {
            $reference = trim($reference . ' — ' . $ligne['nom_reseau']);
        }
        self::champ($pdf, $gauche, $y, $droite, 'Reference :', $reference);
        $y += self::PAS_CHAMP;

        // Le montant se lit en chiffres puis en toutes lettres, comme sur le
        // formulaire papier. La mention des frais est alignée à droite ; si le
        // montant est trop long, les lettres passent sur la ligne suivante.
        $montant = isset($ligne['montant_impaye']) ? (float) $ligne['montant_impaye'] : 0.0;
        $chiffres = number_format($montant, 0, ',', ' ') . ' F';
        $lettres = '(' . AvisCoupure::montantEnLettres($montant) . ')';
        $mention = 'PREVOIR LES FRAIS DE REMISE EN SUS';
        $largeurMention = $pdf->largeurTexte($mention, 7.5, true);
        $finValeur = $droite - $largeurMention - 10;

        $pdf->texteDroite($droite, $y + 3, $mention, 7.5, true, $gris);
        $largeurComplet = $pdf->largeurTexte($chiffres . ' ' . $lettres, 10, true);
        $xValeur = $gauche + $pdf->largeurTexte('Montant impayés :', 9.5) + 6;
        if ($xValeur + $largeurComplet <= $finValeur) {
            self::champ($pdf, $gauche, $y, $finValeur, 'Montant impayés :', $chiffres . ' ' . $lettres);
            $y += self::PAS_CHAMP;
        } else {
            self::champ($pdf, $gauche, $y, $finValeur, 'Montant impayés :', $chiffres);
            $pdf->texte($xValeur, $y + 13, $lettres, 8.5, false, $gris);
            $y += self::PAS_CHAMP + 8;
        }

        $compteur = isset($ligne['numero_compteur']) ? (string) $ligne['numero_compteur'] : '';
        self::champ($pdf, $gauche, $y, $droite, 'N° Compteur :', $compteur);
        $y += self::PAS_CHAMP;

        $index = isset($ligne['dernier_index']) && $ligne['dernier_index'] !== null && $ligne['dernier_index'] !== ''
            ? rtrim(rtrim(number_format((float) $ligne['dernier_index'], 2, ',', ' '), '0'), ',')
            : '';
        self::champ($pdf, $gauche, $y, $droite, 'INDEX :', $index);
        $y += self::PAS_CHAMP;

        // « Signature » reste vide : elle est apposée à la main après impression.
        $responsable = isset($contexte['responsable']) ? (string) $contexte['responsable'] : '';
        $xSignature = $droite - 150;
        self::champ($pdf, $gauche, $y, $xSignature - 10, 'Coupure effectuée par :', $responsable);
        self::champ($pdf, $xSignature, $y, $droite, 'Signature', '');
        $y += self::PAS_CHAMP;

        $pdf->texte($gauche, $y, 'Type de coupure :', 9.5);
        $xTypes = $gauche + $pdf->largeurTexte('Type de coupure :', 9.5) + 6;
        $pdf->texte($xTypes, $y, '----bouche à clé----compteur déposé----branchement déposé', 9.5);
        $y += self::PAS_CHAMP;

        $dateAvis = '';
        if (!empty($ligne['date_avis'])) {
            $d = DateTime::createFromFormat('Y-m-d', $ligne['date_avis']);
            $dateAvis = $d ? $d->format('d/m/Y') : (string) $ligne['date_avis'];
        }
        $xHeure = $gauche + 250;
        self::champ($pdf, $gauche, $y, $xHeure - 10, 'Date :', $dateAvis);
        self::champ($pdf, $xHeure, $y, $droite, 'Heure :', '');
        $y += self::PAS_CHAMP;

        $contact = isset($contexte['contact']) ? (string) $contexte['contact'] : '';
        self::champ($pdf, $gauche, $y, $droite, 'Contact :', $contact);
        $y += self::PAS_CHAMP;

        // Emplacement laissé libre pour le cachet, apposé après impression.
        $pdf->rectangle($gauche, $y, 112, 44, 0.4, array(0.6, 0.6, 0.6));
        $pdf->texteCentre($gauche + 56, $y + 17, 'Cachet', 8, false, $gris);

        $pdf->texteCentre(
            $centre,
            $haut + self::HAUTEUR_AVIS - 30,
            'ATTENTION : tout rétablissement frauduleux est sujet à une poursuite judiciaire.',
            8.5,
            true
        );
    }

    /**
     * Trace un champ « libellé : valeur » suivi d'un pointillé jusqu'à $xFin,
     * à la manière des lignes à remplir du formulaire papier.
     */
    private static function champ(Pdf $pdf, $x, $y, $xFin, $libelle, $valeur)
    {
        $pdf->texte($x, $y, $libelle, 9.5);
        $xValeur = $x + $pdf->largeurTexte($libelle, 9.5) + 6;
        $valeur = trim((string) $valeur);

        if ($valeur !== '') {
            // Une valeur trop longue est réduite plutôt que de déborder sur le
            // champ voisin : l'avis doit rester lisible tel quel.
            $taille = 10.0;
            $disponible = $xFin - $xValeur;
            while ($taille > 6.5 && $pdf->largeurTexte($valeur, $taille, true) > $disponible) {
                $taille -= 0.5;
            }
            $pdf->texte($xValeur, $y, $valeur, $taille, true);
            $xValeur += $pdf->largeurTexte($valeur, $taille, true) + 4;
        }

        if ($xValeur < $xFin) {
            $pdf->ligne($xValeur, $y + 12, $xFin, $y + 12, 0.4, array(1, 2), array(0.45, 0.45, 0.45));
        }
    }
}
