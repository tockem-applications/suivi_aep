<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Role extends Manager
{
    public $nom;

    public function __construct($id = 0, $nom = '')
    {
        $this->id = $id;
        $this->nom = $nom;
    }

    public static function hasRole($userId, $string)
    {
        hash('sha256', $string);
    }

    public function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    public function getDonnee()
    {
        return array('nom' => $this->nom);
    }

    public function getNomTable()
    {
        return "roles";
    }

    /**
     * Récupère un rôle par ID
     * @param int $id ID du rôle
     * @return array|null
     */
    public static function getRole($id)
    {
        return self::prepare_query("SELECT * FROM roles WHERE id = ?", array($id))->fetch();
    }

    /**
     * Récupère un rôle par nom
     * @param string $nom Nom du rôle
     * @return array|null
     */
    public static function getRoleByName($nom)
    {
        return self::prepare_query("SELECT * FROM roles WHERE nom = ?", array($nom))->fetch();
    }

    /**
     * Supprime un rôle par ID
     * @param int $id ID du rôle
     * @return bool
     */
    public static function deleteById($id)
    {
        // Supprimer les relations associées
        self::prepare_query("DELETE FROM user_roles WHERE role_id = ?", array($id));
        self::prepare_query("DELETE FROM page_role_aep WHERE role_id = ?", array($id));
        return self::prepare_query("DELETE FROM roles WHERE id = ?", array($id))->rowCount() > 0;
    }
}
?>