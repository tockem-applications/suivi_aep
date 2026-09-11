<?php

/**
 * Générateur PDF minimal, sans dépendance externe.
 *
 * Le dépôt n'embarque ni Composer ni bibliothèque PDF : ce moteur suit la même
 * approche que le moteur Docx maison (donnees/docx/docx.php) et écrit
 * directement un fichier PDF 1.4 avec les polices « base 14 » (Helvetica), qui
 * sont garanties présentes dans tout lecteur PDF et n'ont donc pas besoin
 * d'être embarquées. L'application doit rester utilisable hors ligne : aucune
 * ressource distante n'est requise.
 *
 * Le repère de l'API est le repère naturel d'une page imprimée — origine en
 * haut à gauche, y croissant vers le bas — la conversion vers le repère PDF
 * (origine en bas à gauche) est faite au moment du rendu.
 */
class Pdf
{
    /** Dimensions A4 en points PostScript (1 pt = 1/72 pouce). */
    const A4_LARGEUR = 595.28;
    const A4_HAUTEUR = 841.89;

    /** Identifiants des polices dans les ressources de page. */
    const POLICE_NORMALE = 'F1';
    const POLICE_GRASSE = 'F2';

    /** @var string[] flux de contenu, un par page */
    private $pages = array();

    /** @var string flux de contenu de la page courante */
    private $courante = '';

    /** @var bool une page a-t-elle déjà été ouverte ? */
    private $pageOuverte = false;

    /** @var float */
    private $largeur;

    /** @var float */
    private $hauteur;

    /** @var array<string,string> métadonnées du document */
    private $meta;

    /**
     * @param array<string,mixed> $options titre, auteur, sujet, largeur, hauteur
     */
    public function __construct(array $options = array())
    {
        $this->largeur = isset($options['largeur']) ? (float) $options['largeur'] : self::A4_LARGEUR;
        $this->hauteur = isset($options['hauteur']) ? (float) $options['hauteur'] : self::A4_HAUTEUR;
        $this->meta = array(
            'titre' => isset($options['titre']) ? (string) $options['titre'] : '',
            'auteur' => isset($options['auteur']) ? (string) $options['auteur'] : '',
            'sujet' => isset($options['sujet']) ? (string) $options['sujet'] : '',
        );
    }

    public function largeurPage()
    {
        return $this->largeur;
    }

    public function hauteurPage()
    {
        return $this->hauteur;
    }

    /** Clôt la page courante et en ouvre une nouvelle. */
    public function nouvellePage()
    {
        if ($this->pageOuverte) {
            $this->pages[] = $this->courante;
        }
        $this->courante = '';
        $this->pageOuverte = true;
        return $this;
    }

    /**
     * Écrit un texte dont (x, y) est le coin haut gauche de la ligne de base
     * augmentée de l'ascendante : y correspond au sommet du texte.
     *
     * @param float  $x
     * @param float  $y       distance depuis le haut de la page
     * @param string $texte   UTF-8
     * @param float  $taille  corps en points
     * @param bool   $gras
     * @param array  $couleur composantes RVB entre 0 et 1
     */
    public function texte($x, $y, $texte, $taille = 10, $gras = false, array $couleur = array(0, 0, 0))
    {
        $this->ouvrirPageSiBesoin();
        $texte = (string) $texte;
        if ($texte === '') {
            return $this;
        }
        // La ligne de base se situe une ascendante sous le sommet du texte.
        $baseline = $this->hauteur - ((float) $y + $taille * 0.8);
        $this->courante .= sprintf(
            "BT %s /%s %s Tf 1 0 0 1 %s %s Tm (%s) Tj ET\n",
            $this->couleurTexte($couleur),
            $gras ? self::POLICE_GRASSE : self::POLICE_NORMALE,
            $this->nombre($taille),
            $this->nombre($x),
            $this->nombre($baseline),
            $this->echapper($texte)
        );
        return $this;
    }

    /** Écrit un texte centré horizontalement autour de $xCentre. */
    public function texteCentre($xCentre, $y, $texte, $taille = 10, $gras = false, array $couleur = array(0, 0, 0))
    {
        $x = (float) $xCentre - $this->largeurTexte($texte, $taille, $gras) / 2;
        return $this->texte($x, $y, $texte, $taille, $gras, $couleur);
    }

