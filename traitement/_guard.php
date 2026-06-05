<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'donnees' . DIRECTORY_SEPARATOR . 'web_guard.php';

/**
 * Garde licence + auth pour accès direct aux scripts traitement/.
 *
 * @param bool $jsonResponse réponse JSON si refus (AJAX)
 */
function traitement_guard($jsonResponse = false)
{
    if (php_sapi_name() === 'cli') {
        return;
    }
    $script = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    if ($script === false) {
        return;
    }
    $needle = DIRECTORY_SEPARATOR . 'traitement' . DIRECTORY_SEPARATOR;
    if (strpos($script, $needle) === false) {
        return;
    }
    WebGuard::setRedirectPrefix('../');
    WebGuard::enforceLicenceAndAuth($jsonResponse);
}
