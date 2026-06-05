<?php
require_once __DIR__ . '/_web_guard.php';
bd_web_guard();
/**
 * Script de mise à jour de la base de données pour les Bornes Fontaines
 * Ce script est idempotent : peut être exécuté plusieurs fois sans erreur
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

class DatabaseUpdaterBF
{
    private static $bd = null;

    public static function connect()
    {
        if (self::$bd === null) {
            // Vérifier que la classe Connexion est disponible
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

    public static function executeQuery($query, $params = array())
    {
        try {
            $bd = self::connect();
            if (empty($params)) {
                // Pour les requêtes DDL (CREATE, ALTER, etc.), utiliser exec directement
                return $bd->exec($query) !== false;
            } else {
                // Pour les requêtes avec paramètres, utiliser prepare
                $stmt = $bd->prepare($query);
                return $stmt->execute($params);
            }
        } catch (Exception $e) {
            error_log("Erreur lors de l'exécution de la requête: " . $e->getMessage());
            error_log("Requête: " . $query);
            echo "   ✗ Erreur: " . $e->getMessage() . "\n";
            return false;
        }
    }

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
        
        // Exécuter chaque instruction séparément
        $bd = self::connect();
        $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || substr($statement, 0, 2) === '--') {
                continue;
            }
            try {
                $bd->exec($statement);
            } catch (Exception $e) {
                // Ignorer les erreurs de colonnes/tables existantes
                if (strpos($e->getMessage(), 'Duplicate column') === false && 
                    strpos($e->getMessage(), 'already exists') === false) {
                    error_log("Erreur SQL: " . $e->getMessage());
                }
            }
        }
        
        $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
        return true;
    }

    public static function updateDatabase()
    {
        echo "=== Début de la mise à jour de la base de données pour les Bornes Fontaines ===\n\n";

        // 1. Ajouter le champ type_abone
        echo "1. Vérification du champ type_abone dans la table abone...\n";
        if (!self::columnExists('abone', 'type_abone')) {
            echo "   → Ajout du champ type_abone...\n";
            $query = "ALTER TABLE abone ADD COLUMN type_abone VARCHAR(2) DEFAULT 'BP' 
                      COMMENT 'BP=Branchement Privé, BF=Borne Fontaine' AFTER id_reseau";
            if (self::executeQuery($query)) {
                echo "   ✓ Champ type_abone ajouté avec succès\n";
                // Mettre à jour les abonnés existants
                self::executeQuery("UPDATE abone SET type_abone = 'BP' WHERE type_abone IS NULL OR type_abone = ''");
            } else {
                echo "   ✗ Erreur lors de l'ajout du champ type_abone\n";
            }
        } else {
            echo "   ✓ Champ type_abone existe déjà\n";
        }

        // 2. Créer la table borne_fontaine
        echo "\n2. Vérification de la table borne_fontaine...\n";
        if (!self::tableExists('borne_fontaine')) {
            echo "   → Création de la table borne_fontaine...\n";
            $bd = self::connect();
            $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                $query = "CREATE TABLE `borne_fontaine` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Table des Bornes Fontaines'";
                $bd->exec($query);
                $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                echo "   ✓ Table borne_fontaine créée avec succès\n";
            } catch (Exception $e) {
                $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                echo "   ✗ Erreur lors de la création de la table borne_fontaine: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✓ Table borne_fontaine existe déjà\n";
        }

        // 3. Créer la table bf_gerant
        echo "\n3. Vérification de la table bf_gerant...\n";
        if (!self::tableExists('bf_gerant')) {
            echo "   → Création de la table bf_gerant...\n";
            $bd = self::connect();
            $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                $query = "CREATE TABLE `bf_gerant` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Table des gérants des Bornes Fontaines avec historique'";
                $bd->exec($query);
                $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                echo "   ✓ Table bf_gerant créée avec succès\n";
            } catch (Exception $e) {
                $bd->exec('SET FOREIGN_KEY_CHECKS = 1');
                echo "   ✗ Erreur lors de la création de la table bf_gerant: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✓ Table bf_gerant existe déjà\n";
        }

        // 4. Créer l'index pour améliorer les performances
        echo "\n4. Vérification de l'index sur type_abone...\n";
        try {
            $bd = self::connect();
            $bd->exec("CREATE INDEX IF NOT EXISTS idx_abone_type ON abone (type_abone)");
            echo "   ✓ Index créé ou existe déjà\n";
        } catch (Exception $e) {
            echo "   ⚠ Index peut-être déjà existant\n";
        }

        echo "\n=== Mise à jour terminée ===\n";
    }
}

// Exécuter la mise à jour si le script est appelé directement
if (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1')) {
    DatabaseUpdaterBF::updateDatabase();
}
