<?php
// Router for PHP built-in server to preview the app (mimics .htaccess front-controller).
$root = '/Applications/XAMPP/xamppfiles/htdocs/giftdekedekho';
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = realpath($root . $uri);

// Serve existing static files directly (css, js, images, uploads), but never PHP source dirs.
if ($file && is_file($file) && strpos($file, $root) === 0) {
    $blocked = ['/config/', '/app/', '/libs/'];
    foreach ($blocked as $b) { if (strpos($uri, $b) === 0) { http_response_code(403); return true; } }
    return false; // let the built-in server serve the static file
}

// Everything else -> front controller
require $root . '/index.php';
