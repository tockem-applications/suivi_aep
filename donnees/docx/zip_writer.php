<?php

/**
 * Écriture d'archives ZIP en PHP pur.
 *
 * L'extension zip est absente des PHP 5.3 ciblés par la v1 (php_zip.dll n'est
 * pas livré avec WAMP 5.3.4 ni avec l'image Docker), donc ZipArchive est
 * inutilisable. On écrit ici les en-têtes ZIP à la main : c'est suffisant pour
 * produire un .docx, qui n'est qu'une archive ZIP de fichiers XML.
 *
 * Seules les fonctions du noyau sont utilisées (pack, crc32, gzdeflate) ;
 * gzdeflate provient de zlib, compilé en dur dans toutes les builds PHP.
 */
class ZipWriter
{
    /** Signature en-tête local. */
    const SIG_LOCAL = "\x50\x4b\x03\x04";
    /** Signature entrée du répertoire central. */
    const SIG_CENTRAL = "\x50\x4b\x01\x02";
    /** Signature fin de répertoire central. */
    const SIG_EOCD = "\x50\x4b\x05\x06";

    /** Drapeau « nom de fichier encodé en UTF-8 ». */
    const FLAG_UTF8 = 0x0800;

    /** @var string en-têtes locaux + données déjà sérialisés */
    private $corps = '';

    /** @var array<int,array> métadonnées des entrées, pour le répertoire central */
    private $entrees = array();

    /** @var int heure MS-DOS appliquée à toutes les entrées */
    private $heureDos;

    /** @var int date MS-DOS appliquée à toutes les entrées */
    private $dateDos;

    /**
     * @param int|null $timestamp horodatage des entrées (défaut : maintenant)
     */
    public function __construct($timestamp = null)
    {
        $timestamp = ($timestamp === null) ? time() : (int) $timestamp;
        // Calculé une seule fois : getdate() est bruyant quand date.timezone
        // n'est pas configuré, et l'archive n'a qu'un horodatage.
        $d = getdate($timestamp);
        if ($d['year'] < 1980) {
            $d = getdate(315532800); // ZIP ne descend pas sous 1980-01-01
        }
        $this->heureDos = ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
        $this->dateDos = (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
    }

    /**
     * Ajoute un fichier à l'archive.
     *
     * @param string $nom       chemin interne, séparateurs '/'
     * @param string $contenu   données brutes
     * @param bool   $compresser false pour stocker sans compression
     * @return void
     */
    public function ajouter($nom, $contenu, $compresser = true)
    {
        $nom = str_replace(chr(92), '/', (string) $nom);
        $nom = ltrim($nom, '/');
        $contenu = (string) $contenu;

        $tailleBrute = self::longueur($contenu);
        $crc = crc32($contenu);

        $methode = 0;
        $donnees = $contenu;
        if ($compresser && $tailleBrute > 0 && function_exists('gzdeflate')) {
            $compresse = @gzdeflate($contenu, 6);
            // On ne garde la compression que si elle fait réellement gagner de la place.
            if ($compresse !== false && self::longueur($compresse) < $tailleBrute) {
                $methode = 8;
                $donnees = $compresse;
            }
        }
        $tailleCompressee = self::longueur($donnees);

        $enteteLocal = self::SIG_LOCAL
            . pack('v', 20)                 // version minimale requise
            . pack('v', self::FLAG_UTF8)    // drapeaux
            . pack('v', $methode)           // méthode de compression
            . pack('v', $this->heureDos)
            . pack('v', $this->dateDos)
            . pack('V', $crc)
            . pack('V', $tailleCompressee)
            . pack('V', $tailleBrute)
            . pack('v', self::longueur($nom))
            . pack('v', 0)                  // longueur du champ extra
            . $nom;

        $this->entrees[] = array(
            'nom' => $nom,
            'methode' => $methode,
            'crc' => $crc,
            'taille_compressee' => $tailleCompressee,
            'taille_brute' => $tailleBrute,
            'decalage' => self::longueur($this->corps),
        );

        $this->corps .= $enteteLocal . $donnees;
    }

    /**
     * Sérialise l'archive complète.
     *
     * @return string contenu binaire du .zip
     */
    public function rendu()
    {
        $central = '';
        foreach ($this->entrees as $e) {
            $central .= self::SIG_CENTRAL
                . pack('v', 20)                 // version d'écriture
                . pack('v', 20)                 // version minimale requise
                . pack('v', self::FLAG_UTF8)
                . pack('v', $e['methode'])
                . pack('v', $this->heureDos)
                . pack('v', $this->dateDos)
                . pack('V', $e['crc'])
                . pack('V', $e['taille_compressee'])
                . pack('V', $e['taille_brute'])
                . pack('v', self::longueur($e['nom']))
                . pack('v', 0)                  // extra
                . pack('v', 0)                  // commentaire
                . pack('v', 0)                  // numéro de disque
                . pack('v', 0)                  // attributs internes
                . pack('V', 32)                 // attributs externes (archive)
                . pack('V', $e['decalage'])
                . $e['nom'];
        }

        $nb = count($this->entrees);
        $eocd = self::SIG_EOCD
            . pack('v', 0)                      // disque courant
            . pack('v', 0)                      // disque du répertoire central
            . pack('v', $nb)                    // entrées sur ce disque
            . pack('v', $nb)                    // entrées au total
            . pack('V', self::longueur($central))
            . pack('V', self::longueur($this->corps))
            . pack('v', 0);                     // longueur du commentaire

        return $this->corps . $central . $eocd;
    }

    /**
     * @return int nombre d'entrées ajoutées
     */
    public function nombreEntrees()
    {
        return count($this->entrees);
    }

    /**
     * Longueur en octets, insensible à mbstring.func_overload.
     *
     * @param string $data
     * @return int
     */
    private static function longueur($data)
    {
        if (function_exists('mb_strlen') && ((int) ini_get('mbstring.func_overload') & 2)) {
            return mb_strlen($data, '8bit');
        }
        return strlen($data);
    }
}
