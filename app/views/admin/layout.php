<?php
$adminView = $_adminView ?? null;
$pageTitle = $metaTitle ?? 'Admin';
$navItems = [
    ['icon' => '📊', 'label' => 'Dashboard', 'href' => '/admin', 'match' => ['admin/dashboard', 'admin/index']],
    ['icon' => '🎁', 'label' => 'Products', 'href' => '/admin/products', 'match' => ['admin/products']],
    ['icon' => '🗂️', 'label' => 'Categories', 'href' => '/admin/categories', 'match' => ['admin/categories']],
    ['icon' => '📦', 'label' => 'Orders', 'href' => '/admin/orders', 'match' => ['admin/orders']],
    ['icon' => '🏷️', 'label' => 'Coupons', 'href' => '/admin/coupons', 'match' => ['admin/coupons']],
    ['icon' => '👥', 'label' => 'Customers', 'href' => '/admin/customers', 'match' => ['admin/customers']],
    ['icon' => '⭐', 'label' => 'Reviews', 'href' => '/admin/reviews', 'match' => ['admin/reviews']],
    ['icon' => '🚚', 'label' => 'Shipping', 'href' => '/admin/shipping', 'match' => ['admin/shipping']],
    ['icon' => '🎨', 'label' => 'Design Editor', 'href' => '/admin/design', 'match' => ['admin/design']],
    ['icon' => '🔔', 'label' => 'Notifications', 'href' => '/admin/notifications', 'match' => ['admin/notifications']],
    ['icon' => '💳', 'label' => 'Payment Settings', 'href' => '/admin/settings/payments', 'match' => ['admin/settings_payments']],
    ['icon' => '⚙️', 'label' => 'General Settings', 'href' => '/admin/settings', 'match' => ['admin/settings']],
];
$activeView = str_replace('/', '/', (string)$adminView);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?> Admin</title>
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<link rel="stylesheet" href="<?= asset('public/css/admin.css') ?>">
<script>window.GDD_BASE_URL = <?= json_encode(rtrim(SITE_URL, '/')) ?>;</script>
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <img src="<?= asset('public/images/logo.png') ?>" alt="<?= e(SITE_NAME) ?>" onerror="this.style.display='none'">
            <span><?= e(SITE_NAME) ?></span>
        </div>
        <nav class="admin-nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= url($item['href']) ?>" class="admin-nav-link <?= in_array($activeView, $item['match'], true) ? 'active' : '' ?>">
                    <span class="admin-nav-icon"><?= $item['icon'] ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
            <a href="<?= url('/admin/logout') ?>" class="admin-nav-link admin-nav-logout">
                <span class="admin-nav-icon">🚪</span>
                <span>Logout</span>
            </a>
        </nav>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-burger" id="adminBurger" aria-label="Toggle menu">☰</button>
            <h1 class="admin-page-title"><?= e($pageTitle) ?></h1>
            <div class="admin-topbar-right">
                <a href="<?= url('/') ?>" target="_blank" class="admin-view-site">View Site ↗</a>
                <span class="admin-user"><?= e($_SESSION['user_name'] ?? 'Admin') ?></span>
            </div>
        </header>
        <main class="admin-content">
            <?php if ($msg = flash('success')): ?>
                <div class="admin-alert admin-alert-success"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="admin-alert admin-alert-error"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php require viewPath($adminView . '.php'); ?>
        </main>
    </div>
</div>
<script src="<?= asset('public/js/admin.js') ?>"></script>
</body>
</html>
