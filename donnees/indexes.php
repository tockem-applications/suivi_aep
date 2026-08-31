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

    public $id_tarif_differencie;

    function getDonnee()
    {
        $donnees = array(
            'id_compteur' => $this->id_compteur,
            'id_mois_facturation' => $this->id_mois_facturation,
            'ancien_index' => $this->ancien_index,
            'nouvel_index' => $this->nouvel_index,
            'message' => $this->message
        );
        // Ajouter id_tarif_differencie s'il est défini
        if (isset($this->id_tarif_differencie)) {
            $donnees['id_tarif_differencie'] = $this->id_tarif_differencie;
        }
        return $donnees;
    }

    /**
     * Calcule et retourne l'id du tarif différencié à utiliser pour cet index
     * en fonction de l'abonné, de la consommation et des tarifs disponibles
     * 
     * @param int $id_abone ID de l'abonné
     * @return int|null ID du tarif différencié ou NULL pour tarif de base
     */
    public static function calculerIdTarifDifferencie($id_indexes, $id_abone)
    {
        try {
            // Récupérer les informations de l'index et de l'abonné
            $query = "
                SELECT 
                    i.id,
                    i.ancien_index,
                    i.nouvel_index,
                    i.id_mois_facturation,
                    a.tarif_differencie_autorise,
                    mf.id_constante
                FROM indexes i
                INNER JOIN facture f ON f.id_indexes = i.id
                INNER JOIN abone a ON a.id = f.id_abone
                INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
                WHERE i.id = ? AND a.id = ?
            ";
            
            $result = self::prepare_query($query, array($id_indexes, $id_abone));
            $data = $result->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return null;
            }
            
            // Si tarif différencié non autorisé, retourner NULL (tarif de base)
            if (!$data['tarif_differencie_autorise']) {
                return null;
            }
            
            // Calculer la consommation
            $consommation = $data['nouvel_index'] - $data['ancien_index'];
            $id_constante_reseau = $data['id_constante'];
            
            // Chercher le tarif différencié qui correspond à cette consommation
            // Prendre celui avec le min_consommation le plus élevé (le plus spécifique)
            $queryTarif = "
                SELECT td.id
                FROM tarif_differencie td
                WHERE td.id_constante_reseau = ?
                AND ? >= td.min_consommation
                AND (td.max_consommation IS NULL OR ? < td.max_consommation)
                ORDER BY td.min_consommation DESC
                LIMIT 1
            ";
            
            $resultTarif = self::prepare_query($queryTarif, array(
                $id_constante_reseau,
                $consommation,
                $consommation
            ));
            
            $tarif = $resultTarif->fetch(PDO::FETCH_ASSOC);
            
            return $tarif ? (int)$tarif['id'] : null;
            
        } catch (Exception $e) {
            // En cas d'erreur, retourner NULL (tarif de base)
            return null;
        }
    }

    /**
     * Met à jour id_tarif_differencie pour un index donné
     */
    public static function mettreAJourIdTarifDifferencie($id_indexes, $id_abone)
    {
        $id_tarif_differencie = self::calculerIdTarifDifferencie($id_indexes, $id_abone);
        
        if ($id_tarif_differencie === null) {
            // Mettre à jour avec NULL
            self::prepare_query(
                "UPDATE indexes SET id_tarif_differencie = NULL WHERE id = ?",
                array($id_indexes)
            );
        } else {
            // Mettre à jour avec l'ID du tarif différencié
            self::prepare_query(
                "UPDATE indexes SET id_tarif_differencie = ? WHERE id = ?",
                array($id_tarif_differencie, $id_indexes)
            );
        }
        
        return $id_tarif_differencie;
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
