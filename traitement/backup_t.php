<?php

@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");

class Backup_t
{
    public static function phpSqlDump($pdo, $db, $filePath)
    {
        $fh = fopen($filePath, 'w');
//        var_dump(__DIR__.'/../donnees/');
        if (!$fh) {
            throw new Exception('Impossible de créer le fichier d\'export');
        }
        fwrite($fh, "-- Export SQL généré par l'application (PHP)\n");
        fwrite($fh, "SET NAMES utf8;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = array();
        $stmt = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            $res = $pdo->query('SHOW CREATE TABLE `' . $table . '`');
            $create = $res->fetch(PDO::FETCH_NUM);
            fwrite($fh, "\n-- -----------------------------\n");
            fwrite($fh, "-- Structure de la table `" . $table . "`\n");
            fwrite($fh, "-- -----------------------------\n");
            fwrite($fh, 'DROP TABLE IF EXISTS `' . $table . '`;' . "\n");
            fwrite($fh, $create[1] . ";\n\n");

            fwrite($fh, "-- Données de `" . $table . "`\n");
            $columns = array();
            $colsStmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
            while ($c = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = '`' . $c['Field'] . '`';
            }
            $columnsList = implode(',', $columns);

            $limit = 1000;
            $offset = 0;
            $hasRows = false;
            while (true) {
                $dataStmt = $pdo->query('SELECT * FROM `' . $table . '` LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset);
                $rows = $dataStmt->fetchAll(PDO::FETCH_NUM);
                if (!$rows || count($rows) === 0)
                    break;
                $hasRows = true;

                $valuesChunks = array();
                foreach ($rows as $row) {
                    $vals = array();
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $v = str_replace(array("\\", "'"), array("\\\\", "\\'"), (string) $val);
                            $vals[] = "'" . $v . "'";
                        }
                    }
                    $valuesChunks[] = '(' . implode(',', $vals) . ')';
                }
                $insert = 'INSERT INTO `' . $table . '` (' . $columnsList . ') VALUES ' . implode(',', $valuesChunks) . ";\n";
                fwrite($fh, $insert);
                $offset += $limit;
            }
            if ($hasRows)
                fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            throw new Exception('Le fichier généré est vide');
        }
    }
    public static function exportSql()
    {
        $success = 'ok';
        try {
            $host = 'localhost';
            $db = 'suivi_aep_fokoue';
            $user = 'root';
            $pass = '';

            $timestamp = date('Ymd_His');
            $backupDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'backups';
//            var_dump($backupDir);
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0777, true);
            }
            $fileName = 'backup_' . $timestamp . '.sql';
            $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

            // Détecter le chemin mysqldump (WAMP Windows)
            $candidates = array(
                'mysqldump',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql5.7.\bin\\mysqldump.exe',
                'C:\\wamp\\bin\\mysql\\mysql5.7.\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe'
            );
            $mysqldump = null;
            foreach ($candidates as $c) {
                // Si c'est un chemin absolu, vérifier l'existence
                if (strpos($c, ':\\') !== false) {
                    if (file_exists($c)) {
                        $mysqldump = '"' . $c . '"';
                        break;
                    }
                } else {
                    // Conserver la commande simple (si présente dans le PATH)
                    $mysqldump = $c;
                }
            }

            if (!function_exists('exec')) {
                throw new Exception('La fonction exec est désactivée sur ce serveur. Activez-la ou faites l\'export via phpMyAdmin.');
            }

            if ($mysqldump === null) {
                throw new Exception('mysqldump introuvable. Ajoutez son chemin au PATH ou mettez à jour $candidates.');
            }

            // Construire la commande mysqldump (attention quoting Windows)
            $cmd = $mysqldump . ' -h ' . escapeshellarg($host) . ' -u ' . escapeshellarg($user);
            if ($pass !== '') {
                // Sous Windows, éviter l'espace entre -p et le mot de passe
                $cmd .= ' -p' . $pass;
            }
            $cmd .= ' ' . escapeshellarg($db) . ' > ' . '"' . $filePath . '"';

            $output = array();
            $returnVar = 0;
            @exec($cmd, $output, $returnVar);

            if ($returnVar !== 0 || !file_exists($filePath) || filesize($filePath) === 0) {
                $pdo = Connexion::connect();
                self::phpSqlDump($pdo, $db, $filePath);
                header('Location: ..?page=backup&success=backup_created_php');
                exit;
            }

            header('Location: ..?page=backup&success=backup_created');
            exit;
        } catch (Exception $e) {
            header('Location: ..?page=backup&error=backup_failed&message=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public static function applySql()
    {
        try {
            if (!isset($_FILES['sql_file']) || !is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
                throw new Exception('Aucun fichier SQL reçu');
            }

            // 1) Sauvegarde préalable
            self::exportSql(); // redirige, on veut éviter la redirection ici => on factoriserait normalement.
        } catch (Exception $e) {
            // Si exportSql redirige, on ne passe pas ici. Pour éviter la redirection, on duplique l'export minimal:
        }

        // Re-implémentation locale sans redirection (mini export)
        $host = 'localhost';
        $db = 'suivi_aep_fokoue';
        $user = 'root';
        $pass = '';
        $timestamp = date('Ymd_His');
        $backupDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0777, true);
        }
        $preFile = $backupDir . DIRECTORY_SEPARATOR . 'pre_apply_' . $timestamp . '.sql';
        try {
            $pdo = Connexion::connect();
            self::phpSqlDump($pdo, $db, $preFile);
        } catch (Exception $e) {
            header('Location: ..?page=backup&error=prebackup_failed&message=' . urlencode($e->getMessage()));
            exit;
        }

        // 2) Appliquer le fichier uploadé
        $tmp = $_FILES['sql_file']['tmp_name'];
        $content = file_get_contents($tmp);
        if ($content === false || $content === '') {
            header('Location: ..?page=backup&error=empty_file');
            exit;
        }

        // Tenter via mysql.exe si dispo, sinon via PDO
        $applied = false;
        // Chercher mysql.exe
        $candidates = array(
            'mysql',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.\\bin\\mysql.exe',
            'C:\\wamp64\\bin\\mysql\\mysql5.7.\\bin\\mysql.exe',
            'C:\\wamp\\bin\\mysql\\mysql5.7.\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            'C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin\\mysql.exe'
        );
        $mysqlBin = null;
        foreach ($candidates as $c) {
            if (strpos($c, ':\\') !== false) {
                if (file_exists($c)) {
                    $mysqlBin = '"' . $c . '"';
                    break;
                }
            } else {
                $mysqlBin = $c;
            }
        }

        if (function_exists('exec') && $mysqlBin !== null) {
            $cmd = $mysqlBin . ' -h ' . escapeshellarg($host) . ' -u ' . escapeshellarg($user);
            if ($pass !== '') {
                $cmd .= ' -p' . $pass;
            }
            $cmd .= ' ' . escapeshellarg($db) . ' < ' . '"' . $tmp . '"';
            $output = array();
            $code = 0;
            @exec($cmd, $output, $code);
            if ($code === 0) {
                $applied = true;
            }
        }

        if (!$applied) {
            // Application via PDO: découper naïvement par ';' en prenant en compte les lignes
            try {
                $pdo = Connexion::connect();
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                $buffer = '';
                $handle = fopen($tmp, 'r');
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        $trim = trim($line);
                        if ($trim === '' || substr($trim, 0, 2) === '--')
                            continue;
                        $buffer .= $line;
                        if (substr(rtrim($line), -1) === ';') {
                            $pdo->exec($buffer);
                            $buffer = '';
                        }
                    }
                    fclose($handle);
                } else {
                    throw new Exception('Impossible de lire le fichier');
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                $applied = true;
            } catch (Exception $e) {
                header('Location: ..?page=backup&error=apply_failed&message=' . urlencode($e->getMessage()));
                exit;
            }
        }

        header('Location: ..?page=backup&success=applied&prebackup=' . urlencode(basename($preFile)));
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'export_sql') {
    Backup_t::exportSql();
}
if (isset($_POST['action']) && $_POST['action'] === 'apply_sql') {
    Backup_t::applySql();
}


