<?php
require_once __DIR__ . '/_guard.php';
traitement_guard(true);
/**
 * Endpoint AJAX : ouvrages, conduites et import de tuiles pour la cartographie.
 * Réponse JSON uniquement.
 */
header('Content-Type: application/json; charset=utf-8');

@include_once(__DIR__ . '/../donnees/manager.php');
@include_once(__DIR__ . '/../donnees/cartographie.php');

Cartographie::ensureTables();

$id_aep = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
if (!$id_aep) {
    echo json_encode(array('ok' => false, 'error' => 'Aucun AEP sélectionné.'));
    exit;
}

// Requete POST arrivee vide alors qu'un corps a bien ete envoye : PHP a rejete
// la requete entiere car elle depasse post_max_size. Dans ce cas $_POST est
// vide, jeton CSRF et action compris — sans ce controle, le message d'erreur
// renvoye serait trompeur ("Action inconnue" ou "Jeton CSRF invalide").
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && count($_POST) === 0
    && isset($_SERVER['CONTENT_LENGTH'])
    && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
    $limite = ini_get('post_max_size');
    echo json_encode(array(
        'ok' => false,
        'error' => 'Envoi trop volumineux : ' . round((int) $_SERVER['CONTENT_LENGTH'] / 1048576, 1)
            . ' Mo reçus alors que la limite du serveur (post_max_size) est de ' . $limite . '.',
    ));
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

/**
 * Les actions d'écriture exigent le droit "écriture" sur la page cartographie
 * (table page_role_aep, comme le reste de l'application) — au-delà de la simple
 * authentification déjà vérifiée par traitement_guard().
 */
function carto_peut_ecrire($page = 'cartographie')
{
    @include_once(__DIR__ . '/user_t.php');
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($userId <= 0 || !class_exists('AuthManager')) {
        return false;
    }
    return AuthManager::checkPageAccess($userId, $page) >= 1;
}

function carto_require_ecriture($page = 'cartographie')
{
    if (!carto_peut_ecrire($page)) {
        echo json_encode(array('ok' => false, 'error' => "Droit d'écriture requis sur la page " . $page . "."));
        exit;
    }
}

/**
 * @param string $mois
 * @return bool
 */
function csc_mois_valide_carto($mois)
{
    return is_string($mois) && preg_match('/^\d{4}-\d{2}$/', $mois);
}

/**
 * Vérifie que le contenu extrait est bien une image (garde-fou anti-RCE : on
 * n'écrit sur le disque que ce qui est réellement une image, jamais un script).
 * Passe par un fichier temporaire car getimagesizefromstring() est PHP 5.4+.
 *
 * @param string $contenu
 * @return bool
 */
function carto_est_image_valide($contenu)
{
    $tmp = tempnam(sys_get_temp_dir(), 'carto_tuile_');
    if ($tmp === false) {
        return false;
    }
    $ok = false;
    if (@file_put_contents($tmp, $contenu) !== false) {
        $info = @getimagesize($tmp);
        $ok = ($info !== false)
            && isset($info[2])
            && in_array($info[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG), true);
    }
    @unlink($tmp);
    return $ok;
}

// ---------------------------------------------------------------------
// Lecture
// ---------------------------------------------------------------------

if ($action === 'get_donnees') {
    $mois = isset($_GET['mois']) && csc_mois_valide_carto($_GET['mois'])
        ? $_GET['mois']
        : Cartographie::getMoisActif($id_aep);

    echo json_encode(array(
        'ok' => true,
        'compteurs' => Cartographie::getCompteursAvecIndicateurs($id_aep, $mois),
        'ouvrages' => Cartographie::getOuvrages($id_aep),
        'conduites' => Cartographie::getConduites($id_aep),
        'secteurs' => Cartographie::getSecteurs($id_aep),
        'stats_geoloc' => Cartographie::getStatsGeoloc($id_aep),
        'tuiles' => Cartographie::getTuilesMeta($id_aep),
        'mois_disponibles' => Cartographie::getMoisDisponibles($id_aep),
        'mois_courant' => $mois,
        'peut_ecrire' => carto_peut_ecrire(),
    ));
    exit;
}

if ($action === 'get_anomalies') {
    echo json_encode(array('ok' => true, 'anomalies' => Cartographie::getAnomalies($id_aep)));
    exit;
}

