<?php

/**
 * Version de l'application.
 *
 * Les utilisateurs installent l'application sur leur propre PC et la mettent à
 * jour avec update_git.bat : ce numéro, affiché dans la barre de navigation et
 * écrit en tête de chaque sauvegarde, permet de savoir à distance quelle
 * version tourne chez eux. À incrémenter à chaque livraison.
 */
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2026.10.27');
}
