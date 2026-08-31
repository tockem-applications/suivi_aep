<?php
require_once __DIR__ . '/_web_guard.php';
bd_web_guard();
/**
 * Script de mise à jour de la base de données pour le nouveau système de redevances
 * 
 * Modifications :
 * - Ajout des champs base_calcul, type_calcul, montant_par_m3 à la table redevance
 * - Toutes les redevances sont des sorties (est_sortie = 1)
 * - Support pour calcul basé sur vente d'eau ou branchements
 * - Support pour calcul par pourcentage ou montant fixe par m³
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

class DatabaseUpdaterRedevance
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

    public static function updateDatabase()
    {
        echo "=== Début de la mise à jour de la base de données pour les Redevances ===\n\n";

        try {
            $bd = self::connect();
            
            // Note: Les opérations DDL (ALTER TABLE) committent automatiquement la transaction
            // On ne peut donc pas utiliser de transaction pour ces opérations
            
            // 1. Ajouter les nouveaux champs à la table redevance
            self::addNewColumns($bd);

            // 2. Mettre à jour les données existantes (opération DML)
            // On peut utiliser une transaction pour cette partie
            $transactionStarted = false;
            try {
                $bd->beginTransaction();
                $transactionStarted = true;
                self::updateExistingData($bd);
                $bd->commit();
            } catch (Exception $e) {
                if ($transactionStarted) {
                    try {
                        $bd->rollback();
                    } catch (Exception $rollbackException) {
                        // Ignorer l'erreur de rollback si la transaction n'est plus active
                    }
                }
                throw $e;
            }

            // 3. Ajouter les contraintes si nécessaire
            self::addConstraints($bd);

            echo "\n✓ Mise à jour terminée avec succès\n";
        } catch (Exception $e) {
            echo "\n✗ Erreur lors de la mise à jour : " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private static function addNewColumns($bd)
    {
        echo "1. Ajout des nouveaux champs à la table redevance...\n";

        // Vérifier et ajouter base_calcul
        $columns = self::getTableColumns($bd, 'redevance');
        if (!in_array('base_calcul', $columns)) {
            echo "   → Ajout du champ base_calcul...\n";
            $bd->exec("ALTER TABLE redevance ADD COLUMN base_calcul VARCHAR(20) NOT NULL DEFAULT 'vente_eau' COMMENT 'vente_eau ou branchements'");
            echo "   ✓ Champ base_calcul ajouté\n";
        } else {
            echo "   ✓ Champ base_calcul existe déjà\n";
        }

        // Vérifier et ajouter type_calcul
        if (!in_array('type_calcul', $columns)) {
            echo "   → Ajout du champ type_calcul...\n";
            $bd->exec("ALTER TABLE redevance ADD COLUMN type_calcul VARCHAR(20) NOT NULL DEFAULT 'pourcentage' COMMENT 'pourcentage ou montant_fixe'");
            echo "   ✓ Champ type_calcul ajouté\n";
        } else {
            echo "   ✓ Champ type_calcul existe déjà\n";
        }

        // Vérifier et ajouter montant_par_m3
        if (!in_array('montant_par_m3', $columns)) {
            echo "   → Ajout du champ montant_par_m3...\n";
            $bd->exec("ALTER TABLE redevance ADD COLUMN montant_par_m3 DECIMAL(15,2) NULL DEFAULT NULL COMMENT 'Montant fixe par m³ si type_calcul = montant_fixe'");
            echo "   ✓ Champ montant_par_m3 ajouté\n";
        } else {
            echo "   ✓ Champ montant_par_m3 existe déjà\n";
        }

        // Vérifier et ajouter est_sortie
        if (!in_array('est_sortie', $columns)) {
            echo "   → Ajout du champ est_sortie...\n";
            $bd->exec("ALTER TABLE redevance ADD COLUMN est_sortie TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Toujours 1 car toutes les redevances sont des sorties'");
            echo "   ✓ Champ est_sortie ajouté\n";
        } else {
            echo "   ✓ Champ est_sortie existe déjà\n";
        }
    }

    private static function updateExistingData($bd)
    {
        echo "\n2. Mise à jour des données existantes...\n";

        // Mettre à jour toutes les redevances existantes
        // Par défaut, elles sont basées sur la vente d'eau avec pourcentage
        $stmt = $bd->prepare("
            UPDATE redevance 
            SET base_calcul = 'vente_eau',
                type_calcul = 'pourcentage',
                est_sortie = 1
            WHERE base_calcul IS NULL OR base_calcul = ''
        ");
        $stmt->execute();
        $affected = $stmt->rowCount();
        echo "   ✓ " . $affected . " redevance(s) mise(s) à jour\n";
    }

    private static function addConstraints($bd)
    {
        echo "\n3. Vérification des contraintes...\n";
        
        // Modifier la table versements pour permettre id_mois_facturation NULL (versements globaux)
        $columns = self::getTableColumns($bd, 'versements');
        if (in_array('id_mois_facturation', $columns)) {
            // Vérifier si la colonne permet déjà NULL
            $stmt = $bd->query("SHOW COLUMNS FROM versements WHERE Field = 'id_mois_facturation'");
            $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($columnInfo && strtoupper($columnInfo['Null']) == 'NO') {
                echo "   → Modification de la colonne id_mois_facturation pour permettre NULL...\n";
                try {
                    $bd->exec("ALTER TABLE versements MODIFY COLUMN id_mois_facturation INT(4) UNSIGNED NULL");
                    echo "   ✓ Colonne id_mois_facturation modifiée avec succès\n";
                } catch (Exception $e) {
                    echo "   ⚠ Erreur lors de la modification (peut-être déjà modifiée): " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Colonne id_mois_facturation permet déjà NULL\n";
            }
        }
        
        echo "   ✓ Contraintes vérifiées\n";
    }

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

// Exécuter la mise à jour si le script est appelé directement
if (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1')) {
    DatabaseUpdaterRedevance::updateDatabase();
} else {
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Mise à jour de la base de données - Redevances</title>
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
        <h1>Mise à jour de la base de données - Redevances</h1>
        <div class='warning'>
            <strong>⚠ Attention :</strong> Ce script va modifier la structure de votre base de données.
            Assurez-vous d'avoir une sauvegarde avant de continuer.
        </div>
        <p>Ce script va modifier le système de redevances pour supporter :</p>
        <ul>
            <li><strong>Base de calcul</strong> - Vente d'eau consommée ou Branchements</li>
            <li><strong>Type de calcul</strong> - Pourcentage ou Montant fixe par m³</li>
            <li><strong>Calcul estimatif</strong> - Pour connaître le maximum à verser</li>
            <li><strong>Suivi des versements</strong> - Montant versé et reste à verser par mois</li>
        </ul>
        <p><strong>Note :</strong> Le script est idempotent, vous pouvez l'exécuter plusieurs fois sans risque.</p>
        <a href='?run_update=1' class='btn'>Lancer la mise à jour</a>
    </div>
</body>
</html>";
}
