<?php

/**
 * Période de facturation + synthèse versements / estimatif (même règles que le tableau de bord AEP).
 */

@include_once(__DIR__ . '/manager.php');
@include_once(__DIR__ . '/redevance.php');

class RedevanceSynopsisHelper
{
    /**
     * @param int   $aepId
     * @param array $redevancesRows lignes redevance (id, libele, base_calcul, mois_debut, …)
     * @return array{
     *   mois_min: ?string,
     *   mois_max: ?string,
     *   periode_libelle: string,
     *   liste_mois_fact: array,
     *   annees_disponibles: array,
     *   periode_mode: string,
     *   annee_vue: ?int,
     *   nb_mois_glissant: int,
     *   mf_debut_id: int,
     *   mf_fin_id: int,
     *   recap: array,
     *   detail: array,
     *   taux_versement_pct: ?int
     * }
     */
    public static function compute($aepId, array $redevancesRows)
    {
        $aepId = (int) $aepId;
        $empty = array(
            'mois_min' => null,
            'mois_max' => null,
            'periode_libelle' => '',
            'liste_mois_fact' => array(),
            'annees_disponibles' => array(),
            'periode_mode' => '13',
            'annee_vue' => null,
            'nb_mois_glissant' => 12,
            'mf_debut_id' => isset($_GET['mf_debut']) ? (int) $_GET['mf_debut'] : 0,
            'mf_fin_id' => isset($_GET['mf_fin']) ? (int) $_GET['mf_fin'] : 0,
            'recap' => array(
                'vente_eau' => array('count' => 0, 'estimatif' => 0.0, 'verse' => 0.0, 'reste' => 0.0, 'taux_pct' => null),
                'branchements' => array('count' => 0, 'estimatif' => 0.0, 'verse' => 0.0, 'reste' => 0.0, 'taux_pct' => null),
            ),
            'detail' => array(),
            'taux_versement_pct' => null,
        );
        if ($aepId <= 0) {
            return $empty;
        }

        $nbMoisGlissant = 12;
        $periodeMode = '13';
        $anneeVue = null;
        if (isset($_GET['annee'])) {
            $rawAnnee = $_GET['annee'];
            if ($rawAnnee === 'tous') {
                $periodeMode = 'tous';
            } elseif ($rawAnnee === 'intervalle') {
                $periodeMode = 'intervalle';
            } elseif ($rawAnnee === 'm3') {
                $periodeMode = '12';
                $nbMoisGlissant = 4;
            } elseif ($rawAnnee === 'm6') {
                $periodeMode = '12';
                $nbMoisGlissant = 7;
            } elseif ($rawAnnee === '') {
                $periodeMode = '12';
                $nbMoisGlissant = 13;
            } elseif ($rawAnnee !== '') {
                $yy = (int) $rawAnnee;
                if ($yy >= 1990 && $yy <= 2100) {
                    $periodeMode = 'annee';
                    $anneeVue = $yy;
                }
            }
        }

        $moisMin = null;
        $moisMax = null;
        $periodeLibelle = '';
        $anneesDispo = array();
        $mfDebutId = isset($_GET['mf_debut']) ? (int) $_GET['mf_debut'] : 0;
        $mfFinId = isset($_GET['mf_fin']) ? (int) $_GET['mf_fin'] : 0;

        $yCourante = (int) date('Y');
        $yPremierMoisFact = $yCourante;
        $resMinM = Manager::prepare_query(
            "SELECT MIN(mf.mois) AS min_m FROM mois_facturation mf
             INNER JOIN constante_reseau c ON c.id = mf.id_constante
             WHERE c.id_aep = ? AND mf.est_mois_base = 0",
            array($aepId)
        );
        $rowMinM = $resMinM ? $resMinM->fetch() : null;
        if ($rowMinM && !empty($rowMinM['min_m'])) {
            $tsMinFact = strtotime($rowMinM['min_m']);
            if ($tsMinFact !== false) {
                $yPremierMoisFact = (int) date('Y', $tsMinFact);
            }
        }
        if ($yPremierMoisFact > $yCourante) {
            $yPremierMoisFact = $yCourante;
        }
        for ($y = $yCourante; $y >= $yPremierMoisFact; $y--) {
            $anneesDispo[] = $y;
        }

        $resMaxM = Manager::prepare_query(
            "SELECT MAX(mf.mois) AS max_m FROM mois_facturation mf
             INNER JOIN constante_reseau c ON c.id = mf.id_constante
             WHERE c.id_aep = ? AND mf.est_mois_base = 0",
            array($aepId)
        );
        $rowMaxM = $resMaxM ? $resMaxM->fetch() : null;
        $maxMoisFact = ($rowMaxM && !empty($rowMaxM['max_m'])) ? $rowMaxM['max_m'] : null;

        $resListeMf = Manager::prepare_query(
            "SELECT m.id, m.mois FROM mois_facturation m
             INNER JOIN constante_reseau c ON c.id = m.id_constante
             WHERE c.id_aep = ?
             ORDER BY m.mois ASC",
            array($aepId)
        );
        $listeMoisFact = $resListeMf ? $resListeMf->fetchAll(PDO::FETCH_ASSOC) : array();

        if ($periodeMode === 'annee' && $anneeVue !== null) {
            if (!in_array((int) $anneeVue, $anneesDispo, true)) {
                $periodeMode = '12';
                $anneeVue = null;
                $nbMoisGlissant = 12;
            }
        }

        if ($periodeMode === 'intervalle') {
            if (count($listeMoisFact) === 0) {
                $periodeMode = '12';
                $nbMoisGlissant = 12;
            } else {
                $firstId = (int) $listeMoisFact[0]['id'];
                $lastId = (int) $listeMoisFact[count($listeMoisFact) - 1]['id'];
                $debutId = $mfDebutId > 0 ? $mfDebutId : $firstId;
                $finId = $mfFinId > 0 ? $mfFinId : $lastId;
                $chk = Manager::prepare_query(
                    "SELECT m.id, m.mois FROM mois_facturation m
                     INNER JOIN constante_reseau c ON c.id = m.id_constante
                     WHERE c.id_aep = ? AND m.id IN (?, ?)",
                    array($aepId, $debutId, $finId)
                )->fetchAll(PDO::FETCH_ASSOC);
                $byId = array();
                foreach ($chk as $cr) {
                    $byId[(int) $cr['id']] = $cr['mois'];
                }
                if (!isset($byId[$debutId]) || !isset($byId[$finId])) {
                    $periodeMode = '12';
                    $nbMoisGlissant = 12;
                } else {
                    $moisA = $byId[$debutId];
                    $moisB = $byId[$finId];
                    $tsA = strtotime($moisA);
                    $tsB = strtotime($moisB);
                    if ($tsA === false || $tsB === false) {
                        $periodeMode = '12';
                        $nbMoisGlissant = 12;
                    } else {
                        if ($tsA > $tsB) {
                            $tmpId = $debutId;
                            $debutId = $finId;
                            $finId = $tmpId;
                            $tmpM = $moisA;
                            $moisA = $moisB;
                            $moisB = $tmpM;
                        }
                        $mfDebutId = $debutId;
                        $mfFinId = $finId;
                        $moisMin = date('Y-m-01', $tsA);
                        $moisMax = date('Y-m-t', $tsB);
                        $periodeLibelle = 'Du ' . getLetterMonth($moisA) . ' au ' . getLetterMonth($moisB);
                    }
                }
            }
        }

        if ($periodeMode === 'tous') {
            if ($rowMinM && !empty($rowMinM['min_m']) && $maxMoisFact) {
                $tsA = strtotime($rowMinM['min_m']);
                $tsB = strtotime($maxMoisFact);
                if ($tsA !== false && $tsB !== false) {
                    $moisMin = date('Y-m-01', $tsA);
                    $moisMax = date('Y-m-t', $tsB);
                    $periodeLibelle = 'Tous les mois de facturation';
                } else {
                    $periodeMode = '12';
                    $nbMoisGlissant = 12;
                }
            } else {
                $periodeMode = '12';
                $nbMoisGlissant = 12;
            }
        }

        if ($periodeMode === 'annee' && $anneeVue !== null) {
            $moisMin = $anneeVue . '-00-01';
            $moisMax = $anneeVue . '-12-31';
            $periodeLibelle = (string) (int) $anneeVue;
        } elseif ($periodeMode === '12') {
            $nM = max(1, (int) $nbMoisGlissant);
            $res12 = Manager::prepare_query(
                "SELECT MIN(t.mois) AS min_m, MAX(t.mois) AS max_m FROM (
                    SELECT mf.mois AS mois FROM mois_facturation mf
                    INNER JOIN constante_reseau c ON c.id = mf.id_constante
                    WHERE c.id_aep = ? AND mf.est_mois_base = 0
                    GROUP BY mf.mois
                    ORDER BY mf.mois DESC
                    LIMIT " . (int) $nM . "
                ) t",
                array($aepId)
            );
            $row12 = $res12 ? $res12->fetch() : null;
            if ($row12 && !empty($row12['min_m']) && !empty($row12['max_m'])) {
                $tsMin = strtotime($row12['min_m']);
                $tsMax = strtotime($row12['max_m']);
                if ($tsMin !== false && $tsMax !== false) {
                    $moisMin = date('Y-m-01', $tsMin);
                    $moisMax = date('Y-m-t', $tsMax);
                    $periodeLibelle = $nM . ' dernier' . ($nM > 1 ? 's' : '')
                        . ' mois de facturation (jusqu\'à ' . getLetterMonth($row12['max_m']) . ')';
                }
            }
        }

