<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/aep.php');
@include_once('donnees/aep.php');

/**
 * Calcul des volumes et rendements réseau (RDS / RDC).
 */
class ReseauRendement
{
    /**
     * @param int $reseauId
     * @param int $aepId
     * @param string|null $moisDebut format YYYY-MM
     * @param string|null $moisFin format YYYY-MM
     * @return array
     */
    public static function calculer($reseauId, $aepId, $moisDebut = '', $moisFin = '')
    {
        $reseauId = (int) $reseauId;
        $aepId = (int) $aepId;

        $aep = Manager::prepare_query('SELECT type_distribution FROM aep WHERE id = ?', array($aepId))->fetch();
        $typeDist = $aep && isset($aep['type_distribution'])
            ? Aep::normaliserTypeDistribution($aep['type_distribution'])
            : null;
        $isRds = ($typeDist === 'RDS');
        $isRdc = ($typeDist === 'RDC');

        $enfantsDirects = self::getEnfantsDirects($reseauId, $aepId);
        $idsDescendants = self::getIdsDescendants($reseauId, $aepId);
        $idsDescendantsSansSelf = array();
        foreach ($idsDescendants as $id) {
            if ((int) $id !== $reseauId) {
                $idsDescendantsSansSelf[] = (int) $id;
            }
        }

        $moisList = self::collecterMois(
            $reseauId,
            $aepId,
            $idsDescendantsSansSelf,
            $enfantsDirects,
            $moisDebut,
            $moisFin,
            $isRds
        );

        $volProd = $isRds ? self::volumesCompteursReseauParType($reseauId, 'production', $moisDebut, $moisFin) : array();
        $volReservoir = $isRds ? self::volumesCompteursReseauParType($reseauId, 'reservoir', $moisDebut, $moisFin) : array();
        $volDist = self::volumesCompteursReseauParType($reseauId, 'distribution', $moisDebut, $moisFin);
        $volAbonnesDirect = self::volumesAbonnesReseaux(array($reseauId), $moisDebut, $moisFin, null);
        $volAbonnesBp = self::volumesAbonnesReseaux(array($reseauId), $moisDebut, $moisFin, 'BP');
        $volAbonnesBf = self::volumesAbonnesReseaux(array($reseauId), $moisDebut, $moisFin, 'BF');
        $volDistEnfantsDirect = self::volumesDistributionEnfantsDirects($reseauId, $aepId, $moisDebut, $moisFin);
        $volAbonnesDescendants = !empty($idsDescendantsSansSelf)
            ? self::volumesAbonnesReseaux($idsDescendantsSansSelf, $moisDebut, $moisFin, null)
            : array();

        $branchesLocales = array();
        foreach ($enfantsDirects as $enfant) {
            $eid = (int) $enfant['id'];
            $descEnfant = self::getIdsDescendants($eid, $aepId);
            $volDistFils = self::volumesCompteursReseauParType($eid, 'distribution', $moisDebut, $moisFin);
            $volAbonnesBranche = self::volumesAbonnesReseaux($descEnfant, $moisDebut, $moisFin, null);
            $volDistPetitsEnfants = self::volumesDistributionEnfantsDirects($eid, $aepId, $moisDebut, $moisFin);
            $branchesLocales[] = array(
                'id' => $eid,
                'nom' => $enfant['nom'],
                'vol_dist' => $volDistFils,
                'vol_abonnes_branche' => $volAbonnesBranche,
                'vol_dist_enfants' => $volDistPetitsEnfants,
            );
        }

        $lignes = array();
        $chartLabels = array();
        $chartSeries = array(
            'vol_production' => array(),
            'vol_reservoir' => array(),
            'vol_distribution' => array(),
            'vol_abonnes_direct' => array(),
            'vol_dist_enfants' => array(),
            'vol_abonnes_descendants' => array(),
            'pct_refoulement' => array(),
            'pct_adduction' => array(),
            'pct_global_enfants' => array(),
            'pct_global_tous_abonnes' => array(),
            'pct_net_direct' => array(),
        );

        foreach ($moisList as $mois) {
            $vProd = isset($volProd[$mois]) ? (float) $volProd[$mois] : 0.0;
            $vRes = isset($volReservoir[$mois]) ? (float) $volReservoir[$mois] : 0.0;
            $vDist = isset($volDist[$mois]) ? (float) $volDist[$mois] : 0.0;
            $vAbDir = isset($volAbonnesDirect[$mois]) ? (float) $volAbonnesDirect[$mois] : 0.0;
            $vAbBp = isset($volAbonnesBp[$mois]) ? (float) $volAbonnesBp[$mois] : 0.0;
            $vAbBf = isset($volAbonnesBf[$mois]) ? (float) $volAbonnesBf[$mois] : 0.0;
            $vDistEnf = isset($volDistEnfantsDirect[$mois]) ? (float) $volDistEnfantsDirect[$mois] : 0.0;
            $vAbDesc = isset($volAbonnesDescendants[$mois]) ? (float) $volAbonnesDescendants[$mois] : 0.0;

            $avalEnfantsDirect = $vDistEnf + $vAbDir;
            $avalTousAbonnes = $vAbDir + $vAbDesc;

            $pctRefoulement = $isRds ? self::pct($vRes, $vProd) : null;
            $pctAdduction = $isRds ? self::pct($vDist, $vProd) : null;
            $pctGlobalEnfants = self::pct($avalEnfantsDirect, $vDist);
            $pctGlobalTousAbonnes = self::pct($avalTousAbonnes, $vDist);
            $amontNet = $vDist - $vDistEnf;
            $pctNetDirect = self::pct($vAbDir, $amontNet);

            $branchesMois = array();
            foreach ($branchesLocales as $br) {
                $vDistBr = isset($br['vol_dist'][$mois]) ? (float) $br['vol_dist'][$mois] : 0.0;
                $vAbBr = isset($br['vol_abonnes_branche'][$mois]) ? (float) $br['vol_abonnes_branche'][$mois] : 0.0;
                $vDistEnfBr = isset($br['vol_dist_enfants'][$mois]) ? (float) $br['vol_dist_enfants'][$mois] : 0.0;
                $avalBr = $vAbBr + $vDistEnfBr;
                $branchesMois[] = array(
                    'id' => $br['id'],
                    'nom' => $br['nom'],
                    'vol_dist' => $vDistBr,
                    'vol_abonnes' => $vAbBr,
                    'vol_dist_enfants' => $vDistEnfBr,
                    'aval' => $avalBr,
                    'pct' => self::pct($avalBr, $vDistBr),
                );
            }

            $lignes[] = array(
                'mois' => $mois,
                'vol_production' => $vProd,
                'vol_reservoir' => $vRes,
                'vol_distribution' => $vDist,
                'vol_abonnes_direct' => $vAbDir,
                'vol_abonnes_bp' => $vAbBp,
                'vol_abonnes_bf' => $vAbBf,
                'vol_dist_enfants_direct' => $vDistEnf,
                'vol_abonnes_descendants' => $vAbDesc,
                'aval_enfants_direct' => $avalEnfantsDirect,
                'aval_tous_abonnes' => $avalTousAbonnes,
                'amont_net_direct' => $amontNet,
                'pct_refoulement' => $pctRefoulement,
                'pct_adduction' => $pctAdduction,
                'pct_global_enfants' => $pctGlobalEnfants,
                'pct_global_tous_abonnes' => $pctGlobalTousAbonnes,
                'pct_net_direct' => $pctNetDirect,
                'branches' => $branchesMois,
            );

            $chartLabels[] = $mois;
            $chartSeries['vol_production'][] = $vProd;
            $chartSeries['vol_reservoir'][] = $vRes;
            $chartSeries['vol_distribution'][] = $vDist;
            $chartSeries['vol_abonnes_direct'][] = $vAbDir;
            $chartSeries['vol_dist_enfants'][] = $vDistEnf;
            $chartSeries['vol_abonnes_descendants'][] = $vAbDesc;
            $chartSeries['pct_refoulement'][] = $pctRefoulement !== null ? $pctRefoulement : null;
            $chartSeries['pct_adduction'][] = $pctAdduction !== null ? $pctAdduction : null;
            $chartSeries['pct_global_enfants'][] = $pctGlobalEnfants;
            $chartSeries['pct_global_tous_abonnes'][] = $pctGlobalTousAbonnes;
            $chartSeries['pct_net_direct'][] = $pctNetDirect;
        }

        usort($lignes, function ($a, $b) {
            return strcmp($b['mois'], $a['mois']);
        });

        $hasProduction = false;
        foreach ($compteurs = self::listeTypesCompteurs($reseauId) as $tc) {
            if ($tc === 'production') {
                $hasProduction = true;
                break;
            }
        }

        return array(
            'type_distribution' => $typeDist,
            'is_rds' => $isRds,
            'is_rdc' => $isRdc,
            'has_compteur_production' => $hasProduction,
            'enfants_directs' => $enfantsDirects,
            'lignes' => $lignes,
            'chart_labels' => $chartLabels,
            'chart_series' => $chartSeries,
        );
    }

