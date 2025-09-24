<?php
// Outil de mise à jour basé sur Git/GitHub (compatible PHP 5.3.4)
// Usage (à restreindre via token et/ou IP):
//   index.php?task=update_git (si inclus) ou directement traitement/update_git.php?token=VOTRE_TOKEN
//   Options: dry_run=1, auto_stash=1

@ini_set('display_errors', 0);

// ------------------------
// Configuration minimale
// ------------------------
$CONFIG = array(
    'secret_token' => 'osd9wjsks4sdi39jd', // Modifiez ce token et conservez-le secret
    'allowed_branches' => array('recouvrement_branch'),
    'git_bin' => 'git', // Chemin vers git si nécessaire, ex: 'C:\\Program Files\\Git\\bin\\git.exe'
    'repo_dir' => realpath(dirname(__FILE__) . '/..'), // Racine du dépôt (un niveau au-dessus de traitement/)
    'remote' => 'origin',
    'enable_submodules' => false,
    'maintenance_flag' => realpath(dirname(__FILE__) . '/..') . DIRECTORY_SEPARATOR . 'maintenance.flag',
);

// ------------------------
// Helpers
// ------------------------
function respond($status, $data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('status' => $status, 'data' => $data));
    exit;
}

function run_cmd($cmd, $cwd)
{
    $descriptor = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open($cmd, $descriptor, $pipes, $cwd);
    if (!is_resource($process)) {
        return array('code' => 1, 'out' => '', 'err' => 'Impossible de lancer la commande');
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    foreach ($pipes as $p) {
        if (is_resource($p))
            fclose($p);
    }
    $code = proc_close($process);
    return array('code' => (int) $code, 'out' => (string) $out, 'err' => (string) $err);
}

function file_put_contents_silent($path, $content)
{
    @file_put_contents($path, $content);
}

// ------------------------
// Sécurité (token)
// ------------------------
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');
if ($CONFIG['secret_token'] !== 'CHANGEZ-MOI' && $token !== $CONFIG['secret_token']) {
    respond('error', 'Accès refusé: token invalide.');
}

// ------------------------
// Paramètres
// ------------------------
$dryRun = isset($_GET['dry_run']) || (isset($_POST['dry_run']) && $_POST['dry_run']);
$autoStash = isset($_GET['auto_stash']) || (isset($_POST['auto_stash']) && $_POST['auto_stash']);

$git = $CONFIG['git_bin'];
$cwd = $CONFIG['repo_dir'];
if (!is_dir($cwd)) {
    respond('error', 'Répertoire du dépôt introuvable: ' . $cwd);
}

$log = array();

// Mettre en maintenance
file_put_contents_silent($CONFIG['maintenance_flag'], date('c'));
$log[] = 'Maintenance activée.';

// Vérifier présence de git
$ver = run_cmd($git . ' --version', $cwd);
if ($ver['code'] !== 0) {
    @unlink($CONFIG['maintenance_flag']);
    respond('error', 'Git introuvable. Détail: ' . trim($ver['err'] . ' ' . $ver['out']));
}
$log[] = 'Git: ' . trim($ver['out']);

// Rendre le dépôt sûr pour Git si nécessaire (Windows/WAMP)
// On ajoute systématiquement le dossier courant en safe.directory pour éviter l'erreur "owned by someone else"
$safe = run_cmd($git . ' config --global --add safe.directory ' . escapeshellarg($cwd), $cwd);
if ($safe['code'] === 0) {
    $log[] = 'safe.directory ajouté pour: ' . $cwd;
} else {
    $log[] = 'Impossible d\'ajouter safe.directory (non bloquant): ' . trim($safe['err'] . ' ' . $safe['out']);
}

// Branche courante (avec reprise si dépôt non "safe")
$branchRes = run_cmd($git . ' rev-parse --abbrev-ref HEAD', $cwd);
if ($branchRes['code'] !== 0 && (strpos($branchRes['err'], 'unsafe repository') !== false || strpos($branchRes['out'], 'unsafe repository') !== false)) {
    $log[] = 'Dépôt non sûr détecté, tentative d\'ajout safe.directory...';
    $pathVariants = array($cwd, str_replace('\\', '/', $cwd));
    foreach ($pathVariants as $p) {
        $g1 = run_cmd($git . ' config --global --add safe.directory ' . $p, $cwd);
        $log[] = 'safe.directory (global) ' . $p . ' => code=' . $g1['code'];
        $g2 = run_cmd($git . ' config --system --add safe.directory ' . $p, $cwd);
        $log[] = 'safe.directory (system) ' . $p . ' => code=' . $g2['code'];
    }
    // Retente
    $branchRes = run_cmd($git . ' rev-parse --abbrev-ref HEAD', $cwd);
}
if ($branchRes['code'] !== 0) {
    @unlink($CONFIG['maintenance_flag']);
    respond('error', 'Impossible de lire la branche courante: ' . trim($branchRes['err'] . ' ' . $branchRes['out']));
}
$branch = trim($branchRes['out']);
$log[] = 'Branche courante: ' . $branch;

// Contrôle de la branche
if (count($CONFIG['allowed_branches']) && !in_array($branch, $CONFIG['allowed_branches'])) {
    @unlink($CONFIG['maintenance_flag']);
    respond('error', 'Branche non autorisée pour mise à jour: ' . $branch);
}

// État de l’arbre de travail
$status = run_cmd($git . ' status --porcelain', $cwd);
if ($status['code'] === 0 && strlen(trim($status['out'])) > 0) {
    $log[] = 'Modifications locales détectées.';
    if ($autoStash) {
        $stash = run_cmd($git . ' stash push -u -m "auto-stash avant update"', $cwd);
        $log[] = 'auto-stash: code=' . $stash['code'] . ' out=' . trim($stash['out']);
    } else {
        $log[] = 'Attention: des fichiers modifiés existent (activez auto_stash=1 si besoin).';
    }
}

// Fetch
$log[] = 'Fetch des références distantes...';
if (!$dryRun) {
    $fetch = run_cmd($git . ' fetch --all --prune', $cwd);
    if ($fetch['code'] !== 0) {
        @unlink($CONFIG['maintenance_flag']);
        respond('error', 'Échec du fetch: ' . trim($fetch['err'] . ' ' . $fetch['out']));
    }
}

// Pull fast-forward only
$log[] = 'Pull ff-only depuis ' . $CONFIG['remote'] . '/' . $branch . ' ...';
if (!$dryRun) {
    $pull = run_cmd($git . ' pull --ff-only ' . escapeshellarg($CONFIG['remote']) . ' ' . escapeshellarg($branch), $cwd);
    if ($pull['code'] !== 0) {
        @unlink($CONFIG['maintenance_flag']);
        respond('error', 'Échec du pull: ' . trim($pull['err'] . ' ' . $pull['out']));
    }
}

// Submodules
if ($CONFIG['enable_submodules']) {
    $log[] = 'Mise à jour des sous-modules...';
    if (!$dryRun) {
        $sub = run_cmd($git . ' submodule update --init --recursive', $cwd);
        if ($sub['code'] !== 0) {
            @unlink($CONFIG['maintenance_flag']);
            respond('error', 'Échec submodules: ' . trim($sub['err'] . ' ' . $sub['out']));
        }
    }
}

// Invalidation cache PHP si disponible
if (function_exists('opcache_reset')) {
    @opcache_reset();
    $log[] = 'OPcache réinitialisé.';
} elseif (function_exists('apc_clear_cache')) {
    @apc_clear_cache();
    @apc_clear_cache('opcode');
    $log[] = 'APC cache vidé.';
}

// Sortie maintenance
@unlink($CONFIG['maintenance_flag']);
$log[] = 'Maintenance désactivée.';

respond('ok', array(
    'branch' => $branch,
    'dry_run' => (bool) $dryRun,
    'auto_stash' => (bool) $autoStash,
    'messages' => $log,
));

?>
