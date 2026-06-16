<?php
// TEMPORARY diagnostic — safe to delete.
header('Content-Type: text/plain');

define('GDD_PROBE_CONST', 'probe-value-123');

$all = get_defined_constants(true);
foreach (['GDD_PROBE_CONST', 'CURRENCY_SYMBOL'] as $name) {
    $found = '(not found)';
    foreach ($all as $category => $consts) {
        if (array_key_exists($name, $consts)) {
            $found = "category={$category} value=" . var_export($consts[$name], true);
            break;
        }
    }
    echo "{$name}: {$found}\n";
}

echo "\nmonarxprotect loaded: " . (extension_loaded('monarxprotect') ? 'YES' : 'no') . "\n";
echo "monarxprotect ini values:\n";
foreach (ini_get_all('monarxprotect', false) ?: [] as $k => $v) {
    echo "  {$k} = " . var_export($v, true) . "\n";
}
