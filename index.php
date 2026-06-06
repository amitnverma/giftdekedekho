<?php
/**
 * Front controller / router for GiftDekeDekho.
 * All requests (except static files / api / admin sub-handlers via .htaccess) are routed here.
 */

session_start();

require __DIR__ . '/config/config.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/app/helpers.php';

// ---- Simple PSR-0-ish autoloader for controllers and models ----
spl_autoload_register(function ($class) {
    $paths = [
        APP_PATH . '/controllers/' . $class . '.php',
        APP_PATH . '/models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});

// ---- HTTPS redirect in production ----
if (ENVIRONMENT === 'production' && empty($_SERVER['HTTPS'])) {
    redirect('https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'));
}

// ---- Resolve path ----
$basePath = rtrim(parse_url(SITE_URL, PHP_URL_PATH) ?: '', '/');
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $requestUri;
if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
$path = '/' . trim($path, '/');
$segments = $path === '/' ? [] : explode('/', trim($path, '/'));

$method = $_SERVER['REQUEST_METHOD'];

try {
    // ---- Routing table ----

    // Admin routes
    if (($segments[0] ?? '') === 'admin') {
        require APP_PATH . '/controllers/AdminRouter.php';
        (new AdminRouter())->dispatch(array_slice($segments, 1), $method);
        exit;
    }

    // Watch (video-photo QR player) — /watch/{token}
    if (($segments[0] ?? '') === 'watch' && isset($segments[1])) {
        (new WatchController())->show($segments[1]);
        exit;
    }

    // Sitemap
    if ($path === '/sitemap.xml') {
        require BASE_PATH . '/sitemap.php';
        exit;
    }

    // Static-ish routes
    switch (true) {
        case $path === '/':
            (new HomeController())->index();
            break;

        case $path === '/category' || (($segments[0] ?? '') === 'category' && isset($segments[1])):
            $slug = $segments[1] ?? 'all';
            (new CategoryController())->show($slug);
            break;

        case ($segments[0] ?? '') === 'product' && isset($segments[1]):
            (new ProductController())->show($segments[1]);
            break;

        case $path === '/cart':
            (new CartController())->index();
            break;

        case $path === '/cart/add' && $method === 'POST':
            (new CartController())->add();
            break;

        case $path === '/cart/update' && $method === 'POST':
            (new CartController())->update();
            break;

        case $path === '/cart/remove' && $method === 'POST':
            (new CartController())->remove();
            break;

        case $path === '/cart/coupon' && $method === 'POST':
            (new CartController())->applyCoupon();
            break;

        case $path === '/checkout':
            (new CheckoutController())->index();
            break;

        case $path === '/checkout/place-order' && $method === 'POST':
            (new CheckoutController())->placeOrder();
            break;

        case $path === '/checkout/payment-callback':
            (new CheckoutController())->paymentCallback();
            break;

        case preg_match('#^/order/confirmation/(\d+)$#', $path, $m) === 1:
            (new CheckoutController())->confirmation((int)$m[1]);
            break;

        case $path === '/account' || $path === '/account/dashboard':
            (new AccountController())->dashboard();
            break;

        case $path === '/account/login':
            (new AccountController())->login();
            break;

        case $path === '/account/register':
            (new AccountController())->register();
            break;

        case $path === '/account/logout':
            (new AccountController())->logout();
            break;

        case $path === '/account/orders':
            (new AccountController())->orders();
            break;

        case preg_match('#^/account/orders/(\d+)$#', $path, $m) === 1:
            (new AccountController())->orderDetail((int)$m[1]);
            break;

        case $path === '/account/wishlist':
            (new AccountController())->wishlist();
            break;

        case $path === '/account/addresses':
            (new AccountController())->addresses();
            break;

        case $path === '/account/profile':
            (new AccountController())->profile();
            break;

        case $path === '/contact':
            (new PageController())->contact();
            break;

        case $path === '/about':
            (new PageController())->about();
            break;

        case preg_match('#^/api/#', $path) === 1:
            http_response_code(404);
            jsonResponse(['error' => 'Not found']);
            break;

        default:
            http_response_code(404);
            (new PageController())->notFound();
            break;
    }
} catch (Throwable $e) {
    if (ENVIRONMENT === 'development') {
        echo '<pre style="padding:20px;background:#fff3f3;color:#900;font-family:monospace;white-space:pre-wrap">';
        echo 'Uncaught: ' . e($e->getMessage()) . "\n" . e($e->getTraceAsString());
        echo '</pre>';
    } else {
        http_response_code(500);
        echo 'Something went wrong. Please try again later.';
    }
}
