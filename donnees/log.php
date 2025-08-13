<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Log extends Manager
{
//    public $user_id;
    public $page_libelle;
    public $action;
    public $timestamp;

    public function __construct($id = 0, $page_libelle = '', $action = '', $timestamp = '')
    {
        $this->id = $id;
        $this->page_libelle = $page_libelle;
        $this->action = $action;
        $this->timestamp = $timestamp;
    }

    /**
     * @throws Exception
     */
    public static function logRequest($userId, $page, $action, $date)
    {
        $log = new Log($userId, $page, $action, $date);
        return $log->ajouter();
    }

    public function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    public function getDonnee()
    {
        return array(
            'page_libelle' => $this->page_libelle,
            'action' => $this->action,
            'timestamp' => $this->timestamp
        );
    }

    public function getNomTable()
    {
        return "logs";
    }

    /**
     * Ajoute un log
     * @param int $userId ID de l'utilisateur (0 si non connecté)
     * @param string $pageLibelle Libellé de la page
     * @param string $action Action effectuée
     * @return bool
     */
    public static function addLog($userId, $pageLibelle, $action)
    {
        return self::prepare_query(
                "INSERT INTO logs (user_id, page_libelle, action, timestamp) VALUES (?, ?, ?, NOW())",
                array($userId, $pageLibelle, $action)
            )->rowCount() > 0;
    }

    /**
     * Récupère les logs par utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array
     */
    public static function getLogsByUser($userId)
    {
        return self::prepare_query(
            "SELECT * FROM logs WHERE user_id = ? ORDER BY timestamp DESC",
            array($userId)
        )->fetchAll();
    }

    /**
     * Supprime un log par ID
     * @param int $id ID du log
     * @return bool
     */
    public static function deleteById($id)
    {
        return self::prepare_query("DELETE FROM logs WHERE id = ?", array($id))->rowCount() > 0;
    }
}
