<?php
/**
 * Script de migration de la base de données de la version 9 à la version 10
 * 
 * ⚠ MODIFIABLE DIRECTEMENT ⚠
 * Ce fichier contient toutes les migrations de la version 9 à 10.
 * Lors de nouvelles mises à jour de la BD, ajoutez-les directement ici.
 * 
 * Migrations actuelles:
 * 1. Tarifs différenciés (table tarif_differencie, champ tarif_differencie_autorise)
 * 2. Bornes Fontaines (champ type_abone, tables borne_fontaine et bf_gerant)
 * 3. Redevances améliorées (champs base_calcul, type_calcul, montant_par_m3, est_sortie)
 * 4. Figer les tarifs différenciés (champ id_tarif_differencie dans indexes)
 * 5. Type de distribution AEP (champ type_distribution RDS/RDC sur aep)
 * 6. Ordre d'affichage des catégories flux manuels (champ ordre_affichage sur categorie_flux_manuel)
 * 
 * Pour ajouter une nouvelle migration:
 * 1. Ajoutez le code de migration dans une nouvelle section PARTIE X
 * 2. Placez-la après la PARTIE 5 (ou la dernière partie existante)
 * 
 * Ce script est idempotent : peut être exécuté plusieurs fois sans erreur
 * Ce script est automatiquement appelé par update_git.bat après chaque mise à jour Git
 */

// Inclure les fichiers nécessaires - essayer plusieurs chemins possibles
$scriptDir = __DIR__; // donnees/bd/
$donneesDir = dirname($scriptDir); // donnees/
$rootDir = dirname($donneesDir); // racine du projet

// Essayer d'inclure depuis donnees/ (chemin le plus probable)
@include_once($donneesDir . DIRECTORY_SEPARATOR . "connexion.php");
@include_once($donneesDir . DIRECTORY_SEPARATOR . "manager.php");

// Essayer aussi depuis la racine (si le script est appelé depuis un autre contexte)
@include_once($rootDir . DIRECTORY_SEPARATOR . "donnees" . DIRECTORY_SEPARATOR . "connexion.php");
@include_once($rootDir . DIRECTORY_SEPARATOR . "donnees" . DIRECTORY_SEPARATOR . "manager.php");

// Vérifier que Connexion est chargée
if (!class_exists('Connexion')) {
    $connexionPath1 = $donneesDir . DIRECTORY_SEPARATOR . "connexion.php";
    $connexionPath2 = $rootDir . DIRECTORY_SEPARATOR . "donnees" . DIRECTORY_SEPARATOR . "connexion.php";
    die("Erreur: Impossible de charger la classe Connexion.\n" .
        "Chemins testés:\n" .
        "  1. " . $connexionPath1 . " (" . (file_exists($connexionPath1) ? "existe" : "n'existe pas") . ")\n" .
        "  2. " . $connexionPath2 . " (" . (file_exists($connexionPath2) ? "existe" : "n'existe pas") . ")\n" .
        "Script dir: " . $scriptDir . "\n");
}

class DatabaseUpdater9To10
{
    private static $bd = null;

    public static function connect()
    {
        if (self::$bd === null) {
            if (!class_exists('Connexion')) {
                $baseDir = dirname(__DIR__);
                $rootDir = dirname($baseDir);
                @include_once($baseDir . "/connexion.php");
                @include_once($rootDir . "/donnees/connexion.php");
            }
            if (!class_exists('Connexion')) {
                die("Erreur: La classe Connexion n'a pas pu être chargée. Vérifiez les chemins d'inclusion.");
            }
            self::$bd = Connexion::connect();
        }
        return self::$bd;
    }

    /**
     * Vérifie si une table existe
     */
    public static function tableExists($tableName)
    {
        $bd = self::connect();
        $query = "SELECT COUNT(*) as count FROM information_schema.tables 
                  WHERE table_schema = DATABASE() AND table_name = ?";
        $stmt = $bd->prepare($query);
        $stmt->execute(array($tableName));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['count'] > 0;
    }

    /**
     * Vérifie si une colonne existe dans une table
     */
    public static function columnExists($tableName, $columnName)
    {
        $bd = self::connect();
        $query = "SELECT COUNT(*) as count FROM information_schema.columns 
                  WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
        $stmt = $bd->prepare($query);
        $stmt->execute(array($tableName, $columnName));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['count'] > 0;
    }

