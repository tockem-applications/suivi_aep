<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'web_guard.php';

/**
 * Garde pour scripts BD via navigateur (CLI sans garde).
 * Authentification seule : les migrations doivent rester accessibles aux admins
 * même si la licence est absente ou en attente d'import (ex. OpenSSL).
 */
function bd_web_guard()
{
    if (php_sapi_name() === 'cli') {
        return;
    }
    WebGuard::setRedirectPrefix('../../');
    WebGuard::loadCore();
    WebGuard::enforceAuth(false);
}
