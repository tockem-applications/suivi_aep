<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/connexion.php');
@include_once('donnees/connexion.php');

/**
 * Campagnes d'avis de coupure.
 *
 * Un avis de coupure est le document remis à l'abonné dont les factures
 * s'accumulent sans règlement. La campagne est rattachée à un mois de
 * facturation : pour ce mois, l'agent retient les abonnés à couper, la
 * sélection est persistée, puis les avis sont édités en PDF (deux avis par
 * page A4, comme le formulaire papier).
 *
 * Règle de sélection : sont candidats les abonnés actifs de l'AEP qui comptent
 * au moins N mois facturés non soldés « depuis leur dernier avis de coupure »,
 * c'est-à-dire postérieurs au mois du dernier avis déjà édité pour eux. Un
 * abonné qui n'a jamais reçu d'avis est jugé sur tout son historique impayé.
 * Les abonnés déjà retenus pour le mois en cours restent affichés même s'ils ne
 * remplissent plus le critère, afin de pouvoir les retirer de la liste.
 */
class AvisCoupure
{
    /** Nombre de mois impayés à partir duquel un abonné devient candidat. */
    const SEUIL_MOIS_DEFAUT = 3;

    /** Nombre d'avis imprimés sur une page A4. */
    const AVIS_PAR_PAGE = 2;

    private static $tablesVerifiees = false;

    /**
     * Crée les tables si elles n'existent pas encore.
     *
     * Le dépôt ne dispose pas d'outil de migration : comme pour les autres
     * modules récents (cartographie, coût du service...), le schéma est créé à
     * la volée au premier accès.
     */
    public static function ensureTables()
    {
        if (self::$tablesVerifiees) {
            return;
        }
        try {
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS avis_coupure_campagne (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_aep INT(10) UNSIGNED NOT NULL,
                    id_mois_facturation INT(10) UNSIGNED NOT NULL,
                    seuil_mois INT(3) UNSIGNED NOT NULL DEFAULT 3,
                    id_responsable INT(10) UNSIGNED DEFAULT NULL,
                    entete VARCHAR(255) NOT NULL DEFAULT '',
                    date_avis DATE DEFAULT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_avis_campagne (id_aep, id_mois_facturation)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS avis_coupure (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_aep INT(10) UNSIGNED NOT NULL,
                    id_mois_facturation INT(10) UNSIGNED NOT NULL,
                    id_abone INT(10) UNSIGNED NOT NULL,
                    nom_abone VARCHAR(128) NOT NULL DEFAULT '',
                    numero_compteur VARCHAR(32) DEFAULT NULL,
                    dernier_index DECIMAL(10,2) DEFAULT NULL,
                    montant_impaye DECIMAL(15,2) NOT NULL DEFAULT 0,
                    nb_mois_impayes INT(5) UNSIGNED NOT NULL DEFAULT 0,
                    id_responsable INT(10) UNSIGNED DEFAULT NULL,
                    date_avis DATE DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_avis_abone (id_aep, id_mois_facturation, id_abone),
                    KEY idx_avis_campagne (id_aep, id_mois_facturation),
                    KEY idx_avis_abone (id_abone)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            self::$tablesVerifiees = true;
        } catch (Exception $e) {
            // Une base en lecture seule ne doit pas casser l'affichage : les
            // requêtes suivantes échoueront proprement et la page le signalera.
        }
    }

    /**
     * En-tête imprimé en haut de chaque avis, par défaut dérivé de l'AEP.
     *
     * @return string
     */
    public static function enteteParDefaut($idAep)
    {
        $libele = '';
        try {
            $row = Manager::prepare_query("SELECT libele FROM aep WHERE id = ?", array((int) $idAep))
                ->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['libele'])) {
                $libele = trim($row['libele']);
            }
        } catch (Exception $e) {
        }
        $base = "PAYEZ AVEC CET AVIS A LA REGIE COMMUNALE DE L'EAU";
        return $libele === '' ? $base : $base . ' ' . self::majuscules($libele);
    }