    /** Écrit un texte dont le bord droit est aligné sur $xDroite. */
    public function texteDroite($xDroite, $y, $texte, $taille = 10, $gras = false, array $couleur = array(0, 0, 0))
    {
        $x = (float) $xDroite - $this->largeurTexte($texte, $taille, $gras);
        return $this->texte($x, $y, $texte, $taille, $gras, $couleur);
    }

    /**
     * Trace un segment.
     *
     * @param array $tirets longueurs du motif de pointillés, vide pour un trait plein
     */
    public function ligne($x1, $y1, $x2, $y2, $epaisseur = 0.5, array $tirets = array(), array $couleur = array(0, 0, 0))
    {
        $this->ouvrirPageSiBesoin();
        $motif = empty($tirets)
            ? "[] 0 d"
            : '[' . implode(' ', array_map(array($this, 'nombre'), $tirets)) . '] 0 d';
        $this->courante .= sprintf(
            "q %s %s w %s %s %s m %s %s l S Q\n",
            $this->couleurTrait($couleur),
            $this->nombre($epaisseur),
            $motif,
            $this->nombre($x1),
            $this->nombre($this->hauteur - (float) $y1),
            $this->nombre($x2),
            $this->nombre($this->hauteur - (float) $y2)
        );
        return $this;
    }

    /** Trace un rectangle non rempli. */
    public function rectangle($x, $y, $largeur, $hauteur, $epaisseur = 0.5, array $couleur = array(0, 0, 0))
    {
        $this->ouvrirPageSiBesoin();
        $this->courante .= sprintf(
            "q %s %s w %s %s %s %s re S Q\n",
            $this->couleurTrait($couleur),
            $this->nombre($epaisseur),
            $this->nombre($x),
            $this->nombre($this->hauteur - (float) $y - (float) $hauteur),
            $this->nombre($largeur),
            $this->nombre($hauteur)
        );
        return $this;
    }

    /**
     * Largeur d'un texte au corps demandé, en points.
     *
     * @param string $texte UTF-8
     */
    public function largeurTexte($texte, $taille = 10, $gras = false)
    {
        $octets = $this->versWinAnsi((string) $texte);
        $table = $gras ? self::largeursGrasses() : self::largeursNormales();
        $total = 0;
        $n = strlen($octets);
        for ($i = 0; $i < $n; $i++) {
            $code = ord($octets[$i]);
            $total += isset($table[$code]) ? $table[$code] : 556;
        }
        return $total * $taille / 1000;
    }

    /**
     * Découpe un texte en lignes tenant dans $largeurMax.
     *
     * @return string[]
     */
    public function decouper($texte, $largeurMax, $taille = 10, $gras = false)
    {
        $mots = preg_split('/\s+/u', trim((string) $texte));
        if (!$mots || $mots === array('')) {
            return array();
        }
        $lignes = array();
        $courante = '';
        foreach ($mots as $mot) {
            $essai = ($courante === '') ? $mot : $courante . ' ' . $mot;
            if ($courante !== '' && $this->largeurTexte($essai, $taille, $gras) > $largeurMax) {
                $lignes[] = $courante;
                $courante = $mot;
            } else {
                $courante = $essai;
            }
        }
        if ($courante !== '') {
            $lignes[] = $courante;
        }
        return $lignes;
    }

