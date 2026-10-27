<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/facture.php');
@include_once('donnees/facture.php');
@include_once(__DIR__ . '/indexes.php');
@include_once('donnees/indexes.php');

/**
 * Complétion du mois de facturation actif.
 *
 * Un mois peut se retrouver incomplet : création interrompue en cours de route
 * (coupure de courant, PC éteint, WAMP arrêté — les anciennes versions ne
 * protégeaient pas la création par une vraie transaction), ou abonné créé
 * après l'ouverture du mois. Les abonnés concernés n'ont alors ni relevé ni
 * facture : ils n'apparaissent pas sur la page Relevés.
 *
 * Cette classe les repère et les ajoute au mois, exactement comme l'aurait
 * fait la création du mois : relevé initialisé à leur dernier index (ancien =
 * nouvel), facture sans versement. Elle crée aussi la facture d'un relevé
 * d'abonné resté sans facture (cas de l'abonné sur lequel la création s'est
 * interrompue). Les relevés déjà présents ne sont jamais modifiés.
 */
class MoisCompletion
{
    /**
     * Abonnés actifs dont le compteur n'a aucun relevé sur le mois.
     *
     * @return array<int,array<string,mixed>> id_abone, nom, reseau, id_compteur,
     *                                        numero_compteur, derniers_index
     */
    public static function getAbonesAbsents($idAep, $idMois)
    {
        $lignes = Manager::prepare_query(
            "SELECT a.id AS id_abone, a.nom, r.nom AS reseau, co.id AS id_compteur,
                    co.numero_compteur, co.derniers_index
               FROM abone a
               INNER JOIN reseau r ON r.id = a.id_reseau
               INNER JOIN compteur_abone ca ON ca.id_abone = a.id
               INNER JOIN compteur co ON co.id = ca.id_compteur
              WHERE r.id_aep = ? AND LOWER(TRIM(a.etat)) = 'actif'
                AND NOT EXISTS (
                    SELECT 1 FROM indexes i
                     WHERE i.id_compteur = co.id AND i.id_mois_facturation = ?
                )
              ORDER BY r.nom, a.nom",
            array((int) $idAep, (int) $idMois)
        )->fetchAll(PDO::FETCH_ASSOC);
        return $lignes ? $lignes : array();
    }

    /**
     * Relevés d'abonnés du mois qui n'ont pas de facture.
     *
     * @return array<int,array<string,mixed>> id_indexes, id_abone, nom
     */
    public static function getRelevesSansFacture($idAep, $idMois)
    {
        $lignes = Manager::prepare_query(
            "SELECT i.id AS id_indexes, a.id AS id_abone, a.nom
               FROM indexes i
               INNER JOIN compteur_abone ca ON ca.id_compteur = i.id_compteur
               INNER JOIN abone a ON a.id = ca.id_abone
               INNER JOIN reseau r ON r.id = a.id_reseau
               LEFT JOIN facture f ON f.id_indexes = i.id
              WHERE r.id_aep = ? AND i.id_mois_facturation = ? AND f.id IS NULL
              ORDER BY a.nom",
            array((int) $idAep, (int) $idMois)
        )->fetchAll(PDO::FETCH_ASSOC);
        return $lignes ? $lignes : array();
    }

    /**
     * Le mois appartient-il à l'AEP et est-il le mois actif ? Seul le mois
     * actif est complété : ajouter un relevé à un mois clos fausserait la
     * continuité des index.
     */
    public static function estMoisActifDeLAep($idAep, $idMois)
    {
        $row = Manager::prepare_query(
            "SELECT m.est_actif FROM mois_facturation m
               INNER JOIN constante_reseau c ON c.id = m.id_constante
              WHERE m.id = ? AND c.id_aep = ?",
            array((int) $idMois, (int) $idAep)
        )->fetch(PDO::FETCH_ASSOC);
        return $row && (int) $row['est_actif'] === 1;
    }

    /**
     * Ajoute au mois les abonnés absents et les factures manquantes, en une
     * seule transaction : en cas d'échec ou d'interruption, rien n'est écrit.
     *
     * @return array{abones:int, factures:int}|string résultat, ou message d'erreur
     */
    public static function completer($idAep, $idMois)
    {
        $idAep = (int) $idAep;
        $idMois = (int) $idMois;
        if (!self::estMoisActifDeLAep($idAep, $idMois)) {
            return "Seul le mois de facturation actif de l'AEP peut être complété.";
        }
        $absents = self::getAbonesAbsents($idAep, $idMois);
        $sansFacture = self::getRelevesSansFacture($idAep, $idMois);
        if (empty($absents) && empty($sansFacture)) {
            return array('abones' => 0, 'factures' => 0);
        }

        // La transaction doit porter sur la connexion de Manager, celle qui
        // écrit : Connexion::connect() en ouvre une nouvelle à chaque appel.
        $bd = Manager::getBdd();
        if ($bd === null) {
            Manager::prepare_query('SELECT 1', array());
            $bd = Manager::getBdd();
        }
        $bd->beginTransaction();
        try {
            foreach ($sansFacture as $ligne) {
                Manager::prepare_query(
                    "INSERT INTO facture (montant_verse, date_paiement, penalite, id_indexes, id_abone, message)
                     VALUES (0, '00/00/0000', 0, ?, ?, '')",
                    array((int) $ligne['id_indexes'], (int) $ligne['id_abone'])
                );
                Indexes::mettreAJourIdTarifDifferencie((int) $ligne['id_indexes'], (int) $ligne['id_abone']);
            }
            foreach ($absents as $ligne) {
                $index = (float) $ligne['derniers_index'];
                $facture = new Facture(0, $index, $index, 0, '00/00/0000', 0, $idMois, (int) $ligne['id_abone'], '', (int) $ligne['id_compteur']);
                if (!$facture->save_facture()) {
                    throw new Exception("Impossible d'ajouter l'abonné " . $ligne['nom'] . '.');
                }
            }
            $bd->commit();
        } catch (Exception $e) {
            $bd->rollBack();
            return 'Le mois n\'a pas été modifié : ' . $e->getMessage();
        }
        return array('abones' => count($absents), 'factures' => count($sansFacture));
    }
}
