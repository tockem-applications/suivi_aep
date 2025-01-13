<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Compteur extends Manager
{
    public $id;
    public $numero_compteur;
    public $longitude;
    public $latitude;
    public $derniers_index;
    public $description;

    public static function getAll($table_name)
    {
        return self::prepare_query("SELECT * FROM compteur", array());
    }

    public static function getById($id)
    {
        return self::prepare_query("SELECT * FROM compteur WHERE id = ?", array($id));
    }

    /*public function save2()
    {
        // Insert or update logic here
        if (isset($this->id)) {
            // Update existing record
            return self::prepare_query("UPDATE compteur SET numero_comteur = ?, longitude = ?, latitude = ?, dernier_index = ?, description = ? WHERE id = ?",
                array($this->numero_compteur, $this->longitude, $this->latitude, $this->dernier_index, $this->description, $this->id));
        } else {
            // Insert new record
            return self::prepare_query("INSERT INTO compteur (numero_comteur, longitude, latitude, dernier_index, description) VALUES (?, ?, ?, ?, ?)",
                array($this->numero_compteur, $this->longitude, $this->latitude, $this->dernier_index, $this->description));
        }
    }*/
    public static function getAllByIdReseau($id_reseau)
    {
        return self::prepare_query("SELECT c.*, sum(i.nouvel_index-i.ancien_index) as consomation, count(i.id) nombre_releve
                FROM compteur c
                   inner join compteur_reseau cr on c.id = cr.id_compteur
                   inner join reseau r on r.id = cr.id_reseau
                   inner join indexes i on i.id_compteur = r.id
                WHERE r.id = ? 
                group by i.id",
            array($id_reseau));


    }

    public function delete()
    {
        return self::prepare_query("DELETE FROM compteur WHERE id = ?", array($this->id));
    }

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'numero_compteur' => $this->numero_compteur,
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'derniers_index' => $this->derniers_index,
            'description' => $this->description
        );
    }

    function getNomTable()
    {
        return "compteur";
    }

    public function __construct($id ='', $numero_compteur = "", $longitude = null, $latitude = null, $dernier_index = 0, $description = "")
    {
        $this->id = $id;
        $this->numero_compteur = $numero_compteur;
        $this->longitude = $longitude;
        $this->latitude = $latitude;
        $this->derniers_index = $dernier_index;
        $this->description = $description;
    }
}
