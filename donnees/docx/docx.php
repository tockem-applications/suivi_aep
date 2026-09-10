<?php

@include_once(__DIR__ . '/zip_writer.php');

/**
 * Construction de documents Word (.docx) sans dépendance externe.
 *
 * Un .docx est une archive ZIP de parties XML (OOXML WordprocessingML). Comme
 * l'extension zip est absente des PHP 5.3 ciblés par la v1, PHPWord n'est pas
 * utilisable : on assemble ici les parties à la main et ZipWriter fabrique
 * l'archive.
 *
 * Usage :
 *   $docx = new Docx(array('titre' => "Rapport d'exploitation"));
 *   $docx->entete('AEP de Fokoué', 'Janvier 2026');
 *   $docx->piedDePage('Tockem SPE', true);
 *   $docx->pageDeGarde("Rapport d'exploitation", 'AEP de Fokoué');
 *   $docx->titre('Résumé exécutif', 1);
 *   $docx->paragraphe('...');
 *   $docx->tableau(array('Mois', 'Volume'), array(array('Janvier', '1 240')));
 *   $docx->telecharger('rapport.docx');
 */
class Docx
{
    /** Un pixel (96 dpi) en EMU, unité des images OOXML. */
    const EMU_PAR_PIXEL = 9525;
    /** Un pouce en twips, unité des longueurs OOXML. */
    const TWIP_PAR_POUCE = 1440;
    /** Points par pouce utilisés pour convertir les pixels. */
    const PPP = 96;

    /** Identifiants de relation réservés aux parties fixes du document. */
    const RID_STYLES = 'rId1';
    const RID_SETTINGS = 'rId2';
    const RID_NUMBERING = 'rId3';
    const RID_HEADER = 'rId4';
    const RID_FOOTER = 'rId5';
    const RID_HEADER_PREMIERE = 'rId6';
    const RID_FOOTER_PREMIERE = 'rId7';

    /** @var array<string,mixed> */
    private $options;

    /** @var array<int,string> fragments XML composant le corps */
    private $corps = array();

    /** @var array<int,array> images embarquées */
    private $medias = array();

    /** @var int prochain identifiant de relation libre */
    private $prochainRid = 10;

    /** @var int prochain identifiant d'objet graphique */
    private $prochainDocPr = 1;

    /** @var array<string,string>|null en-tête de page */
    private $entete = null;

    /** @var array<string,mixed>|null pied de page */
    private $pied = null;

    /** @var bool une page de garde a été insérée */
    private $aPageDeGarde = false;

    /** @var bool un sommaire a été inséré */
    private $aSommaire = false;

    /**
     * @param array<string,mixed> $options titre, auteur, sujet, orientation,
     *                                     marges, police, taille, couleur_titre
     */
    public function __construct(array $options = array())
    {
        $defauts = array(
            'titre' => 'Document',
            'auteur' => 'Tockem SPE',
            'sujet' => '',
            'orientation' => 'portrait',
            'police' => 'Calibri',
            'taille' => 11,
            'couleur_titre' => '1F4E79',
            'couleur_accent' => '2E74B5',
            'marges' => array('haut' => 1134, 'bas' => 1134, 'gauche' => 1134, 'droite' => 1134),
        );
        $options = array_merge($defauts, $options);
        $options['marges'] = array_merge($defauts['marges'], isset($options['marges']) ? $options['marges'] : array());
        $this->options = $options;
    }

    // ---------------------------------------------------------------- mise en page

    /**
     * Définit l'en-tête répété sur chaque page.
     *
     * @param string $gauche
     * @param string $droite
     * @return void
     */
    public function entete($gauche, $droite = '')
    {
        $this->entete = array('gauche' => (string) $gauche, 'droite' => (string) $droite);
    }

    /**
     * Définit le pied de page répété sur chaque page.
     *
     * @param string $texte
     * @param bool   $numeroPage ajoute « Page X sur Y » à droite
     * @return void
     */
    public function piedDePage($texte = '', $numeroPage = true)
    {
        $this->pied = array('texte' => (string) $texte, 'numero' => (bool) $numeroPage);
    }