    /**
     * Paramètres de la campagne d'un mois : seuil, responsable, en-tête, date.
     *
     * Renvoie toujours un tableau exploitable, complété par les valeurs par
     * défaut lorsque la campagne n'a pas encore été enregistrée.
     *
     * @return array<string,mixed>
     */
    public static function getCampagne($idAep, $idMois)
    {
        self::ensureTables();
        $defaut = array(
            'id_aep' => (int) $idAep,
            'id_mois_facturation' => (int) $idMois,
            'seuil_mois' => self::SEUIL_MOIS_DEFAUT,
            'id_responsable' => 0,
            'entete' => self::enteteParDefaut($idAep),
            'date_avis' => '',
            'enregistree' => false,
        );
        if ((int) $idAep <= 0 || (int) $idMois <= 0) {
            return $defaut;
        }
        try {
            $row = Manager::prepare_query(
                "SELECT * FROM avis_coupure_campagne WHERE id_aep = ? AND id_mois_facturation = ?",
                array((int) $idAep, (int) $idMois)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $row = false;
        }
        if (!$row) {
            return $defaut;
        }
        return array(
            'id_aep' => (int) $row['id_aep'],
            'id_mois_facturation' => (int) $row['id_mois_facturation'],
            'seuil_mois' => max(1, (int) $row['seuil_mois']),
            'id_responsable' => (int) $row['id_responsable'],
            'entete' => trim($row['entete']) !== '' ? $row['entete'] : $defaut['entete'],
            'date_avis' => $row['date_avis'] ? $row['date_avis'] : '',
            'enregistree' => true,
        );
    }

    /**
     * Enregistre (ou met à jour) les paramètres de la campagne du mois.
     */
    public static function enregistrerCampagne($idAep, $idMois, $seuil, $idResponsable, $entete, $dateAvis)
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        $idMois = (int) $idMois;
        if ($idAep <= 0 || $idMois <= 0) {
            return false;
        }
        $seuil = max(1, (int) $seuil);
        $idResponsable = (int) $idResponsable > 0 ? (int) $idResponsable : null;
        $entete = trim((string) $entete);
        $dateAvis = self::normaliserDate($dateAvis);

        Manager::prepare_query(
            "INSERT INTO avis_coupure_campagne
                (id_aep, id_mois_facturation, seuil_mois, id_responsable, entete, date_avis)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                seuil_mois = VALUES(seuil_mois),
                id_responsable = VALUES(id_responsable),
                entete = VALUES(entete),
                date_avis = VALUES(date_avis)",
            array($idAep, $idMois, $seuil, $idResponsable, $entete, $dateAvis)
        );
        return true;
    }

    /**
     * Abonnés à proposer à l'agent pour le mois de facturation demandé.
     *
     * Chaque ligne porte le nombre de mois impayés depuis le dernier avis, le
     * montant correspondant, le mois du dernier avis reçu et l'indicateur
     * `est_retenu` disant si l'abonné fait déjà partie de la sélection.
     *
     * @param array<string,mixed> $filtres id_reseau, recherche
     * @return array<int,array<string,mixed>>
     */
    public static function getCandidats($idAep, $idMois, $seuil, array $filtres = array())
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        $idMois = (int) $idMois;
        if ($idAep <= 0 || $idMois <= 0) {
            return array();
        }
        $seuil = max(1, (int) $seuil);
        $moisCampagne = self::getMoisValeur($idMois);
        if ($moisCampagne === null) {
            return array();
        }

        $idReseau = isset($filtres['id_reseau']) ? (int) $filtres['id_reseau'] : 0;
        $recherche = isset($filtres['recherche']) ? trim((string) $filtres['recherche']) : '';

