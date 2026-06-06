<?php
/**
 * Shared bootstrap for standalone AJAX endpoint scripts in /api/.
 */

session_start();

require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/app/helpers.php';

spl_autoload_register(function ($class) {
    $paths = [
        APP_PATH . '/controllers/' . $class . '.php',
        APP_PATH . '/models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});

header('Content-Type: application/json');
