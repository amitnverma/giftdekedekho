<?php
// TEMPORARY diagnostic — safe to delete.
header('Content-Type: text/plain');

echo "CURRENCY_SYMBOL value: " . (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '(not defined)') . "\n\n";

echo "=== which category defines CURRENCY_SYMBOL ===\n";
$all = get_defined_constants(true);
foreach ($all as $category => $consts) {
    if (array_key_exists('CURRENCY_SYMBOL', $consts)) {
        echo "category: {$category}  value: " . var_export($consts['CURRENCY_SYMBOL'], true) . "\n";
    }
}

echo "\n=== loaded extensions ===\n";
echo implode(', ', get_loaded_extensions()) . "\n";

echo "\n=== any constant whose value is 262145 ===\n";
foreach ($all as $category => $consts) {
    foreach ($consts as $name => $val) {
        if ((is_int($val) || is_string($val)) && (string)$val === '262145') {
            echo "{$category}: {$name} = {$val}\n";
        }
    }
}
