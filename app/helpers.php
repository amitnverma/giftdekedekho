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

/**
 * Factory defaults for a section "appearance" style block.
 * Centralised so the admin form and the storefront renderer agree.
 */
function sectionStyleDefaults(): array
{
    return [
        'align'         => 'center',
        'kicker_color'  => '#e63946',
        'heading_color' => '#1d1d1f',
        'heading_size'  => '',   // blank = responsive CSS default
        'subtext_color' => '#6b7280',
        'subtext_size'  => '',   // blank = CSS default
        'bg_color'      => '',   // blank = section's natural background
    ];
}

/**
 * Builds an inline style attribute string from a style array,
 * applying only the keys that have been customised.
 */
function sectionBgStyle(array $style): string
{
    $bg = trim((string)($style['bg_color'] ?? ''));
    return $bg !== '' ? 'background:' . e($bg) . ';' : '';
}

/**
 * Renders a standard section heading (kicker + h2 + subtext) with
 * admin-controlled colour, size and alignment applied inline.
 *
 * @param array  $style    The section's 'style' sub-array.
 * @param string $kicker   Kicker / eyebrow text (blank to hide).
 * @param string $heading  Main heading text.
 * @param string $subtext  Sub-paragraph (blank to hide). May contain safe HTML if $rawSub=true.
 * @param bool   $rawSub   When true, $subtext is emitted without escaping.
 */
function renderSectionHeading(array $style, string $kicker, string $heading, string $subtext = '', bool $rawSub = false): void
{
    $d = sectionStyleDefaults();
    $align        = $style['align']         ?? $d['align'];
    $kickerColor  = trim((string)($style['kicker_color']  ?? ''));
    $headingColor = trim((string)($style['heading_color'] ?? ''));
    $headingSize  = trim((string)($style['heading_size']  ?? ''));
    $subColor     = trim((string)($style['subtext_color'] ?? ''));
    $subSize      = trim((string)($style['subtext_size']  ?? ''));

    $kickerStyle  = $kickerColor !== '' ? 'color:' . e($kickerColor) . ';' : '';
    $hStyle = '';
    if ($headingColor !== '') $hStyle .= 'color:' . e($headingColor) . ';';
    if ($headingSize  !== '') $hStyle .= 'font-size:' . (int)$headingSize . 'px;';
    $pStyle = '';
    if ($subColor !== '') $pStyle .= 'color:' . e($subColor) . ';';
    if ($subSize  !== '') $pStyle .= 'font-size:' . (int)$subSize . 'px;';

    echo '<div class="section-heading reveal" style="text-align:' . e($align) . '">';
    if ($kicker !== '') {
        echo '<span class="gdd-kicker"' . ($kickerStyle ? ' style="' . $kickerStyle . '"' : '') . '>' . e($kicker) . '</span>';
    }
    echo '<h2' . ($hStyle ? ' style="' . $hStyle . '"' : '') . '>' . e($heading) . '</h2>';
    if ($subtext !== '') {
        echo '<p' . ($pStyle ? ' style="' . $pStyle . '"' : '') . '>' . ($rawSub ? $subtext : e($subtext)) . '</p>';
    }
    echo '</div>';
}