        if ($moisMin === null && $moisMax === null && $periodeMode === '13') {
            $periodeMode = '12';
            $nbMoisGlissant = 13;
            $nM = max(1, (int) $nbMoisGlissant);
            $res12 = Manager::prepare_query(
                "SELECT MIN(t.mois) AS min_m, MAX(t.mois) AS max_m FROM (
                    SELECT mf.mois AS mois FROM mois_facturation mf
                    INNER JOIN constante_reseau c ON c.id = mf.id_constante
                    WHERE c.id_aep = ? AND mf.est_mois_base = 0
                    GROUP BY mf.mois
                    ORDER BY mf.mois DESC
                    LIMIT " . (int) $nM . "
                ) t",
                array($aepId)
            );
            $row12 = $res12 ? $res12->fetch() : null;
            if ($row12 && !empty($row12['min_m']) && !empty($row12['max_m'])) {
                $tsMin = strtotime($row12['min_m']);
                $tsMax = strtotime($row12['max_m']);
                if ($tsMin !== false && $tsMax !== false) {
                    $moisMin = date('Y-m-01', $tsMin);
                    $moisMax = date('Y-m-t', $tsMax);
                    $periodeLibelle = $nM . ' dernier' . ($nM > 1 ? 's' : '')
                        . ' mois de facturation (jusqu\'à ' . getLetterMonth($row12['max_m']) . ')';
                }
            }
        }

        $recap = array(
            'vente_eau' => array('count' => 0, 'estimatif' => 0.0, 'verse' => 0.0, 'reste' => 0.0, 'taux_pct' => null),
            'branchements' => array('count' => 0, 'estimatif' => 0.0, 'verse' => 0.0, 'reste' => 0.0, 'taux_pct' => null),
        );
        $detail = array();
        $tauxVersementPct = null;

        if ($moisMin !== null && $moisMax !== null && class_exists('Redevance')) {
            $qIdsRng = Manager::prepare_query(
                "SELECT m.id FROM mois_facturation m
                 INNER JOIN constante_reseau c ON c.id = m.id_constante
                 WHERE c.id_aep = ? AND m.mois >= ? AND m.mois <= ?
                 ORDER BY m.mois ASC",
                array($aepId, $moisMin, $moisMax)
            );
            $moisIdsRng = $qIdsRng ? $qIdsRng->fetchAll(PDO::FETCH_COLUMN, 0) : array();
            $ymMin = substr((string) $moisMin, 0, 7);
            $ymMax = substr((string) $moisMax, 0, 7);
            $sumEstVe = 0.0;
            $sumVerseVe = 0.0;

            foreach ($redevancesRows as $rd) {
                $rid = (int) $rd['id'];
                $base = isset($rd['base_calcul']) ? trim((string) $rd['base_calcul']) : 'vente_eau';
                if ($base === '') {
                    $base = 'vente_eau';
                }
                $est = 0.0;
                if ($base === 'vente_eau') {
                    foreach ($moisIdsRng as $mid) {
                        $est += (float) Redevance::calculerMontantEstimatif($rid, (int) $mid);
                    }
                    $sumEstVe += $est;
                } else {
                    $mDebutRd = isset($rd['mois_debut']) && $rd['mois_debut'] !== '' && $rd['mois_debut'] !== null
                        ? (string) $rd['mois_debut'] : '1900-01';
                    $mDebutYm = strlen($mDebutRd) >= 7 ? substr($mDebutRd, 0, 7) : $mDebutRd;
                    $fromYm = strcmp($mDebutYm, $ymMin) > 0 ? $mDebutYm : $ymMin;
                    $rowBr = Manager::prepare_query(
                        "SELECT COALESCE(SUM(z.montant_estimatif), 0) AS s FROM (
                            SELECT COALESCE(
                                CASE WHEN re.type_calcul = 'montant_fixe'
                                    THEN COUNT(b.mois) * IFNULL(re.montant_par_m3, 0) END,
                                CASE WHEN re.type_calcul = 'pourcentage'
                                    THEN SUM(IFNULL(b.versement_fcfa, 0)) * IFNULL(re.pourcentage, 0) / 100 END,
                                0
                            ) AS montant_estimatif
                            FROM branchement_abonne b
                            INNER JOIN abone a ON b.id_abone = a.id
                            INNER JOIN reseau r ON a.id_reseau = r.id
                            INNER JOIN redevance re ON b.mois >= re.mois_debut AND re.id = ?
                            WHERE b.mois >= ? AND b.mois <= ? AND r.id_aep = ?
                            GROUP BY b.mois, re.type_calcul, re.montant_par_m3, re.pourcentage
                        ) z",
                        array($rid, $fromYm, $ymMax, $aepId)
                    )->fetch();
                    $est = $rowBr ? (float) $rowBr['s'] : 0.0;
                }
                $rv = Manager::prepare_query(
                    "SELECT COALESCE(SUM(v.montant), 0) AS s
                     FROM versements v
                     WHERE v.id_redevance = ?
                       AND DATE_FORMAT(v.date_versement, '%Y-%m') >= ?
                       AND DATE_FORMAT(v.date_versement, '%Y-%m') <= ?",
                    array($rid, $ymMin, $ymMax)
                )->fetch();
                $verse = $rv ? (float) $rv['s'] : 0.0;
                if ($base === 'vente_eau') {
                    $sumVerseVe += $verse;
                    $recap['vente_eau']['count']++;
                    $recap['vente_eau']['estimatif'] += $est;
                    $recap['vente_eau']['verse'] += $verse;
                } else {
                    $recap['branchements']['count']++;
                    $recap['branchements']['estimatif'] += $est;
                    $recap['branchements']['verse'] += $verse;
                }
                $detail[] = array(
                    'id' => $rid,
                    'libele' => isset($rd['libele']) ? $rd['libele'] : '',
                    'base_calcul' => $base,
                    'estimatif' => $est,
                    'verse' => $verse,
                    'reste' => max(0.0, $est - $verse),
                );
            }
            if ($sumEstVe > 0.0) {
                $tauxVersementPct = (int) round(($sumVerseVe * 100.0) / $sumEstVe);
            }
            $recap['vente_eau']['reste'] = max(0.0, $recap['vente_eau']['estimatif'] - $recap['vente_eau']['verse']);
            $recap['branchements']['reste'] = max(0.0, $recap['branchements']['estimatif'] - $recap['branchements']['verse']);
            if ($recap['vente_eau']['estimatif'] > 0.0) {
                $recap['vente_eau']['taux_pct'] = (int) round(
                    ($recap['vente_eau']['verse'] * 100.0) / $recap['vente_eau']['estimatif']
                );
            }
            if ($recap['branchements']['estimatif'] > 0.0) {
                $recap['branchements']['taux_pct'] = (int) round(
                    ($recap['branchements']['verse'] * 100.0) / $recap['branchements']['estimatif']
                );
            }
        }

        return array(
            'mois_min' => $moisMin,
            'mois_max' => $moisMax,
            'periode_libelle' => $periodeLibelle,
            'liste_mois_fact' => $listeMoisFact,
            'annees_disponibles' => $anneesDispo,
            'periode_mode' => $periodeMode,
            'annee_vue' => $anneeVue,
            'nb_mois_glissant' => $nbMoisGlissant,
            'mf_debut_id' => $mfDebutId,
            'mf_fin_id' => $mfFinId,
            'recap' => $recap,
            'detail' => $detail,
            'taux_versement_pct' => $tauxVersementPct,
        );
    }
}
