<?php
/**
 * Migration : ajout du champ `type_distribution` sur la table `aep`.
 *
 * Valeurs :
 *   - 'RDS' : Refoulement Distribution Séparé
 *   - 'RDC' : Refoulement Distribution Confondu (aussi appelé « partagé »)
 *
 * Idempotent : peut être ré-exécuté sans risque.
 *
 * Exécution via navigateur :
 *   http://votre-site/donnees/bd/update_database_type_distribution.php?run_update=1
 * Exécution CLI :
 *   php donnees/bd/update_database_type_distribution.php
 */

@include_once(__DIR__ . '/../manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/../connexion.php');
@include_once('donnees/connexion.php');

class DatabaseUpdaterTypeDistribution
{
    public static function columnExists($tableName, $columnName)
    {
        $bd = Connexion::connect();
        $stmt = $bd->prepare(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
        );
        $stmt->execute(array($tableName, $columnName));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && (int) $row['c'] > 0;
    }

    public static function run()
    {
        echo "=== Migration : type_distribution sur la table aep ===\n\n";
        $bd = Connexion::connect();

        if (self::columnExists('aep', 'type_distribution')) {
            echo "✓ Colonne `aep.type_distribution` déjà présente.\n";
            return true;
        }

        echo "→ Ajout de la colonne `aep.type_distribution`...\n";
        try {
            $bd->exec(
                "ALTER TABLE `aep`
                 ADD COLUMN `type_distribution` ENUM('RDS','RDC') NULL DEFAULT NULL
                 COMMENT 'Type de réseau: RDS=Refoulement Distribution Séparé, RDC=Refoulement Distribution Confondu'"
            );
            echo "✓ Colonne ajoutée.\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Erreur : " . $e->getMessage() . "\n";
            return false;
        }
    }
}

if (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1')) {
    DatabaseUpdaterTypeDistribution::run();
}