// ---------------------------------------------------------------------
// Affectation de coordonnées importées (page affectation_coordonnees)
// ---------------------------------------------------------------------

if ($action === 'get_abonnes_reseau') {
    $id_reseau = isset($_GET['id_reseau']) ? (int) $_GET['id_reseau'] : 0;
    if ($id_reseau <= 0) {
        echo json_encode(array('ok' => false, 'error' => 'Réseau non précisé.'));
        exit;
    }
    echo json_encode(array(
        'ok' => true,
        'abonnes' => Cartographie::getAbonnesReseau($id_aep, $id_reseau),
    ));
    exit;
}

if ($action === 'affecter_coordonnees') {
    carto_require_ecriture('affectation_coordonnees');
    Csrf::requireValid('Jeton CSRF invalide.', true);

    $brut = isset($_POST['affectations']) ? json_decode($_POST['affectations'], true) : null;
    if (!is_array($brut) || count($brut) === 0) {
        echo json_encode(array('ok' => false, 'error' => 'Aucune affectation reçue.'));
        exit;
    }

    $resultat = Cartographie::affecterCoordonnees($id_aep, $brut);
    echo json_encode(array(
        'ok' => true,
        'affectes' => $resultat['affectes'],
        'ignores' => $resultat['ignores'],
    ));
    exit;
}

// ---------------------------------------------------------------------
// Secteurs (polygones) et statistiques agrégées
// ---------------------------------------------------------------------

if ($action === 'save_secteur') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $polyRaw = isset($_POST['polygone']) ? json_decode($_POST['polygone'], true) : null;

    if ($nom === '') {
        echo json_encode(array('ok' => false, 'error' => 'Nom du secteur requis.'));
        exit;
    }
    if (!is_array($polyRaw) || count($polyRaw) < 3) {
        echo json_encode(array('ok' => false, 'error' => 'Polygone invalide (au moins 3 points requis).'));
        exit;
    }
    $polygone = array();
    foreach ($polyRaw as $p) {
        if (is_array($p) && count($p) >= 2) {
            $polygone[] = array((float) $p[0], (float) $p[1]);
        }
    }
    if (count($polygone) < 3) {
        echo json_encode(array('ok' => false, 'error' => 'Polygone invalide.'));
        exit;
    }

    $newId = Cartographie::saveSecteur($id, $id_aep, $nom, $polygone, $description);
    echo json_encode(array('ok' => $newId > 0, 'id' => $newId));
    exit;
}

if ($action === 'delete_secteur') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    Cartographie::deleteSecteur($id, $id_aep);
    echo json_encode(array('ok' => true));
    exit;
}

if ($action === 'get_stats_secteur') {
    $mois = isset($_GET['mois']) && csc_mois_valide_carto($_GET['mois'])
        ? $_GET['mois']
        : Cartographie::getMoisActif($id_aep);
    $polyRaw = isset($_GET['polygone']) ? json_decode($_GET['polygone'], true) : null;
    if (!is_array($polyRaw) || count($polyRaw) < 3) {
        echo json_encode(array('ok' => false, 'error' => 'Polygone invalide.'));
        exit;
    }
    $polygone = array();
    foreach ($polyRaw as $p) {
        if (is_array($p) && count($p) >= 2) {
            $polygone[] = array((float) $p[0], (float) $p[1]);
        }
    }
    echo json_encode(array('ok' => true, 'stats' => Cartographie::getStatsSecteur($id_aep, $polygone, $mois)));
    exit;
}

// ---------------------------------------------------------------------
// Ouvrages
// ---------------------------------------------------------------------

if ($action === 'save_ouvrage') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $id_reseau = isset($_POST['id_reseau']) && $_POST['id_reseau'] !== '' ? (int) $_POST['id_reseau'] : null;
    $type = isset($_POST['type']) ? $_POST['type'] : 'autre';
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $lat = isset($_POST['latitude']) ? (float) $_POST['latitude'] : null;
    $lon = isset($_POST['longitude']) ? (float) $_POST['longitude'] : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($nom === '' || $lat === null || $lon === null) {
        echo json_encode(array('ok' => false, 'error' => 'Nom et position requis.'));
        exit;
    }
    $newId = Cartographie::saveOuvrage($id, $id_aep, $id_reseau, $type, $nom, $lat, $lon, $description);
    echo json_encode(array('ok' => $newId > 0, 'id' => $newId));
    exit;
}

