<?php
/**
 * Script principal de mise à jour de la base de données
 * Exécute toutes les mises à jour nécessaires dans l'ordre
 * 
 * Usage:
 *   - Via navigateur: http://votre-site/donnees/bd/update_all.php?run_update=1
 *   - Via ligne de commande: php donnees/bd/update_all.php
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

/**
 * Classe pour gérer toutes les mises à jour
 */
class DatabaseUpdaterAll
{
    private static $errors = array();
    private static $warnings = array();
    private static $success = array();

    /**
     * Exécute toutes les mises à jour
     */
    public static function updateAll()
    {
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║     MISE À JOUR COMPLÈTE DE LA BASE DE DONNÉES              ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

        $startTime = microtime(true);
        $updateCount = 0;
        $successCount = 0;

        // Liste des scripts de mise à jour à exécuter dans l'ordre chronologique
        // L'ordre est important car certaines mises à jour dépendent des précédentes
        $updates = array(
            // Mise à jour 1: Tarifs différenciés (ajout de tarif_differencie, tarif_differencie_autorise)
            array(
                'name' => 'Tarifs différenciés',
                'description' => 'Ajout des tarifs différenciés et du champ tarif_differencie_autorise',
                'file' => __DIR__ . DIRECTORY_SEPARATOR . 'update_database.php',
                'class' => 'DatabaseUpdater',
                'method' => 'updateDatabase',
                'version' => '1.0.0',
                'date' => '2024'
            ),
            // Mise à jour 2: Bornes Fontaines (ajout de type_abone, tables borne_fontaine et bf_gerant)
            array(
                'name' => 'Bornes Fontaines',
                'description' => 'Ajout du système de gestion des Bornes Fontaines (BF) et gérants',
                'file' => __DIR__ . DIRECTORY_SEPARATOR . 'update_database_borne_fontaine.php',
                'class' => 'DatabaseUpdaterBF',
                'method' => 'updateDatabase',
                'version' => '1.1.0',
                'date' => '2024'
            ),
            // Mise à jour 3: Redevances améliorées (calcul estimatif, base de calcul, type de calcul)
            array(
                'name' => 'Redevances améliorées',
                'description' => 'Système de redevances avec calcul estimatif, base de calcul (vente eau/branchements), type de calcul (pourcentage/montant fixe)',
                'file' => __DIR__ . DIRECTORY_SEPARATOR . 'update_database_redevance.php',
                'class' => 'DatabaseUpdaterRedevance',
                'method' => 'updateDatabase',
                'version' => '1.2.0',
                'date' => '2024'
            ),
            // Mise à jour 4: Figer les tarifs différenciés dans les factures (id_tarif_differencie dans indexes)
            array(
                'name' => 'Figer les tarifs différenciés',
                'description' => 'Ajout de id_tarif_differencie dans indexes pour figer le tarif utilisé au moment de la création de la facture',
                'file' => __DIR__ . DIRECTORY_SEPARATOR . 'update_database_fixer_tarifs.php',
                'class' => 'UpdateDatabaseFixerTarifs',
                'method' => 'updateDatabase',
                'version' => '1.3.0',
                'date' => '2024'
            ),
            array(
                'name' => 'Migration version 9 → 10',
                'description' => 'Compte rendu, catégories flux, codes budgétaires, réseaux, RDS/RDC, UTF-8, données synthèse',
                'file' => __DIR__ . DIRECTORY_SEPARATOR . 'update_database_9_to_10.php',
                'class' => 'DatabaseUpdater9To10',
                'method' => 'updateDatabase',
                'version' => '10.0.0',
                'date' => '2025'
            )
            // Ajouter ici les futures mises à jour dans l'ordre chronologique
            // Exemple:
            // array(
            //     'name' => 'Nouvelle fonctionnalité',
            //     'description' => 'Description de la mise à jour',
            //     'file' => __DIR__ . DIRECTORY_SEPARATOR . 'update_nouvelle_feature.php',
            //     'class' => 'DatabaseUpdaterNouvelleFeature',
            //     'method' => 'updateDatabase',
            //     'version' => '1.2.0',
            //     'date' => '2024'
            // )
        );

        // Exécuter chaque mise à jour
        foreach ($updates as $index => $update) {
            $updateNum = $index + 1;
            $totalUpdates = count($updates);
            
            echo "\n" . str_repeat("═", 70) . "\n";
            echo "MISE À JOUR $updateNum/$totalUpdates : " . $update['name'] . "\n";
            if (isset($update['description'])) {
                echo "Description: " . $update['description'] . "\n";
            }
            if (isset($update['version'])) {
                echo "Version: " . $update['version'];
                if (isset($update['date'])) {
                    echo " (" . $update['date'] . ")";
                }
                echo "\n";
            }
            echo str_repeat("═", 70) . "\n\n";

            if (!file_exists($update['file'])) {
                $error = "Fichier introuvable: " . $update['file'];
                echo "   ✗ $error\n";
                self::$errors[] = $error;
                continue;
            }

            // Inclure le fichier de mise à jour
            try {
                require_once($update['file']);
                
                // Vérifier que la classe existe
                if (!class_exists($update['class'])) {
                    $error = "Classe {$update['class']} introuvable dans " . basename($update['file']);
                    echo "   ✗ $error\n";
                    self::$errors[] = $error;
                    continue;
                }

                // Exécuter la mise à jour
                $updateCount++;
                try {
                    call_user_func(array($update['class'], $update['method']));
                    $successCount++;
                    self::$success[] = $update['name'];
                    echo "\n   ✓ Mise à jour '{$update['name']}' terminée avec succès\n";
                } catch (Exception $e) {
                    $error = "Erreur lors de la mise à jour '{$update['name']}': " . $e->getMessage();
                    echo "\n   ✗ $error\n";
                    self::$errors[] = $error;
                }
            } catch (Exception $e) {
                $error = "Erreur lors du chargement de '{$update['name']}': " . $e->getMessage();
                echo "   ✗ $error\n";
                self::$errors[] = $error;
            }
        }

        // Résumé
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        echo "\n" . str_repeat("═", 70) . "\n";
        echo "RÉSUMÉ DE LA MISE À JOUR\n";
        echo str_repeat("═", 70) . "\n";
        echo "Durée totale: {$duration} secondes\n";
        echo "Total des mises à jour disponibles: " . count($updates) . "\n";
        echo "Mises à jour exécutées: $updateCount\n";
        echo "Mises à jour réussies: $successCount\n";
        echo "Mises à jour échouées: " . count(self::$errors) . "\n";
        echo "Mises à jour ignorées: " . (count($updates) - $updateCount) . "\n";

        if (count(self::$success) > 0) {
            echo "\n✓ Mises à jour réussies:\n";
            foreach (self::$success as $success) {
                echo "   - $success\n";
            }
        }

        if (count(self::$errors) > 0) {
            echo "\n✗ Erreurs rencontrées:\n";
            foreach (self::$errors as $error) {
                echo "   - $error\n";
            }
        }

        if (count(self::$warnings) > 0) {
            echo "\n⚠ Avertissements:\n";
            foreach (self::$warnings as $warning) {
                echo "   - $warning\n";
            }
        }

        echo "\n" . str_repeat("═", 70) . "\n";
        
        if (count(self::$errors) == 0) {
            echo "✓ Toutes les mises à jour ont été appliquées avec succès !\n";
        } else {
            echo "⚠ Certaines mises à jour ont échoué. Vérifiez les erreurs ci-dessus.\n";
        }
        
        echo str_repeat("═", 70) . "\n";
    }
}

// Exécuter toutes les mises à jour si le script est appelé directement
if (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1')) {
    DatabaseUpdaterAll::updateAll();
} else {
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Mise à jour de la base de données</title>
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
        <h1>Mise à jour de la base de données</h1>
        <div class='warning'>
            <strong>⚠ Attention :</strong> Ce script va modifier la structure de votre base de données.
            Assurez-vous d'avoir une sauvegarde avant de continuer.
        </div>
        <p>Ce script va exécuter <strong>toutes</strong> les mises à jour de la base de données depuis le début du projet :</p>
        <ul>
            <li><strong>Tarifs différenciés</strong> - Ajout des tarifs différenciés et du champ tarif_differencie_autorise</li>
            <li><strong>Bornes Fontaines</strong> - Ajout du système de gestion des Bornes Fontaines (BF) et gérants</li>
        </ul>
        <p><strong>Note :</strong> Les scripts sont idempotents, vous pouvez les exécuter plusieurs fois sans risque.</p>
        <a href='?run_update=1' class='btn'>Lancer toutes les mises à jour</a>
    </div>
</body>
</html>";
}
