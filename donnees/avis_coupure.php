<?php

@include_once(__DIR__ . '/manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/connexion.php');
@include_once('donnees/connexion.php');
@include_once(__DIR__ . '/pdf/pdf.php');
@include_once('donnees/pdf/pdf.php');

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
 *
 * Le montant impayé est le solde net des factures (total facturé moins versé,
 * trop-perçus compris), comme sur la facture elle-même ; le nombre de mois non
 * réglés est le nombre de factures les plus récentes que ce solde laisse
 * découvertes.
 *
 * Retenir un abonné le rend « non actif » : il n'est plus facturé tant qu'il
 * n'a pas été rétabli. Le rétablissement enregistre le montant versé et les
 * frais de remise en service sur sa dernière facture, le repasse « actif » et
 * conserve l'avis, daté du rétablissement, pour la traçabilité.
 *
 * Le logo imprimé en tête de chaque avis est propre à l'AEP, pas à la
 * campagne : il est conservé d'un mois sur l'autre jusqu'à ce qu'on le
 * remplace. Les frais de remise en service sont fixés par campagne et
 * reportés d'un mois sur l'autre.
 */
class AvisCoupure
{
    /** Nombre de mois impayés à partir duquel un abonné devient candidat. */
    const SEUIL_MOIS_DEFAUT = 3;

    /** Nombre d'avis imprimés sur une page A4. */
    const AVIS_PAR_PAGE = 2;

    /** Taille maximale acceptée pour le logo, en octets. */
    const LOGO_TAILLE_MAX = 1048576;

    /** État donné à l'abonné retenu pour la coupure (valeur déjà utilisée par l'application). */
    const ETAT_COUPE = 'non actif';

