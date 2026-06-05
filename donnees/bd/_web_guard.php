<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'web_guard.php';

/**
 * Garde licence + auth pour scripts BD via navigateur (CLI autorisé sans garde).
 */
function bd_web_guard()
{
    if (php_sapi_name() === 'cli') {
        return;
    }
    WebGuard::setRedirectPrefix('../../');
    WebGuard::enforceLicenceAndAuth(false);
}
