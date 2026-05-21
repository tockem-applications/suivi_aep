<?php
/**
 * Migration : ordre d'affichage des catégories flux manuel (comptes d'exploitation).
 * Idempotent.
 *
 * http://votre-site/donnees/bd/update_database_categorie_ordre.php?run_update=1
 * php donnees/bd/update_database_categorie_ordre.php
 */

@include_once(__DIR__ . '/../manager.php');
@include_once('donnees/manager.php');
@include_once(__DIR__ . '/../connexion.php');
@include_once('donnees/connexion.php');

class DatabaseUpdaterCategorieOrdre
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
        echo "=== Migration : ordre_affichage sur categorie_flux_manuel ===\n\n";
        $bd = Connexion::connect();

        if (!self::columnExists('categorie_flux_manuel', 'ordre_affichage')) {
            echo "→ Ajout de la colonne ordre_affichage...\n";
            try {
                $bd->exec(
                    "ALTER TABLE `categorie_flux_manuel`
                     ADD COLUMN `ordre_affichage` INT(11) NOT NULL DEFAULT 0
                     COMMENT 'Ordre d''affichage dans les comptes d''exploitation (croissant)'"
                );
                echo "✓ Colonne ajoutée.\n";
            } catch (Exception $e) {
                echo "✗ Erreur : " . $e->getMessage() . "\n";
                return false;
            }
        } else {
            echo "✓ Colonne ordre_affichage déjà présente.\n";
        }

        echo "→ Initialisation des ordres existants...\n";
        try {
            $bd->exec(
                "UPDATE categorie_flux_manuel
                 SET ordre_affichage = id * 10
                 WHERE ordre_affichage IS NULL OR ordre_affichage = 0"
            );
            echo "✓ Ordres initialisés.\n";
        } catch (Exception $e) {
            echo "⚠ " . $e->getMessage() . "\n";
        }

        return true;
    }
}

if (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1')) {
    DatabaseUpdaterCategorieOrdre::run();
} else {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migration ordre catégories</title></head><body>";
    echo "<p><a href='?run_update=1'>Lancer la migration ordre_affichage</a></p>";
    echo "<p><a href='../../index.php?page=categories_flux_manuel'>Retour aux catégories</a></p></body></html>";
}
