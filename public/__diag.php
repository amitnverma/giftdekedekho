<?php
/**
 * TEMPORARY diagnostic — safe to delete. Pinpoints where CURRENCY_SYMBOL
 * is being defined as "262145". No secrets exposed (DB_PASS redacted).
 */
header('Content-Type: text/plain');

$root = dirname(__DIR__);

echo "=== auto_prepend_file ===\n";
echo "ini value: " . var_export(ini_get('auto_prepend_file'), true) . "\n";

echo "\n=== before requiring anything ===\n";
echo "CURRENCY_SYMBOL already defined? " . (defined('CURRENCY_SYMBOL') ? 'YES -> ' . CURRENCY_SYMBOL : 'no') . "\n";

echo "\n=== requiring config/local.php directly ===\n";
$localFile = $root . '/config/local.php';
if (is_file($localFile)) {
    $arr = require $localFile;
    echo "local.php returned keys: " . (is_array($arr) ? implode(', ', array_keys($arr)) : '(not an array)') . "\n";
    echo "CURRENCY_SYMBOL defined after local.php? " . (defined('CURRENCY_SYMBOL') ? 'YES -> ' . CURRENCY_SYMBOL : 'no') . "\n";
    $raw = file_get_contents($localFile);
    // Redact anything that looks like a password value.
    $redacted = preg_replace("/(PASS[^=>]*[=>]+\s*['\"]).*?(['\"])/i", '$1***$2', $raw);
    echo "--- local.php contents (passwords redacted) ---\n" . $redacted . "\n--- end ---\n";
} else {
    echo "local.php does not exist\n";
}

echo "\n=== scanning for stray CURRENCY_SYMBOL defines ===\n";
foreach ([
    $root . '/config/config.php',
    $root . '/config/local.php',
    $root . '/app/helpers.php',
    $root . '/index.php',
    $root . '/.user.ini',
    $root . '/public/.user.ini',
] as $f) {
    if (is_file($f)) {
        $c = file_get_contents($f);
        $has262 = strpos($c, '262145') !== false;
        $hasDef = stripos($c, "define('CURRENCY_SYMBOL'") !== false || stripos($c, 'define("CURRENCY_SYMBOL"') !== false;
        echo basename($f) . ": 262145=" . ($has262 ? 'YES' : 'no') . " define=" . ($hasDef ? 'YES' : 'no') . "\n";
    }
}
