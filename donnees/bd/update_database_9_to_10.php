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
            if (!empty(trim($statement))) {
                try {
                    $bd->exec($statement);
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
            echo "- Vues mises à jour\n";

        } catch (Exception $e) {
            echo "\n✗ Erreur lors de la migration : " . $e->getMessage() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
            throw $e;
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

// Exécuter la migration si le script est appelé directement
if (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1')) {
    DatabaseUpdater9To10::updateDatabase();
} else {
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
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        .btn:hover { background: #0056b3; }
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
        </ul>
        <p><strong>Note :</strong> Le script est idempotent, vous pouvez l'exécuter plusieurs fois sans risque.</p>
        <a href='?run_update=1' class='btn'>Lancer la migration</a>
    </div>
</body>
</html>";
}
