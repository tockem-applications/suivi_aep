<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/connexion.php');
@include_once('donnees/connexion.php');
@include_once(__DIR__ . '/categorie_flux_manuel.php');
@include_once('donnees/categorie_flux_manuel.php');

/**
 * Sélection, éditable par mois, des charges (catégories de flux manuel + redevances)
 * prises en compte dans le calcul du « coût du service » (page analyse_financiere).
 *
 * Règles :
 * - Un mois sans sélection explicite hérite de la sélection du mois configuré le plus
 *   proche avant lui (report), et cette sélection reportée est aussitôt persistée pour
 *   ce mois : il devient alors indépendant (l'éditer n'affecte plus aucun autre mois).
 * - Si aucun mois n'a jamais été configuré pour l'AEP, le mois hérite du comportement
 *   historique fixe (catégories « activité vente d'eau » + redevances « base vente d'eau »).
 */
class CoutServiceCharge
{
    const TYPE_CATEGORIE = 'categorie';
    const TYPE_REDEVANCE = 'redevance';
    const TYPE_SANS_CATEGORIE = 'sans_categorie';
    const ID_SANS_CATEGORIE = 0;

    public static function ensureTable()
    {
        try {
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS cout_service_charge_mois (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_aep INT(10) UNSIGNED NOT NULL,
                    mois VARCHAR(7) NOT NULL,
                    type_element VARCHAR(20) NOT NULL,
                    id_element INT(10) UNSIGNED NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_charge (id_aep, mois, type_element, id_element),
                    KEY idx_aep_mois (id_aep, mois)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
                array()
            );
        } catch (Exception $e) {
        }
    }

    /**
     * Sélection historique fixe (comportement v1 avant l'introduction de ce module) :
     * catégories de charge avec activite_associee = 'vente_eau' + redevances avec
     * base_calcul = 'vente_eau' (ou vide/NULL) + les sorties sans catégorie.
     *
     * @return array{categories:int[], redevances:int[], sans_categorie:bool}
     */
    public static function legacyDefaultSelection($id_aep)
    {
        $id_aep = (int) $id_aep;
        $categories = Manager::prepare_query(
            "SELECT id FROM categorie_flux_manuel
             WHERE type_flux = 'charge' AND activite_associee = 'vente_eau'
             AND (id_aep = ? OR id_aep IS NULL)",
            array($id_aep)
        )->fetchAll(PDO::FETCH_COLUMN);

        $redevances = Manager::prepare_query(
            "SELECT id FROM redevance
             WHERE id_aep = ? AND (base_calcul = 'vente_eau' OR base_calcul IS NULL OR base_calcul = '')",
            array($id_aep)
        )->fetchAll(PDO::FETCH_COLUMN);

        return array(
            'categories' => array_map('intval', $categories ? $categories : array()),
            'redevances' => array_map('intval', $redevances ? $redevances : array()),
            'sans_categorie' => true,
        );
    }

    /**
     * Sélection explicitement enregistrée pour ce mois exact (null si aucune ligne).
     *
     * @return array{categories:int[], redevances:int[], sans_categorie:bool}|null
     */
    public static function getExplicit($id_aep, $mois)
    {
        self::ensureTable();
        $rows = Manager::prepare_query(
            "SELECT type_element, id_element FROM cout_service_charge_mois WHERE id_aep = ? AND mois = ?",
            array((int) $id_aep, $mois)
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return null;
        }
        $out = array('categories' => array(), 'redevances' => array(), 'sans_categorie' => false);
        foreach ($rows as $r) {
            if ($r['type_element'] === self::TYPE_CATEGORIE) {
                $out['categories'][] = (int) $r['id_element'];
            } elseif ($r['type_element'] === self::TYPE_REDEVANCE) {
                $out['redevances'][] = (int) $r['id_element'];
            } elseif ($r['type_element'] === self::TYPE_SANS_CATEGORIE) {
                $out['sans_categorie'] = true;
            }
        }
        return $out;
    }

    /**
     * Mois explicitement configuré le plus récent, strictement avant $mois.
     */
    private static function getMoisPrecedentConfigure($id_aep, $mois)
    {
        self::ensureTable();
        $row = Manager::prepare_query(
            "SELECT mois FROM cout_service_charge_mois WHERE id_aep = ? AND mois < ? ORDER BY mois DESC LIMIT 1",
            array((int) $id_aep, $mois)
        )->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['mois'] : null;
    }

