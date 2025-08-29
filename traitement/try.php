<?php
class FacturationTable
{
    private static $bd = null;

    /**
     * Prépare et exécute une requête SQL avec PDO.
     * @param string $query La requête SQL avec des placeholders
     * @param array $data Les paramètres pour la requête
     * @return PDOStatement|bool Retourne l'objet PDOStatement si succès, false sinon
     * @throws Exception En cas d'erreur SQL
     */
    public static function prepare_query($query, $data)
    {
        try {
            if (self::$bd == null) {
                self::$bd = Connexion::connect();
            }
            $req = self::$bd->prepare($query);
            $res = $req->execute($data);
            return $res ? $req : false;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Génère un fichier .sql pour la base de données ou une table spécifique.
     * @param string|null $table Nom de la table à exporter (null pour toute la base)
     * @param bool $include_data Inclure les données (true) ou seulement le schéma (false)
     * @return array Résultat avec succès, chemin du fichier, et message
     */
    public static function generateDatabaseScript($table = null, $include_data = true)
    {
        try {
            // Informations de connexion (doivent correspondre à Connexion.php)
            $db_host = 'localhost';
            $db_name = 'suivi_aep_fokoue';
            $db_user = 'root';
            $db_pass = ''; // Remplacez par votre mot de passe si nécessaire

            // Chemin pour le fichier temporaire
            $backup_path = 'C:/wamp/www/fokoue/suivi_reseau/backups/';
            if (!is_dir($backup_path)) {
                mkdir($backup_path, 0755, true);
            }
            $backup_file = $backup_path . 'backup_' . date('Ymd_His') . '.sql';

            // Construire la commande mysqldump
            $command = "mysqldump -h {$db_host} -u {$db_user}";
            if ($db_pass) {
                $command .= " -p" . escapeshellarg($db_pass);
            }
            $command .= " {$db_name}";
            if ($table) {
                $command .= " {$table}";
            }
            if (!$include_data) {
                $command .= " --no-data";
            }
            $command .= " > " . escapeshellarg($backup_file);

            // Exécuter la commande
            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception("Échec de la génération du script SQL. Code de retour : {$return_var}");
            }

            if (!file_exists($backup_file) || filesize($backup_file) == 0) {
                throw new Exception("Le fichier SQL n'a pas été généré ou est vide.");
            }

            return array(
                'success' => true,
                'file_path' => $backup_file,
                'message' => 'Script SQL généré avec succès.'
            );
        } catch (Exception $e) {
            error_log("Erreur dans FacturationTable::generateDatabaseScript: " . $e->getMessage());
            return array(
                'success' => false,
                'file_path' => null,
                'message' => 'Erreur lors de la génération du script : ' . $e->getMessage()
            );
        }
    }
}
    // ... Autres méthodes existantes (updateMonth, deleteMonth, etc.) ...

if (isset($_GET['download_sql']) && $_GET['download_sql'] === 'true') {
    $table = isset($_GET['table']) ? $_GET['table'] : null;
    $include_data = isset($_GET['include_data']) && $_GET['include_data'] === 'false' ? false : true;

    $result = FacturationTable::generateDatabaseScript($table, $include_data);

    if ($result['success']) {
        $file_path = $result['file_path'];
        $file_name = basename($file_path);

        // Envoyer les en-têtes pour le téléchargement
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');

        // Lire et envoyer le fichier
        readfile($file_path);

        // Supprimer le fichier temporaire après téléchargement
        unlink($file_path);

        exit();
    } else {
        echo $result['message'];
    }
} else {
    echo "Paramètres manquants.";
}

