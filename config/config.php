<?php
/**
 * Site-wide configuration. Copy real secrets here or load from environment.
 * In production, set these via environment variables instead of committing real values.
 */

// ---- Environment ----
define('ENVIRONMENT', getenv('APP_ENV') ?: 'development'); // development | production

// ---- Site basics ----
define('SITE_NAME', 'GiftDekeDekho');

/*
 * Base URL resolution order:
 *   1. APP_URL environment variable (set this in production for certainty), else
 *   2. Auto-detected from the current request (scheme + host), else
 *   3. localhost fallback for CLI / first-run.
 * Auto-detection honours Hostinger/Cloudflare HTTPS proxies via X-Forwarded-Proto
 * so asset URLs never fall back to http://localhost (which triggers the browser's
 * "access other apps and services on this device" / Local Network Access prompt).
 */
if (!function_exists('gdd_detect_base_url')) {
    function gdd_detect_base_url(): string
    {
        $envUrl = getenv('APP_URL');
        if ($envUrl) {
            return rtrim($envUrl, '/');
        }
        if (!empty($_SERVER['HTTP_HOST'])) {
            $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                || $forwardedProto === 'https'
                || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
            $scheme = $isHttps ? 'https' : 'http';

            // Work out the sub-path the app lives in (e.g. "/giftdekedekho" on local
            // XAMPP, "" when deployed at the domain root on the VPS) by comparing the
            // app's folder to the web server's document root. Filesystem-based so it
            // is correct from every entry point (index.php, api/*.php, sitemap.php).
            $basePath = '';
            $docRoot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
            $appRoot = str_replace('\\', '/', dirname(__DIR__)); // config/ -> app root
            if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
                $basePath = rtrim(substr($appRoot, strlen($docRoot)), '/');
            }
            return $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath;
        }
        return 'http://localhost/giftdekedekho';
    }
}
define('SITE_URL', gdd_detect_base_url());
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

// ---- Paths ----
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('UPLOAD_URL', '/public/uploads');

// ---- Session ----
define('SESSION_TIMEOUT_SECONDS', 1800); // 30 minutes admin inactivity timeout

// ---- Security secrets (placeholders — override via environment in production) ----
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// ---- Third-party API placeholders (actual values are stored in `settings` table, editable from admin) ----
// These constants act only as fallback/defaults for first install.
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: '');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');
define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: '');
define('PAYPAL_CLIENT_SECRET', getenv('PAYPAL_CLIENT_SECRET') ?: '');
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: '');
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: '');
define('SHIPROCKET_EMAIL', getenv('SHIPROCKET_EMAIL') ?: '');
define('SHIPROCKET_PASSWORD', getenv('SHIPROCKET_PASSWORD') ?: '');
define('MSG91_API_KEY', getenv('MSG91_API_KEY') ?: '');
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', getenv('SMTP_PORT') ?: '587');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');

// ---- Error display ----
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Kolkata');