    /**
     * Assemble le document.
     *
     * @return string contenu binaire du PDF
     */
    public function rendu()
    {
        if ($this->pageOuverte) {
            $this->pages[] = $this->courante;
            $this->courante = '';
            $this->pageOuverte = false;
        }
        if (empty($this->pages)) {
            $this->pages[] = '';
        }

        $nbPages = count($this->pages);
        // 1 catalogue + 1 arbre de pages + 2 polices + 1 info, puis 2 objets par page.
        $idCatalogue = 1;
        $idPages = 2;
        $idPoliceNormale = 3;
        $idPoliceGrasse = 4;
        $idInfo = 5;
        $premierIdPage = 6;

        $objets = array();
        $kids = array();
        for ($i = 0; $i < $nbPages; $i++) {
            $kids[] = ($premierIdPage + $i * 2) . ' 0 R';
        }

        $objets[$idCatalogue] = '<< /Type /Catalog /Pages ' . $idPages . ' 0 R >>';
        $objets[$idPages] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nbPages . ' >>';
        $objets[$idPoliceNormale] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objets[$idPoliceGrasse] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objets[$idInfo] = $this->dictionnaireInfo();

        for ($i = 0; $i < $nbPages; $i++) {
            $idPage = $premierIdPage + $i * 2;
            $idContenu = $idPage + 1;
            $objets[$idPage] = '<< /Type /Page /Parent ' . $idPages . ' 0 R'
                . ' /MediaBox [0 0 ' . $this->nombre($this->largeur) . ' ' . $this->nombre($this->hauteur) . ']'
                . ' /Resources << /Font << /' . self::POLICE_NORMALE . ' ' . $idPoliceNormale . ' 0 R'
                . ' /' . self::POLICE_GRASSE . ' ' . $idPoliceGrasse . ' 0 R >> >>'
                . ' /Contents ' . $idContenu . ' 0 R >>';
            $flux = $this->pages[$i];
            $objets[$idContenu] = '<< /Length ' . strlen($flux) . " >>\nstream\n" . $flux . "endstream";
        }

        ksort($objets);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array();
        foreach ($objets as $id => $corps) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $corps . "\nendobj\n";
        }

