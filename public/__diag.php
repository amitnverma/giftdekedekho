<?php
// TEMPORARY diagnostic — safe to delete.
header('Content-Type: text/plain');
echo "CURRENCY_SYMBOL at start: " . (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '(not defined)') . "\n";
echo "opcache.preload: " . var_export(ini_get('opcache.preload'), true) . "\n";
echo "opcache.preload_user: " . var_export(ini_get('opcache.preload_user'), true) . "\n";
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    echo "opcache preload statistics present: " . (isset($st['preload_statistics']) ? 'YES' : 'no') . "\n";
    if (isset($st['preload_statistics']['scripts'])) {
        echo "preloaded scripts:\n  " . implode("\n  ", $st['preload_statistics']['scripts']) . "\n";
    }
}
