<?php
/**
 * Global helper functions used throughout the app and views.
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function asset(string $path): string
{
    // Absolute URLs pass through untouched.
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    // URL-encode each path segment (so filenames with spaces, commas, etc. work
    // reliably across all clients) while preserving the slash separators.
    $clean = ltrim($path, '/');
    $segments = array_map('rawurlencode', explode('/', $clean));
    return rtrim(SITE_URL, '/') . '/' . implode('/', $segments);
}

function url(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function old(string $key, $default = '')
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function formatPrice($amount): string
{
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function csrfToken(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrfField(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): bool
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'item';
}

function siteSetting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = Database::getInstance()->query('SELECT setting_key, setting_value FROM settings');
            foreach ($stmt->fetchAll() as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function viewPath(string $relative): string
{
    return APP_PATH . '/views/' . ltrim($relative, '/');
}

/**
 * Render a view inside the storefront layout.
 */
function render(string $view, array $data = []): void
{
    extract($data);
    $contentFile = viewPath('store/' . $view . '.php');
    ob_start();
    require $contentFile;
    $content = ob_get_clean();
    require viewPath('layout/header.php');
    echo $content;
    require viewPath('layout/footer.php');
}

/**
 * Render a raw view (no layout wrapper) — used for admin (own layout) and partials.
 */
function renderRaw(string $relativeView, array $data = []): void
{
    extract($data);
    require viewPath($relativeView . '.php');
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('d M Y', strtotime($datetime));
}

function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function paginate(int $totalItems, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return compact('totalItems', 'perPage', 'currentPage', 'totalPages', 'offset');
}

function starRating(float $rating): string
{
    $rating = round($rating * 2) / 2;
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<span class="star star-full">★</span>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<span class="star star-half">★</span>';
        } else {
            $html .= '<span class="star star-empty">☆</span>';
        }
    }
    return $html;
}
