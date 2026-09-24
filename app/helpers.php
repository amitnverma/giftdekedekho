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
    $url = rtrim(SITE_URL, '/') . '/' . implode('/', $segments);

    $version = assetVersion($clean);
    return $version === null ? $url : $url . '?v=' . $version;
}

/**
 * Cache-busting stamp for a local asset, or null if there is no such file.
 *
 * The production server sends `Cache-Control: max-age=315360000` — ten years —
 * on static files. Without a changing URL, a deployed CSS or JS fix simply
 * never reaches anyone who has already visited: their browser has no reason to
 * ask again. Appending the file's modification time gives each new build a new
 * URL, so updates take effect immediately while unchanged files stay cached.
 *
 * Results are memoised per request; a miss is cached too, so repeated calls for
 * remote or generated paths do not keep hitting the filesystem.
 */
function assetVersion(string $relativePath): ?string
{
    static $cache = [];

    if (array_key_exists($relativePath, $cache)) {
        return $cache[$relativePath];
    }

    // Query strings and anchors are not part of the filename.
    $bare = strtok($relativePath, '?#');
    $full = BASE_PATH . '/' . $bare;

    // Never let a crafted path walk outside the application.
    $real = realpath($full);
    $root = realpath(BASE_PATH);
    $ok = $real !== false && $root !== false
        && strpos($real, $root . DIRECTORY_SEPARATOR) === 0
        && is_file($real);

    return $cache[$relativePath] = $ok ? (string)filemtime($real) : null;
}

/**
 * Resolve a stored product image path to a public URL.
 * Handles both legacy seed paths ("/images/foo.png", root-relative) and
 * admin-uploaded paths ("products/prod_xxx.jpg", relative to the uploads dir).
 */
function productImage(?string $path): string
{
    if ($path === null || $path === '') {
        return asset('/images/GDKD logo.png');
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    // Root-relative legacy paths (e.g. "/images/...") pass straight through.
    if ($path[0] === '/') {
        return asset($path);
    }
    // Everything else is an upload stored under the uploads directory.
    return asset(trim(UPLOAD_URL, '/') . '/' . $path);
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
    return GDD_CURRENCY_SYMBOL . number_format((float)$amount, 2);
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

/**
 * End a customer's login once their password has changed since they signed in
 * (reset link, or changed from another browser). Sessions from before the
 * password stamp existed carry none and are left alone.
 */
function endStaleCustomerSession(): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer' || !isset($_SESSION['user_pw'])) {
        return;
    }
    try {
        $user = (new User())->find((int)$_SESSION['user_id']);
    } catch (Throwable $e) {
        return;
    }
    if (!$user || !hash_equals(AccountController::passwordStamp($user), (string)$_SESSION['user_pw'])) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['user_pw']);
        session_regenerate_id(true);
    }
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
    // Silenced: a character with no ASCII form (e.g. Devanagari) raises a notice,
    // which production prints into the page ahead of any redirect header.
    $text = @iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
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
 * Factory content of the DEx landing page (/dex): the copy, images and colours
 * of the Interactive Memories Catalogue PDF. Admin → Design Editor → DEx Landing
 * saves over this as the `dex_landing` site section; images here are the
 * originals in /images/dex, uploads replace them with /public/uploads paths.
 */
