<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');

/**
 * Désactivation des abonnés sans consommation.
 *
 * Avant de passer au mois de facturation suivant, l'application propose la
 * liste des abonnés actifs dont les N derniers mois facturés n'ont enregistré
 * aucune consommation (nouvel index égal à l'ancien). L'agent valide ceux à
 * désactiver : ils passent « non actif » et ne reçoivent plus de facture à
 * partir du mois créé.
 *
 * Le seuil N est un paramètre de l'AEP, modifiable depuis l'écran de création
 * du mois. Chaque désactivation est journalisée avec son motif.
 */
class InactiviteAbone
{
    /** Seuil appliqué tant que l'AEP n'en a pas fixé un autre. */
    const SEUIL_DEFAUT = 3;

    /** Clé du paramètre dans la table des paramètres d'AEP. */
    const CLE_SEUIL = 'mois_sans_consommation_desactivation';

    /** État donné aux abonnés désactivés (valeur déjà utilisée par l'application). */
    const ETAT_INACTIF = 'non actif';

    private static $tablesVerifiees = false;

    /**
     * Crée les tables à la volée, comme les autres modules récents : le dépôt
     * n'a pas d'outil de migration.
     */
    public static function ensureTables()
    {
        if (self::$tablesVerifiees) {
            return;
        }
        try {
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS parametre_aep (
                    id_aep INT(10) UNSIGNED NOT NULL,
                    cle VARCHAR(64) NOT NULL,
                    valeur VARCHAR(255) NOT NULL DEFAULT '',
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_aep, cle)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS abone_desactivation (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_aep INT(10) UNSIGNED NOT NULL,
                    id_abone INT(10) UNSIGNED NOT NULL,
                    nom_abone VARCHAR(128) NOT NULL DEFAULT '',
                    motif VARCHAR(64) NOT NULL DEFAULT '',
                    nb_mois INT(5) UNSIGNED NOT NULL DEFAULT 0,
                    etat_avant VARCHAR(16) DEFAULT NULL,
                    id_user INT(10) UNSIGNED DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_desactivation_abone (id_abone),
                    KEY idx_desactivation_aep (id_aep)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            self::$tablesVerifiees = true;
        } catch (Exception $e) {
            // Base en lecture seule : l'affichage ne doit pas casser.
        }
    }

    /**
     * Nombre de mois sans consommation à partir duquel un abonné est proposé.
     *
     * @return int
     */
    public static function getSeuil($idAep)
    {
        self::ensureTables();
        try {
            $row = Manager::prepare_query(
                "SELECT valeur FROM parametre_aep WHERE id_aep = ? AND cle = ?",
                array((int) $idAep, self::CLE_SEUIL)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $row = false;
        }
        $seuil = $row ? (int) $row['valeur'] : 0;
        return $seuil > 0 ? $seuil : self::SEUIL_DEFAUT;
    }

    public static function setSeuil($idAep, $seuil)
    {
        self::ensureTables();
        $seuil = max(1, min(120, (int) $seuil));
        Manager::prepare_query(
            "INSERT INTO parametre_aep (id_aep, cle, valeur) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)",
            array((int) $idAep, self::CLE_SEUIL, (string) $seuil)
        );
        return $seuil;
    }

    /**
     * Abonnés actifs dont les $seuil derniers mois facturés sont tous sans
     * consommation.
     *
     * Chaque ligne porte `nb_mois_sans_conso` (mois consécutifs sans
     * consommation, en partant du plus récent), `dernier_mois_conso` (dernier
     * mois où une consommation a été relevée, ou null) et `solde` (impayé net).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getAbonesSansConsommation($idAep, $seuil)
    {
        $idAep = (int) $idAep;
        $seuil = max(1, (int) $seuil);
        if ($idAep <= 0) {
            return array();
        }
        try {
            $abones = Manager::prepare_query(
                "SELECT a.id AS id_abone, a.nom, a.numero_telephone, r.nom AS nom_reseau,
                        (SELECT c.numero_compteur FROM compteur_abone ca
                          INNER JOIN compteur c ON c.id = ca.id_compteur
                          WHERE ca.id_abone = a.id ORDER BY c.id DESC LIMIT 1) AS numero_compteur
                   FROM abone a
                   INNER JOIN reseau r ON r.id = a.id_reseau
                  WHERE r.id_aep = ? AND LOWER(TRIM(a.etat)) = 'actif'
                  ORDER BY r.nom, a.nom",
                array($idAep)
            )->fetchAll(PDO::FETCH_ASSOC);
            // Historique de facturation des abonnés de l'AEP, du plus récent au
            // plus ancien, parcouru une seule fois.
            $factures = Manager::prepare_query(
                "SELECT id_abone, mois, COALESCE(consommation, 0) AS consommation,
                        COALESCE(montant_restant, 0) AS montant_restant
                   FROM vue_abones_facturation
                  WHERE id_aep = ?
                  ORDER BY id_abone, mois DESC",
                array($idAep)
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }

        $historique = array();
        foreach ($factures ? $factures : array() as $f) {
            $historique[(int) $f['id_abone']][] = $f;
        }

        $resultat = array();
        foreach ($abones ? $abones : array() as $a) {
            $idAbone = (int) $a['id_abone'];
            if (!isset($historique[$idAbone])) {
                continue;
            }
            $nbSans = 0;
            $dernierMoisConso = null;
            $solde = 0.0;
            foreach ($historique[$idAbone] as $f) {
                $solde += (float) $f['montant_restant'];
                if ($dernierMoisConso !== null) {
                    continue;
                }
                if ((float) $f['consommation'] > 0) {
                    $dernierMoisConso = $f['mois'];
                } else {
                    $nbSans++;
                }
            }
            if ($nbSans < $seuil) {
                continue;
            }
            $a['nb_mois_sans_conso'] = $nbSans;
            $a['dernier_mois_conso'] = $dernierMoisConso;
            $a['solde'] = max(0, round($solde, 2));
            $resultat[] = $a;
        }
        return $resultat;
    }

    /**
     * Désactive les abonnés demandés parmi ceux que le critère propose : un
     * identifiant absent de la liste calculée est ignoré, l'écran ne peut
     * désactiver que ce qu'il a affiché.
     *
     * @param int[] $idsAbones
     * @return int nombre d'abonnés désactivés
     */
    public static function desactiver($idAep, array $idsAbones, $seuil, $idUser)
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        $idsAbones = array_values(array_unique(array_filter(array_map('intval', $idsAbones))));
        if ($idAep <= 0 || empty($idsAbones)) {
            return 0;
        }
        $proposes = array();
        foreach (self::getAbonesSansConsommation($idAep, $seuil) as $a) {
            $proposes[(int) $a['id_abone']] = $a;
        }

        $nb = 0;
        foreach ($idsAbones as $idAbone) {
            if (!isset($proposes[$idAbone])) {
                continue;
            }
            $a = $proposes[$idAbone];
            $etat = Manager::prepare_query("SELECT etat FROM abone WHERE id = ?", array($idAbone))->fetch(PDO::FETCH_ASSOC);
            Manager::prepare_query("UPDATE abone SET etat = ? WHERE id = ?", array(self::ETAT_INACTIF, $idAbone));
            Manager::prepare_query(
                "INSERT INTO abone_desactivation (id_aep, id_abone, nom_abone, motif, nb_mois, etat_avant, id_user)
                 VALUES (?, ?, ?, 'sans_consommation', ?, ?, ?)",
                array(
                    $idAep,
                    $idAbone,
                    $a['nom'],
                    (int) $a['nb_mois_sans_conso'],
                    $etat ? $etat['etat'] : null,
                    (int) $idUser > 0 ? (int) $idUser : null,
                )
            );
            $nb++;
        }
        return $nb;
    }
}