    private static function pct($aval, $amont)
    {
        $aval = (float) $aval;
        $amont = (float) $amont;
        if ($amont <= 0.00001) {
            return null;
        }
        return round(($aval / $amont) * 100, 2);
    }

    private static function listeTypesCompteurs($reseauId)
    {
        $rows = Manager::prepare_query(
            "SELECT DISTINCT type_compteur FROM compteur_reseau WHERE id_reseau = ?",
            array((int) $reseauId)
        )->fetchAll(PDO::FETCH_COLUMN);
        return $rows ? $rows : array();
    }

    /**
     * @return array<int,array{id:int,nom:string}>
     */
    private static function getEnfantsDirects($reseauId, $aepId)
    {
        return Manager::prepare_query(
            "SELECT id, nom FROM reseau WHERE id_reseau_parent = ? AND id_aep = ? ORDER BY nom ASC",
            array((int) $reseauId, (int) $aepId)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int>
     */
    private static function getIdsDescendants($reseauId, $aepId)
    {
        $ids = array((int) $reseauId);
        $queue = array((int) $reseauId);
        $seen = array((int) $reseauId => true);
        while (!empty($queue)) {
            $cur = array_shift($queue);
            $rows = Manager::prepare_query(
                "SELECT id FROM reseau WHERE id_reseau_parent = ? AND id_aep = ?",
                array($cur, (int) $aepId)
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $rid) {
                $rid = (int) $rid;
                if (!isset($seen[$rid])) {
                    $seen[$rid] = true;
                    $ids[] = $rid;
                    $queue[] = $rid;
                }
            }
        }
        return $ids;
    }

    private static function collecterMois($reseauId, $aepId, $idsDesc, $enfantsDirects, $moisDebut, $moisFin, $isRds)
    {
        $maps = array(
            self::volumesCompteursReseauParType($reseauId, 'distribution', $moisDebut, $moisFin),
            self::volumesAbonnesReseaux(array($reseauId), $moisDebut, $moisFin, null),
            self::volumesDistributionEnfantsDirects($reseauId, $aepId, $moisDebut, $moisFin),
        );
        if ($isRds) {
            $maps[] = self::volumesCompteursReseauParType($reseauId, 'production', $moisDebut, $moisFin);
            $maps[] = self::volumesCompteursReseauParType($reseauId, 'reservoir', $moisDebut, $moisFin);
        }
        if (!empty($idsDesc)) {
            $maps[] = self::volumesAbonnesReseaux($idsDesc, $moisDebut, $moisFin, null);
        }
        $all = array();
        foreach ($maps as $map) {
            foreach (array_keys($map) as $m) {
                $all[$m] = true;
            }
        }
        $mois = array_keys($all);
        sort($mois);
        return $mois;
    }

    /**
     * @return array<string,float> mois => volume m3
     */
    private static function volumesCompteursReseauParType($reseauId, $typeCompteur, $moisDebut, $moisFin)
    {
        $sql = "SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS vol
                FROM indexes i
                INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
                INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
                WHERE cr.id_reseau = ? AND cr.type_compteur = ?";
        $params = array((int) $reseauId, $typeCompteur);
        $sql .= self::clauseMois($moisDebut, $moisFin, $params);
        $sql .= ' GROUP BY mf.mois ORDER BY mf.mois';
        return self::mapMoisVolumes(Manager::prepare_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<int> $reseauIds
     * @param string|null $typeAbone 'BP'|'BF'|null tous
     * @return array<string,float>
     */
    private static function volumesAbonnesReseaux($reseauIds, $moisDebut, $moisFin, $typeAbone = null)
    {
        if (empty($reseauIds)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($reseauIds), '?'));
        $sql = "SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS vol
                FROM indexes i
                INNER JOIN compteur_abone ca ON ca.id_compteur = i.id_compteur
                INNER JOIN abone a ON a.id = ca.id_abone
                INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
                WHERE a.id_reseau IN ($placeholders)";
        $params = array_map('intval', $reseauIds);
        if ($typeAbone !== null && $typeAbone !== '') {
            $sql .= ' AND a.type_abone = ?';
            $params[] = $typeAbone;
        }
        $sql .= self::clauseMois($moisDebut, $moisFin, $params);
        $sql .= ' GROUP BY mf.mois ORDER BY mf.mois';
        return self::mapMoisVolumes(Manager::prepare_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Compteurs distribution des reseaux fils directs.
     * @return array<string,float>
     */
    private static function volumesDistributionEnfantsDirects($reseauId, $aepId, $moisDebut, $moisFin)
    {
        $sql = "SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS vol
                FROM indexes i
                INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
                INNER JOIN reseau rf ON rf.id = cr.id_reseau
                INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
                WHERE rf.id_reseau_parent = ? AND rf.id_aep = ? AND cr.type_compteur = 'distribution'";
        $params = array((int) $reseauId, (int) $aepId);
        $sql .= self::clauseMois($moisDebut, $moisFin, $params);
        $sql .= ' GROUP BY mf.mois ORDER BY mf.mois';
        return self::mapMoisVolumes(Manager::prepare_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function clauseMois($moisDebut, $moisFin, &$params)
    {
        $sql = '';
        if ($moisDebut !== '') {
            $sql .= ' AND mf.mois >= ?';
            $params[] = $moisDebut;
        }
        if ($moisFin !== '') {
            $sql .= ' AND mf.mois <= ?';
            $params[] = $moisFin;
        }
        return $sql;
    }

    private static function mapMoisVolumes($rows)
    {
        $map = array();
        foreach ($rows as $r) {
            $map[$r['mois']] = (float) $r['vol'];
        }
        return $map;
    }
}
