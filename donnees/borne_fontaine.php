<?php
/**
 * Classe pour gérer les Bornes Fontaines (BF)
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");
@include_once("../donnees/Abones.php");
@include_once("donnees/Abones.php");

class BorneFontaine extends Manager
{
    public $id;
    public $id_abone;
    public $numero_borne;
    public $localisation;
    public $date_creation;
    public $date_modification;
    public $description;

    function getNomTable()
    {
        return "borne_fontaine";
    }

    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        $donnees = array(
            'id_abone' => $this->id_abone,
            'numero_borne' => $this->numero_borne,
            'localisation' => $this->localisation,
            'description' => $this->description
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

    public function __construct($id = 0, $id_abone = 0, $numero_borne = '', $localisation = '', $description = '')
    {
        $this->id = $id;
        $this->id_abone = $id_abone;
        $this->numero_borne = $numero_borne;
        $this->localisation = $localisation;
        $this->description = $description;
    }

    /**
     * Convertir un abonné en Borne Fontaine
     * @param int $id_abone ID de l'abonné à convertir
     * @param string $numero_borne Numéro de la borne
     * @param string $localisation Localisation de la borne
     * @param string $description Description
     * @return bool|int ID de la BF créée ou false en cas d'erreur
     */
    public static function convertirAboneEnBF($id_abone, $numero_borne = '', $localisation = '', $description = '')
    {
        try {
            // Vérifier si l'abonné existe
            $abone = Manager::prepare_query("SELECT id, type_abone FROM abone WHERE id = ?", array($id_abone))->fetch();
            if (!$abone) {
                return false;
            }

            // Vérifier si l'abonné n'est pas déjà une BF
            if ($abone['type_abone'] == 'BF') {
                // Vérifier si une BF existe déjà
                $bf = self::prepare_query("SELECT id FROM borne_fontaine WHERE id_abone = ?", array($id_abone))->fetch();
                if ($bf) {
                    return $bf['id'];
                }
            }

            // Démarrer une transaction
            if (self::$bd === null) {
                self::$bd = Connexion::connect();
            }
            self::$bd->beginTransaction();

            try {
                // Mettre à jour le type de l'abonné
                self::prepare_query("UPDATE abone SET type_abone = 'BF' WHERE id = ?", array($id_abone));

                // Créer l'enregistrement dans borne_fontaine
                $bf = new BorneFontaine(0, $id_abone, $numero_borne, $localisation, $description);
                $bf->ajouter();

                self::$bd->commit();
                return $bf->id;
            } catch (Exception $e) {
                self::$bd->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Erreur lors de la conversion en BF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer toutes les BF avec leurs informations
     * @param int $id_aep ID de l'AEP
     * @return PDOStatement
     */
    public static function getAllBF($id_aep = 0)
    {
        $where = "";
        $params = array();
        
        if ($id_aep > 0) {
            $where = "WHERE r.id_aep = ?";
            $params[] = $id_aep;
        }

        return self::prepare_query(
            "SELECT bf.*, a.nom, a.numero_telephone, a.numero_compte_anticipation, 
                    a.etat, a.rang, r.nom as nom_reseau, r.id as id_reseau,
                    (SELECT COUNT(*) FROM bf_gerant bg WHERE bg.id_borne_fontaine = bf.id) as nb_gerants,
                    (SELECT COUNT(*) FROM bf_gerant bg WHERE bg.id_borne_fontaine = bf.id AND bg.est_actif = 1) as nb_gerants_actifs
             FROM borne_fontaine bf
             INNER JOIN abone a ON bf.id_abone = a.id
             INNER JOIN reseau r ON a.id_reseau = r.id
             $where
             ORDER BY bf.date_creation DESC",
            $params
        );
    }

    /**
     * Récupérer une BF par son ID avec toutes ses informations
     * @param int $id ID de la BF
     * @return array|false
     */
    public static function getBFById($id)
    {
        $bf = self::prepare_query(
            "SELECT bf.*, a.nom, a.numero_telephone, a.numero_compte_anticipation, 
                    a.etat, a.rang, r.nom as nom_reseau, r.id as id_reseau
             FROM borne_fontaine bf
             INNER JOIN abone a ON bf.id_abone = a.id
             INNER JOIN reseau r ON a.id_reseau = r.id
             WHERE bf.id = ?",
            array($id)
        )->fetch(PDO::FETCH_ASSOC);

        return $bf;
    }

    /**
     * Récupérer le gérant actif d'une BF
     * @param int $id_borne_fontaine ID de la BF
     * @return array|false
     */
    public static function getGerantActif($id_borne_fontaine)
    {
        return self::prepare_query(
            "SELECT * FROM bf_gerant 
             WHERE id_borne_fontaine = ? AND est_actif = 1 
             ORDER BY date_debut DESC 
             LIMIT 1",
            array($id_borne_fontaine)
        )->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer l'historique complet des gérants d'une BF
     * @param int $id_borne_fontaine ID de la BF
     * @return PDOStatement
     */
    public static function getHistoriqueGerants($id_borne_fontaine)
    {
        return self::prepare_query(
            "SELECT * FROM bf_gerant 
             WHERE id_borne_fontaine = ? 
             ORDER BY date_debut DESC, date_creation DESC",
            array($id_borne_fontaine)
        );
    }
}
