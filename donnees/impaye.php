<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Impaye extends Manager
{

    public $id_facture;
    public $montant;
    public $est_regle;
    public $date_reglement;

    public static function getImpaye($id_facture){
        return self::prepare_query("select sum(montant) as impaye from impaye where  id_facture=?", array($id_facture));
    }

    public static function deleteByIdCacture($id_facture)
    {
        return self::prepare_query("delete from impaye where  id_facture=?", array($id_facture));
    }


    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array('id_facture' => $this->id_facture, 'montant' => $this->montant, 'date_reglement' => $this->date_reglement, 'est_regle'=>$this->est_regle);
    }

    function getNomTable()
    {
        return "impaye";
    }

    public function __construct($id, $id_facture, $montant, $est_regle, $date_reglement)
    {
        $this->id = $id;
        $this->id_facture = $id_facture;
        $this->montant = $montant;
        $this->est_regle = $est_regle;
        $this->date_reglement = $date_reglement;
    }
}