        // Mois du dernier avis édité pour l'abonné, antérieur à la campagne en
        // cours : la campagne du mois ne doit pas se neutraliser elle-même.
        $sqlDernierAvis = "(SELECT MAX(mfa.mois)
                              FROM avis_coupure ac
                              INNER JOIN mois_facturation mfa ON mfa.id = ac.id_mois_facturation
                             WHERE ac.id_abone = a.id AND ac.id_aep = ? AND mfa.mois < ?)";

        $params = array();
        $sqlInterne = "SELECT a.id AS id_abone, a.nom, a.numero_telephone, a.rang, a.etat,
                              r.id AS id_reseau, r.nom AS nom_reseau, r.abreviation,
                              CASE WHEN LOWER(TRIM(a.etat)) = 'actif' THEN 1 ELSE 0 END AS est_actif,
                              " . $sqlDernierAvis . " AS dernier_avis_mois
                         FROM abone a
                         INNER JOIN reseau r ON r.id = a.id_reseau
                        WHERE r.id_aep = ?";
        $params[] = $idAep;        // sous-requête dernier avis
        $params[] = $moisCampagne; // sous-requête dernier avis
        $params[] = $idAep;        // filtre AEP
        if ($idReseau > 0) {
            $sqlInterne .= " AND a.id_reseau = ?";
            $params[] = $idReseau;
        }
        if ($recherche !== '') {
            $sqlInterne .= " AND a.nom LIKE ?";
            $params[] = '%' . $recherche . '%';
        }

        // Les impayés sont comptés sur les mois facturés jusqu'à la campagne et
        // postérieurs au dernier avis reçu.
        $conditionImpayes = "v.id_abone = t.id_abone
                             AND COALESCE(v.montant_restant, 0) > 0
                             AND v.mois <= ?
                             AND (t.dernier_avis_mois IS NULL OR v.mois > t.dernier_avis_mois)";

        $sql = "SELECT t.*,
                       (SELECT COUNT(*) FROM vue_abones_facturation v WHERE " . $conditionImpayes . ") AS nb_mois_impayes,
                       (SELECT COALESCE(SUM(v.montant_restant), 0) FROM vue_abones_facturation v WHERE " . $conditionImpayes . ") AS montant_impaye,
                       (SELECT v.nouvel_index FROM vue_abones_facturation v
                         WHERE v.id_abone = t.id_abone AND v.mois <= ?
                         ORDER BY v.mois DESC LIMIT 1) AS dernier_index,
                       (SELECT c.numero_compteur FROM compteur_abone ca
                         INNER JOIN compteur c ON c.id = ca.id_compteur
                         WHERE ca.id_abone = t.id_abone ORDER BY c.id DESC LIMIT 1) AS numero_compteur,
                       (SELECT COUNT(*) FROM avis_coupure ac2
                         WHERE ac2.id_abone = t.id_abone AND ac2.id_aep = ? AND ac2.id_mois_facturation = ?) AS est_retenu
                  FROM (" . $sqlInterne . ") t
                HAVING (est_actif = 1 AND nb_mois_impayes >= ?) OR est_retenu > 0
                ORDER BY est_retenu DESC, nb_mois_impayes DESC, montant_impaye DESC, t.nom ASC";

        $paramsFinaux = array_merge(
            // nb_mois_impayes, montant_impaye, dernier_index, est_retenu
            array($moisCampagne, $moisCampagne, $moisCampagne, $idAep, $idMois),
            $params,
            array($seuil)
        );

        $lignes = Manager::prepare_query($sql, $paramsFinaux)->fetchAll(PDO::FETCH_ASSOC);
        return $lignes ? $lignes : array();
    }

    /**
     * Avis persistés pour un mois de facturation, dans l'ordre d'impression.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getAvis($idAep, $idMois)
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        $idMois = (int) $idMois;
        if ($idAep <= 0 || $idMois <= 0) {
            return array();
        }
        try {
            $lignes = Manager::prepare_query(
                "SELECT ac.*, a.nom AS nom_actuel, a.numero_telephone, a.etat,
                        r.nom AS nom_reseau, r.abreviation,
                        u.nom AS responsable_nom, u.prenom AS responsable_prenom,
                        u.numero_telephone AS responsable_telephone
                   FROM avis_coupure ac
                   LEFT JOIN abone a ON a.id = ac.id_abone
                   LEFT JOIN reseau r ON r.id = a.id_reseau
                   LEFT JOIN users u ON u.id = ac.id_responsable
                  WHERE ac.id_aep = ? AND ac.id_mois_facturation = ?
                  ORDER BY r.nom ASC, a.nom ASC, ac.id ASC",
                array($idAep, $idMois)
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
        return $lignes ? $lignes : array();
    }

    /**
     * Nombre d'avis retenus pour le mois et montant impayé cumulé.
     *
     * Ces totaux portent sur toute la campagne, indépendamment des filtres
     * d'affichage : ils comptent les avis qui seront réellement édités.
     *
     * @return array{nb:int, montant:float}
     */
    public static function getTotauxRetenus($idAep, $idMois)
    {
        self::ensureTables();
        try {
            $row = Manager::prepare_query(
                "SELECT COUNT(*) AS nb, COALESCE(SUM(montant_impaye), 0) AS montant
                   FROM avis_coupure WHERE id_aep = ? AND id_mois_facturation = ?",
                array((int) $idAep, (int) $idMois)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $row = false;
        }
        return array(
            'nb' => $row ? (int) $row['nb'] : 0,
            'montant' => $row ? (float) $row['montant'] : 0.0,
        );
    }

    /**
     * Identifiants des abonnés retenus pour le mois.
     *
     * @return int[]
     */
    public static function getIdsRetenus($idAep, $idMois)
    {
        self::ensureTables();
        try {
            $ids = Manager::prepare_query(
                "SELECT id_abone FROM avis_coupure WHERE id_aep = ? AND id_mois_facturation = ?",
                array((int) $idAep, (int) $idMois)
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return array();
        }
        return $ids ? array_map('intval', $ids) : array();
    }

    /**
     * Met à jour la sélection du mois à partir des cases cochées.
     *
     * Les abonnés ajoutés reçoivent un instantané de leur situation (montant
     * impayé, nombre de mois, compteur, index) : l'avis est un document daté,
     * il doit rester fidèle à ce qui a été constaté au moment de sa validation.
     * Les abonnés déjà retenus conservent leur instantané et leur date.
     *
     * Le périmètre délimite les abonnés sur lesquels l'écran s'est prononcé —
     * ceux qu'il affichait. Un abonné retenu mais absent de l'écran (masqué par
     * un filtre de réseau ou de recherche) est conservé : filtrer la liste ne
     * doit jamais supprimer des avis que l'agent n'a pas vus.
     *
     * @param int[] $idsAbones abonnés cochés
     * @param int[] $perimetre abonnés affichés ; vide = toute la sélection
     * @return array{ajoutes:int, retires:int, conserves:int}
     */
    public static function enregistrerSelection($idAep, $idMois, array $idsAbones, $idResponsable, $dateAvis, array $perimetre = array())
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        $idMois = (int) $idMois;
        $resultat = array('ajoutes' => 0, 'retires' => 0, 'conserves' => 0);
        if ($idAep <= 0 || $idMois <= 0) {
            return $resultat;
        }

        $idsAbones = array_values(array_unique(array_filter(array_map('intval', $idsAbones))));
        $perimetre = array_values(array_unique(array_filter(array_map('intval', $perimetre))));
        $existants = self::getIdsRetenus($idAep, $idMois);

        // On ne décoche que dans le périmètre affiché ; une case cochée hors de
        // ce périmètre ne peut pas venir de l'écran et est ignorée.
        if (!empty($perimetre)) {
            $idsAbones = array_values(array_intersect($idsAbones, $perimetre));
            $decochables = array_intersect($existants, $perimetre);
        } else {
            $decochables = $existants;
        }

        $aRetirer = array_diff($decochables, $idsAbones);
        $aAjouter = array_diff($idsAbones, $existants);
        $resultat['conserves'] = count(array_intersect($existants, $idsAbones));

        foreach ($aRetirer as $idAbone) {
            Manager::prepare_query(
                "DELETE FROM avis_coupure WHERE id_aep = ? AND id_mois_facturation = ? AND id_abone = ?",
                array($idAep, $idMois, (int) $idAbone)
            );
            $resultat['retires']++;
        }

        if (!empty($aAjouter)) {
            // Un seul passage sur les candidats : l'instantané de chaque abonné
            // ajouté est repris de la liste déjà calculée pour l'écran.
            $candidats = self::getCandidats($idAep, $idMois, 1);
            $parId = array();
            foreach ($candidats as $candidat) {
                $parId[(int) $candidat['id_abone']] = $candidat;
            }
            foreach ($aAjouter as $idAbone) {
                $idAbone = (int) $idAbone;
                $c = isset($parId[$idAbone]) ? $parId[$idAbone] : self::instantaneMinimal($idAep, $idAbone);
                if ($c === null) {
                    continue;
                }
                Manager::prepare_query(
                    "INSERT INTO avis_coupure
                        (id_aep, id_mois_facturation, id_abone, nom_abone, numero_compteur,
                         dernier_index, montant_impaye, nb_mois_impayes, id_responsable, date_avis)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE id_abone = VALUES(id_abone)",
                    array(
                        $idAep,
                        $idMois,
                        $idAbone,
                        isset($c['nom']) ? $c['nom'] : '',
                        isset($c['numero_compteur']) ? $c['numero_compteur'] : null,
                        isset($c['dernier_index']) && $c['dernier_index'] !== null ? $c['dernier_index'] : null,
                        isset($c['montant_impaye']) ? round((float) $c['montant_impaye'], 2) : 0,
                        isset($c['nb_mois_impayes']) ? (int) $c['nb_mois_impayes'] : 0,
                        (int) $idResponsable > 0 ? (int) $idResponsable : null,
                        self::normaliserDate($dateAvis),
                    )
                );
                $resultat['ajoutes']++;
            }
        }

        // Le responsable et la date valent pour toute la campagne : les avis
        // conservés sont réalignés sur les paramètres courants.
        Manager::prepare_query(
            "UPDATE avis_coupure SET id_responsable = ?, date_avis = ?
              WHERE id_aep = ? AND id_mois_facturation = ?",
            array(
                (int) $idResponsable > 0 ? (int) $idResponsable : null,
                self::normaliserDate($dateAvis),
                $idAep,
                $idMois,
            )
        );

        return $resultat;
    }

    /**
     * Instantané de repli pour un abonné absent des candidats calculés : l'agent
     * a voulu l'avis, on ne le perd pas faute de statistiques.
     *
     * @return array<string,mixed>|null
     */
    private static function instantaneMinimal($idAep, $idAbone)
    {
        try {
            $row = Manager::prepare_query(
                "SELECT a.nom,
                        (SELECT c.numero_compteur FROM compteur_abone ca
                          INNER JOIN compteur c ON c.id = ca.id_compteur
                          WHERE ca.id_abone = a.id ORDER BY c.id DESC LIMIT 1) AS numero_compteur,
                        (SELECT v.nouvel_index FROM vue_abones_facturation v
                          WHERE v.id_abone = a.id ORDER BY v.mois DESC LIMIT 1) AS dernier_index
                   FROM abone a
                   INNER JOIN reseau r ON r.id = a.id_reseau
                  WHERE a.id = ? AND r.id_aep = ?",
                array((int) $idAbone, (int) $idAep)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
        if (!$row) {
            return null;
        }
        $row['montant_impaye'] = 0;
        $row['nb_mois_impayes'] = 0;
        return $row;
    }

    /**
     * Valeur du champ `mois` d'un mois de facturation (format « AAAA-MM-JJ »).
     *
     * @return string|null
     */
    public static function getMoisValeur($idMois)
    {
        try {
            $row = Manager::prepare_query(
                "SELECT mois FROM mois_facturation WHERE id = ?",
                array((int) $idMois)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
        return $row && isset($row['mois']) ? $row['mois'] : null;
    }

    /**
     * Utilisateurs proposés comme responsable de la coupure.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getResponsablesPossibles()
    {
        try {
            $lignes = Manager::prepare_query(
                "SELECT id, nom, prenom, numero_telephone FROM users ORDER BY nom, prenom",
                array()
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
        return $lignes ? $lignes : array();
    }

    /**
     * Nom affichable d'un utilisateur.
     */
    public static function nomComplet(array $utilisateur)
    {
        $nom = isset($utilisateur['nom']) ? trim($utilisateur['nom']) : '';
        $prenom = isset($utilisateur['prenom']) ? trim($utilisateur['prenom']) : '';
        $complet = trim($nom . ' ' . $prenom);
        return $complet !== '' ? $complet : '—';
    }

    /**
     * Montant en toutes lettres, comme sur le formulaire papier
     * (« 47000 F (Quarante sept mille francs CFA) »).
     *
     * @param float $montant
     * @return string
     */
    public static function montantEnLettres($montant)
    {
        $entier = (int) round((float) $montant);
        if ($entier <= 0) {
            return 'Zéro franc CFA';
        }
        $lettres = self::nombreEnLettres($entier);
        // « un million de francs », mais « un million cinq cents francs ».
        $franc = preg_match('/(million|milliard)s?$/', $lettres)
            ? 'de francs CFA'
            : ($entier > 1 ? 'francs CFA' : 'franc CFA');
        return self::majusculePremiere($lettres) . ' ' . $franc;
    }

    /**
     * Écriture en toutes lettres d'un entier positif, en français.
     */
    public static function nombreEnLettres($nombre)
    {
        $nombre = (int) $nombre;
        if ($nombre < 0) {
            return 'moins ' . self::nombreEnLettres(-$nombre);
        }
        if ($nombre < 17) {
            $unites = array(
                'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
                'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
            );
            return $unites[$nombre];
        }
        if ($nombre < 20) {
            return 'dix-' . self::nombreEnLettres($nombre - 10);
        }
        if ($nombre < 100) {
            $dizaines = array(
                2 => 'vingt', 3 => 'trente', 4 => 'quarante',
                5 => 'cinquante', 6 => 'soixante', 7 => 'soixante', 8 => 'quatre-vingt', 9 => 'quatre-vingt',
            );
            $d = (int) ($nombre / 10);
            $u = $nombre % 10;
            if ($d === 7 || $d === 9) {
                // 70-79 et 90-99 s'écrivent « soixante-dix... » / « quatre-vingt-dix... ».
                $reste = $nombre - ($d === 7 ? 60 : 80);
                return $dizaines[$d] . '-' . self::nombreEnLettres($reste);
            }
            if ($u === 0) {
                return $dizaines[$d] . ($d === 8 ? 's' : '');
            }
            if ($u === 1 && $d !== 8) {
                return $dizaines[$d] . '-et-un';
            }
            return $dizaines[$d] . '-' . self::nombreEnLettres($u);
        }
        if ($nombre < 1000) {
            $c = (int) ($nombre / 100);
            $reste = $nombre % 100;
            $prefixe = $c > 1 ? self::nombreEnLettres($c) . ' cent' : 'cent';
            if ($reste === 0) {
                return $prefixe . ($c > 1 ? 's' : '');
            }
            return $prefixe . ' ' . self::nombreEnLettres($reste);
        }
        if ($nombre < 1000000) {
            $m = (int) ($nombre / 1000);
            $reste = $nombre % 1000;
            $prefixe = $m > 1 ? self::multiplicateur($m) . ' mille' : 'mille';
            return $reste === 0 ? $prefixe : $prefixe . ' ' . self::nombreEnLettres($reste);
        }
        if ($nombre < 1000000000) {
            $m = (int) ($nombre / 1000000);
            $reste = $nombre % 1000000;
            $prefixe = self::multiplicateur($m) . ' million' . ($m > 1 ? 's' : '');
            return $reste === 0 ? $prefixe : $prefixe . ' ' . self::nombreEnLettres($reste);
        }
        $mrd = (int) ($nombre / 1000000000);
        $reste = $nombre % 1000000000;
        $prefixe = self::multiplicateur($mrd) . ' milliard' . ($mrd > 1 ? 's' : '');
        return $reste === 0 ? $prefixe : $prefixe . ' ' . self::nombreEnLettres($reste);
    }

    /**
     * Écriture d'un multiplicateur (« quatre-vingt » mille, « cinq cent »
     * mille) : « vingt » et « cent » ne prennent le s du pluriel que lorsqu'ils
     * terminent le nombre.
     */
    private static function multiplicateur($nombre)
    {
        return preg_replace('/(vingt|cent)s$/', '$1', self::nombreEnLettres($nombre));
    }

    /**
     * Normalise une date de formulaire en date SQL, ou null si vide/invalide.
     *
     * @return string|null
     */
    private static function normaliserDate($date)
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if ($d && $d->format('Y-m-d') === $date) {
            return $date;
        }
        return null;
    }

    /** Majuscules en tenant compte des accents. */
    private static function majuscules($texte)
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($texte, 'UTF-8');
        }
        return strtoupper($texte);
    }

    /** Met la première lettre en majuscule, accents compris. */
    private static function majusculePremiere($texte)
    {
        if ($texte === '') {
            return '';
        }
        if (function_exists('mb_strtoupper') && function_exists('mb_substr') && function_exists('mb_strlen')) {
            // La longueur est explicite : sous PHP 5.3 un null y serait lu comme 0.
            $reste = mb_strlen($texte, 'UTF-8') - 1;
            return mb_strtoupper(mb_substr($texte, 0, 1, 'UTF-8'), 'UTF-8')
                . ($reste > 0 ? mb_substr($texte, 1, $reste, 'UTF-8') : '');
        }
        return ucfirst($texte);
    }
}
