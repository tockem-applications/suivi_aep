<?php
/**
 * Script de mise à jour pour figer les tarifs différenciés utilisés dans les factures
 * 
 * Problème : Actuellement, les factures utilisent les tarifs différenciés de manière dynamique
 * via tarif_differencie_autorise. Si on change tarif_differencie_autorise, toutes les factures
 * passées sont recalculées, ce qui n'est pas souhaitable.
 * 
 * Solution : Stocker le tarif différencié utilisé (id_tarif_differencie) dans la table indexes
 * au moment de la création de l'index. Ainsi, les factures passées conservent leur tarif.
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

class UpdateDatabaseFixerTarifs
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
                die("Erreur: La classe Connexion n'est pas disponible.\n");
            }
            self::$bd = Connexion::connect();
        }
        return self::$bd;
    }

    /**
     * Vérifie si une colonne existe dans une table
     */
    public static function columnExists($table, $column)
    {
        try {
            $bd = self::connect();
            $stmt = $bd->prepare("SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ?");
            $stmt->execute(array($table, $column));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
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
            echo "   ✗ Erreur SQL: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Mise à jour de la base de données
     */
    public static function updateDatabase()
    {
        echo "=== Mise à jour : Figer les tarifs différenciés dans les factures ===\n\n";

        // 1. Ajouter la colonne id_tarif_differencie dans indexes
        echo "1. Vérification de la colonne id_tarif_differencie dans indexes...\n";
        if (!self::columnExists('indexes', 'id_tarif_differencie')) {
            echo "   → Ajout de la colonne id_tarif_differencie...\n";
            $query = "
                ALTER TABLE `indexes` 
                ADD COLUMN `id_tarif_differencie` INT(2) UNSIGNED NULL DEFAULT NULL
                COMMENT 'ID du tarif différencié utilisé au moment de la création (NULL = tarif de base)',
                ADD KEY `fk_tarif_differencie_indexes` (`id_tarif_differencie`),
                ADD CONSTRAINT `fk_tarif_differencie_indexes` 
                    FOREIGN KEY (`id_tarif_differencie`) 
                    REFERENCES `tarif_differencie` (`id`) 
                    ON DELETE SET NULL";
            if (self::executeQuery($query)) {
                echo "   ✓ Colonne id_tarif_differencie ajoutée avec succès\n";
            } else {
                echo "   ✗ Erreur lors de l'ajout de la colonne id_tarif_differencie\n";
                return false;
            }
        } else {
            echo "   ✓ Colonne id_tarif_differencie existe déjà\n";
        }

        // 2. Peupler id_tarif_differencie pour les index existants
        echo "\n2. Peuplement de id_tarif_differencie pour les index existants...\n";
        echo "   → Calcul du tarif différencié utilisé pour chaque index...\n";
        
        // Requête pour mettre à jour id_tarif_differencie en fonction de:
        // - tarif_differencie_autorise de l'abonné
        // - consommation de l'index
        // - tarifs différenciés disponibles
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
                -- Prendre le tarif différencié avec le min_consommation le plus élevé (le plus spécifique)
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
            $bd = self::connect();
            $stmt = $bd->prepare($query);
            $stmt->execute();
            $rowsAffected = $stmt->rowCount();
            echo "   ✓ " . $rowsAffected . " index mis à jour\n";
        } catch (Exception $e) {
            echo "   ✗ Erreur lors du peuplement: " . $e->getMessage() . "\n";
            return false;
        }

        // 3. Mettre à jour la vue vue_indexes_tarifs_resolved pour utiliser id_tarif_differencie si défini
        // ATTENTION: La vue ne peut être mise à jour que si la colonne existe déjà
        echo "\n3. Mise à jour de la vue vue_indexes_tarifs_resolved...\n";
        echo "   → La vue utilisera id_tarif_differencie stocké s'il existe, sinon calculera dynamiquement...\n";
        
        // Vérifier que la colonne existe avant de mettre à jour la vue
        if (self::columnExists('indexes', 'id_tarif_differencie')) {
            // Mettre à jour la vue en exécutant le fichier SQL
            $vueSqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'create_vue_indexes_tarifs_resolved.sql';
            if (file_exists($vueSqlFile)) {
                $vueSql = file_get_contents($vueSqlFile);
                if ($vueSql) {
                    // Exécuter la requête SQL directement (pas via executeQuery car c'est une vue)
                    try {
                        $bd = self::connect();
                        // Diviser le SQL en plusieurs requêtes si nécessaire
                        $statements = array_filter(
                            array_map('trim', explode(';', $vueSql)),
                            function($stmt) {
                                return !empty($stmt) && !preg_match('/^--/', $stmt);
                            }
                        );
                        foreach ($statements as $statement) {
                            if (!empty(trim($statement))) {
                                $bd->exec($statement);
                            }
                        }
                        echo "   ✓ Vue vue_indexes_tarifs_resolved mise à jour avec succès\n";
                    } catch (Exception $e) {
                        echo "   ✗ Erreur lors de la mise à jour de la vue: " . $e->getMessage() . "\n";
                        echo "   → Fichier SQL disponible: $vueSqlFile\n";
                        echo "   → Vous pouvez l'exécuter manuellement si nécessaire\n";
                    }
                } else {
                    echo "   ✗ Impossible de lire le fichier SQL de la vue\n";
                }
            } else {
                echo "   ⚠ Fichier SQL de la vue introuvable: $vueSqlFile\n";
                echo "   → Vous devrez mettre à jour la vue manuellement\n";
            }
        } else {
            echo "   ⚠ La colonne id_tarif_differencie n'existe pas encore, la vue sera mise à jour lors de la prochaine exécution\n";
        }
        
        echo "\n✓ Mise à jour terminée avec succès\n";
        echo "\nNOTE IMPORTANTE:\n";
        echo "- Les factures existantes ont maintenant leur tarif figé dans id_tarif_differencie\n";
        echo "- Pour les nouvelles factures, le code PHP doit calculer et stocker id_tarif_differencie lors de la création\n";
        echo "- Vous devez maintenant mettre à jour:\n";
        echo "  1. La vue vue_indexes_tarifs_resolved (via create_vue_indexes_tarifs_resolved.sql)\n";
        echo "  2. Le code PHP qui crée les index pour stocker id_tarif_differencie\n";
        
        return true;
    }
}

// Exécuter la mise à jour si appelé directement
if (basename(__FILE__) == basename($_SERVER["SCRIPT_NAME"])) {
    UpdateDatabaseFixerTarifs::updateDatabase();
}