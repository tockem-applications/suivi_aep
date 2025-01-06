<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class FluxFinancier extends Manager
{
    public $id;              // Identifiant unique
    public $date;            // Date du flux financier
    public $mois;            // Mois du flux financier
    public $libele;          // Libellé du flux
    public $prix;            // Montant du flux
    public $id_aep;            // Type de flux (sortie ou entrée)
    public $type;            // Type de flux (sortie ou entrée)
    public $description;     // Description du flux

    public static function getFinanceData($mois = '', $type = '', $prix_min = 0, $id_aep='')
    {
        return self::prepare_query("
                            SELECT * 
                            from flux_financier 
                            where mois like concat('%', ?) and 
                                  type like concat('%', ?) and 
                                  prix>=? and
                                  id_aep=?
                            order by mois desc;", array($mois, $type, $prix_min, $id_aep));
    }

    public static function getFluxById($id)
    {
        return self::prepare_query("SELECT * from flux_financier where id = ?;", array($id));
    }

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'date' => $this->date,
            'mois' => $this->mois,
            'libele' => $this->libele,
            'prix' => $this->prix,
            'type' => $this->type,
            'id_aep' => $this->id_aep,
            'description' => $this->description
        );
    }

    function getNomTable()
    {
        return "flux_financier";
    }

    public static function delete_flux($id){
        return self::prepare_query("DELETE FROM flux_financier where id =?", array($id));
    }

    public function __construct($id, $date, $libele, $prix, $type, $description, $mois = '', $id_aep = 1)
    {
        $this->id = $id;
        $this->date = $date;
        $this->mois = $mois == '' ? date('Y-m', strtotime($date)) : $mois;
        $this->libele = $libele;
        $this->prix = $prix;
        $this->type = $type;
        $this->id_aep = $id_aep;
        $this->description = $description;
    }
}