    /**
     * Exécute un fichier SQL
     */
    public static function executeSqlFile($filePath)
    {
        if (!file_exists($filePath)) {
            echo "   ✗ Fichier SQL introuvable: $filePath\n";
            return false;
        }

        $sql = file_get_contents($filePath);
        if ($sql === false) {
            echo "   ✗ Erreur lors de la lecture du fichier: $filePath\n";
            return false;
        }

        $bd = self::connect();

        // Pour les vues, on doit d'abord supprimer les commentaires et exécuter directement
        // Les vues avec CREATE OR REPLACE VIEW doivent être exécutées en une seule fois
        if (preg_match('/CREATE\s+(?:OR\s+REPLACE\s+)?VIEW/i', $sql)) {
            // Supprimer les commentaires de ligne et de bloc
            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

            // Nettoyer les espaces multiples
            $sql = preg_replace('/\s+/', ' ', $sql);
            $sql = trim($sql);

            // Supprimer le point-virgule final s'il existe
            $sql = rtrim($sql, ';');

            try {
                $bd->exec($sql);
                return true;
            } catch (Exception $e) {
                echo "   ⚠ Erreur lors de la création de la vue: " . $e->getMessage() . "\n";
                return false;
            }
        }

        // Pour les autres types de requêtes, utiliser l'ancienne méthode
        $bd->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Diviser le SQL en plusieurs requêtes
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*/', $stmt);
            }
        );

        foreach ($statements as $statement) {
            $trimmedStatement = trim($statement);
            if ($trimmedStatement !== '') {
                try {
                    $bd->exec($trimmedStatement);
                } catch (Exception $e) {
                    // Ignorer les erreurs de colonnes/tables existantes
                    if (
                        strpos($e->getMessage(), 'Duplicate column') === false &&
                        strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate key') === false
                    ) {
                        echo "   ⚠ Erreur SQL (peut être ignorée): " . $e->getMessage() . "\n";
                    }
                }
            }
        }

        $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
        return true;
    }

    /**
     * Exécute toutes les mises à jour de la version 9 à 10
     */
    public static function updateDatabase()
    {
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║     MIGRATION DE LA BASE DE DONNÉES VERSION 9 → 10           ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

        try {
            $bd = self::connect();

            // ============================================================
            // PARTIE 1: TARIFS DIFFÉRENCIÉS
            // ============================================================
            echo "═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 1: TARIFS DIFFÉRENCIÉS\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 1.1. Créer la table tarif_differencie
            echo "1.1. Vérification de la table tarif_differencie...\n";
            if (!self::tableExists('tarif_differencie')) {
                echo "   → Création de la table tarif_differencie...\n";
                try {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
                    $bd->exec("
                        CREATE TABLE IF NOT EXISTS `tarif_differencie` (
                            `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
                            `id_constante_reseau` int(2) unsigned NOT NULL,
                            `prix_metre_cube_eau` int(5) unsigned NOT NULL,
                            `prix_entretient_compteur` int(5) unsigned NOT NULL,
                            `prix_tva` decimal(7,2) unsigned NOT NULL,
                            `min_consommation` decimal(10,2) unsigned NOT NULL DEFAULT '0.00',
                            `max_consommation` decimal(10,2) unsigned DEFAULT NULL,
                            `date_creation` date NOT NULL,
                            `description` text,
                            PRIMARY KEY (`id`),
                            KEY `fk_constante_tarif_differencie` (`id_constante_reseau`),
                            CONSTRAINT `fk_constante_tarif_differencie` FOREIGN KEY (`id_constante_reseau`) REFERENCES `constante_reseau` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=latin1
                    ");
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✓ Table tarif_differencie créée avec succès\n";
                } catch (Exception $e) {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✗ Erreur lors de la création: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Table tarif_differencie existe déjà\n";
            }

            // 1.2. Ajouter le champ tarif_differencie_autorise à la table abone
            echo "\n1.2. Vérification du champ tarif_differencie_autorise dans la table abone...\n";
            if (!self::columnExists('abone', 'tarif_differencie_autorise')) {
                echo "   → Ajout du champ tarif_differencie_autorise...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `abone` 
                        ADD COLUMN `tarif_differencie_autorise` tinyint(1) NOT NULL DEFAULT 1 
                        COMMENT 'Indique si le tarif différencié peut être appliqué (1=oui, 0=non)'
                    ");
                    echo "   ✓ Champ tarif_differencie_autorise ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ tarif_differencie_autorise existe déjà\n";
            }

            // ============================================================
            // PARTIE 2: BORNES FONTAINES
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 2: BORNES FONTAINES\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 2.1. Ajouter le champ type_abone
            echo "2.1. Vérification du champ type_abone dans la table abone...\n";
            if (!self::columnExists('abone', 'type_abone')) {
                echo "   → Ajout du champ type_abone...\n";
                try {
                    $bd->exec("
                        ALTER TABLE abone 
                        ADD COLUMN type_abone VARCHAR(2) DEFAULT 'BP' 
                        COMMENT 'BP=Branchement Privé, BF=Borne Fontaine' 
                        AFTER id_reseau
                    ");
                    // Mettre à jour les abonnés existants
                    $bd->exec("UPDATE abone SET type_abone = 'BP' WHERE type_abone IS NULL OR type_abone = ''");
                    echo "   ✓ Champ type_abone ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ type_abone existe déjà\n";
            }

            // 2.2. Créer la table borne_fontaine
            echo "\n2.2. Vérification de la table borne_fontaine...\n";
            if (!self::tableExists('borne_fontaine')) {
                echo "   → Création de la table borne_fontaine...\n";
                try {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
                    $bd->exec("
                        CREATE TABLE `borne_fontaine` (
                            `id` INT(4) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `id_abone` INT(4) UNSIGNED NOT NULL,
                            `numero_borne` VARCHAR(32) DEFAULT NULL COMMENT 'Numéro d''identification de la borne',
                            `localisation` VARCHAR(128) DEFAULT NULL COMMENT 'Localisation de la borne',
                            `date_creation` DATETIME NOT NULL,
                            `date_modification` DATETIME DEFAULT NULL,
                            `description` TEXT DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `id_abone` (`id_abone`),
                            KEY `idx_numero_borne` (`numero_borne`),
                            CONSTRAINT `fk_borne_fontaine_abone` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Table des Bornes Fontaines'
                    ");
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✓ Table borne_fontaine créée avec succès\n";
                } catch (Exception $e) {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✗ Erreur lors de la création: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Table borne_fontaine existe déjà\n";
            }

            // 2.3. Créer la table bf_gerant
            echo "\n2.3. Vérification de la table bf_gerant...\n";
            if (!self::tableExists('bf_gerant')) {
                echo "   → Création de la table bf_gerant...\n";
                try {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
                    $bd->exec("
                        CREATE TABLE `bf_gerant` (
                            `id` INT(4) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `id_borne_fontaine` INT(4) UNSIGNED NOT NULL,
                            `nom_gerant` VARCHAR(128) NOT NULL,
                            `numero_telephone` VARCHAR(16) DEFAULT NULL,
                            `numero_piece_identite` VARCHAR(32) DEFAULT NULL COMMENT 'Numéro de pièce d''identité',
                            `type_piece_identite` VARCHAR(16) DEFAULT NULL COMMENT 'CNI, Passeport, etc.',
                            `date_debut` DATE NOT NULL COMMENT 'Date de début de gestion',
                            `date_fin` DATE DEFAULT NULL COMMENT 'Date de fin de gestion (NULL si toujours actif)',
                            `est_actif` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indique si c''est le gérant actuel',
                            `date_creation` DATETIME NOT NULL,
                            `date_modification` DATETIME DEFAULT NULL,
                            `notes` TEXT DEFAULT NULL COMMENT 'Notes additionnelles sur le gérant',
                            PRIMARY KEY (`id`),
                            KEY `idx_id_borne_fontaine` (`id_borne_fontaine`),
                            KEY `idx_nom_gerant` (`nom_gerant`),
                            KEY `idx_est_actif` (`est_actif`),
                            KEY `idx_dates` (`date_debut`, `date_fin`),
                            CONSTRAINT `fk_bf_gerant_borne_fontaine` FOREIGN KEY (`id_borne_fontaine`) REFERENCES `borne_fontaine` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Table des gérants des Bornes Fontaines avec historique'
                    ");
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✓ Table bf_gerant créée avec succès\n";
                } catch (Exception $e) {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✗ Erreur lors de la création: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Table bf_gerant existe déjà\n";
            }

            // ============================================================
            // PARTIE 3: REDEVANCES AMÉLIORÉES
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 3: REDEVANCES AMÉLIORÉES\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 3.1. Ajouter les nouveaux champs à la table redevance
            echo "3.1. Ajout des nouveaux champs à la table redevance...\n";

            $columns = self::getTableColumns($bd, 'redevance');

            // base_calcul
            if (!in_array('base_calcul', $columns)) {
                echo "   → Ajout du champ base_calcul...\n";
                $bd->exec("ALTER TABLE redevance ADD COLUMN base_calcul VARCHAR(20) NOT NULL DEFAULT 'vente_eau' COMMENT 'vente_eau ou branchements'");
                echo "   ✓ Champ base_calcul ajouté\n";
            } else {
                echo "   ✓ Champ base_calcul existe déjà\n";
            }

            // type_calcul
            if (!in_array('type_calcul', $columns)) {
                echo "   → Ajout du champ type_calcul...\n";
                $bd->exec("ALTER TABLE redevance ADD COLUMN type_calcul VARCHAR(20) NOT NULL DEFAULT 'pourcentage' COMMENT 'pourcentage ou montant_fixe'");
                echo "   ✓ Champ type_calcul ajouté\n";
            } else {
                echo "   ✓ Champ type_calcul existe déjà\n";
            }

            // montant_par_m3
            if (!in_array('montant_par_m3', $columns)) {
                echo "   → Ajout du champ montant_par_m3...\n";
                $bd->exec("ALTER TABLE redevance ADD COLUMN montant_par_m3 DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'Montant fixe par m³ si type_calcul = montant_fixe'");
                echo "   ✓ Champ montant_par_m3 ajouté\n";
            } else {
                echo "   ✓ Champ montant_par_m3 existe déjà\n";
            }

            // est_sortie
            if (!in_array('est_sortie', $columns)) {
                echo "   → Ajout du champ est_sortie...\n";
                $bd->exec("ALTER TABLE redevance ADD COLUMN est_sortie TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Toujours 1 car toutes les redevances sont des sorties'");
                echo "   ✓ Champ est_sortie ajouté\n";
            } else {
                echo "   ✓ Champ est_sortie existe déjà\n";
            }

            // 3.2. Mettre à jour les données existantes
            echo "\n3.2. Mise à jour des données existantes...\n";
            try {
                $bd->beginTransaction();
                $stmt = $bd->prepare("
                    UPDATE redevance 
                    SET base_calcul = 'vente_eau',
                        type_calcul = 'pourcentage',
                        est_sortie = 1
                    WHERE base_calcul IS NULL OR base_calcul = ''
                ");
                $stmt->execute();
                $affected = $stmt->rowCount();
                $bd->commit();
                echo "   ✓ " . $affected . " redevance(s) mise(s) à jour\n";
            } catch (Exception $e) {
                try {
                    $bd->rollback();
                } catch (Exception $rollbackException) {
                    // Ignorer l'erreur de rollback
                }
                echo "   ⚠ Erreur lors de la mise à jour (peut être ignorée): " . $e->getMessage() . "\n";
            }

            // 3.3. Modifier la table versements pour permettre id_mois_facturation NULL
            echo "\n3.3. Modification de la table versements...\n";
            if (self::columnExists('versements', 'id_mois_facturation')) {
                $stmt = $bd->query("SHOW COLUMNS FROM versements WHERE Field = 'id_mois_facturation'");
                $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($columnInfo && strtoupper($columnInfo['Null']) == 'NO') {
                    echo "   → Modification de la colonne id_mois_facturation pour permettre NULL...\n";
                    try {
                        $bd->exec("ALTER TABLE versements MODIFY COLUMN id_mois_facturation INT(4) UNSIGNED NULL");
                        echo "   ✓ Colonne id_mois_facturation modifiée avec succès\n";
                    } catch (Exception $e) {
                        echo "   ⚠ Erreur (peut-être déjà modifiée): " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "   ✓ Colonne id_mois_facturation permet déjà NULL\n";
                }
            } else {
                echo "   ⚠ Colonne id_mois_facturation n'existe pas dans versements\n";
            }

            // ============================================================
            // PARTIE 4: FIGER LES TARIFS DIFFÉRENCIÉS
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 4: FIGER LES TARIFS DIFFÉRENCIÉS\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 4.1. Ajouter la colonne id_tarif_differencie dans indexes
            echo "4.1. Vérification de la colonne id_tarif_differencie dans indexes...\n";
            if (!self::columnExists('indexes', 'id_tarif_differencie')) {
                echo "   → Ajout de la colonne id_tarif_differencie...\n";
                try {
                    // Vérifier que la table tarif_differencie existe
                    if (!self::tableExists('tarif_differencie')) {
                        echo "   ⚠ Table tarif_differencie n'existe pas encore, création...\n";
                        $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
                        $bd->exec("
                            CREATE TABLE IF NOT EXISTS `tarif_differencie` (
                                `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
                                `id_constante_reseau` int(2) unsigned NOT NULL,
                                `prix_metre_cube_eau` int(5) unsigned NOT NULL,
                                `prix_entretient_compteur` int(5) unsigned NOT NULL,
                                `prix_tva` decimal(7,2) unsigned NOT NULL,
                                `min_consommation` decimal(10,2) unsigned NOT NULL DEFAULT '0.00',
                                `max_consommation` decimal(10,2) unsigned DEFAULT NULL,
                                `date_creation` date NOT NULL,
                                `description` text,
                                PRIMARY KEY (`id`),
                                KEY `fk_constante_tarif_differencie` (`id_constante_reseau`),
                                CONSTRAINT `fk_constante_tarif_differencie` FOREIGN KEY (`id_constante_reseau`) REFERENCES `constante_reseau` (`id`) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
                        ");
                        $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    }

                    $bd->exec("
                        ALTER TABLE `indexes` 
                        ADD COLUMN `id_tarif_differencie` INT(2) UNSIGNED NULL DEFAULT NULL
                        COMMENT 'ID du tarif différencié utilisé au moment de la création (NULL = tarif de base)'
                    ");

                    // Ajouter la clé étrangère seulement si la table tarif_differencie existe
                    if (self::tableExists('tarif_differencie')) {
                        try {
                            $bd->exec("
                                ALTER TABLE `indexes`
                                ADD KEY `fk_tarif_differencie_indexes` (`id_tarif_differencie`)
                            ");
                            // Essayer d'ajouter la contrainte de clé étrangère
                            try {
                                $bd->exec("
                                    ALTER TABLE `indexes`
                                    ADD CONSTRAINT `fk_tarif_differencie_indexes` 
                                        FOREIGN KEY (`id_tarif_differencie`) 
                                        REFERENCES `tarif_differencie` (`id`) 
                                        ON DELETE SET NULL
                                ");
                            } catch (Exception $e) {
                                // La contrainte peut déjà exister ou échouer pour d'autres raisons
                                if (
                                    strpos($e->getMessage(), 'Duplicate key') === false &&
                                    strpos($e->getMessage(), 'already exists') === false
                                ) {
                                    echo "   ⚠ Contrainte de clé étrangère non ajoutée (peut être ignorée): " . $e->getMessage() . "\n";
                                }
                            }
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate key') === false) {
                                echo "   ⚠ Erreur lors de l'ajout de la clé: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                    echo "   ✓ Colonne id_tarif_differencie ajoutée avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout de la colonne: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Colonne id_tarif_differencie existe déjà\n";
            }

            // 4.2. Peupler id_tarif_differencie pour les index existants
            echo "\n4.2. Peuplement de id_tarif_differencie pour les index existants...\n";
            echo "   → Calcul du tarif différencié utilisé pour chaque index...\n";

            // Vérifier que la table tarif_differencie existe avant de peupler
            if (self::tableExists('tarif_differencie') && self::columnExists('indexes', 'id_tarif_differencie')) {
                $query = "
                    UPDATE indexes i
                    INNER JOIN facture f ON f.id_indexes = i.id
                    INNER JOIN abone a ON a.id = f.id_abone
                    INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
                    INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
                    LEFT JOIN tarif_differencie td ON (
                        td.id_constante_reseau = cr.id
                        AND (i.nouvel_index - i.ancien_index) >= td.min_consommation
                        AND (td.max_consommation IS NULL OR (i.nouvel_index - i.ancien_index) < td.max_consommation)
                        AND NOT EXISTS (
                            SELECT 1
                            FROM tarif_differencie td2
                            WHERE td2.id_constante_reseau = cr.id
                            AND (i.nouvel_index - i.ancien_index) >= td2.min_consommation
                            AND (td2.max_consommation IS NULL OR (i.nouvel_index - i.ancien_index) < td2.max_consommation)
                            AND td2.min_consommation > td.min_consommation
                        )
                    )
                    SET i.id_tarif_differencie = CASE 
                        WHEN a.tarif_differencie_autorise = 1 AND td.id IS NOT NULL THEN td.id
                        ELSE NULL
                    END
                    WHERE i.id_tarif_differencie IS NULL";

                try {
                    $stmt = $bd->prepare($query);
                    $stmt->execute();
                    $rowsAffected = $stmt->rowCount();
                    echo "   ✓ " . $rowsAffected . " index mis à jour\n";
                } catch (Exception $e) {
                    echo "   ⚠ Erreur lors du peuplement (peut être ignorée): " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ⚠ Table tarif_differencie ou colonne id_tarif_differencie n'existe pas, peuplement ignoré\n";
            }

            // ============================================================
            // PARTIE 5: CRÉATION/MISE À JOUR DES VUES
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 5: CRÉATION/MISE À JOUR DES VUES\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 5.1. Mettre à jour la vue vue_indexes_tarifs_resolved
            echo "5.1. Mise à jour de la vue vue_indexes_tarifs_resolved...\n";
            $vueSqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'create_vue_indexes_tarifs_resolved.sql';
            if (file_exists($vueSqlFile)) {
                if (self::executeSqlFile($vueSqlFile)) {
                    echo "   ✓ Vue vue_indexes_tarifs_resolved créée/mise à jour avec succès\n";
                } else {
                    echo "   ⚠ Erreur lors de la mise à jour de la vue (peut être ignorée)\n";
                }
            } else {
                echo "   ⚠ Fichier SQL introuvable: $vueSqlFile\n";
            }

            // 5.2. Mettre à jour la vue vue_abones_facturation
            echo "\n5.2. Mise à jour de la vue vue_abones_facturation...\n";
            $vueSqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'create_vue_abones_facturation.sql';
            if (file_exists($vueSqlFile)) {
                if (self::executeSqlFile($vueSqlFile)) {
                    echo "   ✓ Vue vue_abones_facturation créée/mise à jour avec succès\n";
                } else {
                    echo "   ⚠ Erreur lors de la mise à jour de la vue (peut être ignorée)\n";
                }
            } else {
                echo "   ⚠ Fichier SQL introuvable: $vueSqlFile\n";
            }

            // ============================================================
            // PARTIE 6: SYSTÈME DE COMPTE RENDU FINANCIER SIMPLIFIÉ
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 6: SYSTÈME DE COMPTE RENDU FINANCIER SIMPLIFIÉ\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 6.1. Créer la table de configuration des libellés du compte rendu
            echo "6.1. Vérification de la table config_compte_rendu_financier...\n";
            if (!self::tableExists('config_compte_rendu_financier')) {
                echo "   → Création de la table config_compte_rendu_financier...\n";
                try {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
                    $bd->exec("
                        CREATE TABLE `config_compte_rendu_financier` (
                            `id` INT(2) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `code_type` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Code du type: recouvrements, branchements, redevances',
                            `libelle` VARCHAR(128) NOT NULL COMMENT 'Libellé à afficher dans le compte rendu',
                            `type_flux` VARCHAR(16) NOT NULL COMMENT 'recette ou charge',
                            `id_aep` INT(4) UNSIGNED NULL DEFAULT NULL COMMENT 'ID de l''AEP (NULL = configuration globale)',
                            `date_creation` DATETIME NOT NULL,
                            `date_modification` DATETIME DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            KEY `idx_code_type` (`code_type`),
                            KEY `idx_id_aep` (`id_aep`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Configuration des libellés pour le compte rendu financier'
                    ");
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✓ Table config_compte_rendu_financier créée avec succès\n";

                    // Insérer les configurations par défaut
                    echo "   → Insertion des configurations par défaut...\n";
                    $configs_defaut = array(
                        array('code_type' => 'recouvrements', 'libelle' => 'Recouvrements', 'type_flux' => 'recette'),
                        array('code_type' => 'branchements', 'libelle' => 'Branchements', 'type_flux' => 'recette'),
                        array('code_type' => 'redevances', 'libelle' => 'Redevances', 'type_flux' => 'charge')
                    );

                    $stmt = $bd->prepare("
                        INSERT INTO config_compte_rendu_financier (code_type, libelle, type_flux, id_aep, date_creation) 
                        VALUES (?, ?, ?, NULL, NOW())
                    ");
                    foreach ($configs_defaut as $config) {
                        try {
                            $stmt->execute(array($config['code_type'], $config['libelle'], $config['type_flux']));
                        } catch (Exception $e) {
                            // Ignorer les erreurs de duplication
                        }
                    }
                    echo "   ✓ Configurations par défaut insérées\n";
                } catch (Exception $e) {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✗ Erreur lors de la création: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Table config_compte_rendu_financier existe déjà\n";
            }

            // 6.2. Créer la table categorie_flux_manuel pour catégoriser les flux manuels
            echo "\n6.2. Vérification de la table categorie_flux_manuel...\n";
            if (!self::tableExists('categorie_flux_manuel')) {
                echo "   → Création de la table categorie_flux_manuel...\n";
                try {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
                    $bd->exec("
                        CREATE TABLE `categorie_flux_manuel` (
                            `id` INT(4) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `nom` VARCHAR(128) NOT NULL COMMENT 'Nom de la catégorie',
                            `type_flux` VARCHAR(16) NOT NULL COMMENT 'recette ou charge',
                            `description` TEXT DEFAULT NULL COMMENT 'Description de la catégorie',
                            `id_aep` INT(4) UNSIGNED NULL DEFAULT NULL COMMENT 'ID de l''AEP (NULL = catégorie globale)',
                            `est_actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indique si la catégorie est active',
                            `date_creation` DATETIME NOT NULL,
                            PRIMARY KEY (`id`),
                            KEY `idx_type_flux` (`type_flux`),
                            KEY `idx_id_aep` (`id_aep`),
                            KEY `idx_est_actif` (`est_actif`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Catégories pour les flux financiers manuels'
                    ");
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✓ Table categorie_flux_manuel créée avec succès\n";

                    // Insérer quelques catégories par défaut
                    echo "   → Insertion des catégories par défaut...\n";
                    $categories_defaut = array(
                        array('nom' => 'Maintenance', 'type_flux' => 'charge', 'description' => 'Dépenses de maintenance du réseau'),
                        array('nom' => 'Salaires', 'type_flux' => 'charge', 'description' => 'Salaires du personnel'),
                        array('nom' => 'Matériel', 'type_flux' => 'charge', 'description' => 'Achat de matériel'),
                        array('nom' => 'Autres charges', 'type_flux' => 'charge', 'description' => 'Autres dépenses diverses'),
                        array('nom' => 'Subventions', 'type_flux' => 'recette', 'description' => 'Subventions reçues'),
                        array('nom' => 'Autres recettes', 'type_flux' => 'recette', 'description' => 'Autres revenus divers')
                    );

                    $stmt = $bd->prepare("
                        INSERT INTO categorie_flux_manuel (nom, type_flux, description, id_aep, est_actif, date_creation) 
                        VALUES (?, ?, ?, NULL, 1, NOW())
                    ");
                    foreach ($categories_defaut as $cat) {
                        try {
                            $stmt->execute(array($cat['nom'], $cat['type_flux'], $cat['description']));
                        } catch (Exception $e) {
                            // Ignorer les erreurs de duplication
                        }
                    }
                    echo "   ✓ Catégories par défaut insérées\n";
                } catch (Exception $e) {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo "   ✗ Erreur lors de la création: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Table categorie_flux_manuel existe déjà\n";
            }

            // 6.3. Ajouter id_categorie_flux_manuel à la table flux_financier
            echo "\n6.3. Vérification du champ id_categorie_flux_manuel dans la table flux_financier...\n";
            if (!self::columnExists('flux_financier', 'id_categorie_flux_manuel')) {
                echo "   → Ajout du champ id_categorie_flux_manuel...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `flux_financier` 
                        ADD COLUMN `id_categorie_flux_manuel` INT(4) UNSIGNED NULL DEFAULT NULL
                        COMMENT 'Catégorie du flux manuel (NULL = non catégorisé)'
                    ");
                    // Ajouter la clé étrangère
                    if (self::tableExists('categorie_flux_manuel')) {
                        try {
                            $bd->exec("
                                ALTER TABLE `flux_financier`
                                ADD KEY `fk_flux_categorie` (`id_categorie_flux_manuel`),
                                ADD CONSTRAINT `fk_flux_categorie` 
                                    FOREIGN KEY (`id_categorie_flux_manuel`) 
                                    REFERENCES `categorie_flux_manuel` (`id`) 
                                    ON DELETE SET NULL
                            ");
                        } catch (Exception $e) {
                            if (
                                strpos($e->getMessage(), 'Duplicate key') === false &&
                                strpos($e->getMessage(), 'already exists') === false
                            ) {
                                echo "   ⚠ Contrainte de clé étrangère non ajoutée (peut être ignorée): " . $e->getMessage() . "\n";
                            }
                        }
                    }
                    echo "   ✓ Champ id_categorie_flux_manuel ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ id_categorie_flux_manuel existe déjà\n";
            }

            // ============================================================
            // PARTIE 7: AJOUT DU CHAMP MOIS DE BASE
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 7: AJOUT DU CHAMP MOIS DE BASE\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 7.1. Ajouter le champ est_mois_base à la table mois_facturation
            echo "7.1. Vérification du champ est_mois_base dans la table mois_facturation...\n";
            if (!self::columnExists('mois_facturation', 'est_mois_base')) {
                echo "   → Ajout du champ est_mois_base...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `mois_facturation` 
                        ADD COLUMN `est_mois_base` TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Indique si ce mois est le mois de base pour l''AEP (un seul par AEP)'
                    ");
                    echo "   ✓ Champ est_mois_base ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ est_mois_base existe déjà\n";
            }

            // ============================================================
            // PARTIE 8: AJOUT DES CODES BUDGÉTAIRES ET ACTIVITÉS ASSOCIÉES
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 8: AJOUT DES CODES BUDGÉTAIRES ET ACTIVITÉS ASSOCIÉES\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            // 8.1. Ajouter code_budgetaire et activite_associee à categorie_flux_manuel
            echo "8.1. Vérification des champs code_budgetaire et activite_associee dans categorie_flux_manuel...\n";
            if (!self::columnExists('categorie_flux_manuel', 'code_budgetaire')) {
                echo "   → Ajout du champ code_budgetaire...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `categorie_flux_manuel` 
                        ADD COLUMN `code_budgetaire` VARCHAR(10) NULL DEFAULT NULL
                        COMMENT 'Code budgétaire de la catégorie'
                    ");
                    echo "   ✓ Champ code_budgetaire ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ code_budgetaire existe déjà\n";
            }

            if (!self::columnExists('categorie_flux_manuel', 'activite_associee')) {
                echo "   → Ajout du champ activite_associee...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `categorie_flux_manuel` 
                        ADD COLUMN `activite_associee` ENUM('branchements', 'vente_eau', 'autre') NOT NULL DEFAULT 'autre'
                        COMMENT 'Activité associée: branchements, vente_eau, ou autre'
                    ");
                    // Ajouter index pour les regroupements
                    try {
                        $bd->exec("
                            ALTER TABLE `categorie_flux_manuel`
                            ADD KEY `idx_code_budgetaire` (`code_budgetaire`),
                            ADD KEY `idx_activite_associee` (`activite_associee`)
                        ");
                    } catch (Exception $e) {
                        if (
                            strpos($e->getMessage(), 'Duplicate key') === false &&
                            strpos($e->getMessage(), 'already exists') === false
                        ) {
                            echo "   ⚠ Index non ajouté (peut être ignoré): " . $e->getMessage() . "\n";
                        }
                    }
                    echo "   ✓ Champ activite_associee ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ activite_associee existe déjà\n";
            }

            if (!self::columnExists('categorie_flux_manuel', 'ordre_affichage')) {
                echo "   → Ajout du champ ordre_affichage...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `categorie_flux_manuel`
                        ADD COLUMN `ordre_affichage` INT(11) NOT NULL DEFAULT 0
                        COMMENT 'Ordre d''affichage dans les comptes d''exploitation'
                    ");
                    $bd->exec("
                        UPDATE categorie_flux_manuel
                        SET ordre_affichage = id * 10
                        WHERE ordre_affichage = 0
                    ");
                    echo "   ✓ Champ ordre_affichage ajouté\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur ordre_affichage : " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ ordre_affichage existe déjà\n";
            }

            // 8.2. Ajouter code_budgetaire et activite_associee à config_compte_rendu_financier
            echo "\n8.2. Vérification des champs code_budgetaire et activite_associee dans config_compte_rendu_financier...\n";
            if (!self::columnExists('config_compte_rendu_financier', 'code_budgetaire')) {
                echo "   → Ajout du champ code_budgetaire...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `config_compte_rendu_financier` 
                        ADD COLUMN `code_budgetaire` VARCHAR(10) NULL DEFAULT NULL
                        COMMENT 'Code budgétaire pour les catégories automatiques'
                    ");
                    echo "   ✓ Champ code_budgetaire ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ code_budgetaire existe déjà\n";
            }

            if (!self::columnExists('config_compte_rendu_financier', 'activite_associee')) {
                echo "   → Ajout du champ activite_associee...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `config_compte_rendu_financier` 
                        ADD COLUMN `activite_associee` ENUM('branchements', 'vente_eau', 'autre') NOT NULL DEFAULT 'autre'
                        COMMENT 'Activité associée: branchements, vente_eau, ou autre'
                    ");
                    // Mettre à jour les valeurs par défaut selon le code_type
                    try {
                        $bd->exec("
                            UPDATE config_compte_rendu_financier 
                            SET activite_associee = 'branchements' 
                            WHERE code_type = 'branchements'
                        ");
                        $bd->exec("
                            UPDATE config_compte_rendu_financier 
                            SET activite_associee = 'vente_eau' 
                            WHERE code_type = 'recouvrements'
                        ");
                        $bd->exec("
                            UPDATE config_compte_rendu_financier 
                            SET activite_associee = 'autre' 
                            WHERE code_type = 'redevances'
                        ");
                    } catch (Exception $e) {
                        echo "   ⚠ Mise à jour des valeurs par défaut non effectuée (peut être ignoré): " . $e->getMessage() . "\n";
                    }
                    echo "   ✓ Champ activite_associee ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ activite_associee existe déjà\n";
            }

            // ============================================================
            // PARTIE 10: HIÉRARCHIE DES RÉSEAUX
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 10: HIÉRARCHIE DES RÉSEAUX\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            echo "10.1. Vérification du champ id_reseau_parent dans la table reseau...\n";
            if (!self::columnExists('reseau', 'id_reseau_parent')) {
                echo "   → Ajout du champ id_reseau_parent...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `reseau`
                        ADD COLUMN `id_reseau_parent` int(5) unsigned NULL DEFAULT NULL
                        COMMENT 'Réseau parent direct pour construire un arbre'
                    ");
                    echo "   ✓ Champ id_reseau_parent ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ id_reseau_parent existe déjà\n";
            }

            echo "\n10.2. Vérification de l'index idx_reseau_parent...\n";
            try {
                $indexCheck = $bd->prepare("
                    SELECT COUNT(*) AS c
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = 'reseau'
                      AND index_name = 'idx_reseau_parent'
                ");
                $indexCheck->execute(array());
                $indexRow = $indexCheck->fetch(PDO::FETCH_ASSOC);
                if (!$indexRow || (int) $indexRow['c'] === 0) {
                    $bd->exec("CREATE INDEX `idx_reseau_parent` ON `reseau` (`id_reseau_parent`)");
                    echo "   ✓ Index idx_reseau_parent créé\n";
                } else {
                    echo "   ✓ Index idx_reseau_parent existe déjà\n";
                }
            } catch (Exception $e) {
                echo "   ⚠ Vérification/création index: " . $e->getMessage() . "\n";
            }

            // ============================================================
            // PARTIE 11: TYPES DE COMPTEURS RÉSEAU
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 11: TYPES DE COMPTEURS RÉSEAU\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            echo "11.1. Vérification du champ type_compteur dans compteur_reseau...\n";
            if (!self::columnExists('compteur_reseau', 'type_compteur')) {
                echo "   → Ajout du champ type_compteur...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `compteur_reseau`
                        ADD COLUMN `type_compteur` ENUM('production','distribution','reservoir')
                        NOT NULL DEFAULT 'distribution'
                        COMMENT 'Type du compteur réseau utilisé pour les calculs de rendement'
                    ");
                    echo "   ✓ Champ type_compteur ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ type_compteur existe déjà\n";
            }

            echo "\n11.2. Initialisation des valeurs existantes à distribution...\n";
            try {
                $updated = $bd->exec("UPDATE compteur_reseau SET type_compteur = 'distribution' WHERE type_compteur IS NULL OR type_compteur = ''");
                echo "   ✓ $updated ligne(s) initialisée(s)\n";
            } catch (Exception $e) {
                echo "   ⚠ Initialisation: " . $e->getMessage() . "\n";
            }

            // ============================================================
            // PARTIE 12: TYPE DE DISTRIBUTION AEP (RDS / RDC)
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 12: TYPE DE DISTRIBUTION AEP (RDS / RDC)\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            echo "12.1. Vérification du champ type_distribution dans la table aep...\n";
            if (!self::columnExists('aep', 'type_distribution')) {
                echo "   → Ajout du champ type_distribution...\n";
                try {
                    $bd->exec("
                        ALTER TABLE `aep`
                        ADD COLUMN `type_distribution` ENUM('RDS','RDC') NULL DEFAULT NULL
                        COMMENT 'Type de réseau: RDS=Refoulement Distribution Séparé, RDC=Refoulement Distribution Confondu'
                    ");
                    echo "   ✓ Champ type_distribution ajouté avec succès\n";
                } catch (Exception $e) {
                    echo "   ✗ Erreur lors de l'ajout du champ: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Champ type_distribution existe déjà\n";
            }

            echo "\n12.2. Vérification de l'index idx_type_distribution...\n";
            try {
                $indexCheck = $bd->prepare("
                    SELECT COUNT(*) AS c
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = 'aep'
                      AND index_name = 'idx_type_distribution'
                ");
                $indexCheck->execute(array());
                $indexRow = $indexCheck->fetch(PDO::FETCH_ASSOC);
                if (!$indexRow || (int) $indexRow['c'] === 0) {
                    $bd->exec("CREATE INDEX `idx_type_distribution` ON `aep` (`type_distribution`)");
                    echo "   ✓ Index idx_type_distribution créé\n";
                } else {
                    echo "   ✓ Index idx_type_distribution existe déjà\n";
                }
            } catch (Exception $e) {
                echo "   ⚠ Vérification/création index: " . $e->getMessage() . "\n";
            }


            // ============================================================
            // PARTIE 13: CÔTÉ RÉSEAU / OPPOSE SUR BRANCHEMENTS
            // ============================================================
            echo "\n═══════════════════════════════════════════════════════════════\n";
            echo "PARTIE 13: CÔTÉ RÉSEAU / OPPOSE (branchement_abonne.cote_reseau)\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";

            echo "13.1. Colonne cote_reseau...\n";
            if (!self::columnExists('branchement_abonne', 'cote_reseau')) {
                try {
                    $bd->exec("
                        ALTER TABLE `branchement_abonne`
                        ADD COLUMN `cote_reseau` ENUM('reseau','oppose') NULL DEFAULT NULL
                        COMMENT 'Côté réseau ou côté opposé'
                        AFTER `versement_fcfa`
                    ");
                    echo "   ✓ Colonne cote_reseau ajoutée\n";
                } catch (Exception $e) {
                    echo "   ✗ " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Colonne cote_reseau existe déjà\n";
            }

            echo "\n13.2. Initialisation par montant (62 500 / 70 000 FCFA)...\n";
            try {
                $n1 = $bd->exec("UPDATE branchement_abonne SET cote_reseau = 'reseau' WHERE versement_fcfa = 62500 AND (cote_reseau IS NULL OR cote_reseau = '')");
                $n2 = $bd->exec("UPDATE branchement_abonne SET cote_reseau = 'oppose' WHERE versement_fcfa = 70000 AND (cote_reseau IS NULL OR cote_reseau = '')");
                echo "   ✓ Côté réseau: $n1 ligne(s), côté opposé: $n2 ligne(s)\n";
            } catch (Exception $e) {
                echo "   ⚠ " . $e->getMessage() . "\n";
            }

            // ============================================================
            // PARTIE 14: DONNÉES SYNTHÈSE COMPTE D'EXPLOITATION
            // ============================================================
            self::postMigrationSyntheseCompteExploitation($bd);

            echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
            echo "║     MIGRATION TERMINÉE AVEC SUCCÈS                            ║\n";
            echo "╚═══════════════════════════════════════════════════════════════╝\n";
            echo "\nNote: Les modifications suivantes ont été appliquées:\n";
            echo "- Table tarif_differencie créée\n";
            echo "- Champ tarif_differencie_autorise ajouté à abone\n";
            echo "- Champ type_abone ajouté à abone\n";
            echo "- Tables borne_fontaine et bf_gerant créées\n";
            echo "- Champs base_calcul, type_calcul, montant_par_m3, est_sortie ajoutés à redevance\n";
            echo "- Colonne id_mois_facturation de versements peut maintenant être NULL\n";
            echo "- Champ id_tarif_differencie ajouté à indexes\n";
            echo "- Table config_compte_rendu_financier créée pour configurer les libellés du compte rendu\n";
            echo "- Table categorie_flux_manuel créée pour catégoriser les flux manuels\n";
            echo "- Champ id_categorie_flux_manuel ajouté à flux_financier\n";
            echo "- Champ est_mois_base ajouté à mois_facturation\n";
            echo "- Champs code_budgetaire, activite_associee et ordre_affichage ajoutés aux catégories\n";
            echo "- Vues mises à jour\n";
            echo "- Hiérarchie des réseaux activée (id_reseau_parent)\n";
            echo "- Types de compteurs réseau activés (production/distribution/reservoir)\n";
            echo "- Type de distribution AEP activé (RDS / RDC) — champ aep.type_distribution\n";
            echo "- Côté réseau/opposé sur branchements (cote_reseau)\n";
            echo "- Données synthèse : codes budgétaires par défaut + rapport de cohérence\n";

        } catch (Exception $e) {
            echo "\n✗ Erreur lors de la migration : " . $e->getMessage() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
            throw $e;
        }
    }

    /**
     * Relance uniquement la partie 14 (données synthèse) — utile après restore d'une vieille base déjà migrée en structure.
     */
    public static function runPostMigrationSyntheseOnly()
    {
        $bd = self::connect();
        self::postMigrationSyntheseCompteExploitation($bd);
    }

    /**
     * Post-migration : codes budgétaires manquants + diagnostic multi-libellés (synthèse multi-AEP).
     */
    private static function postMigrationSyntheseCompteExploitation($bd)
    {
        echo "\n═══════════════════════════════════════════════════════════════\n";
        echo "PARTIE 14: DONNÉES SYNTHÈSE COMPTE D'EXPLOITATION\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        if (!self::tableExists('categorie_flux_manuel')) {
            echo "   ⚠ Table categorie_flux_manuel absente — étape ignorée.\n";
            return;
        }

        if (!self::columnExists('categorie_flux_manuel', 'code_budgetaire')) {
            echo "   ⚠ Colonne code_budgetaire absente — étape ignorée.\n";
            return;
        }

        echo "14.1. Attribution de codes budgétaires provisoires (catégories actives sans code)...\n";
        try {
            $n = $bd->exec(
                "UPDATE categorie_flux_manuel
                 SET code_budgetaire = CONCAT('CAT', id)
                 WHERE est_actif = 1
                   AND (code_budgetaire IS NULL OR TRIM(code_budgetaire) = '')"
            );
            echo "   ✓ " . (int) $n . " catégorie(s) reçoivent un code CAT{id}\n";
            echo "   → Vous pouvez renommer ces codes dans l'écran Catégories flux manuels.\n";
        } catch (Exception $e) {
            echo "   ✗ " . $e->getMessage() . "\n";
        }

        if (self::columnExists('categorie_flux_manuel', 'ordre_affichage')) {
            echo "\n14.2. Vérification ordre_affichage...\n";
            try {
                $n2 = $bd->exec(
                    "UPDATE categorie_flux_manuel
                     SET ordre_affichage = id * 10
                     WHERE est_actif = 1 AND (ordre_affichage IS NULL OR ordre_affichage = 0)"
                );
                echo "   ✓ ordre_affichage initialisé pour " . (int) $n2 . " catégorie(s)\n";
            } catch (Exception $e) {
                echo "   ⚠ " . $e->getMessage() . "\n";
            }
        }

        echo "\n14.3. Diagnostic — même code budgétaire, libellés différents (fusion en synthèse)...\n";
        try {
            $stmt = $bd->query(
                "SELECT code_budgetaire,
                        COUNT(DISTINCT nom) AS nb_noms,
                        GROUP_CONCAT(DISTINCT nom ORDER BY nom SEPARATOR ' | ') AS libelles
                 FROM categorie_flux_manuel
                 WHERE est_actif = 1
                   AND code_budgetaire IS NOT NULL
                   AND TRIM(code_budgetaire) != ''
                 GROUP BY code_budgetaire
                 HAVING nb_noms > 1
                 ORDER BY code_budgetaire
                 LIMIT 50"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
            if (empty($rows)) {
                echo "   ✓ Aucun code partagé avec des libellés différents.\n";
            } else {
                echo "   ⚠ " . count($rows) . " code(s) à harmoniser (affichage max 50) :\n";
                foreach ($rows as $r) {
                    echo "      - [" . $r['code_budgetaire'] . "] " . $r['libelles'] . "\n";
                }
                echo "   → Harmonisez les noms ou attribuez des codes distincts par AEP.\n";
            }
        } catch (Exception $e) {
            echo "   ⚠ Diagnostic non exécuté : " . $e->getMessage() . "\n";
        }

        if (self::tableExists('flux_financier') && self::columnExists('flux_financier', 'id_categorie_flux_manuel')) {
            echo "\n14.4. Flux financiers sans catégorie...\n";
            try {
                $stmt = $bd->query("SELECT COUNT(*) AS c FROM flux_financier WHERE id_categorie_flux_manuel IS NULL");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                $c = $row ? (int) $row['c'] : 0;
                if ($c === 0) {
                    echo "   ✓ Tous les flux sont catégorisés.\n";
                } else {
                    echo "   ⚠ $c flux sans catégorie — à rattacher pour une synthèse complète.\n";
                }
            } catch (Exception $e) {
                echo "   ⚠ " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Récupère la liste des colonnes d'une table
     */
    private static function getTableColumns($bd, $tableName)
    {
        $stmt = $bd->query("SHOW COLUMNS FROM `$tableName`");
        $columns = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        return $columns;
    }
}

// Exécuter uniquement si ce fichier est le script principal (pas via update_all.php)
$isMainScript = !empty($_SERVER['SCRIPT_FILENAME'])
    && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__);
if ($isMainScript && (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1'))) {
    DatabaseUpdater9To10::updateDatabase();
} elseif ($isMainScript) {
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Migration Base de données 9 → 10</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; margin-right: 8px; }
        .btn-primary { background: #007bff; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .actions { margin-top: 16px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Migration Base de données Version 9 → 10</h1>
        <div class='warning'>
            <strong>⚠ Attention :</strong> Ce script va modifier la structure de votre base de données.
            Assurez-vous d'avoir une sauvegarde avant de continuer.
        </div>
        <p>Ce script va appliquer toutes les modifications suivantes :</p>
        <ul>
            <li><strong>Tarifs différenciés</strong> - Table tarif_differencie et champ tarif_differencie_autorise</li>
            <li><strong>Bornes Fontaines</strong> - Champ type_abone, tables borne_fontaine et bf_gerant</li>
            <li><strong>Redevances améliorées</strong> - Champs base_calcul, type_calcul, montant_par_m3, est_sortie</li>
            <li><strong>Figer les tarifs</strong> - Champ id_tarif_differencie dans indexes</li>
            <li><strong>Hiérarchie des réseaux</strong> - Champ id_reseau_parent et index pour structurer l'arbre des réseaux</li>
            <li><strong>Types de compteurs réseau</strong> - Champ type_compteur dans compteur_reseau (distribution par défaut)</li>
            <li><strong>Type de distribution AEP</strong> - Champ type_distribution dans aep (RDS / RDC) + index</li>
            <li><strong>Ordre catégories flux</strong> - Champ ordre_affichage sur categorie_flux_manuel (tri compte d'exploitation)</li>
        </ul>
        <p><strong>Note :</strong> Le script est idempotent, vous pouvez l'exécuter plusieurs fois sans risque.</p>
        <div class='actions'>
            <a href='../../index.php' class='btn btn-secondary'>← Retour à l'application</a>
            <a href='?run_update=1' class='btn btn-primary'>Lancer la migration</a>
        </div>
    </div>
</body>
</html>";
}