    /**
     * Enregistre (remplace) la sélection explicite d'un mois donné.
     */
    public static function saveSelection($id_aep, $mois, array $categories, array $redevances, $sans_categorie)
    {
        self::ensureTable();
        $id_aep = (int) $id_aep;
        Manager::prepare_query(
            "DELETE FROM cout_service_charge_mois WHERE id_aep = ? AND mois = ?",
            array($id_aep, $mois)
        );
        $insert = "INSERT INTO cout_service_charge_mois (id_aep, mois, type_element, id_element) VALUES (?, ?, ?, ?)";
        foreach ($categories as $cid) {
            Manager::prepare_query($insert, array($id_aep, $mois, self::TYPE_CATEGORIE, (int) $cid));
        }
        foreach ($redevances as $rid) {
            Manager::prepare_query($insert, array($id_aep, $mois, self::TYPE_REDEVANCE, (int) $rid));
        }
        if ($sans_categorie) {
            Manager::prepare_query($insert, array($id_aep, $mois, self::TYPE_SANS_CATEGORIE, self::ID_SANS_CATEGORIE));
        }
    }

    /**
     * Sélection effective pour un mois : explicite si elle existe, sinon reportée depuis
     * le mois configuré précédent le plus proche (et persistée), sinon comportement legacy
     * (persisté également, pour que ce premier mois devienne à son tour un point de report).
     *
     * @return array{categories:int[], redevances:int[], sans_categorie:bool, herite_de:string|null}
     */
    public static function getSelectionEffective($id_aep, $mois)
    {
        self::ensureTable();
        $explicit = self::getExplicit($id_aep, $mois);
        if ($explicit !== null) {
            $explicit['herite_de'] = null;
            return $explicit;
        }

        $moisPrecedent = self::getMoisPrecedentConfigure($id_aep, $mois);
        $selection = ($moisPrecedent !== null)
            ? self::getExplicit($id_aep, $moisPrecedent)
            : self::legacyDefaultSelection($id_aep);

        self::saveSelection($id_aep, $mois, $selection['categories'], $selection['redevances'], $selection['sans_categorie']);
        $selection['herite_de'] = $moisPrecedent;
        return $selection;
    }

    /**
     * Total des charges (FCFA) d'un mois selon une sélection donnée (réelle ou simulée).
     */
    public static function calculerTotalDepenses($id_aep, $mois, array $selection)
    {
        $id_aep = (int) $id_aep;
        $total = 0.0;

        if (!empty($selection['categories'])) {
            $placeholders = implode(',', array_fill(0, count($selection['categories']), '?'));
            $params = array_merge(array($id_aep), $selection['categories'], array($mois, $mois));
            $row = Manager::prepare_query(
                "SELECT COALESCE(SUM(ff.prix), 0) AS total
                 FROM flux_financier ff
                 WHERE ff.id_aep = ? AND ff.type = 'sortie'
                 AND ff.id_categorie_flux_manuel IN ($placeholders)
                 AND (
                     (ff.mois IS NOT NULL AND ff.mois != '' AND ff.mois = ?) OR
                     ((ff.mois IS NULL OR ff.mois = '') AND DATE_FORMAT(ff.date, '%Y-%m') = ?)
                 )",
                $params
            )->fetch(PDO::FETCH_ASSOC);
            $total += $row ? (float) $row['total'] : 0.0;
        }

        if (!empty($selection['sans_categorie'])) {
            $row = Manager::prepare_query(
                "SELECT COALESCE(SUM(ff.prix), 0) AS total
                 FROM flux_financier ff
                 WHERE ff.id_aep = ? AND ff.type = 'sortie'
                 AND ff.id_categorie_flux_manuel IS NULL
                 AND (
                     (ff.mois IS NOT NULL AND ff.mois != '' AND ff.mois = ?) OR
                     ((ff.mois IS NULL OR ff.mois = '') AND DATE_FORMAT(ff.date, '%Y-%m') = ?)
                 )",
                array($id_aep, $mois, $mois)
            )->fetch(PDO::FETCH_ASSOC);
            $total += $row ? (float) $row['total'] : 0.0;
        }

        if (!empty($selection['redevances'])) {
            $placeholders = implode(',', array_fill(0, count($selection['redevances']), '?'));
            $params = array_merge($selection['redevances'], array($mois, $mois));
            $row = Manager::prepare_query(
                "SELECT COALESCE(SUM(v.montant), 0) AS total
                 FROM versements v
                 INNER JOIN redevance r ON v.id_redevance = r.id
                 LEFT JOIN mois_facturation mf ON v.id_mois_facturation = mf.id
                 WHERE v.id_redevance IN ($placeholders)
                 AND (
                     (mf.mois IS NOT NULL AND mf.mois = ?) OR
                     (mf.mois IS NULL AND DATE_FORMAT(v.date_versement, '%Y-%m') = ?)
                 )",
                $params
            )->fetch(PDO::FETCH_ASSOC);
            $total += $row ? (float) $row['total'] : 0.0;
        }

        return $total;
    }