function dexLandingDefaults(): array
{
    $img = fn(string $f): string => '/images/dex/' . $f;
    return [
        'is_active'         => true,
        'meta_title'        => 'DEx · Digital Experience — Interactive Memories Catalogue',
        'meta_description'  => 'DEx turns printed photos, frames, albums and cards into living memories. Scan. Connect. Watch. Relive. Real Moments. Digital Magic. Forever Yours.',
        'colors' => [
            'navy'       => '#0b2234',
            'gold'       => '#b8862f',
            'gold_dark'  => '#7a4e14',
            'gold_light' => '#e2bf6c',
            'cream'      => '#f9f4e9',
            'ink'        => '#152233',
        ],
        'hero' => [
            'kicker'      => 'Interactive',
            'title_gold'  => 'Memories',
            'title'       => 'Catalogue',
            'tagline'     => 'Real Moments. Digital Magic. Forever Yours.',
            'verbs'       => ['Scan', 'Connect', 'Watch', 'Relive'],
            'script_1'    => 'More Than Photos',
            'script_2'    => 'Living Memories',
            'cta_primary' => 'See how it works',
            'cta_demo'    => 'Try the live demo',
            'image'       => $img('hero-scene.webp'),
            'promises'    => [
                ['title' => 'Personalised', 'sub' => 'For every stage'],
                ['title' => 'Premium',      'sub' => 'Quality'],
                ['title' => 'Lifetime',     'sub' => 'Emotions'],
                ['title' => 'Perfect',      'sub' => 'For all ages'],
            ],
            'bottom_line' => 'Memories that live beyond time',
        ],
        'steps' => [
            'script'     => '3 Easy Steps to',
            'title'      => 'Experience your',
            'title_big'  => 'Memories',
            'banner'     => 'Personalisation',
            'subline'    => 'Scan. Connect. Watch. Relive.',
            'items'      => [
                ['title' => 'Scan',       'desc' => 'Scan the QR code on your frame.',  'image' => $img('step-1.webp')],
                ['title' => 'Connect',    'desc' => 'Open the link on your phone.',     'image' => $img('step-2.webp')],
                ['title' => 'Experience', 'desc' => 'Watch your memories come alive.',  'image' => $img('step-3.webp')],
            ],
            'benefits'   => ['Scan instantly', 'One-tap access', 'Watch your moments', 'Relive memories'],
        ],
        'experiences' => [
            'eyebrow'     => 'See it in action',
            'heading'     => 'Unlocks Multi-Dimensional Experiences',
            'main_image'  => $img('frame-bride.webp'),
            'gallery'     => [$img('frame-graduation.webp'), $img('frame-roadtrip.webp'), $img('album-ar.webp')],
            'try_eyebrow' => 'Try it live',
            'try_heading' => 'See this photo come alive',
            'qr_image'    => $img('demo-qr.png'),
            'demo_code'   => 'gdd-zh2gdv',
            'scan_label'  => 'Scan me',
            'scan_then'   => 'then point your camera at the photo',
            'try_hint'    => "Scan the code with your phone, then hold it over the bride's photo on this screen.",
        ],
        'collection' => [
            'eyebrow'  => 'Turn the page to explore',
            'heading'  => 'Our Collection',
            'products' => [
                ['label' => 'Baby Frame',             'image' => $img('product-baby-frame.webp')],
                ['label' => 'Car Hanger',             'image' => $img('product-car-hanger.webp')],
                ['label' => 'Wallet Card',            'image' => $img('product-wallet-card.webp')],
                ['label' => 'Photo Box',              'image' => $img('product-photo-box.webp')],
                ['label' => 'Spotify Standee',        'image' => $img('product-spotify-standee.webp')],
                ['label' => 'My Baby 1st Album',      'image' => $img('product-baby-first-album.webp')],
                ['label' => 'Advertisement Pamphlet', 'image' => $img('product-advertisement-pamphlet.webp')],
                ['label' => 'Personalised God Frame', 'image' => $img('product-god-frame.webp')],
                ['label' => 'Visiting Card',          'image' => $img('product-visiting-card.webp')],
            ],
        ],
        'partners' => [
            'heading'    => 'Become a DEx partner',
            'text'       => 'Sell Living Photo DEx to your own customers, from your own branded DEx Studio.',
            'points'     => [
                ['title' => 'Your own branded studio',  'desc' => 'Your logo and colours on every page your customers see.'],
                ['title' => 'Create DEx experiences',   'desc' => 'Link photos and videos to frames, albums, cards and print.'],
                ['title' => 'Pay as you go',            'desc' => 'Buy credit packs as you need them — no subscription.'],
            ],
            'card_title' => 'Get started',
            'card_text'  => 'Register your business in a couple of minutes.',
            // The main button: register and buy credits.
            'buy_cta'     => 'Register & buy credits',
            // Shown only while the free trial is on, beneath the main button;
            // {credits} and {days} are filled in live.
            'trial_badge' => 'Free trial · {credits} credits',
            'trial_cta'   => 'Not ready to buy? Try it free',
            'trial_text'  => '{credits} free credits, no payment. Trial content is deleted automatically after {days} days.',
        ],
        'footer_line' => 'Real Moments. Digital Magic. Forever Yours.',
    ];
}

/**
 * The free DEx trial new partners get, or null when there is none to offer
 * (switched off in Admin → AR Partners, or its migration not run yet). Public
 * pages advertise the trial only through this, so they never promise one
 * that registration would not give.
 *
 * @return array{enabled: bool, credits: int, days: int}|null
 */
function dexTrialOffer(): ?array
{
    static $offer = false;
    if ($offer === false) {
        $offer = null;
        try {
            if ((new ArPartner())->trialsReady()) {
                $trial = ArPartner::trialSettings();
                $offer = $trial['enabled'] ? $trial : null;
            }
        } catch (Throwable $e) {
            error_log('DEx trial offer unavailable: ' . $e->getMessage());
        }
    }
    return $offer;
}

/** Fill {credits} and {days} in admin-written trial copy with the live trial settings. */
function dexTrialText(string $text, array $trial): string
{
    return strtr($text, [
        '{credits}' => number_format((int)$trial['credits']),
        '{days}'    => (string)(int)$trial['days'],
    ]);
}

/**
 * The DEx landing page content as saved in the Design Editor, laid over the
 * defaults so a key the form never saved still has its catalogue value.
 */
function dexLandingContent(): array
{
    static $content = null;
    if ($content !== null) {
        return $content;
    }
    $saved = [];
    try {
        $stmt = Database::getInstance()->prepare('SELECT content_json FROM site_sections WHERE section_key = ? LIMIT 1');
        $stmt->execute(['dex_landing']);
        $row = $stmt->fetch();
        $saved = $row ? (json_decode($row['content_json'], true) ?: []) : [];
    } catch (Throwable $e) {
        error_log('dex_landing load failed: ' . $e->getMessage());
    }
    // Groups merge one level deep; lists (products, steps …) replace wholesale.
    $content = dexLandingDefaults();
    foreach ($saved as $key => $value) {
        $isGroup = isset($content[$key]) && is_array($content[$key])
            && array_keys($content[$key]) !== range(0, count($content[$key]) - 1);
        if (is_array($value) && $isGroup) {
            $content[$key] = array_replace($content[$key], $value);
        } else {
            $content[$key] = $value;
        }
    }
    return $content;
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
