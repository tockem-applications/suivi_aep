<?php
/**
 * Données post-migration pour la synthèse compte d'exploitation (partie 14).
 * À lancer après restore d'une vieille base ou si la migration 9→10 structure est déjà faite.
 *
 * Usage :
 *   http://votre-site/donnees/bd/post_migration_synthese_data.php?run_update=1
 *   php donnees/bd/post_migration_synthese_data.php
 */

$scriptDir = __DIR__;
$donneesDir = dirname($scriptDir);
@include_once($donneesDir . DIRECTORY_SEPARATOR . 'connexion.php');
@include_once($donneesDir . DIRECTORY_SEPARATOR . 'manager.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'update_database_9_to_10.php');

$isMainScript = !empty($_SERVER['SCRIPT_FILENAME'])
    && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__);

if ($isMainScript && (php_sapi_name() === 'cli' || (isset($_GET['run_update']) && $_GET['run_update'] === '1'))) {
    echo "=== Post-migration données synthèse ===\n\n";
    DatabaseUpdater9To10::runPostMigrationSyntheseOnly();
    echo "\nTerminé.\n";
} elseif ($isMainScript) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Données synthèse</title></head><body>';
    echo '<h1>Données synthèse compte d\'exploitation</h1>';
    echo '<p>Attribue les codes <code>CAT{id}</code> aux catégories sans code et affiche un diagnostic des codes à harmoniser.</p>';
    echo '<p><a href="?run_update=1">Lancer</a> · <a href="../../index.php">Retour application</a></p>';
    echo '</body></html>';
}
