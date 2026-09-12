<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/connexion.php');
@include_once('donnees/connexion.php');

/**
 * Remplacement du compteur d'un abonné.
 *
 * Un abonné est rattaché à un seul compteur (compteur_abone) ; ses index et
 * factures restent liés à l'abonné, quel que soit le compteur relevé. Changer
 * de compteur consiste donc à :
 *  - clore l'ancien compteur sur son index de dépose : son dernier index est
 *    figé et, si le mois de facturation en cours a déjà un relevé ouvert pour
 *    lui, ce relevé reçoit l'index de dépose — la consommation faite sur
 *    l'ancien compteur ce mois-ci est ainsi facturée ;
 *  - créer le nouveau compteur avec son index de départ et le rattacher à
 *    l'abonné ; le mois suivant sera ouvert sur cet index de départ, la
 *    consommation du nouveau compteur est facturée à partir de là ;
 *  - conserver la trace du remplacement (ancien et nouveau compteur, index,
 *    date, motif, auteur).
 */
class CompteurRemplacement
{
    private static $tablesVerifiees = false;

    /** Créée à la volée, comme pour les autres modules récents. */
    public static function ensureTables()
    {
        if (self::$tablesVerifiees) {
            return;
        }
        try {
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS compteur_remplacement (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_abone INT(10) UNSIGNED NOT NULL,
                    id_ancien_compteur INT(10) UNSIGNED NOT NULL,
                    id_nouveau_compteur INT(10) UNSIGNED NOT NULL,
                    ancien_numero VARCHAR(16) NOT NULL DEFAULT '',
                    nouveau_numero VARCHAR(16) NOT NULL DEFAULT '',
                    index_depose DECIMAL(10,2) NOT NULL DEFAULT 0,
                    index_initial DECIMAL(10,2) NOT NULL DEFAULT 0,
                    date_remplacement DATE NOT NULL,
                    motif VARCHAR(255) NOT NULL DEFAULT '',
                    id_mois_facturation INT(10) UNSIGNED DEFAULT NULL,
                    id_user INT(10) UNSIGNED DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_remplacement_abone (id_abone)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            self::$tablesVerifiees = true;
        } catch (Exception $e) {
            // Base en lecture seule : l'affichage ne doit pas casser.
        }
    }

    /**
     * Compteur actuel de l'abonné et relevé du mois de facturation en cours.
     *
     * @return array<string,mixed>|null id_compteur, numero_compteur, derniers_index,
     *                                  latitude, longitude, id_aep, mois courant (id_index,
     *                                  ancien_index, nouvel_index, mois) ou null
     */
    public static function getSituation($idAbone)
    {
        $idAbone = (int) $idAbone;
        if ($idAbone <= 0) {
            return null;
        }
        $row = Manager::prepare_query(
            "SELECT a.id AS id_abone, a.nom, r.id_aep, c.id AS id_compteur, c.numero_compteur,
                    c.derniers_index, c.latitude, c.longitude
               FROM abone a
               INNER JOIN reseau r ON r.id = a.id_reseau
               INNER JOIN compteur_abone ca ON ca.id_abone = a.id
               INNER JOIN compteur c ON c.id = ca.id_compteur
              WHERE a.id = ?
              ORDER BY c.id DESC LIMIT 1",
            array($idAbone)
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        // Relevé du mois actif pour ce compteur : c'est lui qui reçoit l'index de dépose.
        $releve = Manager::prepare_query(
            "SELECT i.id AS id_index, i.ancien_index, i.nouvel_index, mf.id AS id_mois, mf.mois
               FROM indexes i
               INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
               INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
              WHERE i.id_compteur = ? AND cr.id_aep = ? AND mf.est_actif = 1
              ORDER BY mf.mois DESC LIMIT 1",
            array((int) $row['id_compteur'], (int) $row['id_aep'])
        )->fetch(PDO::FETCH_ASSOC);
        $row['releve_courant'] = $releve ? $releve : null;
        // L'index de dépose ne peut pas être inférieur à ce qui a déjà été relevé.
        $row['index_minimum'] = $releve
            ? max((float) $releve['ancien_index'], (float) $releve['nouvel_index'])
            : (float) $row['derniers_index'];
        return $row;
    }

    /**
     * Remplace le compteur de l'abonné.
     *
     * @param array<string,mixed> $donnees numero_compteur, index_initial, index_depose,
     *                                     date_remplacement, latitude, longitude, motif
     * @param int                 $idUser
     * @return array<string,mixed>|string informations sur le remplacement, ou message d'erreur
     */
    public static function remplacer($idAbone, array $donnees, $idUser)
    {
        self::ensureTables();
        $situation = self::getSituation($idAbone);
        if ($situation === null) {
            return "Abonné ou compteur introuvable.";
        }

        $numero = isset($donnees['numero_compteur']) ? trim((string) $donnees['numero_compteur']) : '';
        if ($numero === '') {
            return 'Le numéro du nouveau compteur est obligatoire.';
        }
        if (function_exists('mb_strlen') ? mb_strlen($numero, 'UTF-8') > 16 : strlen($numero) > 16) {
            return 'Le numéro du compteur ne doit pas dépasser 16 caractères.';
        }
        if (strcasecmp($numero, (string) $situation['numero_compteur']) === 0) {
            return "Le nouveau numéro est identique à celui du compteur actuel.";
        }

        $indexInitial = isset($donnees['index_initial']) ? self::nombre($donnees['index_initial']) : 0.0;
        $indexDepose = isset($donnees['index_depose']) ? self::nombre($donnees['index_depose']) : null;
        if ($indexInitial === null || $indexInitial < 0) {
            return "L'index de départ du nouveau compteur est invalide.";
        }
        if ($indexDepose === null || $indexDepose < 0) {
            return "L'index de dépose de l'ancien compteur est invalide.";
        }
        if ($indexDepose < (float) $situation['index_minimum'] - 0.005) {
            return "L'index de dépose (" . self::formatIndex($indexDepose) . ") ne peut pas être inférieur au dernier index relevé ("
                . self::formatIndex($situation['index_minimum']) . ').';
        }

        $date = isset($donnees['date_remplacement']) ? trim((string) $donnees['date_remplacement']) : '';
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $date = date('Y-m-d');
        }

        $latitude = isset($donnees['latitude']) && trim((string) $donnees['latitude']) !== '' ? self::nombre($donnees['latitude']) : null;
        $longitude = isset($donnees['longitude']) && trim((string) $donnees['longitude']) !== '' ? self::nombre($donnees['longitude']) : null;
        if ($latitude === null || $longitude === null) {
            // Par défaut, le nouveau compteur est posé au même endroit.
            $latitude = $situation['latitude'];
            $longitude = $situation['longitude'];
        }
        $motif = isset($donnees['motif']) ? trim((string) $donnees['motif']) : '';
        if (function_exists('mb_substr')) {
            $motif = mb_substr($motif, 0, 255, 'UTF-8');
        } else {
            $motif = substr($motif, 0, 255);
        }

        // Un même numéro ne doit pas servir à deux abonnés de l'AEP.
        $doublon = Manager::prepare_query(
            "SELECT a.nom FROM compteur c
               INNER JOIN compteur_abone ca ON ca.id_compteur = c.id
               INNER JOIN abone a ON a.id = ca.id_abone
               INNER JOIN reseau r ON r.id = a.id_reseau
              WHERE r.id_aep = ? AND c.numero_compteur = ? AND a.id <> ?
              LIMIT 1",
            array((int) $situation['id_aep'], $numero, (int) $idAbone)
        )->fetch(PDO::FETCH_ASSOC);
        if ($doublon) {
            return 'Le compteur n° ' . $numero . ' est déjà rattaché à l\'abonné ' . $doublon['nom'] . '.';
        }

        $bd = self::connexion();
        $bd->beginTransaction();
        try {
            // 1. L'ancien compteur est clos sur son index de dépose.
            Manager::prepare_query(
                "UPDATE compteur SET derniers_index = ?,
                        description = CONCAT(COALESCE(description, ''), ?)
                  WHERE id = ?",
                array(
                    $indexDepose,
                    "\nDéposé le " . $d->format('d/m/Y') . ' (index ' . self::formatIndex($indexDepose) . '), remplacé par le n° ' . $numero,
                    (int) $situation['id_compteur'],
                )
            );
            $releve = $situation['releve_courant'];
            if ($releve !== null) {
                Manager::prepare_query(
                    "UPDATE indexes SET nouvel_index = ?,
                            message = CONCAT(COALESCE(message, ''), ?)
                      WHERE id = ?",
                    array(
                        $indexDepose,
                        ' Compteur ' . $situation['numero_compteur'] . ' déposé le ' . $d->format('d/m/Y')
                            . ' à l\'index ' . self::formatIndex($indexDepose) . ', remplacé par le n° ' . $numero
                            . ' (index de départ ' . self::formatIndex($indexInitial) . ').',
                        (int) $releve['id_index'],
                    )
                );
            }

            // 2. Le nouveau compteur, rattaché à l'abonné à la place de l'ancien.
            Manager::prepare_query(
                "INSERT INTO compteur (numero_compteur, longitude, latitude, derniers_index, description)
                 VALUES (?, ?, ?, ?, ?)",
                array(
                    $numero,
                    $longitude,
                    $latitude,
                    $indexInitial,
                    'Posé le ' . $d->format('d/m/Y') . ' en remplacement du n° ' . $situation['numero_compteur']
                        . ($motif !== '' ? ' — ' . $motif : ''),
                )
            );
            $idNouveau = (int) $bd->lastInsertId();
            if ($idNouveau <= 0) {
                throw new Exception('Le nouveau compteur n\'a pas pu être créé.');
            }
            Manager::prepare_query(
                "UPDATE compteur_abone SET id_compteur = ? WHERE id_abone = ? AND id_compteur = ?",
                array($idNouveau, (int) $idAbone, (int) $situation['id_compteur'])
            );

            // 3. Trace du remplacement.
            Manager::prepare_query(
                "INSERT INTO compteur_remplacement
                    (id_abone, id_ancien_compteur, id_nouveau_compteur, ancien_numero, nouveau_numero,
                     index_depose, index_initial, date_remplacement, motif, id_mois_facturation, id_user)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array(
                    (int) $idAbone,
                    (int) $situation['id_compteur'],
                    $idNouveau,
                    $situation['numero_compteur'],
                    $numero,
                    $indexDepose,
                    $indexInitial,
                    $date,
                    $motif,
                    $releve !== null ? (int) $releve['id_mois'] : null,
                    (int) $idUser > 0 ? (int) $idUser : null,
                )
            );
            $bd->commit();
        } catch (Exception $e) {
            $bd->rollBack();
            return 'Le remplacement a échoué : ' . $e->getMessage();
        }

        return array(
            'ancien_numero' => $situation['numero_compteur'],
            'nouveau_numero' => $numero,
            'id_nouveau_compteur' => $idNouveau,
            'index_depose' => $indexDepose,
            'index_initial' => $indexInitial,
            'releve_mis_a_jour' => $releve !== null,
            'mois' => $releve !== null ? $releve['mois'] : null,
        );
    }

    /**
     * Remplacements successifs du compteur de l'abonné, le plus récent d'abord.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getHistorique($idAbone)
    {
        self::ensureTables();
        try {
            $lignes = Manager::prepare_query(
                "SELECT cr.*, u.nom AS user_nom, u.prenom AS user_prenom
                   FROM compteur_remplacement cr
                   LEFT JOIN users u ON u.id = cr.id_user
                  WHERE cr.id_abone = ?
                  ORDER BY cr.date_remplacement DESC, cr.id DESC",
                array((int) $idAbone)
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
        return $lignes ? $lignes : array();
    }

    /**
     * Connexion utilisée par Manager::prepare_query(), pour que la transaction
     * couvre bien les requêtes : Connexion::connect() ouvre une connexion
     * neuve à chaque appel.
     *
     * @return PDO
     */
    private static function connexion()
    {
        $bd = Manager::getBdd();
        if ($bd === null) {
            Manager::prepare_query('SELECT 1', array());
            $bd = Manager::getBdd();
        }
        return $bd;
    }

    /** Nombre saisi avec point ou virgule, ou null s'il est illisible. */
    private static function nombre($valeur)
    {
        $valeur = str_replace(array(' ', ','), array('', '.'), trim((string) $valeur));
        if ($valeur === '' || !is_numeric($valeur)) {
            return null;
        }
        return (float) $valeur;
    }

    public static function formatIndex($valeur)
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, ',', ' '), '0'), ',');
    }
}
