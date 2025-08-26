<?php
@include_once '../donnees/manager.php';
@include_once 'donnees/manager.php';
const DB_HOST = 'localhost';
const DB_USER = 'root'; // Remplacez par votre utilisateur MySQL
const DB_PASS = ''; // Remplacez par votre mot de passe MySQL
const DB_NAME = 'suivi_aep_fokoue';

function getDbConnection() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8';
        $conn = new PDO($dsn, DB_USER, DB_PASS, array(PDO::ATTR_PERSISTENT=>true));
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
}

class AepModel {
    private $conn;

    public function __construct() {
        $this->conn = getDbConnection();
    }

    // Récupérer les informations de l'AEP
    public function getAepInfo($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM aep WHERE id = :aepId");
            $stmt->execute(array(':aepId' => $aepId));
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des informations de l'AEP : " . $e->getMessage());
        }
    }

    // Récupérer les réseaux associés
    public function getReseaux($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT id, nom FROM reseau WHERE id_aep = :aepId");
            $stmt->execute(array(':aepId' => $aepId));
            $reseaux = array();
            while ($row = $stmt->fetch()) {
                $reseaux[] = $row;
            }
            return $reseaux;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des réseaux : " . $e->getMessage());
        }
    }

    // Récupérer le nombre total d'abonnés
    public function getAbonesCount($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT a.id) as count
                FROM abone a
                JOIN reseau r ON a.id_reseau = r.id
                WHERE r.id_aep = :aepId");
            $stmt->execute(array(':aepId' => $aepId));
            $row = $stmt->fetch();
            return $row['count'];
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération du nombre d'abonnés : " . $e->getMessage());
        }
    }

    // Récupérer le total des factures
    public function getFactureTotal($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT SUM(f.montant_verse) as total
                FROM facture f
                JOIN abone a ON f.id_abone = a.id
                JOIN reseau r ON a.id_reseau = r.id
                WHERE r.id_aep = :aepId");
            $stmt->execute(array(':aepId' => $aepId));
            $row = $stmt->fetch();
            $total = $row['total'];
            return is_null($total) ? 0 : $total;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération du total des factures : " . $e->getMessage());
        }
    }

    // Récupérer le total des impayés
    public function getImpayeTotal($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT SUM(i.montant) as total
                FROM impaye i
                JOIN facture f ON i.id_facture = f.id
                JOIN abone a ON f.id_abone = a.id
                JOIN reseau r ON a.id_reseau = r.id
                WHERE r.id_aep = :aepId AND i.est_regle = 0");
            $stmt->execute(array(':aepId' => $aepId));
            $row = $stmt->fetch();
            $total = $row['total'];
            return is_null($total) ? 0 : $total;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération du total des impayés : " . $e->getMessage());
        }
    }

    // Récupérer les flux financiers (entrées et sorties)
    public function getFluxFinanciers($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT type, SUM(prix) as total
                FROM flux_financier
                WHERE id_aep = :aepId
                GROUP BY type");
            $stmt->execute(array(':aepId' => $aepId));
            $flux = array('entrees' => 0, 'sorties' => 0);
            while ($row = $stmt->fetch()) {
                if ($row['type'] === 'entree') {
                    $flux['entrees'] = $row['total'];
                } else {
                    $flux['sorties'] = $row['total'];
                }
            }
            return $flux;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des flux financiers : " . $e->getMessage());
        }
    }

    // Récupérer les redevances
    public function getRedevances($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT libele, pourcentage, type, mois_debut
                FROM redevance
                WHERE id_aep = :aepId");
            $stmt->execute(array(':aepId' => $aepId));
            $redevances = array();
            while ($row = $stmt->fetch()) {
                $redevances[] = $row;
            }
            return $redevances;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des redevances : " . $e->getMessage());
        }
    }

    // Récupérer l'historique des index (simplifié pour le graphique)
    public function getIndexHistory($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT SUM(i.nouvel_index - i.ancien_index) as value, m.mois as date
                FROM indexes i
                JOIN mois_facturation m ON i.id_mois_facturation = m.id
                JOIN compteur c ON i.id_compteur = c.id
                JOIN compteur_abone ca ON c.id = ca.id_compteur
                JOIN abone a ON ca.id_abone = a.id
                JOIN reseau r ON a.id_reseau = r.id
                WHERE r.id_aep = :aepId
                GROUP BY m.id
                ORDER BY m.mois");
            $stmt->execute(array(':aepId' => $aepId));
            $indexHistory = array();
            while ($row = $stmt->fetch()) {
                $indexHistory[] = $row;
            }
            return $indexHistory;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération de l'historique des index : " . $e->getMessage());
        }
    }

    // Récupérer les factures récentes (limitées à 5)
    public function getRecentFactures($aepId, $mois = null) {
        try {
            $query = "SELECT a.nom as abone_nom, f.montant_verse, f.date_paiement
                FROM facture f
                JOIN abone a ON f.id_abone = a.id
                JOIN reseau r ON a.id_reseau = r.id
                JOIN indexes i ON f.id_indexes = i.id
                JOIN mois_facturation m ON i.id_mois_facturation = m.id
                WHERE r.id_aep = :aepId";
            if ($mois && $mois !== 'all') {
                $query .= " AND m.mois = :mois";
                $stmt = $this->conn->prepare($query);
                $stmt->execute(array(':aepId' => $aepId, ':mois' => $mois));
            } else {
                $query .= " AND m.est_actif = 1";
                $stmt = $this->conn->prepare($query);
                $stmt->execute(array(':aepId' => $aepId));
            }
            $factures = array();
            while ($row = $stmt->fetch()) {
                $factures[] = $row;
            }
            return $factures;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des factures récentes : " . $e->getMessage());
        }
    }

    // Récupérer les impayés
    public function getImpayes($aepId) {
        try {
            $stmt = $this->conn->prepare("SELECT i.id_facture, i.montant, i.date_reglement
                FROM impaye i
                JOIN facture f ON i.id_facture = f.id
                JOIN abone a ON f.id_abone = a.id
                JOIN reseau r ON a.id_reseau = r.id
                WHERE r.id_aep = :aepId AND i.est_regle = 0");
            $stmt->execute(array(':aepId' => $aepId));
            $impayes = array();
            while ($row = $stmt->fetch()) {
                $impayes[] = $row;
            }
            return $impayes;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des impayés : " . $e->getMessage());
        }
    }


    // Nouvelle méthode : Récupérer les montants facturés et recouvrés par mois
    public function getMontantsParMois($aepId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    m.mois as date,
                    SUM((i.nouvel_index - i.ancien_index)*(1 + cr.prix_tva/100) * cr.prix_metre_cube_eau + cr.prix_entretient_compteur) as montant_facture,
                    SUM(f.montant_verse) as montant_recouvre
                FROM facture f
                JOIN abone a ON f.id_abone = a.id
                JOIN reseau r ON a.id_reseau = r.id
                JOIN indexes i ON f.id_indexes = i.id
                JOIN mois_facturation m ON i.id_mois_facturation = m.id
                INNER JOIN constante_reseau cr ON m.id_constante = cr.id
                WHERE r.id_aep = :aepId
                GROUP BY m.id
                ORDER BY m.mois
            ");
            $stmt->execute(array(':aepId' => $aepId));
            $montants = array();
            while ($row = $stmt->fetch()) {
                $montants[] = $row;
            }
            return $montants;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des montants par mois : " . $e->getMessage());
        }
    }

    public function __destruct() {
        $this->conn = null; // PDO ferme automatiquement la connexion à la destruction
    }
}