    /** État rendu à l'abonné rétabli. */
    const ETAT_ACTIF = 'actif';

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
                    frais_remise DECIMAL(15,2) NOT NULL DEFAULT 0,
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
                    etat_avant VARCHAR(16) DEFAULT NULL,
                    date_reactivation DATE DEFAULT NULL,
                    montant_reactivation DECIMAL(15,2) DEFAULT NULL,
                    frais_reactivation DECIMAL(15,2) DEFAULT NULL,
                    id_facture_reactivation INT(10) UNSIGNED DEFAULT NULL,
                    id_mois_reactivation INT(10) UNSIGNED DEFAULT NULL,
                    id_user_reactivation INT(10) UNSIGNED DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_avis_abone (id_aep, id_mois_facturation, id_abone),
                    KEY idx_avis_campagne (id_aep, id_mois_facturation),
                    KEY idx_avis_abone (id_abone)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS avis_coupure_logo (
                    id_aep INT(10) UNSIGNED NOT NULL,
                    contenu MEDIUMBLOB NOT NULL,
                    type_mime VARCHAR(32) NOT NULL DEFAULT 'image/png',
                    nom_fichier VARCHAR(128) NOT NULL DEFAULT '',
                    largeur INT(6) UNSIGNED NOT NULL DEFAULT 0,
                    hauteur INT(6) UNSIGNED NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_aep)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                array()
            );
            // Colonnes ajoutées après la création initiale des tables : MySQL 5.1
            // n'a pas de ADD COLUMN IF NOT EXISTS, on compare aux colonnes existantes.
            self::ajouterColonnesManquantes('avis_coupure_campagne', array(
                'frais_remise' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            ));
            self::ajouterColonnesManquantes('avis_coupure', array(
                'etat_avant' => 'VARCHAR(16) DEFAULT NULL',
                'date_reactivation' => 'DATE DEFAULT NULL',
                'montant_reactivation' => 'DECIMAL(15,2) DEFAULT NULL',
                'frais_reactivation' => 'DECIMAL(15,2) DEFAULT NULL',
                'id_facture_reactivation' => 'INT(10) UNSIGNED DEFAULT NULL',
                'id_mois_reactivation' => 'INT(10) UNSIGNED DEFAULT NULL',
                'id_user_reactivation' => 'INT(10) UNSIGNED DEFAULT NULL',
            ));
            self::$tablesVerifiees = true;
        } catch (Exception $e) {
            // Une base en lecture seule ne doit pas casser l'affichage : les
            // requêtes suivantes échoueront proprement et la page le signalera.
        }
    }

    /**
     * @param array<string,string> $colonnes nom => définition SQL
     */
    private static function ajouterColonnesManquantes($table, array $colonnes)
    {
        $existantes = Manager::prepare_query("SHOW COLUMNS FROM " . $table, array())->fetchAll(PDO::FETCH_COLUMN);
        $existantes = array_map('strtolower', $existantes ? $existantes : array());
        foreach ($colonnes as $nom => $definition) {
            if (!in_array(strtolower($nom), $existantes, true)) {
                Manager::prepare_query("ALTER TABLE " . $table . " ADD COLUMN " . $nom . " " . $definition, array());
            }
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
     * Frais de remise en service hérités : ceux de la dernière campagne
     * enregistrée avant le mois demandé. Une fois fixés, les frais se
     * reportent ainsi de mois en mois sans être ressaisis.
     *
     * @return float
     */
    public static function fraisRemiseHerites($idAep, $idMois)
    {
        $mois = self::getMoisValeur($idMois);
        try {
            $row = Manager::prepare_query(
                "SELECT c.frais_remise
                   FROM avis_coupure_campagne c
                   INNER JOIN mois_facturation m ON m.id = c.id_mois_facturation
                  WHERE c.id_aep = ? AND c.id_mois_facturation <> ? AND (? IS NULL OR m.mois < ?)
                  ORDER BY m.mois DESC LIMIT 1",
                array((int) $idAep, (int) $idMois, $mois, $mois)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $row = false;
        }
        return $row ? (float) $row['frais_remise'] : 0.0;
    }

    /**
     * Paramètres de la campagne d'un mois : seuil, responsable, en-tête, date,
     * frais de remise en service.
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
            'frais_remise' => 0.0,
            'enregistree' => false,
        );
        if ((int) $idAep <= 0 || (int) $idMois <= 0) {
            return $defaut;
        }
        $defaut['frais_remise'] = self::fraisRemiseHerites($idAep, $idMois);
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
            'frais_remise' => isset($row['frais_remise']) ? (float) $row['frais_remise'] : 0.0,
            'enregistree' => true,
        );
    }

    /**
     * Enregistre (ou met à jour) les paramètres de la campagne du mois.
     *
     * @param float|null $fraisRemise null pour conserver la valeur en place (ou héritée)
     */
    public static function enregistrerCampagne($idAep, $idMois, $seuil, $idResponsable, $entete, $dateAvis, $fraisRemise = null)
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
        if ($fraisRemise === null) {
            $courante = self::getCampagne($idAep, $idMois);
            $fraisRemise = $courante['frais_remise'];
        }
        $fraisRemise = max(0, round((float) $fraisRemise, 2));

        Manager::prepare_query(
            "INSERT INTO avis_coupure_campagne
                (id_aep, id_mois_facturation, seuil_mois, id_responsable, entete, date_avis, frais_remise)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                seuil_mois = VALUES(seuil_mois),
                id_responsable = VALUES(id_responsable),
                entete = VALUES(entete),
                date_avis = VALUES(date_avis),
                frais_remise = VALUES(frais_remise)",
            array($idAep, $idMois, $seuil, $idResponsable, $entete, $dateAvis, $fraisRemise)
        );
        return true;
    }

    /**
     * Logo de l'AEP imprimé sur les avis, ou null s'il n'en a pas.
     *
     * @return array<string,mixed>|null contenu, type_mime, nom_fichier, largeur, hauteur, updated_at
     */
    public static function getLogo($idAep)
    {
        self::ensureTables();
        if ((int) $idAep <= 0) {
            return null;
        }
        try {
            $row = Manager::prepare_query(
                "SELECT contenu, type_mime, nom_fichier, largeur, hauteur, updated_at
                   FROM avis_coupure_logo WHERE id_aep = ?",
                array((int) $idAep)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $row = false;
        }
        if (!$row || $row['contenu'] === null || $row['contenu'] === '') {
            return null;
        }
        // Selon le pilote, un BLOB peut revenir sous forme de flux.
        if (is_resource($row['contenu'])) {
            $row['contenu'] = stream_get_contents($row['contenu']);
        }
        $row['largeur'] = (int) $row['largeur'];
        $row['hauteur'] = (int) $row['hauteur'];
        return $row;
    }

    /**
     * Remplace le logo de l'AEP.
     *
     * Le contenu est validé par le moteur PDF lui-même : ce qu'il ne sait pas
     * embarquer (WebP, PNG entrelacé ou 16 bits, fichier tronqué...) est refusé
     * dès l'envoi plutôt qu'à l'impression.
     *
     * @param string $binaire    contenu du fichier
     * @param string $nomFichier nom d'origine, pour information
     * @return true|string true, ou le message d'erreur à afficher
     */
    public static function enregistrerLogo($idAep, $binaire, $nomFichier = '')
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        if ($idAep <= 0) {
            return 'Aucun AEP sélectionné.';
        }
        $binaire = (string) $binaire;
        if ($binaire === '') {
            return 'Le fichier est vide.';
        }
        if (strlen($binaire) > self::LOGO_TAILLE_MAX) {
            return 'Le logo dépasse ' . (self::LOGO_TAILLE_MAX / 1048576) . ' Mo : réduisez l\'image avant de l\'envoyer.';
        }
        $info = class_exists('Pdf') ? Pdf::analyserImage($binaire) : false;
        if ($info === false) {
            return 'Format non pris en charge : envoyez un PNG (non entrelacé) ou un JPEG.';
        }
        $nomFichier = preg_replace('/[^A-Za-z0-9._ -]/', '', basename((string) $nomFichier));
        if (function_exists('mb_substr')) {
            $nomFichier = mb_substr($nomFichier, 0, 128, 'UTF-8');
        } else {
            $nomFichier = substr($nomFichier, 0, 128);
        }

        Manager::prepare_query(
            "INSERT INTO avis_coupure_logo (id_aep, contenu, type_mime, nom_fichier, largeur, hauteur)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                contenu = VALUES(contenu),
                type_mime = VALUES(type_mime),
                nom_fichier = VALUES(nom_fichier),
                largeur = VALUES(largeur),
                hauteur = VALUES(hauteur)",
            array(
                $idAep,
                $binaire,
                $info['type'] === 'jpeg' ? 'image/jpeg' : 'image/png',
                $nomFichier,
                (int) $info['largeur'],
                (int) $info['hauteur'],
            )
        );
        return true;
    }

    /** Retire le logo de l'AEP : les avis sont alors imprimés sans logo. */
    public static function supprimerLogo($idAep)
    {
        self::ensureTables();
        if ((int) $idAep <= 0) {
            return false;
        }
        Manager::prepare_query("DELETE FROM avis_coupure_logo WHERE id_aep = ?", array((int) $idAep));
        return true;
    }

    /**
     * Abonnés à proposer à l'agent pour le mois de facturation demandé.
     *
     * Chaque ligne porte le nombre de mois impayés depuis le dernier avis, le
     * montant correspondant, le mois du dernier avis reçu, l'indicateur
     * `est_retenu` disant si l'abonné fait déjà partie de la sélection et
     * `date_reactivation` s'il a déjà été rétabli pour ce mois.
     *
     * Le montant impayé est le solde net (facturé moins versé) des mois
     * considérés, trop-perçus compris, comme le calcule la facture. Le nombre de
     * mois non réglés est le nombre de factures qu'il faut remonter, de la plus
     * récente à la plus ancienne, pour couvrir ce solde : les versements sont
     * réputés régler les factures les plus anciennes d'abord.
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

        // Solde net des mois facturés jusqu'à la campagne et postérieurs au
        // dernier avis reçu. Les mois en trop-perçu comptent en négatif.
        $conditionMois = "v.id_abone = t.id_abone
                          AND v.mois <= ?
                          AND (t.dernier_avis_mois IS NULL OR v.mois > t.dernier_avis_mois)";

        $sql = "SELECT t.*,
                       (SELECT COALESCE(SUM(v.montant_restant), 0) FROM vue_abones_facturation v WHERE " . $conditionMois . ") AS montant_impaye,
                       (SELECT v.nouvel_index FROM vue_abones_facturation v
                         WHERE v.id_abone = t.id_abone AND v.mois <= ?
                         ORDER BY v.mois DESC LIMIT 1) AS dernier_index,
                       (SELECT c.numero_compteur FROM compteur_abone ca
                         INNER JOIN compteur c ON c.id = ca.id_compteur
                         WHERE ca.id_abone = t.id_abone ORDER BY c.id DESC LIMIT 1) AS numero_compteur,
                       (SELECT COUNT(*) FROM avis_coupure ac2
                         WHERE ac2.id_abone = t.id_abone AND ac2.id_aep = ? AND ac2.id_mois_facturation = ?) AS est_retenu,
                       (SELECT MAX(ac3.date_reactivation) FROM avis_coupure ac3
                         WHERE ac3.id_abone = t.id_abone AND ac3.id_aep = ? AND ac3.id_mois_facturation = ?) AS date_reactivation
                  FROM (" . $sqlInterne . ") t
                 WHERE (SELECT COALESCE(SUM(v.montant_restant), 0) FROM vue_abones_facturation v WHERE " . $conditionMois . ") > 0
                    OR EXISTS (SELECT 1 FROM avis_coupure ac4
                                WHERE ac4.id_abone = t.id_abone AND ac4.id_aep = ? AND ac4.id_mois_facturation = ?)";

        $paramsFinaux = array_merge(
            // montant_impaye, dernier_index, est_retenu, date_reactivation
            array($moisCampagne, $moisCampagne, $idAep, $idMois, $idAep, $idMois),
            $params,
            // filtre solde > 0, filtre retenu
            array($moisCampagne, $idAep, $idMois)
        );

        $lignes = Manager::prepare_query($sql, $paramsFinaux)->fetchAll(PDO::FETCH_ASSOC);
        if (!$lignes) {
            return array();
        }

        // Les factures des abonnés débiteurs, de la plus récente à la plus
        // ancienne, pour compter les mois que le solde laisse découverts.
        $ids = array();
        foreach ($lignes as $ligne) {
            if ((float) $ligne['montant_impaye'] > 0) {
                $ids[] = (int) $ligne['id_abone'];
            }
        }
        $factures = array();
        if (!empty($ids)) {
            $marqueurs = implode(',', array_fill(0, count($ids), '?'));
            $rows = Manager::prepare_query(
                "SELECT id_abone, mois, COALESCE(montant_total, 0) AS montant_total
                   FROM vue_abones_facturation
                  WHERE id_abone IN (" . $marqueurs . ") AND mois <= ?
                  ORDER BY id_abone, mois DESC",
                array_merge($ids, array($moisCampagne))
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows ? $rows : array() as $row) {
                $factures[(int) $row['id_abone']][] = $row;
            }
        }

        $resultat = array();
        foreach ($lignes as $ligne) {
            $idAbone = (int) $ligne['id_abone'];
            $solde = round((float) $ligne['montant_impaye'], 2);
            $ligne['montant_impaye'] = max(0, $solde);
            $ligne['nb_mois_impayes'] = $solde > 0
                ? self::compterMoisDecouverts(
                    isset($factures[$idAbone]) ? $factures[$idAbone] : array(),
                    $solde,
                    $ligne['dernier_avis_mois']
                )
                : 0;
            $estActif = (int) $ligne['est_actif'] === 1;
            $estRetenu = (int) $ligne['est_retenu'] > 0;
            if (($estActif && $ligne['nb_mois_impayes'] >= $seuil) || $estRetenu) {
                $resultat[] = $ligne;
            }
        }

        usort($resultat, array('AvisCoupure', 'comparerCandidats'));
        return $resultat;
    }

    /**
     * Nombre de factures, de la plus récente à la plus ancienne, nécessaires
     * pour couvrir le solde dû.
     *
     * @param array<int,array<string,mixed>> $factures mois, montant_total ; ordre décroissant
     * @param float                          $solde    solde net dû (> 0)
     * @param string|null                    $dernierAvisMois mois exclus (antérieurs ou égaux)
     */
    private static function compterMoisDecouverts(array $factures, $solde, $dernierAvisMois)
    {
        $cumul = 0.0;
        $nb = 0;
        foreach ($factures as $f) {
            if ($dernierAvisMois !== null && $dernierAvisMois !== '' && $f['mois'] <= $dernierAvisMois) {
                break;
            }
            if ($cumul >= $solde - 0.005) {
                break;
            }
            // Un mois sans montant facturé n'a rien à régler : il ne compte pas.
            if ((float) $f['montant_total'] <= 0) {
                continue;
            }
            $nb++;
            $cumul += (float) $f['montant_total'];
        }
        // Un solde dû sans facture pour l'expliquer (pénalité seule, données
        // anciennes...) compte au moins pour un mois.
        return max(1, $nb);
    }

    /** Ordre d'affichage : retenus d'abord, puis mois impayés, montant, nom. */
    private static function comparerCandidats($a, $b)
    {
        $ra = (int) $a['est_retenu'] > 0 ? 1 : 0;
        $rb = (int) $b['est_retenu'] > 0 ? 1 : 0;
        if ($ra !== $rb) {
            return $rb - $ra;
        }
        if ((int) $a['nb_mois_impayes'] !== (int) $b['nb_mois_impayes']) {
            return (int) $b['nb_mois_impayes'] - (int) $a['nb_mois_impayes'];
        }
        if ((float) $a['montant_impaye'] !== (float) $b['montant_impaye']) {
            return (float) $b['montant_impaye'] > (float) $a['montant_impaye'] ? 1 : -1;
        }
        return strcasecmp($a['nom'], $b['nom']);
    }

    /**
     * Avis persistés pour un mois de facturation, dans l'ordre d'impression.
     *
     * Les abonnés déjà rétablis en sont exclus par défaut : leur avis est
     * conservé pour la traçabilité mais n'a plus à être remis.
     *
     * @param bool $avecRetablis inclure les avis dont l'abonné a été rétabli
     * @return array<int,array<string,mixed>>
     */
    public static function getAvis($idAep, $idMois, $avecRetablis = false)
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
                  WHERE ac.id_aep = ? AND ac.id_mois_facturation = ?"
                  . ($avecRetablis ? '' : ' AND ac.date_reactivation IS NULL') . "
                  ORDER BY r.nom ASC, a.nom ASC, ac.id ASC",
                array($idAep, $idMois)
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
        return $lignes ? $lignes : array();
    }

    /**
     * Nombre d'avis retenus pour le mois, montant impayé cumulé, et nombre
     * d'avis encore à imprimer (abonnés non rétablis).
     *
     * Ces totaux portent sur toute la campagne, indépendamment des filtres
     * d'affichage.
     *
     * @return array{nb:int, montant:float, nb_a_imprimer:int, nb_retablis:int}
     */
    public static function getTotauxRetenus($idAep, $idMois)
    {
        self::ensureTables();
        try {
            $row = Manager::prepare_query(
                "SELECT COUNT(*) AS nb, COALESCE(SUM(montant_impaye), 0) AS montant,
                        SUM(CASE WHEN date_reactivation IS NULL THEN 1 ELSE 0 END) AS nb_a_imprimer
                   FROM avis_coupure WHERE id_aep = ? AND id_mois_facturation = ?",
                array((int) $idAep, (int) $idMois)
            )->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $row = false;
        }
        $nb = $row ? (int) $row['nb'] : 0;
        $aImprimer = $row ? (int) $row['nb_a_imprimer'] : 0;
        return array(
            'nb' => $nb,
            'montant' => $row ? (float) $row['montant'] : 0.0,
            'nb_a_imprimer' => $aImprimer,
            'nb_retablis' => $nb - $aImprimer,
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
     * Chaque abonné retenu passe « non actif » (son état antérieur est mémorisé
     * sur l'avis) ; un abonné retiré de la sélection retrouve son état
     * antérieur. Un avis dont l'abonné a été rétabli n'est jamais retiré : il
     * garde la trace de la coupure et du règlement.
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
        $retablis = self::getIdsRetablis($idAep, $idMois);

        // On ne décoche que dans le périmètre affiché ; une case cochée hors de
        // ce périmètre ne peut pas venir de l'écran et est ignorée.
        if (!empty($perimetre)) {
            $idsAbones = array_values(array_intersect($idsAbones, $perimetre));
            $decochables = array_intersect($existants, $perimetre);
        } else {
            $decochables = $existants;
        }

        $aRetirer = array_diff($decochables, $idsAbones, $retablis);
        $aAjouter = array_diff($idsAbones, $existants);
        $resultat['conserves'] = count($existants) - count($aRetirer);

        foreach ($aRetirer as $idAbone) {
            $idAbone = (int) $idAbone;
            // L'abonné retrouve l'état qu'il avait avant d'être retenu, à
            // condition que personne ne l'ait modifié entre-temps.
            Manager::prepare_query(
                "UPDATE abone a
                   INNER JOIN avis_coupure ac ON ac.id_abone = a.id
                    SET a.etat = ac.etat_avant
                  WHERE ac.id_aep = ? AND ac.id_mois_facturation = ? AND ac.id_abone = ?
                    AND ac.etat_avant IS NOT NULL AND ac.etat_avant <> ''
                    AND LOWER(TRIM(a.etat)) = ?",
                array($idAep, $idMois, $idAbone, self::ETAT_COUPE)
            );
            Manager::prepare_query(
                "DELETE FROM avis_coupure WHERE id_aep = ? AND id_mois_facturation = ? AND id_abone = ?",
                array($idAep, $idMois, $idAbone)
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
                         dernier_index, montant_impaye, nb_mois_impayes, id_responsable, date_avis, etat_avant)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                        isset($c['etat']) && trim((string) $c['etat']) !== '' ? trim((string) $c['etat']) : self::ETAT_ACTIF,
                    )
                );
                $resultat['ajoutes']++;
            }
        }

        // Tout abonné retenu et non rétabli est coupé : il n'est plus facturé
        // tant qu'il n'a pas réglé. Les avis créés avant l'introduction de
        // cette règle sont rattrapés au passage.
        Manager::prepare_query(
            "UPDATE abone a
               INNER JOIN avis_coupure ac ON ac.id_abone = a.id
                SET ac.etat_avant = CASE WHEN ac.etat_avant IS NULL OR ac.etat_avant = '' THEN a.etat ELSE ac.etat_avant END,
                    a.etat = ?
              WHERE ac.id_aep = ? AND ac.id_mois_facturation = ?
                AND ac.date_reactivation IS NULL
                AND LOWER(TRIM(a.etat)) <> ?",
            array(self::ETAT_COUPE, $idAep, $idMois, self::ETAT_COUPE)
        );

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
     * Identifiants des abonnés du mois déjà rétablis : leur avis est figé.
     *
     * @return int[]
     */
    public static function getIdsRetablis($idAep, $idMois)
    {
        try {
            $ids = Manager::prepare_query(
                "SELECT id_abone FROM avis_coupure
                  WHERE id_aep = ? AND id_mois_facturation = ? AND date_reactivation IS NOT NULL",
                array((int) $idAep, (int) $idMois)
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return array();
        }
        return $ids ? array_map('intval', $ids) : array();
    }

    /**
     * Abonnés coupés en attente de rétablissement, toutes campagnes confondues.
     *
     * Un abonné n'apparaît qu'une fois, avec son avis le plus récent, l'impayé
     * constaté à l'époque, les frais de remise en service de cette campagne et
     * sa dernière facture — celle sur laquelle le règlement sera enregistré.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getCoupesEnAttente($idAep)
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        if ($idAep <= 0) {
            return array();
        }
        try {
            $lignes = Manager::prepare_query(
                "SELECT ac.id AS id_avis, ac.id_abone, ac.id_mois_facturation, ac.nom_abone, ac.montant_impaye,
                        ac.nb_mois_impayes, ac.date_avis, mf.mois AS mois_avis,
                        a.nom, a.etat, a.numero_telephone, r.nom AS nom_reseau,
                        COALESCE(c.frais_remise, 0) AS frais_remise,
                        (SELECT v.id_facture FROM vue_abones_facturation v
                          WHERE v.id_abone = a.id ORDER BY v.mois DESC LIMIT 1) AS id_facture_derniere,
                        (SELECT v.mois FROM vue_abones_facturation v
                          WHERE v.id_abone = a.id ORDER BY v.mois DESC LIMIT 1) AS mois_derniere,
                        (SELECT v.id_mois FROM vue_abones_facturation v
                          WHERE v.id_abone = a.id ORDER BY v.mois DESC LIMIT 1) AS id_mois_derniere
                   FROM avis_coupure ac
                   INNER JOIN abone a ON a.id = ac.id_abone
                   INNER JOIN reseau r ON r.id = a.id_reseau
                   INNER JOIN mois_facturation mf ON mf.id = ac.id_mois_facturation
                   LEFT JOIN avis_coupure_campagne c
                          ON c.id_aep = ac.id_aep AND c.id_mois_facturation = ac.id_mois_facturation
                  WHERE ac.id_aep = ? AND ac.date_reactivation IS NULL
                    AND LOWER(TRIM(a.etat)) <> ?
                  ORDER BY mf.mois DESC, a.nom ASC",
                array($idAep, self::ETAT_ACTIF)
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
        // Un seul avis par abonné : le plus récent.
        $parAbone = array();
        foreach ($lignes ? $lignes : array() as $ligne) {
            $idAbone = (int) $ligne['id_abone'];
            if (!isset($parAbone[$idAbone])) {
                // Les frais non enregistrés pour cette campagne sont ceux hérités.
                if ((float) $ligne['frais_remise'] <= 0) {
                    $ligne['frais_remise'] = self::fraisRemiseHerites($idAep, (int) $ligne['id_mois_facturation']);
                }
                $parAbone[$idAbone] = $ligne;
            }
        }
        return array_values($parAbone);
    }

    /**
     * Rétablit un abonné coupé après règlement.
     *
     * Le montant versé et les frais de remise en service sont portés sur la
     * dernière facture de l'abonné (versement d'un côté, pénalité de l'autre,
     * pour que le solde de la facture reste juste), l'abonné repasse « actif »
     * et l'avis reçoit la date, le montant, les frais et l'auteur du
     * rétablissement. L'avis n'est jamais supprimé.
     *
     * @param int    $idAbone
     * @param float  $montantVerse montant réglé par l'abonné (frais compris)
     * @param string $date         date du règlement
     * @param float  $frais        frais de remise en service appliqués
     * @param int    $idUser       utilisateur qui enregistre
     * @return array<string,mixed>|string informations sur le rétablissement, ou message d'erreur
     */
    public static function retablir($idAep, $idAbone, $montantVerse, $date, $frais, $idUser)
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        $idAbone = (int) $idAbone;
        if ($idAep <= 0 || $idAbone <= 0) {
            return 'Abonné inconnu.';
        }
        $montantVerse = round((float) $montantVerse, 2);
        $frais = max(0, round((float) $frais, 2));
        if ($montantVerse < 0) {
            return 'Le montant versé ne peut pas être négatif.';
        }
        $date = self::normaliserDate($date);
        if ($date === null) {
            return 'La date du règlement est invalide.';
        }

        $enAttente = null;
        foreach (self::getCoupesEnAttente($idAep) as $ligne) {
            if ((int) $ligne['id_abone'] === $idAbone) {
                $enAttente = $ligne;
                break;
            }
        }
        if ($enAttente === null) {
            return "Cet abonné n'est pas en attente de rétablissement.";
        }
        if (empty($enAttente['id_facture_derniere'])) {
            return "Aucune facture n'existe pour cet abonné : impossible d'y enregistrer le règlement.";
        }
        $idFacture = (int) $enAttente['id_facture_derniere'];

        if ($montantVerse > 0 || $frais > 0) {
            Manager::prepare_query(
                "UPDATE facture
                    SET montant_verse = COALESCE(montant_verse, 0) + ?,
                        penalite = COALESCE(penalite, 0) + ?,
                        date_paiement = ?
                  WHERE id = ?",
                array($montantVerse, $frais, $date, $idFacture)
            );
        }

        // Tous les avis en attente de l'abonné sont clos ; le règlement n'est
        // porté que sur le plus récent pour ne pas être compté deux fois.
        Manager::prepare_query(
            "UPDATE avis_coupure
                SET date_reactivation = ?, id_user_reactivation = ?,
                    montant_reactivation = 0, frais_reactivation = 0
              WHERE id_aep = ? AND id_abone = ? AND date_reactivation IS NULL",
            array($date, (int) $idUser > 0 ? (int) $idUser : null, $idAep, $idAbone)
        );
        Manager::prepare_query(
            "UPDATE avis_coupure
                SET montant_reactivation = ?, frais_reactivation = ?,
                    id_facture_reactivation = ?, id_mois_reactivation = ?
              WHERE id = ?",
            array(
                $montantVerse,
                $frais,
                $idFacture,
                !empty($enAttente['id_mois_derniere']) ? (int) $enAttente['id_mois_derniere'] : null,
                (int) $enAttente['id_avis'],
            )
        );
        Manager::prepare_query("UPDATE abone SET etat = ? WHERE id = ?", array(self::ETAT_ACTIF, $idAbone));

        return array(
            'nom' => $enAttente['nom'],
            'montant' => $montantVerse,
            'frais' => $frais,
            'mois' => $enAttente['mois_derniere'],
        );
    }

    /**
     * Derniers rétablissements enregistrés pour l'AEP, les plus récents d'abord.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getRetablissements($idAep, $limite = 30)
    {
        self::ensureTables();
        $idAep = (int) $idAep;
        if ($idAep <= 0) {
            return array();
        }
        try {
            $lignes = Manager::prepare_query(
                "SELECT ac.id AS id_avis, ac.id_abone, ac.nom_abone, ac.montant_impaye, ac.nb_mois_impayes,
                        ac.date_avis, ac.date_reactivation, ac.montant_reactivation, ac.frais_reactivation,
                        mf.mois AS mois_avis, mfr.mois AS mois_reglement,
                        a.nom, a.etat, r.nom AS nom_reseau,
                        u.nom AS user_nom, u.prenom AS user_prenom
                   FROM avis_coupure ac
                   INNER JOIN abone a ON a.id = ac.id_abone
                   INNER JOIN reseau r ON r.id = a.id_reseau
                   INNER JOIN mois_facturation mf ON mf.id = ac.id_mois_facturation
                   LEFT JOIN mois_facturation mfr ON mfr.id = ac.id_mois_reactivation
                   LEFT JOIN users u ON u.id = ac.id_user_reactivation
                  WHERE ac.id_aep = ? AND ac.date_reactivation IS NOT NULL
                    AND (ac.montant_reactivation > 0 OR ac.frais_reactivation > 0 OR ac.id_facture_reactivation IS NOT NULL)
                  ORDER BY ac.date_reactivation DESC, ac.id DESC
                  LIMIT " . (int) max(1, $limite),
                array($idAep)
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
        return $lignes ? $lignes : array();
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
                "SELECT a.nom, a.etat,
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