if ($action === 'delete_ouvrage') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    Cartographie::deleteOuvrage($id, $id_aep);
    echo json_encode(array('ok' => true));
    exit;
}

// ---------------------------------------------------------------------
// Conduites
// ---------------------------------------------------------------------

if ($action === 'save_conduite') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $id_reseau = isset($_POST['id_reseau']) && $_POST['id_reseau'] !== '' ? (int) $_POST['id_reseau'] : null;
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $pointsRaw = isset($_POST['points']) ? json_decode($_POST['points'], true) : null;

    if (!is_array($pointsRaw) || count($pointsRaw) < 2) {
        echo json_encode(array('ok' => false, 'error' => 'Tracé invalide (au moins 2 points requis).'));
        exit;
    }
    $points = array();
    foreach ($pointsRaw as $p) {
        if (!is_array($p) || count($p) < 2) {
            continue;
        }
        $points[] = array((float) $p[0], (float) $p[1]);
    }
    if (count($points) < 2) {
        echo json_encode(array('ok' => false, 'error' => 'Tracé invalide.'));
        exit;
    }

    $newId = Cartographie::saveConduite($id, $id_aep, $id_reseau, $nom, $points, $description);
    $longueur = Cartographie::calculerLongueur($points);
    echo json_encode(array('ok' => $newId > 0, 'id' => $newId, 'longueur_m' => round($longueur, 2)));
    exit;
}

if ($action === 'delete_conduite') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    Cartographie::deleteConduite($id, $id_aep);
    echo json_encode(array('ok' => true));
    exit;
}

// ---------------------------------------------------------------------
// Import des tuiles (ZIP) — point sensible : validations strictes, cf. faille
// v1 « upload arbitraire → RCE ». Seules des images sont écrites sur disque,
// jamais un fichier exécutable, et aucun chemin ne peut sortir du dossier cible.
// ---------------------------------------------------------------------