    /**
     * Détail ligne par ligne (catégories + redevances) des charges d'un mois, pour affichage.
     *
     * @return array<int,array{type:string,nom:string,code_budgetaire:string,montant:float}>
     */
    public static function calculerDetailDepenses($id_aep, $mois, array $selection)
    {
        $id_aep = (int) $id_aep;
        $details = array();

        if (!empty($selection['categories'])) {
            $placeholders = implode(',', array_fill(0, count($selection['categories']), '?'));
            $params = array_merge(array($id_aep), $selection['categories'], array($mois, $mois));
            $rows = Manager::prepare_query(
                "SELECT cfm.id, COALESCE(cfm.code_budgetaire, '') AS code_budgetaire,
                        COALESCE(cfm.nom, 'Sans catégorie') AS nom_categorie,
                        COALESCE(SUM(ff.prix), 0) AS montant
                 FROM flux_financier ff
                 INNER JOIN categorie_flux_manuel cfm ON ff.id_categorie_flux_manuel = cfm.id
                 WHERE ff.id_aep = ? AND ff.type = 'sortie'
                 AND cfm.id IN ($placeholders)
                 AND (
                     (ff.mois IS NOT NULL AND ff.mois != '' AND ff.mois = ?) OR
                     ((ff.mois IS NULL OR ff.mois = '') AND DATE_FORMAT(ff.date, '%Y-%m') = ?)
                 )
                 GROUP BY cfm.id, cfm.code_budgetaire, cfm.nom
                 HAVING montant > 0
                 ORDER BY montant DESC",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $details[] = array(
                    'type' => 'categorie',
                    'code_budgetaire' => $row['code_budgetaire'],
                    'nom' => $row['nom_categorie'],
                    'montant' => (float) $row['montant'],
                );
            }
        }

        if (!empty($selection['sans_categorie'])) {
            $row = Manager::prepare_query(
                "SELECT COALESCE(SUM(ff.prix), 0) AS montant
                 FROM flux_financier ff
                 WHERE ff.id_aep = ? AND ff.type = 'sortie'
                 AND ff.id_categorie_flux_manuel IS NULL
                 AND (
                     (ff.mois IS NOT NULL AND ff.mois != '' AND ff.mois = ?) OR
                     ((ff.mois IS NULL OR ff.mois = '') AND DATE_FORMAT(ff.date, '%Y-%m') = ?)
                 )",
                array($id_aep, $mois, $mois)
            )->fetch(PDO::FETCH_ASSOC);
            $montant = $row ? (float) $row['montant'] : 0.0;
            if ($montant > 0) {
                $details[] = array(
                    'type' => 'categorie',
                    'code_budgetaire' => '',
                    'nom' => 'Sans catégorie',
                    'montant' => $montant,
                );
            }
        }

