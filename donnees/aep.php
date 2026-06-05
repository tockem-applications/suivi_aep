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
        );
    }

    function getNomTable()
    {
        return "aep";
    }

    public function __construct($id, $libele, $fichier_facture, $date, $description, $nom_banque, $numero_compte, $type_distribution = null)
    {
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
