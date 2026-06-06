<?php
/**
 * Site-wide configuration. Copy real secrets here or load from environment.
 * In production, set these via environment variables instead of committing real values.
 */

// ---- Environment ----
define('ENVIRONMENT', getenv('APP_ENV') ?: 'development'); // development | production

// ---- Site basics ----
define('SITE_NAME', 'GiftDekeDekho');
define('SITE_URL', getenv('APP_URL') ?: 'http://localhost/giftdekedekho');
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
