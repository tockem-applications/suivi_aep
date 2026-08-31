<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

/*
CREATE TABLE pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chaine VARCHAR(255) NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    description TEXT
);

 */

class Page extends Manager
{
//    public $id;
    public $libelle;
    public $chaine;
    public $description;

    public function __construct($id = 0, $libelle = '', $chaine='', $description = '')
    {
        $this->id = $id;
        $this->libelle = $libelle;
        $this->chaine = $chaine;
        $this->description = $description;
    }

    public function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    public function getDonnee()
    {
        return array(
            'libelle' => $this->libelle,
            'chaine' => $this->chaine,
            'description' => $this->description
        );
    }

    public function getNomTable()
    {
        return "pages";
    }

    /**
     * Récupère une page par ID
     * @param int $id ID de la page
     * @return array|null
     */
    public static function getPage($id)
    {
        return self::prepare_query("SELECT * FROM pages WHERE id = ?", array($id))->fetch();
    }

    /**
     * Récupère une page par libellé
     * @param string $libelle Libellé de la page
     * @return array|null
     */
    public static function getPageByLibelle($libelle)
    {
        return self::prepare_query("SELECT * FROM pages WHERE libelle = ?", array($libelle))->fetch();
    }

    /**
     * Supprime une page par ID
     * @param int $id ID de la page
     * @return bool
     */
    public static function deleteById($id)
    {
        // Supprimer les relations associées
        self::prepare_query("DELETE FROM page_role_aep WHERE page_id = ?", array($id));
        return self::prepare_query("DELETE FROM pages WHERE id = ?", array($id))->rowCount() > 0;
    }
}
