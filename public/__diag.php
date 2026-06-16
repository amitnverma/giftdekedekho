<?php
/**
 * TEMPORARY diagnostic — safe to delete. Finds the file defining CURRENCY_SYMBOL=262145.
 */
header('Content-Type: text/plain');
$root = dirname(__DIR__);

echo "CURRENCY_SYMBOL at script start: " . (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '(not defined)') . "\n";
echo "auto_prepend_file (ini_get): " . var_export(ini_get('auto_prepend_file'), true) . "\n";
echo "auto_prepend_file (get_cfg_var): " . var_export(get_cfg_var('auto_prepend_file'), true) . "\n";
echo "loaded php.ini: " . var_export(php_ini_loaded_file(), true) . "\n";
echo "scanned .ini files: " . var_export(php_ini_scanned_files(), true) . "\n";
echo "included files so far:\n  " . implode("\n  ", get_included_files()) . "\n";

echo "\n=== recursive scan for '262145' and CURRENCY_SYMBOL defines ===\n";
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
$count = 0;
foreach ($rii as $file) {
    if ($file->getExtension() !== 'php' && $file->getExtension() !== 'ini') continue;
    if (strpos($file->getPathname(), '/libs/') !== false) continue; // skip vendor noise
    $c = @file_get_contents($file->getPathname());
    if ($c === false) continue;
    $has262 = strpos($c, '262145') !== false;
    $hasDef = preg_match('/define\s*\(\s*[\'"]CURRENCY_SYMBOL[\'"]/i', $c);
    if ($has262 || $hasDef) {
        echo str_replace($root, '', $file->getPathname())
           . "  [262145=" . ($has262 ? 'YES' : 'no')
           . " define=" . ($hasDef ? 'YES' : 'no') . "]\n";
        $count++;
    }
}
echo $count === 0 ? "(no matching files found)\n" : "($count file(s) matched)\n";
