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
                   left join indexes i on i.id_compteur = r.id
                WHERE r.id = ? 
                group by i.id",
            array($id_reseau));


    }

    public static function estFacturable($id_compteur)
    {
        $res = self::prepare_query("select ca.id_abone as id_abone from compteur c 
                    inner join compteur_abone ca on c.id = ca.id_compteur
                    where c.id=? limit 1;", array($id_compteur));
        $res = $res->fetchAll();
        var_dump('est facturable donne ');
        var_dump($res);
        if (count($res) == 1) {
            if($res[0]["id_abone"]){
                return $res[0]["id_abone"];
            }
        }
        return false;
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

    public function save_compteur_reseau($id_reseau)
    {
        try {

            $this->ajouter();
            return self::prepare_query("insert into compteur_reseau(id_compteur, id_reseau) values(?, ?)", array($this->id, $id_reseau));
        }catch (Exception $e){
            echo $e->getMessage();
            throw $e;
        }
    }
}
