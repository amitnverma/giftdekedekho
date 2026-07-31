<?php
/**
 * Applies a .sql migration using the application's own database credentials.
 *
 * Usage:  php tools/run-migration.php migrations/2026_07_29_ar_frames.sql
 *         php tools/run-migration.php migrations/<file>.sql --dry-run
 *         php tools/run-migration.php --list
 *
 * Production is an unprivileged shared-hosting account: no sudo, and the only
 * tools guaranteed to exist are PHP and MySQL. This avoids needing the `mysql`
 * client binary or typing credentials on the command line (where they would land
 * in shell history) — it reads config/local.php through the app's own Database
 * class, exactly as the site does.
 *
 * Statements are executed one at a time so a failure names the statement that
 * broke instead of aborting anonymously. Migrations in this project use
 * CREATE TABLE IF NOT EXISTS / ADD COLUMN, so re-running a completed migration
 * is expected to be harmless — but the summary reports what actually ran.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';

const MIGRATIONS_DIR = __DIR__ . '/../migrations';

function out(string $line = ''): void { echo $line . "\n"; }

function listMigrations(): int
{
    $files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
    sort($files);
    out("\nMigrations in " . realpath(MIGRATIONS_DIR) . ":\n");
    foreach ($files as $file) {
        out('  ' . basename($file));
    }
    out("\nRun one with:  php tools/run-migration.php migrations/<file>.sql\n");
    return 0;
}

/**
 * Split a migration into individual statements.
 *
 * Scans character by character rather than splitting on ";", because a
 * semicolon is only a separator when it is not inside a string, a quoted
 * identifier or a comment. The migrations in this project genuinely contain
 * such cases — for example:
 *
 *     `order_item_id` INT UNSIGNED DEFAULT NULL,  -- online sales only; NULL for walk-ins
 *
 * A naive split turns that one CREATE TABLE into two invalid fragments.
 * Comments are stripped as they are recognised, so they never reach MySQL.
 */
function splitStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        // --- comments (only outside quotes, which are handled below) ---
        // MySQL requires whitespace after "--", so "a--b" stays arithmetic.
        if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
            while ($i < $length && $sql[$i] !== "\n") { $i++; }
            $current .= "\n";
            continue;
        }
        if ($char === '#') {
            while ($i < $length && $sql[$i] !== "\n") { $i++; }
            $current .= "\n";
            continue;
        }
        if ($char === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $length : $end + 1;
            $current .= ' ';
            continue;
        }

        // --- quoted regions: copied verbatim, separators inside are literal ---
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $current .= $char;
            $i++;
            while ($i < $length) {
                $c = $sql[$i];
                // Backslash escapes apply to strings but not to backtick identifiers.
                if ($c === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $current .= $c . $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                // A doubled quote is an escaped quote, not the end.
                if ($c === $quote && ($i + 1 < $length && $sql[$i + 1] === $quote)) {
                    $current .= $c . $c;
                    $i += 2;
                    continue;
                }
                $current .= $c;
                if ($c === $quote) { break; }
                $i++;
            }
            continue;
        }

        if ($char === ';') {
            if (trim($current) !== '') { $statements[] = trim($current); }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    if (trim($current) !== '') { $statements[] = trim($current); }
    return $statements;
}

/** First few words, for a readable progress line. */
function describe(string $statement): string
{
    $flat = preg_replace('/\s+/', ' ', $statement);
    return mb_strlen($flat) > 68 ? mb_substr($flat, 0, 68) . '…' : $flat;
}

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$args = array_values(array_filter($args, fn($a) => $a !== '--dry-run'));
$arg = $args[0] ?? null;

if ($arg === null || $arg === '--help' || $arg === '-h') {
    out("\nUsage: php tools/run-migration.php <path-to.sql> [--dry-run]");
    out("       php tools/run-migration.php --list\n");
    out("  --dry-run  parse and show the statements without executing them.");
    out("             Always worth doing first on production.\n");
    exit(1);
}

if ($arg === '--list') {
    exit(listMigrations());
}

// Accept a path relative to the project root or an absolute one.
$path = $arg;
if ($path[0] !== '/') {
    $path = BASE_PATH . '/' . ltrim($path, './');
}
if (!is_file($path)) {
    out("\nFile not found: {$arg}");
    out("Use --list to see available migrations.\n");
    exit(1);
}

$sql = file_get_contents($path);
if ($sql === false || trim($sql) === '') {
    out("\nCould not read (or empty file): {$path}\n");
    exit(1);
}

$statements = splitStatements($sql);
if (!$statements) {
    out("\nNo executable statements found in " . basename($path) . "\n");
    exit(1);
}

out("\nMigration: " . basename($path));
out('Statements: ' . count($statements));

if ($dryRun) {
    out("Mode:      DRY RUN — nothing will be executed\n");
    foreach ($statements as $i => $statement) {
        out(sprintf('  [%d/%d] %s', $i + 1, count($statements), describe($statement)));
    }
    out("\nRe-run without --dry-run to apply.\n");
    exit(0);
}

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    out("\nCould not connect to the database. Check config/local.php.");
    out('  ' . $e->getMessage() . "\n");
    exit(1);
}

// Report which database we are about to change, so a wrong config/local.php is
// obvious before anything is written rather than after.
try {
    $dbName = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    out('Database:  ' . ($dbName !== '' ? $dbName : '(none selected)'));
} catch (Throwable $e) {
    out('Database:  (could not determine)');
}
out('');

$applied = 0;
foreach ($statements as $i => $statement) {
    $label = sprintf('  [%d/%d] %s', $i + 1, count($statements), describe($statement));
    try {
        $db->exec($statement);
        out($label);
        $applied++;
    } catch (PDOException $e) {
        out($label . '   FAILED');
        out("\n  " . $e->getMessage() . "\n");
        out('Stopped. ' . $applied . ' statement(s) applied before the failure.');
        out("Nothing was rolled back — MySQL commits DDL implicitly.\n");
        exit(1);
    }
}

out("\nDone. {$applied} statement(s) applied successfully.\n");
exit(0);