        $nbObjets = count($objets) + 1;
        $offsetXref = strlen($pdf);
        $pdf .= "xref\n0 " . $nbObjets . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id < $nbObjets; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", isset($offsets[$id]) ? $offsets[$id] : 0);
        }
        $pdf .= "trailer\n<< /Size " . $nbObjets . ' /Root ' . $idCatalogue . ' 0 R /Info ' . $idInfo . " 0 R >>\n";
        $pdf .= "startxref\n" . $offsetXref . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * Envoie le document au navigateur en pièce jointe.
     *
     * @param string $nomFichier nom proposé au téléchargement
     */
    public function telecharger($nomFichier)
    {
        $contenu = $this->rendu();
        $nomFichier = preg_replace('/[^A-Za-z0-9._-]/', '_', $nomFichier);
        if (substr($nomFichier, -4) !== '.pdf') {
            $nomFichier .= '.pdf';
        }
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
            header('Content-Length: ' . strlen($contenu));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        echo $contenu;
    }

    private function ouvrirPageSiBesoin()
    {
        if (!$this->pageOuverte) {
            $this->nouvellePage();
        }
    }

    private function dictionnaireInfo()
    {
        $champs = array(
            '/Title' => $this->meta['titre'],
            '/Author' => $this->meta['auteur'],
            '/Subject' => $this->meta['sujet'],
            '/Producer' => 'Suivi AEP',
            '/Creator' => 'Suivi AEP',
        );
        $parties = array();
        foreach ($champs as $cle => $valeur) {
            if ((string) $valeur !== '') {
                $parties[] = $cle . ' (' . $this->echapper($valeur) . ')';
            }
        }
        $parties[] = '/CreationDate (D:' . date('YmdHis') . ")";
        return '<< ' . implode(' ', $parties) . ' >>';
    }

    /** Formate un nombre sans notation scientifique ni virgule décimale locale. */
    private function nombre($valeur)
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function couleurTexte(array $rvb)
    {
        return $this->nombre($rvb[0]) . ' ' . $this->nombre($rvb[1]) . ' ' . $this->nombre($rvb[2]) . ' rg';
    }

    private function couleurTrait(array $rvb)
    {
        return $this->nombre($rvb[0]) . ' ' . $this->nombre($rvb[1]) . ' ' . $this->nombre($rvb[2]) . ' RG';
    }

    /** Échappe une chaîne UTF-8 pour une chaîne littérale PDF encodée en WinAnsi. */
    private function echapper($texte)
    {
        $octets = $this->versWinAnsi((string) $texte);
        return strtr($octets, array('\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => ' '));
    }

    /**
     * Convertit de l'UTF-8 vers Windows-1252, l'encodage déclaré pour les polices.
     */
    private function versWinAnsi($texte)
    {
        if ($texte === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $converti = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texte);
            if ($converti !== false) {
                return $converti;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            $converti = @mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
            if ($converti !== false && $converti !== null) {
                return $converti;
            }
        }
        // Dernier recours : on retire ce qui n'est pas ASCII plutôt que de produire
        // des octets qui afficheraient des caractères faux.
        return preg_replace('/[^\x20-\x7E]/', '', $texte);
    }

    /**
     * Chasses Helvetica (unités de 1/1000 em), indexées par code WinAnsi.
     *
     * Les lettres accentuées d'Helvetica ont la même chasse que leur lettre de
     * base : la table de base est donc recopiée sur les codes accentués.
     *
     * @return array<int,int>
     */
    private static function largeursNormales()
    {
        static $table = null;
        if ($table === null) {
            $table = self::construireTable(array(
                278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
                556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
                1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
                667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
                333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
                556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
            ));
        }
        return $table;
    }

    /**
     * Chasses Helvetica-Bold, indexées par code WinAnsi.
     *
     * @return array<int,int>
     */
    private static function largeursGrasses()
    {
        static $table = null;
        if ($table === null) {
            $table = self::construireTable(array(
                278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
                556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
                975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
                667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
                333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
                611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
            ));
        }
        return $table;
    }

    /**
     * Complète une table de chasses ASCII (codes 32 à 126) avec les codes
     * WinAnsi supérieurs : chaque lettre accentuée reprend la chasse de sa
     * lettre de base, les symboles restants recevant leur chasse propre.
     *
     * @param int[] $ascii chasses des codes 32 à 126, dans l'ordre
     * @return array<int,int>
     */
    private static function construireTable(array $ascii)
    {
        $table = array();
        foreach ($ascii as $i => $largeur) {
            $table[32 + $i] = $largeur;
        }

        // Lettre de base de chaque code WinAnsi accentué (128-255).
        $bases = array(
            192 => 'A', 193 => 'A', 194 => 'A', 195 => 'A', 196 => 'A', 197 => 'A',
            199 => 'C', 200 => 'E', 201 => 'E', 202 => 'E', 203 => 'E',
            204 => 'I', 205 => 'I', 206 => 'I', 207 => 'I', 209 => 'N',
            210 => 'O', 211 => 'O', 212 => 'O', 213 => 'O', 214 => 'O', 216 => 'O',
            217 => 'U', 218 => 'U', 219 => 'U', 220 => 'U', 221 => 'Y',
            224 => 'a', 225 => 'a', 226 => 'a', 227 => 'a', 228 => 'a', 229 => 'a',
            231 => 'c', 232 => 'e', 233 => 'e', 234 => 'e', 235 => 'e',
            236 => 'i', 237 => 'i', 238 => 'i', 239 => 'i', 241 => 'n',
            242 => 'o', 243 => 'o', 244 => 'o', 245 => 'o', 246 => 'o', 248 => 'o',
            249 => 'u', 250 => 'u', 251 => 'u', 252 => 'u', 253 => 'y', 255 => 'y',
            138 => 'S', 154 => 's', 142 => 'Z', 158 => 'z', 159 => 'Y',
        );
        foreach ($bases as $code => $lettre) {
            $table[$code] = $table[ord($lettre)];
        }

        // Symboles WinAnsi dont la chasse ne dérive pas d'une lettre.
        $symboles = array(
            128 => 556, 130 => 222, 131 => 556, 132 => 333, 133 => 1000, 134 => 556, 135 => 556,
            136 => 333, 137 => 1000, 139 => 333, 140 => 1000, 145 => 222, 146 => 222, 147 => 333,
            148 => 333, 149 => 350, 150 => 556, 151 => 1000, 152 => 333, 153 => 1000, 155 => 333,
            156 => 944, 160 => 278, 161 => 333, 162 => 556, 163 => 556, 164 => 556, 165 => 556,
            166 => 260, 167 => 556, 168 => 333, 169 => 737, 170 => 370, 171 => 556, 172 => 584,
            173 => 333, 174 => 737, 175 => 333, 176 => 400, 177 => 584, 178 => 333, 179 => 333,
            180 => 333, 181 => 556, 182 => 537, 183 => 278, 184 => 333, 185 => 333, 186 => 365,
            187 => 556, 188 => 834, 189 => 834, 190 => 834, 191 => 611, 198 => 1000, 208 => 722,
            215 => 584, 222 => 667, 223 => 611, 230 => 889, 240 => 556, 247 => 584, 254 => 556,
        );
        foreach ($symboles as $code => $largeur) {
            if (!isset($table[$code])) {
                $table[$code] = $largeur;
            }
        }

        return $table;
    }
}