        if (!empty($selection['redevances'])) {
            $placeholders = implode(',', array_fill(0, count($selection['redevances']), '?'));
            $params = array_merge($selection['redevances'], array($mois, $mois));
            $rows = Manager::prepare_query(
                "SELECT r.id, COALESCE(r.libele, 'Redevance') AS libelle, COALESCE(SUM(v.montant), 0) AS montant
                 FROM versements v
                 INNER JOIN redevance r ON v.id_redevance = r.id
                 LEFT JOIN mois_facturation mf ON v.id_mois_facturation = mf.id
                 WHERE r.id IN ($placeholders)
                 AND (
                     (mf.mois IS NOT NULL AND mf.mois = ?) OR
                     (mf.mois IS NULL AND DATE_FORMAT(v.date_versement, '%Y-%m') = ?)
                 )
                 GROUP BY r.id, r.libele
                 HAVING montant > 0
                 ORDER BY montant DESC",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $details[] = array(
                    'type' => 'redevance',
                    'code_budgetaire' => '',
                    'nom' => 'Redevance : ' . $row['libelle'],
                    'montant' => (float) $row['montant'],
                );
            }
        }

        return $details;
    }

    /**
     * Consommation totale (m³) et prix moyen du m³ pour un mois (indépendant de la
     * sélection de charges — mêmes règles que l'ancien calcul de analyse_financiere_page).
     *
     * @return array{total_conso:float, prix_moyen_m3:float}
     */
    public static function calculerConsommationEtPrixMoyen($id_aep, $mois)
    {
        $id_aep = (int) $id_aep;

        $query_prix_moyen = "
            SELECT
                i.nouvel_index - i.ancien_index AS conso,
                COALESCE(td.prix_metre_cube_eau, cr.prix_metre_cube_eau) AS prix_m3
            FROM indexes i
            INNER JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
            INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
            LEFT JOIN tarif_differencie td ON i.id_tarif_differencie = td.id
            WHERE cr.id_aep = ?
            AND mf.mois = ?
            AND mf.est_mois_base = 0
            AND (i.nouvel_index - i.ancien_index) > 0
        ";
        $rows = Manager::prepare_query($query_prix_moyen, array($id_aep, $mois))->fetchAll(PDO::FETCH_ASSOC);

        $somme_prix_conso = 0.0;
        $somme_conso = 0.0;
        foreach ($rows as $row) {
            $conso_i = (float) $row['conso'];
            $prix_m3_i = (float) $row['prix_m3'];
            $somme_prix_conso += $prix_m3_i * $conso_i;
            $somme_conso += $conso_i;
        }

        $prix_moyen = 0.0;
        if ($somme_conso > 0) {
            $prix_moyen = $somme_prix_conso / $somme_conso;
        } else {
            $row_prix_standard = Manager::prepare_query(
                "SELECT prix_metre_cube_eau
                 FROM constante_reseau cr
                 INNER JOIN mois_facturation mf ON mf.id_constante = cr.id
                 WHERE cr.id_aep = ? AND mf.mois = ?
                 LIMIT 1",
                array($id_aep, $mois)
            )->fetch(PDO::FETCH_ASSOC);
            $prix_moyen = $row_prix_standard ? (float) $row_prix_standard['prix_metre_cube_eau'] : 0.0;
        }

        $row_conso = Manager::prepare_query(
            "SELECT SUM(i.nouvel_index - i.ancien_index) AS total_conso
             FROM indexes i
             INNER JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
             WHERE cr.id_aep = ? AND mf.mois = ? AND mf.est_mois_base = 0",
            array($id_aep, $mois)
        )->fetch(PDO::FETCH_ASSOC);
        $total_conso = $row_conso ? (float) $row_conso['total_conso'] : 0.0;

        return array('total_conso' => $total_conso, 'prix_moyen_m3' => $prix_moyen);
    }

    /**
     * Calcul complet d'un mois (dépenses selon $selection + consommation + coût/m³),
     * utilisé aussi bien pour l'affichage réel que pour une simulation.
     *
     * @return array{total_depenses:float, total_conso:float, cout_par_m3:float, prix_moyen_m3:float}
     */
    public static function calculerCoutMois($id_aep, $mois, array $selection)
    {
        $total_depenses = self::calculerTotalDepenses($id_aep, $mois, $selection);
        $conso = self::calculerConsommationEtPrixMoyen($id_aep, $mois);
        $cout_par_m3 = $conso['total_conso'] > 0 ? ($total_depenses / $conso['total_conso']) : 0.0;

        return array(
            'total_depenses' => $total_depenses,
            'total_conso' => $conso['total_conso'],
            'cout_par_m3' => $cout_par_m3,
            'prix_moyen_m3' => $conso['prix_moyen_m3'],
        );
    }

    /**
     * Liste des mois de facturation (hors mois de base) d'un AEP dans une période.
     *
     * @return string[]
     */
    public static function getMoisListPeriode($id_aep, $mois_debut, $mois_fin)
    {
        $rows = Manager::prepare_query(
            "SELECT DISTINCT mf.mois
             FROM mois_facturation mf
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
             WHERE cr.id_aep = ? AND mf.mois >= ? AND mf.mois <= ? AND mf.est_mois_base = 0
             ORDER BY mf.mois ASC",
            array((int) $id_aep, $mois_debut, $mois_fin)
        )->fetchAll(PDO::FETCH_COLUMN);
        return $rows ? $rows : array();
    }

    /**
     * Catégories de charge disponibles pour l'AEP (globales + spécifiques), pour l'écran d'édition.
     *
     * @return array<int,array>
     */
    public static function getCategoriesChargeDisponibles($id_aep)
    {
        $id_aep = (int) $id_aep;
        return Manager::prepare_query(
            "SELECT id, nom, code_budgetaire, est_actif, activite_associee
             FROM categorie_flux_manuel
             WHERE type_flux = 'charge' AND (id_aep = ? OR id_aep IS NULL)
             ORDER BY est_actif DESC, activite_associee ASC, nom ASC",
            array($id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Redevances disponibles pour l'AEP, pour l'écran d'édition.
     *
     * @return array<int,array>
     */
    public static function getRedevancesDisponibles($id_aep)
    {
        return Manager::prepare_query(
            "SELECT id, libele FROM redevance WHERE id_aep = ? ORDER BY libele ASC",
            array((int) $id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
