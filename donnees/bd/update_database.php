<?php
require_once __DIR__ . '/_web_guard.php';
bd_web_guard();
/**
 * Script de mise à jour de la base de données pour les tarifs différenciés
 * Ce script est idempotent : peut être exécuté plusieurs fois sans erreur
 * Garantit la rétrocompatibilité avec les données existantes
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");

class DatabaseUpdater
{
    private static $bd = null;

    public static function connect()
    {
        if (self::$bd === null) {
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
     * Vérifie si une vue existe
     */
    public static function viewExists($viewName)
    {
        $bd = self::connect();
        $query = "SELECT COUNT(*) as count FROM information_schema.views 
                  WHERE table_schema = DATABASE() AND table_name = ?";
        $stmt = $bd->prepare($query);
        $stmt->execute(array($viewName));
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
     * Exécute une requête SQL
     */
    public static function executeQuery($query, $params = array())
    {
        try {
            $bd = self::connect();
            $stmt = $bd->prepare($query);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Erreur lors de l'exécution de la requête: " . $e->getMessage());
            error_log("Requête: " . $query);
            return false;
        }
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
        
        return self::executeQuery($sql);
    }

    /**
     * Exécute toutes les mises à jour
     */
    public static function updateDatabase()
    {
        echo "=== Début de la mise à jour de la base de données ===\n\n";

        // 1. Créer la table tarif_differencie
        echo "1. Vérification de la table tarif_differencie...\n";
        if (!self::tableExists('tarif_differencie')) {
            echo "   → Création de la table tarif_differencie...\n";
            if (self::executeSqlFile(__DIR__ . '/create_tarif_differencie.sql')) {
                echo "   ✓ Table tarif_differencie créée avec succès\n";
            } else {
                echo "   ✗ Erreur lors de la création de la table tarif_differencie\n";
            }
        } else {
            echo "   ✓ Table tarif_differencie existe déjà\n";
        }

        // 2. Ajouter le champ tarif_differencie_autorise à la table abone
        echo "\n2. Vérification du champ tarif_differencie_autorise dans la table abone...\n";
        if (!self::columnExists('abone', 'tarif_differencie_autorise')) {
            echo "   → Ajout du champ tarif_differencie_autorise...\n";
            $query = "
                ALTER TABLE `abone` 
                ADD COLUMN `tarif_differencie_autorise` tinyint(1) NOT NULL DEFAULT 1 
                COMMENT 'Indique si le tarif différencié peut être appliqué (1=oui, 0=non)'";
            if (self::executeQuery($query)) {
                echo "   ✓ Champ tarif_differencie_autorise ajouté avec succès\n";
            } else {
                echo "   ✗ Erreur lors de l'ajout du champ tarif_differencie_autorise\n";
            }
        } else {
            echo "   ✓ Champ tarif_differencie_autorise existe déjà\n";
        }

        // 3. Créer la vue vue_indexes_tarifs_resolved
        echo "\n3. Mise à jour de la vue vue_indexes_tarifs_resolved...\n";
        echo "   → Recréation de la vue vue_indexes_tarifs_resolved...\n";
        if (self::executeSqlFile(__DIR__ . '/create_vue_indexes_tarifs_resolved.sql')) {
            echo "   ✓ Vue vue_indexes_tarifs_resolved créée/mise à jour avec succès\n";
        } else {
            echo "   ✗ Erreur lors de la création/mise à jour de la vue vue_indexes_tarifs_resolved\n";
        }

        // 4. Mettre à jour la vue vue_abones_facturation
        echo "\n4. Mise à jour de la vue vue_abones_facturation...\n";
        echo "   → Recréation de la vue vue_abones_facturation...\n";
        if (self::executeSqlFile(__DIR__ . '/create_vue_abones_facturation.sql')) {
            echo "   ✓ Vue vue_abones_facturation créée/mise à jour avec succès\n";
        } else {
            echo "   ✗ Erreur lors de la création/mise à jour de la vue vue_abones_facturation\n";
        }

        echo "\n=== Mise à jour terminée ===\n";
        echo "\nNote: Les anciennes factures continuent de fonctionner avec les tarifs de base.\n";
        echo "Les tarifs différenciés s'appliquent uniquement aux nouvelles factures si configurés.\n";
    }
}

// Exécuter la mise à jour si le script est appelé directement
if (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1')) {
    DatabaseUpdater::updateDatabase();
}
// Pour exécuter via navigateur: http://votre-site/donnees/bd/update_database.php?run_update=1
