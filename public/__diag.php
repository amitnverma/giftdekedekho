<?php
/**
 * TEMPORARY diagnostic — safe to delete. Reports how currency resolves on this
 * server so we can pinpoint why CURRENCY_SYMBOL is wrong. No secrets exposed.
 */
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/helpers.php';

header('Content-Type: text/plain');

echo "CURRENCY_SYMBOL bytes: " . bin2hex(CURRENCY_SYMBOL) . "\n";
echo "CURRENCY_SYMBOL value: " . CURRENCY_SYMBOL . "\n";
echo "CURRENCY_CODE: " . CURRENCY_CODE . "\n";
echo "gdd_local CURRENCY_SYMBOL set? " . (gdd_local('CURRENCY_SYMBOL') !== null ? 'YES -> ' . gdd_local('CURRENCY_SYMBOL') : 'no (using default)') . "\n";
echo "config/local.php exists? " . (is_file(dirname(__DIR__) . '/config/local.php') ? 'yes' : 'no') . "\n";
echo "config/config.php mtime: " . date('c', filemtime(dirname(__DIR__) . '/config/config.php')) . "\n";
echo "config/config.php contains 262145? " . (strpos(file_get_contents(dirname(__DIR__) . '/config/config.php'), '262145') !== false ? 'YES (file on disk is corrupted)' : 'no (file on disk is clean)') . "\n";

if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    echo "opcache enabled: " . ($st && !empty($st['opcache_enabled']) ? 'YES' : 'no') . "\n";
    $cfg = function_exists('opcache_get_configuration') ? opcache_get_configuration() : null;
    if ($cfg) {
        echo "opcache.validate_timestamps: " . var_export($cfg['directives']['opcache.validate_timestamps'] ?? null, true) . "\n";
        echo "opcache.revalidate_freq: " . var_export($cfg['directives']['opcache.revalidate_freq'] ?? null, true) . "\n";
    }
} else {
    echo "opcache: functions not available\n";
}
echo "formatPrice(349): " . (function_exists('formatPrice') ? formatPrice(349) : 'n/a') . "\n";
