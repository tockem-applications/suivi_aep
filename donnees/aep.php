<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Aep extends Manager
{

    public $libele;
    public $date;
    public $fichier_facture;
    public $description;
    public $numero_compte;
    public $nom_banque;
    /** @var string|null 'RDS' (Refoulement Distribution Séparé) ou 'RDC' (Refoulement Distribution Confondu) */
    public $type_distribution;

    /**
     * Code marchand Mobile Money, tel qu'il sera lu par l'abonné.
     *
     * Texte libre : au Cameroun un paiement MTN MoMo ou Orange Money se compose
     * en tapant une syntaxe USSD, et l'abonné a besoin de la chaîne exacte plus
     * du nom qui s'affichera pour qu'il vérifie qu'il paie au bon destinataire.
     * D'où un champ de texte plutôt qu'une colonne structurée : la forme varie
     * d'un opérateur à l'autre et changera avant nos colonnes.
     *
     * Exemple : MOMO: *126*14*654190514*Montant#. Nom: Erick Tsafack
     *
     * @var string|null
     */
    public $code_marchand_1;

    /**
     * Ajoute la colonne de code marchand si elle manque.
     *
     * Même approche que le reste du projet : la base des installations
     * existantes est migrée à la volée, sans script à lancer à la main sur
     * chaque poste client.
     */
    public static function ensureColonnesCodeMarchand()
    {
        static $fait = false;
        if ($fait) {
            return;
        }
        $fait = true;
        try {
            $existe = Manager::prepare_query(
                "SHOW COLUMNS FROM aep LIKE ?",
                array('code_marchand_1')
            );
            if (!$existe || !$existe->fetch()) {
                Manager::prepare_query(
                    "ALTER TABLE aep ADD COLUMN code_marchand_1 VARCHAR(255) DEFAULT NULL",
                    array()
                );
            }
        } catch (Exception $e) {
            // Une base en lecture seule ou un privilège manquant ne doit pas
            // empêcher l'application de démarrer : le champ restera absent.
        }

        // Nettoyage d'un second champ introduit puis abandonné avant toute
        // mise en production : aucune installation n'y a de donnée utile.
        try {
            $existe = Manager::prepare_query(
                "SHOW COLUMNS FROM aep LIKE ?",
                array('code_marchand_2')
            );
            if ($existe && $existe->fetch()) {
                Manager::prepare_query("ALTER TABLE aep DROP COLUMN code_marchand_2", array());
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Codes marchands d'un AEP, sans les entrées vides.
     *
     * Reste un tableau (et non une chaîne) : la facture qui l'affiche n'a pas
     * à changer si un second moyen de paiement redevient nécessaire un jour.
     *
     * @return string[]
     */
    public static function codesMarchands($aepRow)
    {
        $codes = array();
        if (isset($aepRow['code_marchand_1'])) {
            $v = trim((string) $aepRow['code_marchand_1']);
            if ($v !== '') {
                $codes[] = $v;
            }
        }
        return $codes;
    }

    /**
     * Libellés affichables pour le type de distribution.
     */
    public static function getTypesDistribution()
    {
        return array(
            'RDS' => 'Refoulement Distribution Séparé (RDS)',
            'RDC' => 'Refoulement Distribution Confondu (RDC)',
        );
    }

    /**
     * Normalise une saisie utilisateur en code RDS|RDC|null.
     */
    public static function normaliserTypeDistribution($valeur)
    {
        $v = strtoupper(trim((string) $valeur));
        if ($v === 'RDS' || $v === 'RDC') {
            return $v;
        }
        return null;
    }

    /**
     * Libellé court (badge / liste) pour un code.
     */
    public static function libelleCourtTypeDistribution($code)
    {
        $code = strtoupper(trim((string) $code));
        if ($code === 'RDS') {
            return 'RDS';
        }
        if ($code === 'RDC') {
            return 'RDC';
        }
        return '';
    }

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'libele' => $this->libele,
            'fichier_facture' => $this->fichier_facture,
            'date' => $this->date,
            'numero_compte' => $this->numero_compte,
            'nom_banque' => $this->nom_banque,
            'description' => $this->description,
            'type_distribution' => self::normaliserTypeDistribution($this->type_distribution),
            'code_marchand_1' => ($this->code_marchand_1 === '' ? null : $this->code_marchand_1),
        );
    }

    function getNomTable()
    {
        return "aep";
    }

    public function __construct($id, $libele, $fichier_facture, $date, $description, $nom_banque, $numero_compte, $type_distribution = null, $code_marchand_1 = null)
    {
        self::ensureColonnesCodeMarchand();
        $this->code_marchand_1 = ($code_marchand_1 === null) ? null : trim((string) $code_marchand_1);
        $this->id = $id;
        $this->libele = $libele;
        $this->date = $date;
        $this->description = $description;
        $this->nom_banque = $nom_banque;
        $this->numero_compte = $numero_compte;
        $this->fichier_facture = $fichier_facture;
        $this->type_distribution = self::normaliserTypeDistribution($type_distribution);
    }
}
