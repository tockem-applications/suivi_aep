<?php
/**
 * Classe pour gérer les gérants des Bornes Fontaines
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");

class BFGerant extends Manager
{
    public $id;
    public $id_borne_fontaine;
    public $nom_gerant;
    public $numero_telephone;
    public $numero_piece_identite;
    public $type_piece_identite;
    public $date_debut;
    public $date_fin;
    public $est_actif;
    public $notes;

    function getNomTable()
    {
        return "bf_gerant";
    }

    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        $donnees = array(
            'id_borne_fontaine' => $this->id_borne_fontaine,
            'nom_gerant' => $this->nom_gerant,
            'numero_telephone' => $this->numero_telephone,
            'numero_piece_identite' => $this->numero_piece_identite,
            'type_piece_identite' => $this->type_piece_identite,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'est_actif' => $this->est_actif ? 1 : 0,
            'notes' => $this->notes
        );
        
        // Gérer les dates automatiquement
        if ($this->id == 0) {
            // Nouvel enregistrement : définir date_creation
            $donnees['date_creation'] = date('Y-m-d H:i:s');
        } else {
            // Mise à jour : définir date_modification
            $donnees['date_modification'] = date('Y-m-d H:i:s');
        }
        
        return $donnees;
    }

    public function __construct($id = 0, $id_borne_fontaine = 0, $nom_gerant = '', $numero_telephone = '', 
                                $numero_piece_identite = '', $type_piece_identite = '', $date_debut = '', 
                                $date_fin = null, $est_actif = true, $notes = '')
    {
        $this->id = $id;
        $this->id_borne_fontaine = $id_borne_fontaine;
        $this->nom_gerant = $nom_gerant;
        $this->numero_telephone = $numero_telephone;
        $this->numero_piece_identite = $numero_piece_identite;
        $this->type_piece_identite = $type_piece_identite;
        $this->date_debut = $date_debut;
        $this->date_fin = $date_fin;
        $this->est_actif = $est_actif;
        $this->notes = $notes;
    }

    /**
     * Ajouter un nouveau gérant à une BF
     * Si un gérant actif existe, il sera désactivé automatiquement
     * @return bool|int ID du gérant créé ou false en cas d'erreur
     */
    public function ajouterGerant()
    {
        try {
            if (self::$bd === null) {
                self::$bd = Connexion::connect();
            }
            self::$bd->beginTransaction();

            // Désactiver tous les gérants actifs de cette BF
            if ($this->est_actif) {
                self::prepare_query(
                    "UPDATE bf_gerant SET est_actif = 0, date_fin = ? 
                     WHERE id_borne_fontaine = ? AND est_actif = 1",
                    array($this->date_debut, $this->id_borne_fontaine)
                );
            }

            // Ajouter le nouveau gérant
            $this->ajouter();

            self::$bd->commit();
            return $this->id;
        } catch (Exception $e) {
            if (self::$bd !== null) {
                self::$bd->rollback();
            }
            error_log("Erreur lors de l'ajout du gérant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Terminer la période d'un gérant (mettre fin à sa gestion)
     * @param int $id_gerant ID du gérant
     * @param string $date_fin Date de fin
     * @return bool
     */
    public static function terminerPeriodeGerant($id_gerant, $date_fin)
    {
        try {
            return self::prepare_query(
                "UPDATE bf_gerant SET est_actif = 0, date_fin = ? WHERE id = ?",
                array($date_fin, $id_gerant)
            ) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Récupérer tous les gérants d'une BF
     * @param int $id_borne_fontaine ID de la BF
     * @return PDOStatement
     */
    public static function getGerantsByBF($id_borne_fontaine)
    {
        return self::prepare_query(
            "SELECT * FROM bf_gerant 
             WHERE id_borne_fontaine = ? 
             ORDER BY date_debut DESC",
            array($id_borne_fontaine)
        );
    }

    /**
     * Récupérer un gérant par son ID
     * @param int $id ID du gérant
     * @return array|false
     */
    public static function getGerantById($id)
    {
        return self::prepare_query(
            "SELECT * FROM bf_gerant WHERE id = ?",
            array($id)
        )->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifier si un gérant existe déjà (même nom et même BF)
     * @param int $id_borne_fontaine ID de la BF
     * @param string $nom_gerant Nom du gérant
     * @return bool
     */
    public static function gerantExiste($id_borne_fontaine, $nom_gerant)
    {
        $result = self::prepare_query(
            "SELECT COUNT(*) as count FROM bf_gerant 
             WHERE id_borne_fontaine = ? AND nom_gerant = ?",
            array($id_borne_fontaine, $nom_gerant)
        )->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
}
