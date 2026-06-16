<?php
/**
 * Per-server credential overrides.
 *
 * Copy this file to `config/local.php` on each machine and fill in the values
 * for that environment. `config/local.php` is gitignored, so it is NOT touched
 * by deployments (`git reset --hard origin/main` never overwrites untracked
 * files) — your production credentials stay put across every deploy.
 *
 * Any key omitted here falls back to environment variables, then to the
 * local-development defaults in config/database.php.
 */

return [
    // Database credentials
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'giftdekedekho',
    'DB_USER' => 'your_db_user',
    'DB_PASS' => 'your_db_password',

    // Optional site overrides (omit any to keep the defaults below)
    // 'APP_ENV'         => 'production',  // default: development
    // 'CURRENCY_SYMBOL' => '₹',          // default: ₹
    // 'CURRENCY_CODE'   => 'INR',        // default: INR
];