    /**
     * @return void
     */
    public function sautDePage()
    {
        $this->corps[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    /**
     * Paragraphe vide servant d'espacement.
     *
     * @param int $hauteur en twips
     * @return void
     */
    public function espace($hauteur = 120)
    {
        $this->corps[] = '<w:p><w:pPr><w:spacing w:after="' . (int) $hauteur . '"/></w:pPr></w:p>';
    }

    /**
     * Trait horizontal de séparation.
     *
     * @return void
     */
    public function separateur()
    {
        $this->corps[] = '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="BFBFBF"/></w:pBdr>'
            . '<w:spacing w:after="200"/></w:pPr></w:p>';
    }

    // ---------------------------------------------------------------- contenu

    /**
     * Titre hiérarchisé, repris par le sommaire.
     *
     * @param string $texte
     * @param int    $niveau 1 à 3
     * @return void
     */
    public function titre($texte, $niveau = 1)
    {
        $niveau = max(1, min(3, (int) $niveau));
        $this->corps[] = '<w:p><w:pPr><w:pStyle w:val="Heading' . $niveau . '"/></w:pPr>'
            . $this->run($texte, array())
            . '</w:p>';
    }

    /**
     * @param string               $texte  les retours à la ligne deviennent des sauts de ligne Word
     * @param array<string,mixed>  $opt    gras, italique, souligne, taille, couleur, police,
     *                                     align (gauche|centre|droite|justifie), fond,
     *                                     espace_avant, espace_apres, indentation, garder_avec_suivant
     * @return void
     */
    public function paragraphe($texte, array $opt = array())
    {
        $this->corps[] = '<w:p>' . $this->pPr($opt) . $this->run($texte, $opt) . '</w:p>';
    }

    /**
     * Liste à puces.
     *
     * @param array<int,string>   $items
     * @param array<string,mixed> $opt
     * @return void
     */
    public function listePuces(array $items, array $opt = array())
    {
        $this->liste($items, 1, $opt);
    }

    /**
     * Liste numérotée.
     *
     * @param array<int,string>   $items
     * @param array<string,mixed> $opt
     * @return void
     */
    public function listeNumerotee(array $items, array $opt = array())
    {
        $this->liste($items, 2, $opt);
    }

    /**
     * @param array<int,string>   $items
     * @param int                 $numId identifiant de numérotation (1 puces, 2 chiffres)
     * @param array<string,mixed> $opt
     * @return void
     */
    private function liste(array $items, $numId, array $opt = array())
    {
        foreach ($items as $item) {
            $pPr = '<w:pPr><w:pStyle w:val="ListParagraph"/>'
                . '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="' . (int) $numId . '"/></w:numPr>'
                . '<w:spacing w:after="60"/><w:ind w:left="720" w:hanging="360"/></w:pPr>';
            $this->corps[] = '<w:p>' . $pPr . $this->run($item, $opt) . '</w:p>';
        }
    }

    /**
     * Tableau à bordures.
     *
     * Une cellule est soit une valeur scalaire, soit un tableau associatif :
     * texte, gras, italique, align, couleur, fond, taille, fusion (nombre de
     * colonnes fusionnées).
     *
     * @param array<int,mixed>     $entetes tableau vide pour un tableau sans en-tête
     * @param array<int,array>     $lignes
     * @param array<string,mixed>  $opt     largeurs, alignements, couleur_entete,
     *                                      texte_entete, zebra, taille, largeur_pct
     * @return void
     */
    public function tableau(array $entetes, array $lignes, array $opt = array())
    {
        $defauts = array(
            'largeurs' => array(),
            'alignements' => array(),
            'couleur_entete' => $this->options['couleur_titre'],
            'texte_entete' => 'FFFFFF',
            'zebra' => 'F2F7FB',
            'taille' => 10,
            'largeur_pct' => 100,
            'repeter_entete' => true,
            'couleur_bordure' => 'BFBFBF',
        );
        $opt = array_merge($defauts, $opt);

        $nbColonnes = count($entetes);
        if ($nbColonnes === 0) {
            foreach ($lignes as $ligne) {
                $nbColonnes = max($nbColonnes, count($ligne));
            }
        }
        if ($nbColonnes === 0) {
            return;
        }

        $largeurs = $this->largeursColonnes($nbColonnes, $opt['largeurs'], $opt['largeur_pct']);

        $xml = '<w:tbl>' . $this->tblPr($opt) . '<w:tblGrid>';
        foreach ($largeurs as $l) {
            $xml .= '<w:gridCol w:w="' . $l . '"/>';
        }
        $xml .= '</w:tblGrid>';

        if ($nbColonnes > 0 && count($entetes) > 0) {
            $cellules = array();
            foreach ($entetes as $i => $entete) {
                $cellule = is_array($entete) ? $entete : array('texte' => $entete);
                $cellule = array_merge(array(
                    'gras' => true,
                    'couleur' => $opt['texte_entete'],
                    'fond' => $opt['couleur_entete'],
                    'align' => 'centre',
                    'taille' => $opt['taille'],
                ), $cellule);
                $cellules[] = $cellule;
            }
            $trPr = $opt['repeter_entete'] ? '<w:trPr><w:cantSplit/><w:tblHeader/></w:trPr>' : '';
            $xml .= $this->ligneTableau($cellules, $largeurs, $trPr);
        }

        $index = 0;
        foreach ($lignes as $ligne) {
            $cellules = array();
            $position = 0;
            foreach ($ligne as $cellule) {
                $cellule = is_array($cellule) ? $cellule : array('texte' => $cellule);
                if (!isset($cellule['align']) && isset($opt['alignements'][$position])) {
                    $cellule['align'] = $opt['alignements'][$position];
                }
                if (!isset($cellule['taille'])) {
                    $cellule['taille'] = $opt['taille'];
                }
                if (!isset($cellule['fond']) && $opt['zebra'] !== false && ($index % 2) === 1) {
                    $cellule['fond'] = $opt['zebra'];
                }
                $cellules[] = $cellule;
                $position += isset($cellule['fusion']) ? max(1, (int) $cellule['fusion']) : 1;
            }
            $xml .= $this->ligneTableau($cellules, $largeurs, '');
            $index++;
        }

        $xml .= '</w:tbl>';
        // Word exige un paragraphe après un tableau (sinon deux tableaux voisins fusionnent).
        $this->corps[] = $xml . '<w:p><w:pPr><w:spacing w:after="160"/></w:pPr></w:p>';
    }

    /**
     * Image embarquée dans le document.
     *
     * @param string              $binaire contenu du PNG / JPEG / GIF
     * @param array<string,mixed> $opt     largeur (px), hauteur (px), legende, align
     * @return bool false si le format n'est pas reconnu
     */
    public function image($binaire, array $opt = array())
    {
        $info = self::dimensionsImage($binaire);
        if ($info === false) {
            return false;
        }

        $largeurMax = self::largeurUtilePx($this->options);
        $largeur = isset($opt['largeur']) ? (int) $opt['largeur'] : $info['largeur'];
        if ($largeur <= 0 || $largeur > $largeurMax) {
            $largeur = $largeurMax;
        }
        if (isset($opt['hauteur']) && (int) $opt['hauteur'] > 0) {
            $hauteur = (int) $opt['hauteur'];
        } else {
            $hauteur = (int) round($largeur * $info['hauteur'] / max(1, $info['largeur']));
        }

        $rid = 'rId' . $this->prochainRid;
        $this->prochainRid++;
        $nom = 'image' . count($this->medias) . '.' . $info['extension'];
        $this->medias[] = array('nom' => $nom, 'contenu' => $binaire, 'rid' => $rid, 'extension' => $info['extension']);

        $docPr = $this->prochainDocPr;
        $this->prochainDocPr++;
        $cx = $largeur * self::EMU_PAR_PIXEL;
        $cy = $hauteur * self::EMU_PAR_PIXEL;
        $align = isset($opt['align']) ? $opt['align'] : 'centre';

        $dessin = '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            . '<wp:docPr id="' . $docPr . '" name="Image ' . $docPr . '"/>'
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="' . $docPr . '" name="Image ' . $docPr . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rid . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing>';

        $this->corps[] = '<w:p>' . $this->pPr(array('align' => $align, 'espace_apres' => 60))
            . '<w:r>' . $dessin . '</w:r></w:p>';

        if (isset($opt['legende']) && $opt['legende'] !== '') {
            $this->corps[] = '<w:p><w:pPr><w:pStyle w:val="Caption"/><w:jc w:val="center"/></w:pPr>'
                . $this->run($opt['legende'], array()) . '</w:p>';
        }

        return true;
    }

    /**
     * Image fournie sous forme de data URI (sortie de canvas.toDataURL()).
     *
     * @param string              $dataUri
     * @param array<string,mixed> $opt
     * @return bool
     */
    public function imageDataUri($dataUri, array $opt = array())
    {
        $dataUri = (string) $dataUri;
        $position = strpos($dataUri, 'base64,');
        if ($position === false) {
            return false;
        }
        $binaire = base64_decode(substr($dataUri, $position + 7), true);
        if ($binaire === false || $binaire === '') {
            return false;
        }
        return $this->image($binaire, $opt);
    }

    /**
     * Page de garde suivie d'un saut de page.
     *
     * @param string             $titre
     * @param string             $sousTitre
     * @param array<string,string> $lignes  libellé => valeur, affichés sous le titre
     * @param string|null        $logo     contenu binaire d'une image
     * @return void
     */
    public function pageDeGarde($titre, $sousTitre = '', array $lignes = array(), $logo = null)
    {
        $this->aPageDeGarde = true;

        $this->espace(1200);
        if ($logo !== null && $logo !== '') {
            $this->image($logo, array('largeur' => 180, 'align' => 'centre'));
        }
        $this->espace(600);

        $this->corps[] = '<w:p><w:pPr><w:pStyle w:val="Title"/><w:jc w:val="center"/></w:pPr>'
            . $this->run($titre, array()) . '</w:p>';

        if ($sousTitre !== '') {
            $this->corps[] = '<w:p><w:pPr><w:pStyle w:val="Subtitle"/><w:jc w:val="center"/></w:pPr>'
                . $this->run($sousTitre, array()) . '</w:p>';
        }

        $this->espace(800);

        foreach ($lignes as $libelle => $valeur) {
            $texte = is_int($libelle) ? (string) $valeur : $libelle . ' : ' . $valeur;
            $this->paragraphe($texte, array('align' => 'centre', 'couleur' => '595959', 'espace_apres' => 60));
        }

        $this->sautDePage();
    }

    /**
     * Champ « table des matières » ; Word la renseigne à l'ouverture.
     *
     * @param string $titre
     * @return void
     */
    public function sommaire($titre = 'Sommaire')
    {
        $this->aSommaire = true;

        if ($titre !== '') {
            $this->titre($titre, 1);
        }

        $this->corps[] = '<w:p>'
            . '<w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r>'
            . '<w:r><w:instrText xml:space="preserve"> TOC \o "1-3" \h \z \u </w:instrText></w:r>'
            . '<w:r><w:fldChar w:fldCharType="separate"/></w:r>'
            . '<w:r><w:rPr><w:i/><w:color w:val="808080"/></w:rPr>'
            . '<w:t xml:space="preserve">Placez le curseur ici et appuyez sur F9 pour générer le sommaire.</w:t></w:r>'
            . '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
            . '</w:p>';
    }

    // ---------------------------------------------------------------- sortie

    /**
     * @return string contenu binaire du .docx
     */
    public function rendu()
    {
        $zip = new ZipWriter();
        // [Content_Types].xml doit être la première entrée de l'archive.
        $zip->ajouter('[Content_Types].xml', $this->contentTypesXml());
        $zip->ajouter('_rels/.rels', $this->relsRacineXml());
        $zip->ajouter('docProps/core.xml', $this->coreXml());
        $zip->ajouter('docProps/app.xml', $this->appXml());
        $zip->ajouter('word/document.xml', $this->documentXml());
        $zip->ajouter('word/_rels/document.xml.rels', $this->relsDocumentXml());
        $zip->ajouter('word/styles.xml', $this->stylesXml());
        $zip->ajouter('word/settings.xml', $this->settingsXml());
        $zip->ajouter('word/numbering.xml', $this->numberingXml());
        $zip->ajouter('word/header1.xml', $this->headerXml());
        $zip->ajouter('word/footer1.xml', $this->footerXml());
        $zip->ajouter('word/header2.xml', $this->partieVideXml('hdr'));
        $zip->ajouter('word/footer2.xml', $this->partieVideXml('ftr'));
        foreach ($this->medias as $media) {
            // Les images sont déjà compressées : on les stocke telles quelles.
            $zip->ajouter('word/media/' . $media['nom'], $media['contenu'], false);
        }
        return $zip->rendu();
    }

    /**
     * @param string $chemin
     * @return bool
     */
    public function enregistrer($chemin)
    {
        return file_put_contents($chemin, $this->rendu()) !== false;
    }

    /**
     * Envoie le document au navigateur et termine le script.
     *
     * @param string $nomFichier
     * @return void
     */
    public function telecharger($nomFichier = 'document.docx')
    {
        $contenu = $this->rendu();
        $nomFichier = self::nomFichierSur($nomFichier);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
            header('Content-Length: ' . strlen($contenu));
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        echo $contenu;
        exit;
    }

    // ---------------------------------------------------------------- utilitaires publics

    /**
     * Largeur exploitable d'une page, en pixels à 96 dpi. Sert à dimensionner
     * les graphiques rendus côté navigateur avant leur envoi.
     *
     * @param array<string,mixed>|null $options
     * @return int
     */
    public static function largeurUtilePx($options = null)
    {
        if ($options === null) {
            $options = array('orientation' => 'portrait', 'marges' => array('gauche' => 1134, 'droite' => 1134));
        }
        $marges = isset($options['marges']) ? $options['marges'] : array();
        $gauche = isset($marges['gauche']) ? (int) $marges['gauche'] : 1134;
        $droite = isset($marges['droite']) ? (int) $marges['droite'] : 1134;
        $largeurPage = self::estPaysage($options) ? 16838 : 11906;
        $utile = $largeurPage - $gauche - $droite;
        return (int) floor($utile / self::TWIP_PAR_POUCE * self::PPP);
    }

    /**
     * Normalise une chaîne en UTF-8 valide. La base v1 est en latin1 : selon le
     * chemin de lecture, les libellés arrivent en ISO-8859-1 ou en UTF-8.
     *
     * @param mixed $texte
     * @return string
     */
    public static function versUtf8($texte)
    {
        $texte = (string) $texte;
        if ($texte === '') {
            return '';
        }
        if (function_exists('mb_check_encoding')) {
            if (mb_check_encoding($texte, 'UTF-8')) {
                return $texte;
            }
            return mb_convert_encoding($texte, 'UTF-8', 'ISO-8859-1');
        }
        if (preg_match('//u', $texte) === 1) {
            return $texte;
        }
        return function_exists('utf8_encode') ? utf8_encode($texte) : $texte;
    }

    /**
     * Échappe un texte pour insertion dans le XML du document.
     *
     * @param mixed $texte
     * @return string
     */
    public static function echapper($texte)
    {
        $texte = self::versUtf8($texte);
        // Les caractères de contrôle rendent le document illisible par Word.
        $texte = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $texte);
        return htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Dimensions et format d'une image, sans recourir à GD.
     *
     * @param string $binaire
     * @return array{largeur:int,hauteur:int,extension:string}|false
     */
    public static function dimensionsImage($binaire)
    {
        $binaire = (string) $binaire;
        $taille = strlen($binaire);
        if ($taille < 12) {
            return false;
        }

        if (substr($binaire, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $entete = unpack('Nlargeur/Nhauteur', substr($binaire, 16, 8));
            return array(
                'largeur' => (int) $entete['largeur'],
                'hauteur' => (int) $entete['hauteur'],
                'extension' => 'png',
            );
        }

        if (substr($binaire, 0, 3) === 'GIF') {
            $entete = unpack('vlargeur/vhauteur', substr($binaire, 6, 4));
            return array(
                'largeur' => (int) $entete['largeur'],
                'hauteur' => (int) $entete['hauteur'],
                'extension' => 'gif',
            );
        }

        if (substr($binaire, 0, 2) === "\xFF\xD8") {
            $position = 2;
            while ($position + 9 < $taille) {
                if ($binaire[$position] !== "\xFF") {
                    $position++;
                    continue;
                }
                $marqueur = ord($binaire[$position + 1]);
                // SOF0..SOF15 hors marqueurs sans dimensions (DHT C4, JPGA C8, DAC CC)
                if ($marqueur >= 0xC0 && $marqueur <= 0xCF
                    && $marqueur !== 0xC4 && $marqueur !== 0xC8 && $marqueur !== 0xCC) {
                    $entete = unpack('nhauteur/nlargeur', substr($binaire, $position + 5, 4));
                    return array(
                        'largeur' => (int) $entete['largeur'],
                        'hauteur' => (int) $entete['hauteur'],
                        'extension' => 'jpeg',
                    );
                }
                $longueur = unpack('n', substr($binaire, $position + 2, 2));
                $position += 2 + (int) $longueur[1];
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- fragments de contenu

    /**
     * Propriétés de paragraphe.
     *
     * @param array<string,mixed> $opt
     * @return string
     */
    private function pPr(array $opt)
    {
        $xml = '';

        if (isset($opt['style'])) {
            $xml .= '<w:pStyle w:val="' . self::echapper($opt['style']) . '"/>';
        }
        if (!empty($opt['garder_avec_suivant'])) {
            $xml .= '<w:keepNext/>';
        }
        if (isset($opt['fond'])) {
            $xml .= '<w:shd w:val="clear" w:color="auto" w:fill="' . self::couleur($opt['fond']) . '"/>';
        }

        $espacement = '';
        if (isset($opt['espace_avant'])) {
            $espacement .= ' w:before="' . (int) $opt['espace_avant'] . '"';
        }
        if (isset($opt['espace_apres'])) {
            $espacement .= ' w:after="' . (int) $opt['espace_apres'] . '"';
        }
        if ($espacement !== '') {
            $xml .= '<w:spacing' . $espacement . '/>';
        }
        if (isset($opt['indentation'])) {
            $xml .= '<w:ind w:left="' . (int) $opt['indentation'] . '"/>';
        }
        if (isset($opt['align'])) {
            $xml .= '<w:jc w:val="' . self::alignement($opt['align']) . '"/>';
        }

        return $xml === '' ? '' : '<w:pPr>' . $xml . '</w:pPr>';
    }

    /**
     * Propriétés de texte.
     *
     * @param array<string,mixed> $opt
     * @return string
     */
    private function rPr(array $opt)
    {
        $xml = '';
        if (isset($opt['police'])) {
            $police = self::echapper($opt['police']);
            $xml .= '<w:rFonts w:ascii="' . $police . '" w:hAnsi="' . $police . '" w:cs="' . $police . '"/>';
        }
        if (!empty($opt['gras'])) {
            $xml .= '<w:b/>';
        }
        if (!empty($opt['italique'])) {
            $xml .= '<w:i/>';
        }
        if (isset($opt['couleur'])) {
            $xml .= '<w:color w:val="' . self::couleur($opt['couleur']) . '"/>';
        }
        if (isset($opt['taille'])) {
            // OOXML exprime les tailles en demi-points.
            $demiPoints = (int) round($opt['taille'] * 2);
            $xml .= '<w:sz w:val="' . $demiPoints . '"/><w:szCs w:val="' . $demiPoints . '"/>';
        }
        // Le schéma impose u après sz/szCs.
        if (!empty($opt['souligne'])) {
            $xml .= '<w:u w:val="single"/>';
        }
        return $xml === '' ? '' : '<w:rPr>' . $xml . '</w:rPr>';
    }

    /**
     * Un ou plusieurs runs, les retours à la ligne devenant des <w:br/>.
     *
     * @param mixed               $texte
     * @param array<string,mixed> $opt
     * @return string
     */
    private function run($texte, array $opt)
    {
        $texte = self::versUtf8($texte);
        $texte = str_replace(array("\r\n", "\r"), "\n", $texte);
        $lignes = explode("\n", $texte);
        $rPr = $this->rPr($opt);

        $xml = '';
        $premiere = true;
        foreach ($lignes as $ligne) {
            $xml .= '<w:r>' . $rPr;
            if (!$premiere) {
                $xml .= '<w:br/>';
            }
            $xml .= '<w:t xml:space="preserve">' . self::echapper($ligne) . '</w:t></w:r>';
            $premiere = false;
        }
        return $xml;
    }

    /**
     * Propriétés du tableau.
     *
     * @param array<string,mixed> $opt
     * @return string
     */
    private function tblPr(array $opt)
    {
        $bordure = self::couleur($opt['couleur_bordure']);
        $trait = ' w:val="single" w:sz="4" w:space="0" w:color="' . $bordure . '"';
        return '<w:tblPr>'
            . '<w:tblW w:w="' . ((int) $opt['largeur_pct'] * 50) . '" w:type="pct"/>'
            . '<w:jc w:val="left"/>'
            . '<w:tblBorders>'
            . '<w:top' . $trait . '/><w:left' . $trait . '/><w:bottom' . $trait . '/>'
            . '<w:right' . $trait . '/><w:insideH' . $trait . '/><w:insideV' . $trait . '/>'
            . '</w:tblBorders>'
            . '<w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar>'
            . '<w:top w:w="60" w:type="dxa"/><w:left w:w="90" w:type="dxa"/>'
            . '<w:bottom w:w="60" w:type="dxa"/><w:right w:w="90" w:type="dxa"/>'
            . '</w:tblCellMar>'
            // Forme courte : les attributs étendus de tblLook datent d'OOXML 2010.
            . '<w:tblLook w:val="04A0"/>'
            . '</w:tblPr>';
    }

    /**
     * @param array<int,array> $cellules
     * @param array<int,int>   $largeurs
     * @param string           $trPr
     * @return string
     */
    private function ligneTableau(array $cellules, array $largeurs, $trPr)
    {
        $xml = '<w:tr>' . $trPr;
        $colonne = 0;
        $nbColonnes = count($largeurs);

        foreach ($cellules as $cellule) {
            if ($colonne >= $nbColonnes) {
                break;
            }
            $fusion = isset($cellule['fusion']) ? max(1, (int) $cellule['fusion']) : 1;
            $fusion = min($fusion, $nbColonnes - $colonne);

            $largeur = 0;
            for ($i = 0; $i < $fusion; $i++) {
                $largeur += $largeurs[$colonne + $i];
            }

            $tcPr = '<w:tcPr><w:tcW w:w="' . $largeur . '" w:type="dxa"/>';
            if ($fusion > 1) {
                $tcPr .= '<w:gridSpan w:val="' . $fusion . '"/>';
            }
            if (isset($cellule['fond'])) {
                $tcPr .= '<w:shd w:val="clear" w:color="auto" w:fill="' . self::couleur($cellule['fond']) . '"/>';
            }
            $tcPr .= '<w:vAlign w:val="center"/></w:tcPr>';

            $pOpt = array('espace_avant' => 20, 'espace_apres' => 20);
            if (isset($cellule['align'])) {
                $pOpt['align'] = $cellule['align'];
            }
            $texte = isset($cellule['texte']) ? $cellule['texte'] : '';

            $xml .= '<w:tc>' . $tcPr . '<w:p>' . $this->pPr($pOpt) . $this->run($texte, $cellule) . '</w:p></w:tc>';
            $colonne += $fusion;
        }

        // Word rejette une ligne plus courte que la grille : on complète.
        while ($colonne < $nbColonnes) {
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . $largeurs[$colonne] . '" w:type="dxa"/></w:tcPr><w:p/></w:tc>';
            $colonne++;
        }

        return $xml . '</w:tr>';
    }

    /**
     * Répartit la largeur utile entre les colonnes.
     *
     * @param int             $nbColonnes
     * @param array<int,int>  $poids       poids relatifs, vide pour équirépartir
     * @param int             $pourcentage part de la largeur utile occupée
     * @return array<int,int> largeurs en twips
     */
    private function largeursColonnes($nbColonnes, array $poids, $pourcentage)
    {
        $disponible = (int) round($this->largeurContenuTwips() * max(1, min(100, (int) $pourcentage)) / 100);

        if (count($poids) !== $nbColonnes) {
            $poids = array_fill(0, $nbColonnes, 1);
        }
        $total = 0;
        foreach ($poids as $p) {
            $total += max(0.0001, (float) $p);
        }

        $largeurs = array();
        $cumul = 0;
        for ($i = 0; $i < $nbColonnes; $i++) {
            if ($i === $nbColonnes - 1) {
                $largeurs[$i] = $disponible - $cumul; // absorbe les arrondis
            } else {
                $largeurs[$i] = (int) floor($disponible * max(0.0001, (float) $poids[$i]) / $total);
                $cumul += $largeurs[$i];
            }
        }
        return $largeurs;
    }

    /**
     * @return int largeur utile en twips
     */
    private function largeurContenuTwips()
    {
        $largeurPage = self::estPaysage($this->options) ? 16838 : 11906;
        return $largeurPage - (int) $this->options['marges']['gauche'] - (int) $this->options['marges']['droite'];
    }

    // ---------------------------------------------------------------- parties du paquet

    /**
     * @return string
     */
    private function documentXml()
    {
        $marges = $this->options['marges'];
        $paysage = self::estPaysage($this->options);
        $largeur = $paysage ? 16838 : 11906;
        $hauteur = $paysage ? 11906 : 16838;

        $sectPr = '<w:sectPr>'
            . '<w:headerReference w:type="default" r:id="' . self::RID_HEADER . '"/>'
            . '<w:footerReference w:type="default" r:id="' . self::RID_FOOTER . '"/>';
        if ($this->aPageDeGarde) {
            $sectPr .= '<w:headerReference w:type="first" r:id="' . self::RID_HEADER_PREMIERE . '"/>'
                . '<w:footerReference w:type="first" r:id="' . self::RID_FOOTER_PREMIERE . '"/>';
        }
        $sectPr .= '<w:pgSz w:w="' . $largeur . '" w:h="' . $hauteur . '"'
            . ($paysage ? ' w:orient="landscape"' : '') . '/>'
            . '<w:pgMar w:top="' . (int) $marges['haut'] . '" w:right="' . (int) $marges['droite'] . '"'
            . ' w:bottom="' . (int) $marges['bas'] . '" w:left="' . (int) $marges['gauche'] . '"'
            . ' w:header="708" w:footer="708" w:gutter="0"/>'
            . '<w:cols w:space="708"/>';
        // titlePg doit précéder docGrid.
        if ($this->aPageDeGarde) {
            $sectPr .= '<w:titlePg/>';
        }
        $sectPr .= '<w:docGrid w:linePitch="360"/></w:sectPr>';

        return self::PROLOGUE
            . '<w:document ' . self::NS_DOCUMENT . '><w:body>'
            . implode('', $this->corps)
            . $sectPr
            . '</w:body></w:document>';
    }

    /**
     * @return string
     */
    private function contentTypesXml()
    {
        $extensions = array('png' => 'image/png', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif');
        $defauts = '';
        foreach ($extensions as $extension => $type) {
            $defauts .= '<Default Extension="' . $extension . '" ContentType="' . $type . '"/>';
        }

        $base = 'application/vnd.openxmlformats-officedocument.wordprocessingml.';
        return self::PROLOGUE
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . $defauts
            . '<Override PartName="/word/document.xml" ContentType="' . $base . 'document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="' . $base . 'styles+xml"/>'
            . '<Override PartName="/word/settings.xml" ContentType="' . $base . 'settings+xml"/>'
            . '<Override PartName="/word/numbering.xml" ContentType="' . $base . 'numbering+xml"/>'
            . '<Override PartName="/word/header1.xml" ContentType="' . $base . 'header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="' . $base . 'footer+xml"/>'
            . '<Override PartName="/word/header2.xml" ContentType="' . $base . 'header+xml"/>'
            . '<Override PartName="/word/footer2.xml" ContentType="' . $base . 'footer+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    /**
     * @return string
     */
    private function relsRacineXml()
    {
        $type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        $paquet = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
        return self::PROLOGUE
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $type . 'officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="' . $paquet . '" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="' . $type . 'extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    /**
     * @return string
     */
    private function relsDocumentXml()
    {
        $type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        $xml = self::PROLOGUE
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="' . self::RID_STYLES . '" Type="' . $type . 'styles" Target="styles.xml"/>'
            . '<Relationship Id="' . self::RID_SETTINGS . '" Type="' . $type . 'settings" Target="settings.xml"/>'
            . '<Relationship Id="' . self::RID_NUMBERING . '" Type="' . $type . 'numbering" Target="numbering.xml"/>'
            . '<Relationship Id="' . self::RID_HEADER . '" Type="' . $type . 'header" Target="header1.xml"/>'
            . '<Relationship Id="' . self::RID_FOOTER . '" Type="' . $type . 'footer" Target="footer1.xml"/>'
            . '<Relationship Id="' . self::RID_HEADER_PREMIERE . '" Type="' . $type . 'header" Target="header2.xml"/>'
            . '<Relationship Id="' . self::RID_FOOTER_PREMIERE . '" Type="' . $type . 'footer" Target="footer2.xml"/>';

        foreach ($this->medias as $media) {
            $xml .= '<Relationship Id="' . $media['rid'] . '" Type="' . $type . 'image"'
                . ' Target="media/' . $media['nom'] . '"/>';
        }

        return $xml . '</Relationships>';
    }

    /**
     * @return string
     */
    private function settingsXml()
    {
        $maj = $this->aSommaire ? '<w:updateFields w:val="true"/>' : '';
        return self::PROLOGUE
            . '<w:settings xmlns:w="' . self::NS_W . '">'
            . '<w:zoom w:percent="100"/><w:defaultTabStop w:val="708"/>'
            . $maj
            . '<w:compat/>'
            . '</w:settings>';
    }

    /**
     * @return string
     */
    private function numberingXml()
    {
        $police = self::echapper($this->options['police']);
        $niveauPuce = '<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/>'
            . '<w:lvlText w:val="&#8226;"/><w:lvlJc w:val="left"/>'
            . '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>'
            . '<w:rPr><w:rFonts w:ascii="' . $police . '" w:hAnsi="' . $police . '" w:hint="default"/></w:rPr></w:lvl>';
        $niveauChiffre = '<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/>'
            . '<w:lvlText w:val="%1."/><w:lvlJc w:val="left"/>'
            . '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr></w:lvl>';

        return self::PROLOGUE
            . '<w:numbering xmlns:w="' . self::NS_W . '">'
            . '<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="hybridMultilevel"/>' . $niveauPuce . '</w:abstractNum>'
            . '<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="hybridMultilevel"/>' . $niveauChiffre . '</w:abstractNum>'
            . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
            . '<w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>'
            . '</w:numbering>';
    }

    /**
     * @return string
     */
    private function headerXml()
    {
        $corps = '<w:p><w:pPr><w:pStyle w:val="Header"/></w:pPr></w:p>';

        if ($this->entete !== null) {
            $tabDroite = $this->largeurContenuTwips();
            // Le schéma impose pBdr avant tabs.
            $pPr = '<w:pPr><w:pStyle w:val="Header"/>'
                . '<w:pBdr><w:bottom w:val="single" w:sz="6" w:space="4" w:color="BFBFBF"/></w:pBdr>'
                . '<w:tabs><w:tab w:val="right" w:pos="' . $tabDroite . '"/></w:tabs>'
                . '</w:pPr>';
            $style = array('taille' => 9, 'couleur' => '595959');
            $corps = '<w:p>' . $pPr
                . $this->run($this->entete['gauche'], $style)
                . '<w:r><w:tab/></w:r>'
                . $this->run($this->entete['droite'], $style)
                . '</w:p>';
        }

        return self::PROLOGUE . '<w:hdr ' . self::NS_DOCUMENT . '>' . $corps . '</w:hdr>';
    }

    /**
     * @return string
     */
    private function footerXml()
    {
        $corps = '<w:p><w:pPr><w:pStyle w:val="Footer"/></w:pPr></w:p>';

        if ($this->pied !== null) {
            $tabDroite = $this->largeurContenuTwips();
            $pPr = '<w:pPr><w:pStyle w:val="Footer"/>'
                . '<w:pBdr><w:top w:val="single" w:sz="6" w:space="4" w:color="BFBFBF"/></w:pBdr>'
                . '<w:tabs><w:tab w:val="right" w:pos="' . $tabDroite . '"/></w:tabs>'
                . '</w:pPr>';
            $style = array('taille' => 9, 'couleur' => '595959');
            $rPr = $this->rPr($style);

            $numero = '';
            if ($this->pied['numero']) {
                $numero = '<w:r><w:tab/></w:r>'
                    . '<w:r>' . $rPr . '<w:t xml:space="preserve">Page </w:t></w:r>'
                    . self::champ('PAGE', $rPr)
                    . '<w:r>' . $rPr . '<w:t xml:space="preserve"> sur </w:t></w:r>'
                    . self::champ('NUMPAGES', $rPr);
            }

            $corps = '<w:p>' . $pPr . $this->run($this->pied['texte'], $style) . $numero . '</w:p>';
        }

        return self::PROLOGUE . '<w:ftr ' . self::NS_DOCUMENT . '>' . $corps . '</w:ftr>';
    }

    /**
     * En-tête ou pied de page vide, appliqué à la page de garde.
     *
     * @param string $balise hdr ou ftr
     * @return string
     */
    private function partieVideXml($balise)
    {
        return self::PROLOGUE . '<w:' . $balise . ' ' . self::NS_DOCUMENT . '><w:p/></w:' . $balise . '>';
    }

    /**
     * Champ Word simple (PAGE, NUMPAGES...).
     *
     * @param string $instruction
     * @param string $rPr
     * @return string
     */
    private static function champ($instruction, $rPr)
    {
        return '<w:r>' . $rPr . '<w:fldChar w:fldCharType="begin"/></w:r>'
            . '<w:r>' . $rPr . '<w:instrText xml:space="preserve"> ' . $instruction . ' </w:instrText></w:r>'
            . '<w:r>' . $rPr . '<w:fldChar w:fldCharType="separate"/></w:r>'
            . '<w:r>' . $rPr . '<w:t>1</w:t></w:r>'
            . '<w:r>' . $rPr . '<w:fldChar w:fldCharType="end"/></w:r>';
    }

    /**
     * @return string
     */
    private function coreXml()
    {
        $date = gmdate('Y-m-d\TH:i:s\Z');
        return self::PROLOGUE
            . '<cp:coreProperties'
            . ' xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:dcterms="http://purl.org/dc/terms/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::echapper($this->options['titre']) . '</dc:title>'
            . '<dc:subject>' . self::echapper($this->options['sujet']) . '</dc:subject>'
            . '<dc:creator>' . self::echapper($this->options['auteur']) . '</dc:creator>'
            . '<cp:lastModifiedBy>' . self::echapper($this->options['auteur']) . '</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    /**
     * @return string
     */
    private function appXml()
    {
        return self::PROLOGUE
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
            . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Tockem SPE</Application>'
            . '<Company>' . self::echapper($this->options['auteur']) . '</Company>'
            . '</Properties>';
    }

    /**
     * @return string
     */
    private function stylesXml()
    {
        $police = self::echapper($this->options['police']);
        $couleurTitre = self::couleur($this->options['couleur_titre']);
        $couleurAccent = self::couleur($this->options['couleur_accent']);
        $taille = (int) round($this->options['taille'] * 2);
        $rFonts = '<w:rFonts w:ascii="' . $police . '" w:hAnsi="' . $police . '" w:cs="' . $police . '"/>';

        $xml = self::PROLOGUE . '<w:styles xmlns:w="' . self::NS_W . '">'
            . '<w:docDefaults>'
            . '<w:rPrDefault><w:rPr>' . $rFonts . '<w:sz w:val="' . $taille . '"/>'
            . '<w:szCs w:val="' . $taille . '"/><w:lang w:val="fr-FR"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="259" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            . '</w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/><w:qFormat/></w:style>';

        // Les noms « heading N » sont ce qui permet au champ TOC de reconnaître les titres.
        $niveaux = array(
            1 => array('taille' => 32, 'avant' => 360, 'apres' => 160, 'couleur' => $couleurTitre, 'trait' => true),
            2 => array('taille' => 26, 'avant' => 280, 'apres' => 120, 'couleur' => $couleurAccent, 'trait' => false),
            3 => array('taille' => 23, 'avant' => 220, 'apres' => 100, 'couleur' => $couleurAccent, 'trait' => false),
        );
        foreach ($niveaux as $niveau => $reglage) {
            $bordure = $reglage['trait']
                ? '<w:pBdr><w:bottom w:val="single" w:sz="8" w:space="4" w:color="' . $reglage['couleur'] . '"/></w:pBdr>'
                : '';
            $xml .= '<w:style w:type="paragraph" w:styleId="Heading' . $niveau . '">'
                . '<w:name w:val="heading ' . $niveau . '"/>'
                . '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
                . '<w:uiPriority w:val="9"/><w:qFormat/>'
                . '<w:pPr><w:keepNext/><w:keepLines/>'
                . $bordure
                . '<w:spacing w:before="' . $reglage['avant'] . '" w:after="' . $reglage['apres'] . '"/>'
                . '<w:outlineLvl w:val="' . ($niveau - 1) . '"/></w:pPr>'
                . '<w:rPr>' . $rFonts . '<w:b/><w:color w:val="' . $reglage['couleur'] . '"/>'
                . '<w:sz w:val="' . $reglage['taille'] . '"/><w:szCs w:val="' . $reglage['taille'] . '"/></w:rPr>'
                . '</w:style>';
        }

        $xml .= '<w:style w:type="paragraph" w:styleId="Title">'
            . '<w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:spacing w:after="80"/><w:contextualSpacing/></w:pPr>'
            . '<w:rPr>' . $rFonts . '<w:b/><w:color w:val="' . $couleurTitre . '"/><w:sz w:val="56"/><w:szCs w:val="56"/></w:rPr>'
            . '</w:style>';

        $xml .= '<w:style w:type="paragraph" w:styleId="Subtitle">'
            . '<w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:spacing w:after="240"/><w:contextualSpacing/></w:pPr>'
            . '<w:rPr>' . $rFonts . '<w:color w:val="595959"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr>'
            . '</w:style>';

        $xml .= '<w:style w:type="paragraph" w:styleId="Caption">'
            . '<w:name w:val="caption"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:spacing w:after="200"/></w:pPr>'
            . '<w:rPr>' . $rFonts . '<w:i/><w:color w:val="595959"/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr>'
            . '</w:style>';

        $xml .= '<w:style w:type="paragraph" w:styleId="ListParagraph">'
            . '<w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:contextualSpacing/></w:pPr></w:style>';

        $xml .= '<w:style w:type="paragraph" w:styleId="Header">'
            . '<w:name w:val="header"/><w:basedOn w:val="Normal"/>'
            . '<w:pPr><w:spacing w:after="0"/></w:pPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Footer">'
            . '<w:name w:val="footer"/><w:basedOn w:val="Normal"/>'
            . '<w:pPr><w:spacing w:after="0"/></w:pPr></w:style>';

        for ($i = 1; $i <= 3; $i++) {
            $xml .= '<w:style w:type="paragraph" w:styleId="TOC' . $i . '">'
                . '<w:name w:val="toc ' . $i . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
                . '<w:pPr><w:spacing w:after="60"/><w:ind w:left="' . (($i - 1) * 220) . '"/></w:pPr>'
                . '</w:style>';
        }

        return $xml . '</w:styles>';
    }

    // ---------------------------------------------------------------- petites conversions

    /**
     * @param array<string,mixed> $options
     * @return bool
     */
    private static function estPaysage($options)
    {
        $orientation = isset($options['orientation']) ? strtolower((string) $options['orientation']) : 'portrait';
        return ($orientation === 'paysage' || $orientation === 'landscape');
    }

    /**
     * @param string $valeur
     * @return string couleur hexadécimale sur 6 caractères
     */
    private static function couleur($valeur)
    {
        $valeur = ltrim((string) $valeur, '#');
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $valeur) !== 1) {
            return 'auto';
        }
        return strtoupper($valeur);
    }

    /**
     * @param string $valeur
     * @return string valeur w:jc
     */
    private static function alignement($valeur)
    {
        $correspondances = array(
            'gauche' => 'left', 'left' => 'left',
            'centre' => 'center', 'center' => 'center',
            'droite' => 'right', 'right' => 'right',
            'justifie' => 'both', 'justify' => 'both', 'both' => 'both',
        );
        $valeur = strtolower((string) $valeur);
        return isset($correspondances[$valeur]) ? $correspondances[$valeur] : 'left';
    }

    /**
     * @param string $nom
     * @return string nom de fichier sans caractère problématique dans un en-tête HTTP
     */
    private static function nomFichierSur($nom)
    {
        $nom = basename((string) $nom);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nom);
        $nom = trim($nom, '_');
        if ($nom === '' || $nom === '.docx') {
            $nom = 'document.docx';
        }
        if (substr($nom, -5) !== '.docx') {
            $nom .= '.docx';
        }
        return $nom;
    }

    /** Déclaration XML commune à toutes les parties. */
    const PROLOGUE = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

    /** Espace de noms principal de WordprocessingML. */
    const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Espaces de noms nécessaires au document et aux images.
     * En une seule chaîne : PHP 5.3 refuse la concaténation dans une constante.
     */
    const NS_DOCUMENT = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"';
}
