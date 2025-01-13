<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Indexes extends Manager
{
    public $id;
    public $id_compteur;
    public $id_mois_facturation;
    public $ancien_index;
    public $nouvel_index;
    public $message;

    public static function getIndexes($id_compteur, $id_mois_facturation){
        return self::prepare_query("SELECT * FROM indexes WHERE id_compteur = ? AND id_mois_facturation = ?", array($id_compteur, $id_mois_facturation));
    }

    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'id_compteur' => $this->id_compteur,
            'id_mois_facturation' => $this->id_mois_facturation,
            'ancien_index' => $this->ancien_index,
            'nouvel_index' => $this->nouvel_index,
            'message' => $this->message
        );
    }

    function getNomTable()
    {
        return "indexes";
    }

    public function __construct($id, $id_compteur, $id_mois_facturation, $ancien_index, $nouvel_index, $message)
    {
        $this->id = $id;
        $this->id_compteur = $id_compteur;
        $this->id_mois_facturation = $id_mois_facturation;
        $this->ancien_index = $ancien_index;
        $this->nouvel_index = $nouvel_index;
        $this->message = $message;
    }
}
?>