if ($action === 'upload_tuiles') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);

    if (!class_exists('ZipArchive')) {
        echo json_encode(array('ok' => false, 'error' => "L'extension ZipArchive n'est pas disponible sur ce serveur."));
        exit;
    }
    if (!isset($_FILES['fichier_tuiles'])) {
        echo json_encode(array('ok' => false, 'error' => 'Aucun fichier reçu.'));
        exit;
    }
    if ($_FILES['fichier_tuiles']['error'] !== UPLOAD_ERR_OK) {
        // Message precis selon la cause : "fichier invalide" ne dit pas quoi corriger.
        switch ($_FILES['fichier_tuiles']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $msg = 'Archive trop volumineuse : la limite du serveur (upload_max_filesize) est de '
                    . ini_get('upload_max_filesize') . '. Exporte moins de niveaux de zoom, ou relève cette limite.';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $msg = 'Archive trop volumineuse pour le formulaire.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $msg = 'Envoi interrompu : le fichier n\'est arrivé que partiellement.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $msg = 'Aucun fichier sélectionné.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $msg = 'Dossier temporaire introuvable côté serveur.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $msg = 'Écriture du fichier temporaire impossible côté serveur.';
                break;
            default:
                $msg = 'Échec du téléversement (code ' . $_FILES['fichier_tuiles']['error'] . ').';
        }
        echo json_encode(array('ok' => false, 'error' => $msg));
        exit;
    }

    $tmpPath = $_FILES['fichier_tuiles']['tmp_name'];
    if (strtolower(pathinfo($_FILES['fichier_tuiles']['name'], PATHINFO_EXTENSION)) !== 'zip') {
        echo json_encode(array('ok' => false, 'error' => 'Le fichier doit porter l\'extension .zip.'));
        exit;
    }

    // Contrôle du type MIME seulement si l'extension fileinfo est disponible :
    // elle est absente de beaucoup d'installations PHP 5.3 (dont WAMP par défaut),
    // et on ne peut pas conditionner l'import à sa présence chez le client.
    // Ce test n'est de toute façon qu'un premier filtre : la vraie garantie vient
    // de ZipArchive::open() (qui échoue si ce n'est pas une archive), du filtrage
    // strict des chemins {z}/{x}/{y}, et surtout de la validation image de chaque
    // fichier extrait avant écriture sur disque.
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            $mimesAcceptes = array('application/zip', 'application/x-zip-compressed', 'application/octet-stream');
            if ($mime && !in_array($mime, $mimesAcceptes, true)) {
                echo json_encode(array('ok' => false, 'error' => 'Le fichier ne semble pas être une archive ZIP (type détecté : ' . $mime . ').'));
                exit;
            }
        }
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        echo json_encode(array('ok' => false, 'error' => "Impossible d'ouvrir l'archive ZIP."));
        exit;
    }

    $maxFichiers = 60000;
    $maxPoidsTotal = 400 * 1024 * 1024; // 400 Mo
    $extensionsAutorisees = array('png', 'jpg', 'jpeg');

    if ($zip->numFiles > $maxFichiers) {
        $zip->close();
        echo json_encode(array('ok' => false, 'error' => 'Trop de fichiers dans l\'archive (limite : ' . $maxFichiers . ').'));
        exit;
    }

    $destBase = Cartographie::dossierTuiles($id_aep);
    if (!is_dir($destBase)) {
        @mkdir($destBase, 0755, true);
    }
    $destBaseReal = realpath($destBase);

    $acceptes = 0;
    $rejetes = 0;
    $poidsTotal = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        if ($entryName === false || substr($entryName, -1) === '/') {
            continue; // dossier
        }

        // Chemin relatif attendu : {z}/{x}/{y}.ext (les tuiles peuvent être dans
        // un sous-dossier racine unique selon l'outil d'export, on le retire).
        $normalized = str_replace('\\', '/', $entryName);
        $parts = array_values(array_filter(explode('/', $normalized), function ($p) {
            return $p !== '' && $p !== '.';
        }));
        if (in_array('..', $parts, true)) {
            $rejetes++;
            continue;
        }
        // Ne garder que les 3 derniers segments (z/x/y.ext), pour absorber un
        // éventuel dossier racine ajouté par l'outil d'export.
        if (count($parts) < 3) {
            $rejetes++;
            continue;
        }
        $parts = array_slice($parts, -3);
        list($z, $x, $yExt) = $parts;
        if (!ctype_digit($z) || !ctype_digit($x)) {
            $rejetes++;
            continue;
        }
        $ext = strtolower(pathinfo($yExt, PATHINFO_EXTENSION));
        $y = pathinfo($yExt, PATHINFO_FILENAME);
        if (!ctype_digit($y) || !in_array($ext, $extensionsAutorisees, true)) {
            $rejetes++;
            continue;
        }

        $stat = $zip->statIndex($i);
        $poidsTotal += isset($stat['size']) ? (int) $stat['size'] : 0;
        if ($poidsTotal > $maxPoidsTotal) {
            $zip->close();
            echo json_encode(array('ok' => false, 'error' => 'Archive trop volumineuse (limite : 400 Mo décompressés).'));
            exit;
        }

        $contenu = $zip->getFromIndex($i);
        if ($contenu === false || !carto_est_image_valide($contenu)) {
            // Pas une image valide malgré l'extension : on n'écrit rien.
            $rejetes++;
            continue;
        }

        $destDir = $destBase . '/' . $z . '/' . $x;
        $destFile = $destDir . '/' . $y . '.' . $ext;
        $destDirReal = null;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        $destDirReal = realpath($destDir);
        // Vérifie que le dossier reste bien sous le dossier de tuiles de cet AEP.
        if ($destDirReal === false || $destBaseReal === false || strpos($destDirReal, $destBaseReal) !== 0) {
            $rejetes++;
            continue;
        }

        if (@file_put_contents($destFile, $contenu) === false) {
            $rejetes++;
            continue;
        }
        $acceptes++;
    }
    $zip->close();

    $meta = Cartographie::recalculerTuilesMeta($id_aep);
    echo json_encode(array(
        'ok' => $acceptes > 0,
        'acceptes' => $acceptes,
        'rejetes' => $rejetes,
        'tuiles' => $meta,
        'error' => $acceptes === 0 ? 'Aucune tuile valide trouvée dans cette archive.' : null,
    ));
    exit;
}

if ($action === 'delete_tuiles') {
    carto_require_ecriture();
    Csrf::requireValid('Jeton CSRF invalide.', true);
    Cartographie::supprimerTuiles($id_aep);
    echo json_encode(array('ok' => true));
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'Action inconnue.'));
exit;
