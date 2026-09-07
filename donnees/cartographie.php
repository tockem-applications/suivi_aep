<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/connexion.php');
@include_once('donnees/connexion.php');
// Requis par affecterCoordonnees() : appelee depuis traitement/, ou le chemin
// relatif "donnees/compteur.php" ne resout pas.
@include_once(__DIR__ . '/compteur.php');
@include_once('donnees/compteur.php');

/**
 * Cartographie hors ligne : ouvrages ponctuels, conduites (tracés) et métadonnées
 * des jeux de tuiles importés, par AEP. Voir aussi Compteur::updateCoordonnees()
 * pour la position GPS des compteurs abonnés (remontée par l'appli mobile).
 */
class Cartographie
{
    /**
     * Types d'ouvrage autorisés. Méthode et non constante : PHP 5.3 n'accepte
     * pas les tableaux dans les constantes de classe.
     *
     * @return string[]
     */
    public static function typesOuvrage()
    {
        return array('captage', 'reservoir', 'vanne', 'compteur_reseau', 'borne_fontaine', 'autre');
    }

    public static function ensureTables()
    {
        try {
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS carto_ouvrage (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_aep INT(10) UNSIGNED NOT NULL,
                    id_reseau INT(10) UNSIGNED DEFAULT NULL,
                    type VARCHAR(32) NOT NULL DEFAULT 'autre',
                    nom VARCHAR(128) NOT NULL,
                    latitude DECIMAL(10,6) NOT NULL,
                    longitude DECIMAL(10,6) NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_carto_ouvrage_aep (id_aep)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS carto_conduite (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_aep INT(10) UNSIGNED NOT NULL,
                    id_reseau INT(10) UNSIGNED DEFAULT NULL,
                    nom VARCHAR(128) DEFAULT NULL,
                    trace_json LONGTEXT NOT NULL,
                    longueur_m DECIMAL(12,2) NOT NULL DEFAULT 0,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_carto_conduite_aep (id_aep)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS carto_secteur (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_aep INT(10) UNSIGNED NOT NULL,
                    nom VARCHAR(128) NOT NULL,
                    polygone_json LONGTEXT NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_carto_secteur_aep (id_aep)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS carto_tuiles_meta (
                    id_aep INT(10) UNSIGNED NOT NULL,
                    zoom_min INT(3) DEFAULT NULL,
                    zoom_max INT(3) DEFAULT NULL,
                    nb_tuiles INT(10) NOT NULL DEFAULT 0,
                    poids_octets BIGINT(20) NOT NULL DEFAULT 0,
                    date_import TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_aep)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
        } catch (Exception $e) {
        }
    }

    // ---------------------------------------------------------------
    // Compteurs abonnés géolocalisés (position captée par l'appli mobile)
    // ---------------------------------------------------------------

    /**
     * @return array<int,array>
     */
    public static function getCompteursGeolocalises($id_aep)
    {
        $id_aep = (int) $id_aep;
        return Manager::prepare_query(
            "SELECT a.id AS id_abone, a.nom AS nom_abone, a.etat, a.numero_telephone,
                    co.id AS id_compteur, co.numero_compteur, co.latitude, co.longitude, co.derniers_index,
                    r.id AS id_reseau, r.nom AS nom_reseau
             FROM compteur co
             INNER JOIN compteur_abone ca ON ca.id_compteur = co.id
             INNER JOIN abone a ON a.id = ca.id_abone
             INNER JOIN reseau r ON r.id = a.id_reseau
             WHERE r.id_aep = ?
               AND co.latitude IS NOT NULL AND co.longitude IS NOT NULL
               AND NOT (co.latitude = 0 AND co.longitude = 0)
             ORDER BY a.nom ASC",
            array($id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques de couverture GPS pour l'AEP (pour le panneau d'anomalies).
     *
     * @return array{total:int, geolocalises:int, manquants:int}
     */
    public static function getStatsGeoloc($id_aep)
    {
        $id_aep = (int) $id_aep;
        $row = Manager::prepare_query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN co.latitude IS NOT NULL AND co.longitude IS NOT NULL
                          AND NOT (co.latitude = 0 AND co.longitude = 0) THEN 1 ELSE 0 END) AS geolocalises
             FROM compteur co
             INNER JOIN compteur_abone ca ON ca.id_compteur = co.id
             INNER JOIN abone a ON a.id = ca.id_abone
             INNER JOIN reseau r ON r.id = a.id_reseau
             WHERE r.id_aep = ?",
            array($id_aep)
        )->fetch(PDO::FETCH_ASSOC);
        $total = $row ? (int) $row['total'] : 0;
        $geolocalises = $row ? (int) $row['geolocalises'] : 0;
        return array('total' => $total, 'geolocalises' => $geolocalises, 'manquants' => $total - $geolocalises);
    }

    // ---------------------------------------------------------------
    // Indicateurs métier par compteur (cartes thématiques)
    // ---------------------------------------------------------------

    /**
     * Mois de facturation de l'AEP, du plus récent au plus ancien.
     *
     * @return array<int,array{mois:string, est_actif:int}>
     */
    public static function getMoisDisponibles($id_aep)
    {
        return Manager::prepare_query(
            "SELECT DISTINCT mf.mois, mf.est_actif
             FROM mois_facturation mf
             INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
             WHERE cr.id_aep = ? AND mf.est_mois_base = 0
             ORDER BY mf.mois DESC",
            array((int) $id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getMoisActif($id_aep)
    {
        $mois = self::getMoisDisponibles($id_aep);
        foreach ($mois as $m) {
            if (!empty($m['est_actif'])) {
                return $m['mois'];
            }
        }
        return isset($mois[0]['mois']) ? $mois[0]['mois'] : null;
    }

    /**
     * Compteurs géolocalisés enrichis des indicateurs du mois demandé
     * (consommation, impayé du mois, impayé cumulé, versements, pénalité,
     * ancienneté du dernier relevé) pour alimenter les cartes thématiques.
     *
     * @return array<int,array>
     */
    public static function getCompteursAvecIndicateurs($id_aep, $mois)
    {
        $id_aep = (int) $id_aep;
        $rows = Manager::prepare_query(
            "SELECT co.id AS id_compteur, co.numero_compteur, co.latitude, co.longitude, co.derniers_index,
                    a.id AS id_abone, a.nom AS nom_abone, a.etat,
                    r.id AS id_reseau, r.nom AS nom_reseau,
                    v.consommation, v.montant_total, v.montant_verse, v.impaye, v.penalite, v.date_releve,
                    cum.impaye_cumule, dern.dernier_mois_releve
             FROM compteur co
             INNER JOIN compteur_abone ca ON ca.id_compteur = co.id
             INNER JOIN abone a ON a.id = ca.id_abone
             INNER JOIN reseau r ON r.id = a.id_reseau
             LEFT JOIN vue_abones_facturation v ON v.id_compteur = co.id AND v.mois = ?
             LEFT JOIN (
                 SELECT id_compteur, SUM(impaye) AS impaye_cumule
                 FROM vue_abones_facturation WHERE id_aep = ? GROUP BY id_compteur
             ) cum ON cum.id_compteur = co.id
             LEFT JOIN (
                 SELECT id_compteur, MAX(mois) AS dernier_mois_releve
                 FROM vue_abones_facturation
                 WHERE id_aep = ? AND nouvel_index > ancien_index GROUP BY id_compteur
             ) dern ON dern.id_compteur = co.id
             WHERE r.id_aep = ?
               AND co.latitude IS NOT NULL AND co.longitude IS NOT NULL
               AND NOT (co.latitude = 0 AND co.longitude = 0)
             ORDER BY a.nom ASC",
            array($mois, $id_aep, $id_aep, $id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $facture = (float) $row['montant_total'];
            $verse = (float) $row['montant_verse'];
            $row['taux_recouvrement'] = $facture > 0 ? round($verse * 100.0 / $facture, 1) : null;
            $row['mois_sans_releve'] = self::ecartEnMois($row['dernier_mois_releve'], $mois);
        }
        unset($row);

        return $rows;
    }

    /**
     * Nombre de mois entre deux clés 'YYYY-MM' (null si le premier est absent).
     */
    private static function ecartEnMois($moisDebut, $moisFin)
    {
        if (empty($moisDebut) || empty($moisFin)) {
            return null;
        }
        $d = explode('-', $moisDebut);
        $f = explode('-', $moisFin);
        if (count($d) < 2 || count($f) < 2) {
            return null;
        }
        return ((int) $f[0] - (int) $d[0]) * 12 + ((int) $f[1] - (int) $d[1]);
    }

    // ---------------------------------------------------------------
    // Affectation manuelle de coordonnées (import CSV)
    // ---------------------------------------------------------------

    /**
     * Coordonnées plausibles : ni vides, ni (0,0), et dans une emprise large
     * couvrant le Cameroun. Les bornes sont volontairement distinctes par axe :
     * cela permet de détecter une inversion latitude/longitude, cas d'erreur le
     * plus fréquent lors d'un import CSV.
     */
    public static function coordonneesPlausibles($latitude, $longitude)
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return false;
        }
        $lat = (float) $latitude;
        $lon = (float) $longitude;
        if ($lat == 0.0 && $lon == 0.0) {
            return false;
        }
        return $lat >= 1.0 && $lat <= 14.0 && $lon >= 7.0 && $lon <= 17.0;
    }

    /**
     * Abonnés d'un réseau avec leur compteur et leur état de géolocalisation,
     * pour l'écran d'affectation des coordonnées.
     *
     * @return array<int,array>
     */
    public static function getAbonnesReseau($id_aep, $id_reseau)
    {
        return Manager::prepare_query(
            "SELECT a.id AS id_abone, a.nom AS nom_abone, a.etat,
                    co.id AS id_compteur, co.numero_compteur, co.latitude, co.longitude,
                    r.nom AS nom_reseau
             FROM abone a
             INNER JOIN reseau r ON r.id = a.id_reseau
             INNER JOIN compteur_abone ca ON ca.id_abone = a.id
             INNER JOIN compteur co ON co.id = ca.id_compteur
             WHERE r.id_aep = ? AND r.id = ?
             ORDER BY a.nom ASC",
            array((int) $id_aep, (int) $id_reseau)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Affecte des coordonnées à des compteurs. Chaque affectation est un tableau
     * {id_compteur, latitude, longitude}. Seuls les compteurs appartenant bien à
     * l'AEP courant sont modifiés — un identifiant venu du client ne suffit pas.
     *
     * @return array{affectes:int, ignores:array<int,string>}
     */
    public static function affecterCoordonnees($id_aep, array $affectations)
    {
        $id_aep = (int) $id_aep;
        $affectes = 0;
        $ignores = array();

        foreach ($affectations as $a) {
            $idCompteur = isset($a['id_compteur']) ? (int) $a['id_compteur'] : 0;
            $lat = isset($a['latitude']) ? $a['latitude'] : null;
            $lon = isset($a['longitude']) ? $a['longitude'] : null;

            if ($idCompteur <= 0) {
                $ignores[] = 'Compteur inconnu.';
                continue;
            }
            if (!self::coordonneesPlausibles($lat, $lon)) {
                $ignores[] = 'Compteur ' . $idCompteur . ' : coordonnées hors zone plausible ('
                    . $lat . ', ' . $lon . ') — vérifier l\'ordre latitude/longitude.';
                continue;
            }
            if (!self::compteurAppartientAep($idCompteur, $id_aep)) {
                $ignores[] = 'Compteur ' . $idCompteur . ' : n\'appartient pas à cet AEP.';
                continue;
            }

            Compteur::updateCoordonnees($idCompteur, $lat, $lon);
            $affectes++;
        }

        return array('affectes' => $affectes, 'ignores' => $ignores);
    }

    /**
     * Le compteur est-il rattaché à un abonné de cet AEP ?
     */
    private static function compteurAppartientAep($id_compteur, $id_aep)
    {
        $row = Manager::prepare_query(
            "SELECT COUNT(*) AS n
             FROM compteur co
             INNER JOIN compteur_abone ca ON ca.id_compteur = co.id
             INNER JOIN abone a ON a.id = ca.id_abone
             INNER JOIN reseau r ON r.id = a.id_reseau
             WHERE co.id = ? AND r.id_aep = ?",
            array((int) $id_compteur, (int) $id_aep)
        )->fetch(PDO::FETCH_ASSOC);
        return $row && (int) $row['n'] > 0;
    }

    // ---------------------------------------------------------------
    // Qualité des données (anomalies)
    // ---------------------------------------------------------------

    /**
     * Anomalies de géolocalisation : compteurs sans position, positions
     * aberrantes, doublons de coordonnées, rattachement réseau incohérent.
     *
     * @return array<string,array>
     */
    public static function getAnomalies($id_aep)
    {
        $id_aep = (int) $id_aep;

        $sansGps = Manager::prepare_query(
            "SELECT co.id AS id_compteur, co.numero_compteur, a.id AS id_abone, a.nom AS nom_abone, r.nom AS nom_reseau
             FROM compteur co
             INNER JOIN compteur_abone ca ON ca.id_compteur = co.id
             INNER JOIN abone a ON a.id = ca.id_abone
             INNER JOIN reseau r ON r.id = a.id_reseau
             WHERE r.id_aep = ?
               AND (co.latitude IS NULL OR co.longitude IS NULL OR (co.latitude = 0 AND co.longitude = 0))
             ORDER BY r.nom ASC, a.nom ASC",
            array($id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);

        $geo = self::getCompteursGeolocalises($id_aep);

        // Barycentre global et par réseau
        $sommeLat = 0.0; $sommeLon = 0.0; $n = 0;
        $parReseau = array();
        foreach ($geo as $g) {
            $lat = (float) $g['latitude'];
            $lon = (float) $g['longitude'];
            $sommeLat += $lat; $sommeLon += $lon; $n++;
            $idr = (int) $g['id_reseau'];
            if (!isset($parReseau[$idr])) {
                $parReseau[$idr] = array('lat' => 0.0, 'lon' => 0.0, 'n' => 0, 'nom' => $g['nom_reseau']);
            }
            $parReseau[$idr]['lat'] += $lat;
            $parReseau[$idr]['lon'] += $lon;
            $parReseau[$idr]['n']++;
        }
        $barycentres = array();
        foreach ($parReseau as $idr => $p) {
            if ($p['n'] >= 3) { // en dessous, un barycentre n'a pas de sens
                $barycentres[$idr] = array('lat' => $p['lat'] / $p['n'], 'lon' => $p['lon'] / $p['n'], 'nom' => $p['nom']);
            }
        }

        $aberrants = array();
        $doublonsIndex = array();
        $rattachements = array();

        if ($n > 0) {
            $baryLat = $sommeLat / $n;
            $baryLon = $sommeLon / $n;
            foreach ($geo as $g) {
                $lat = (float) $g['latitude'];
                $lon = (float) $g['longitude'];

                // Trop loin du centre de gravité de l'AEP (> 15 km)
                $distance = self::haversine($baryLat, $baryLon, $lat, $lon);
                if ($distance > 15000) {
                    $g['distance_m'] = round($distance);
                    $aberrants[] = $g;
                }

                // Doublons de position (arrondi à ~10 cm)
                $cle = round($lat, 6) . ',' . round($lon, 6);
                if (!isset($doublonsIndex[$cle])) {
                    $doublonsIndex[$cle] = array();
                }
                $doublonsIndex[$cle][] = $g;

                // Rattachement incohérent : plus proche du barycentre d'un autre réseau
                $idr = (int) $g['id_reseau'];
                if (isset($barycentres[$idr])) {
                    $dSien = self::haversine($barycentres[$idr]['lat'], $barycentres[$idr]['lon'], $lat, $lon);
                    foreach ($barycentres as $autreId => $b) {
                        if ($autreId === $idr) {
                            continue;
                        }
                        $dAutre = self::haversine($b['lat'], $b['lon'], $lat, $lon);
                        // Nettement plus proche de l'autre réseau (marge de 2x)
                        if ($dAutre * 2 < $dSien) {
                            $g['reseau_suggere'] = $b['nom'];
                            $g['distance_sien_m'] = round($dSien);
                            $g['distance_suggere_m'] = round($dAutre);
                            $rattachements[] = $g;
                            break;
                        }
                    }
                }
            }
        }

        $doublons = array();
        foreach ($doublonsIndex as $cle => $groupe) {
            if (count($groupe) > 1) {
                $doublons[] = array('position' => $cle, 'compteurs' => $groupe);
            }
        }

        return array(
            'sans_gps' => $sansGps,
            'aberrants' => $aberrants,
            'doublons' => $doublons,
            'rattachements' => $rattachements,
        );
    }

    // ---------------------------------------------------------------
    // Secteurs (polygones) et statistiques agrégées
    // ---------------------------------------------------------------

    public static function getSecteurs($id_aep)
    {
        $rows = Manager::prepare_query(
            "SELECT * FROM carto_secteur WHERE id_aep = ? ORDER BY nom ASC",
            array((int) $id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['polygone'] = json_decode($row['polygone_json'], true);
            unset($row['polygone_json']);
        }
        unset($row);
        return $rows;
    }

    public static function saveSecteur($id, $id_aep, $nom, array $polygone, $description)
    {
        $id = (int) $id;
        $id_aep = (int) $id_aep;
        $json = json_encode($polygone);

        if ($id > 0) {
            Manager::prepare_query(
                "UPDATE carto_secteur SET nom = ?, polygone_json = ?, description = ?, updated_at = NOW()
                 WHERE id = ? AND id_aep = ?",
                array($nom, $json, $description, $id, $id_aep)
            );
            return $id;
        }
        Manager::prepare_query(
            "INSERT INTO carto_secteur (id_aep, nom, polygone_json, description) VALUES (?, ?, ?, ?)",
            array($id_aep, $nom, $json, $description)
        );
        $row = Manager::prepare_query("SELECT LAST_INSERT_ID() AS id", array())->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : 0;
    }

    public static function deleteSecteur($id, $id_aep)
    {
        Manager::prepare_query(
            "DELETE FROM carto_secteur WHERE id = ? AND id_aep = ?",
            array((int) $id, (int) $id_aep)
        );
    }

    /**
     * Test point-dans-polygone (lancer de rayon). $polygone : liste de [lon, lat].
     */
    public static function pointDansPolygone($lon, $lat, array $polygone)
    {
        $dedans = false;
        $n = count($polygone);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $polygone[$i][0]; $yi = (float) $polygone[$i][1];
            $xj = (float) $polygone[$j][0]; $yj = (float) $polygone[$j][1];
            $intersecte = (($yi > $lat) != ($yj > $lat))
                && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) != 0 ? ($yj - $yi) : 1e-12) + $xi);
            if ($intersecte) {
                $dedans = !$dedans;
            }
        }
        return $dedans;
    }

    /**
     * Statistiques agrégées des compteurs situés dans un polygone, pour un mois.
     *
     * @return array<string,mixed>
     */
    public static function getStatsSecteur($id_aep, array $polygone, $mois)
    {
        $compteurs = self::getCompteursAvecIndicateurs($id_aep, $mois);
        $nb = 0; $nbActifs = 0;
        $consommation = 0.0; $facture = 0.0; $verse = 0.0; $impaye = 0.0; $penalite = 0.0;
        $parReseau = array();

        foreach ($compteurs as $c) {
            if (!self::pointDansPolygone((float) $c['longitude'], (float) $c['latitude'], $polygone)) {
                continue;
            }
            $nb++;
            if ($c['etat'] === 'actif') {
                $nbActifs++;
            }
            $consommation += (float) $c['consommation'];
            $facture += (float) $c['montant_total'];
            $verse += (float) $c['montant_verse'];
            $impaye += (float) $c['impaye'];
            $penalite += (float) $c['penalite'];
            $nomReseau = $c['nom_reseau'];
            $parReseau[$nomReseau] = isset($parReseau[$nomReseau]) ? $parReseau[$nomReseau] + 1 : 1;
        }

        return array(
            'nb_compteurs' => $nb,
            'nb_actifs' => $nbActifs,
            'consommation_m3' => round($consommation, 2),
            'montant_facture' => round($facture, 2),
            'montant_verse' => round($verse, 2),
            'impaye' => round($impaye, 2),
            'penalite' => round($penalite, 2),
            'taux_recouvrement' => $facture > 0 ? round($verse * 100.0 / $facture, 1) : null,
            'par_reseau' => $parReseau,
            'mois' => $mois,
        );
    }

    // ---------------------------------------------------------------
    // Ouvrages ponctuels
    // ---------------------------------------------------------------

    public static function getOuvrages($id_aep)
    {
        return Manager::prepare_query(
            "SELECT o.*, r.nom AS nom_reseau FROM carto_ouvrage o
             LEFT JOIN reseau r ON r.id = o.id_reseau
             WHERE o.id_aep = ? ORDER BY o.nom ASC",
            array((int) $id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function typeOuvrageValide($type)
    {
        return in_array($type, self::typesOuvrage(), true);
    }

    /**
     * Crée ou met à jour un ouvrage. Retourne son id, ou 0 en cas d'échec.
     */
    public static function saveOuvrage($id, $id_aep, $id_reseau, $type, $nom, $latitude, $longitude, $description)
    {
        $id = (int) $id;
        $id_aep = (int) $id_aep;
        $id_reseau = $id_reseau ? (int) $id_reseau : null;
        $type = self::typeOuvrageValide($type) ? $type : 'autre';

        if ($id > 0) {
            Manager::prepare_query(
                "UPDATE carto_ouvrage SET id_reseau = ?, type = ?, nom = ?, latitude = ?, longitude = ?, description = ?, updated_at = NOW()
                 WHERE id = ? AND id_aep = ?",
                array($id_reseau, $type, $nom, (float) $latitude, (float) $longitude, $description, $id, $id_aep)
            );
            return $id;
        }

        Manager::prepare_query(
            "INSERT INTO carto_ouvrage (id_aep, id_reseau, type, nom, latitude, longitude, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            array($id_aep, $id_reseau, $type, $nom, (float) $latitude, (float) $longitude, $description)
        );
        $row = Manager::prepare_query("SELECT LAST_INSERT_ID() AS id", array())->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : 0;
    }

    public static function deleteOuvrage($id, $id_aep)
    {
        Manager::prepare_query(
            "DELETE FROM carto_ouvrage WHERE id = ? AND id_aep = ?",
            array((int) $id, (int) $id_aep)
        );
    }

    // ---------------------------------------------------------------
    // Conduites (tracés)
    // ---------------------------------------------------------------

    public static function getConduites($id_aep)
    {
        $rows = Manager::prepare_query(
            "SELECT c.*, r.nom AS nom_reseau FROM carto_conduite c
             LEFT JOIN reseau r ON r.id = c.id_reseau
             WHERE c.id_aep = ? ORDER BY c.nom ASC",
            array((int) $id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['trace'] = json_decode($row['trace_json'], true);
            unset($row['trace_json']);
        }
        unset($row);
        return $rows;
    }

    /**
     * Longueur d'un tracé (liste de [lon, lat]) en mètres, formule haversine.
     */
    public static function calculerLongueur(array $points)
    {
        $total = 0.0;
        $n = count($points);
        for ($i = 1; $i < $n; $i++) {
            $lon1 = (float) $points[$i - 1][0];
            $lat1 = (float) $points[$i - 1][1];
            $lon2 = (float) $points[$i][0];
            $lat2 = (float) $points[$i][1];
            $total += self::haversine($lat1, $lon1, $lat2, $lon2);
        }
        return $total;
    }

    private static function haversine($lat1, $lon1, $lat2, $lon2)
    {
        $r = 6371000.0; // rayon terrestre moyen, en mètres
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $r * $c;
    }

    /**
     * Crée ou met à jour une conduite. $points : liste de [lon, lat]. Retourne l'id.
     */
    public static function saveConduite($id, $id_aep, $id_reseau, $nom, array $points, $description)
    {
        $id = (int) $id;
        $id_aep = (int) $id_aep;
        $id_reseau = $id_reseau ? (int) $id_reseau : null;
        $trace_json = json_encode($points);
        $longueur = self::calculerLongueur($points);

        if ($id > 0) {
            Manager::prepare_query(
                "UPDATE carto_conduite SET id_reseau = ?, nom = ?, trace_json = ?, longueur_m = ?, description = ?, updated_at = NOW()
                 WHERE id = ? AND id_aep = ?",
                array($id_reseau, $nom, $trace_json, $longueur, $description, $id, $id_aep)
            );
            return $id;
        }

        Manager::prepare_query(
            "INSERT INTO carto_conduite (id_aep, id_reseau, nom, trace_json, longueur_m, description)
             VALUES (?, ?, ?, ?, ?, ?)",
            array($id_aep, $id_reseau, $nom, $trace_json, $longueur, $description)
        );
        $row = Manager::prepare_query("SELECT LAST_INSERT_ID() AS id", array())->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : 0;
    }

    public static function deleteConduite($id, $id_aep)
    {
        Manager::prepare_query(
            "DELETE FROM carto_conduite WHERE id = ? AND id_aep = ?",
            array((int) $id, (int) $id_aep)
        );
    }

    // ---------------------------------------------------------------
    // Métadonnées des tuiles importées
    // ---------------------------------------------------------------

    public static function dossierTuiles($id_aep)
    {
        return __DIR__ . '/tuiles/aep_' . (int) $id_aep;
    }

    /**
     * Emprise géographique couverte par les tuiles, déduite des numéros {x}/{y}
     * du niveau de zoom le plus large (peu de tuiles, donc lecture peu coûteuse).
     *
     * @return array{lat_min:float, lon_min:float, lat_max:float, lon_max:float}|null
     */
    public static function calculerEmpriseTuiles($id_aep, $zoom)
    {
        $zoom = (int) $zoom;
        $dossierZoom = self::dossierTuiles($id_aep) . '/' . $zoom;
        if (!is_dir($dossierZoom)) {
            return null;
        }

        $xMin = null; $xMax = null; $yMin = null; $yMax = null;
        foreach (glob($dossierZoom . '/*', GLOB_ONLYDIR) as $dossierX) {
            $x = basename($dossierX);
            if (!ctype_digit($x)) {
                continue;
            }
            $x = (int) $x;
            foreach (glob($dossierX . '/*') as $fichierY) {
                $y = pathinfo($fichierY, PATHINFO_FILENAME);
                if (!ctype_digit($y)) {
                    continue;
                }
                $y = (int) $y;
                $xMin = ($xMin === null) ? $x : min($xMin, $x);
                $xMax = ($xMax === null) ? $x : max($xMax, $x);
                $yMin = ($yMin === null) ? $y : min($yMin, $y);
                $yMax = ($yMax === null) ? $y : max($yMax, $y);
            }
        }
        if ($xMin === null) {
            return null;
        }

        // Coin haut-gauche de la première tuile, coin bas-droit de la dernière.
        return array(
            'lon_min' => self::tuileVersLon($xMin, $zoom),
            'lon_max' => self::tuileVersLon($xMax + 1, $zoom),
            'lat_max' => self::tuileVersLat($yMin, $zoom),
            'lat_min' => self::tuileVersLat($yMax + 1, $zoom),
        );
    }

    private static function tuileVersLon($x, $zoom)
    {
        return $x / pow(2, $zoom) * 360.0 - 180.0;
    }

    private static function tuileVersLat($y, $zoom)
    {
        $n = M_PI - 2.0 * M_PI * $y / pow(2, $zoom);
        return 180.0 / M_PI * atan(0.5 * (exp($n) - exp(-$n)));
    }

    public static function getTuilesMeta($id_aep)
    {
        $id_aep = (int) $id_aep;
        $row = Manager::prepare_query(
            "SELECT * FROM carto_tuiles_meta WHERE id_aep = ?",
            array($id_aep)
        )->fetch(PDO::FETCH_ASSOC);
        $dossier = self::dossierTuiles($id_aep);
        if (!is_dir($dossier)) {
            return array(
                'id_aep' => $id_aep,
                'disponible' => false,
                'zoom_min' => null,
                'zoom_max' => null,
                'nb_tuiles' => 0,
                'poids_octets' => 0,
                'date_import' => null,
                'emprise' => null,
            );
        }
        if (!$row) {
            // Dossier présent sans métadonnées (tuiles déposées directement sur
            // le disque) : c'est le dossier qui fait foi, on recalcule.
            return self::recalculerTuilesMeta($id_aep);
        }
        $row['disponible'] = true;
        $row['id_aep'] = $id_aep;
        $row['emprise'] = ($row['zoom_min'] !== null)
            ? self::calculerEmpriseTuiles($id_aep, $row['zoom_min'])
            : null;
        return $row;
    }

    /**
     * Parcourt le dossier de tuiles {z}/{x}/{y}.ext et enregistre les métadonnées.
     */
    public static function recalculerTuilesMeta($id_aep)
    {
        $id_aep = (int) $id_aep;
        $dossier = self::dossierTuiles($id_aep);
        $zoomMin = null;
        $zoomMax = null;
        $nb = 0;
        $poids = 0;

        if (is_dir($dossier)) {
            $zoomDirs = glob($dossier . '/*', GLOB_ONLYDIR);
            foreach ($zoomDirs as $zd) {
                $z = basename($zd);
                if (!ctype_digit($z)) {
                    continue;
                }
                $z = (int) $z;
                $hasTile = false;
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($zd, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if ($file->isFile()) {
                        $hasTile = true;
                        $nb++;
                        $poids += $file->getSize();
                    }
                }
                if ($hasTile) {
                    $zoomMin = ($zoomMin === null) ? $z : min($zoomMin, $z);
                    $zoomMax = ($zoomMax === null) ? $z : max($zoomMax, $z);
                }
            }
        }

        Manager::prepare_query(
            "REPLACE INTO carto_tuiles_meta (id_aep, zoom_min, zoom_max, nb_tuiles, poids_octets, date_import)
             VALUES (?, ?, ?, ?, ?, NOW())",
            array($id_aep, $zoomMin, $zoomMax, $nb, $poids)
        );

        return self::getTuilesMeta($id_aep);
    }

    public static function supprimerTuiles($id_aep)
    {
        $id_aep = (int) $id_aep;
        $dossier = self::dossierTuiles($id_aep);
        if (is_dir($dossier)) {
            self::supprimerDossierRecursif($dossier);
        }
        Manager::prepare_query("DELETE FROM carto_tuiles_meta WHERE id_aep = ?", array($id_aep));
    }

    private static function supprimerDossierRecursif($dir)
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
