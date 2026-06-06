<?php
$__settings = new Settings();
$__siteName = $__settings->get('site_name', SITE_NAME);
$__logo = $__settings->get('logo_path', '/images/GDKD logo.png');
$__primary = $__settings->get('primary_color', '#e63946');
$__accent = $__settings->get('accent_color', '#457b9d');
$__cart = new Cart();
$__cartCount = 0;
foreach ($__cart->items() as $__ci) { $__cartCount += (int)$__ci['quantity']; }
$__metaTitle = $metaTitle ?? ($__siteName . ' — ' . $__settings->get('site_tagline', 'Personalized Gifts'));
$__metaDesc = $metaDescription ?? $__settings->get('site_tagline', 'Shop personalized gifts online in India.');
$__canonical = url($_SERVER['REQUEST_URI'] ?? '/');

// Topbar round image buttons (admin-managed via Design Studio → Topbar Buttons; sensible defaults if not configured)
$__topbarRow = Database::getInstance()->prepare('SELECT content_json FROM site_sections WHERE section_key = ? LIMIT 1');
$__topbarRow->execute(['topbar_buttons']);
$__topbarSection = ($__r = $__topbarRow->fetch()) ? (json_decode($__r['content_json'], true) ?: []) : [];
$__topbarButtons = $__topbarSection['items'] ?? [];
if (empty($__topbarButtons)) {
    $__topbarButtons = [
        ['label' => 'Video & Photo QR Gifts', 'url' => url('/category/video-photo-gifts'), 'image' => ''],
        ['label' => 'Track Order', 'url' => url('/account/orders'), 'image' => ''],
        ['label' => 'Help & Support', 'url' => url('/contact'), 'image' => ''],
    ];
}
$__topbarEmojis = ['🎬', '📦', '💬', '🎁', '⭐', '❤️'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($__metaTitle) ?></title>
<meta name="description" content="<?= e($__metaDesc) ?>">
<link rel="canonical" href="<?= e($__canonical) ?>">
<meta property="og:title" content="<?= e($__metaTitle) ?>">
<meta property="og:description" content="<?= e($__metaDesc) ?>">
<meta property="og:image" content="<?= e($ogImage ?? asset($__logo)) ?>">
<link rel="icon" href="<?= e(asset($__logo)) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('public/css/main.css') ?>">
<style>:root{ --color-primary: <?= e($__primary) ?>; --color-accent: <?= e($__accent) ?>; }</style>
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<script>window.GDD_BASE_URL = <?= json_encode(rtrim(SITE_URL, '/')) ?>;</script>
</head>
<body>
<?php
$__catModel = new Category();
$__navCats = $__catModel->activeTopLevel();
// Desktop top bar: keep ONE clean row by showing only the most relevant
// categories (the flagship Video-Photo is always included). The full list
// still appears in "All Gifts", the footer, and the mobile menu.
$__navCatLimit = 6;
$__topNavCats = $__navCats;
if (count($__topNavCats) > $__navCatLimit) {
    $__flagship = array_values(array_filter($__topNavCats, fn($c) => ($c['slug'] ?? '') === 'video-photo-gifts'));
    $__rest = array_values(array_filter($__topNavCats, fn($c) => ($c['slug'] ?? '') !== 'video-photo-gifts'));
    $__topNavCats = array_slice($__rest, 0, $__navCatLimit - count($__flagship));
    $__topNavCats = array_merge($__topNavCats, $__flagship);
}
$__currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
function gddNavActive(string $href, string $current): string {
    $hrefPath = parse_url($href, PHP_URL_PATH);
    return ($hrefPath !== '' && $hrefPath !== '/' && str_starts_with($current, $hrefPath)) ? ' active' : '';
}
?>
<header class="gdd-header-wrap">
  <div class="gdd-topbar">
    <div class="container gdd-topbar-inner">
      <span class="gdd-topbar-msg">🎁 <?= e($__settings->get('promo_strip_text') ?: 'Free Shipping on Orders Above ₹999') ?></span>
      <nav class="gdd-topbar-links">
        <?php foreach ($__topbarButtons as $__tbi => $__tb): $__lbl = trim((string)($__tb['label'] ?? '')); if ($__lbl === '') continue; ?>
          <a href="<?= e($__tb['url'] ?? '#') ?>" class="gdd-topbar-link">
            <?php if (!empty($__tb['image'])): ?>
              <img src="<?= e(asset($__tb['image'])) ?>" alt="" class="gdd-topbar-link-img">
            <?php endif; ?>
            <span><?= e($__lbl) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>

  <div class="gdd-header">
    <div class="container gdd-header-main">
      <a href="<?= url('/') ?>" class="gdd-logo">
        <img src="<?= e(asset($__logo)) ?>" alt="<?= e($__siteName) ?>">
      </a>

      <div class="gdd-actions">
        <button class="gdd-burger" id="gddBurgerBtn" aria-label="Open menu">☰</button>
        <a href="<?= url('/account/wishlist') ?>" class="gdd-icon-btn" title="Wishlist">♡</a>
        <a href="<?= url('/cart') ?>" class="gdd-icon-btn" title="Cart">
          🛍 <?php if ($__cartCount > 0): ?><span class="badge-count"><?= (int)$__cartCount ?></span><?php endif; ?>
        </a>
        <?php if (isLoggedIn()): ?>
          <a href="<?= url('/account') ?>" class="gdd-account-btn" title="My Account">
            <span class="av"><?= e(strtoupper(substr((string)($_SESSION['user_name'] ?? 'A'), 0, 1))) ?></span>
            <span>Account</span>
          </a>
        <?php else: ?>
          <a href="<?= url('/account/login') ?>" class="btn btn-primary btn-sm">Login</a>
        <?php endif; ?>
      </div>
    </div>

    <nav class="gdd-catnav">
      <div class="container gdd-catnav-inner">
        <a href="<?= url('/') ?>" class="gdd-catpill <?= ($__currentPath === '/' || $__currentPath === '') ? 'active' : '' ?>">
          <span class="gdd-catpill-icon emoji">🏠</span>
          <span class="gdd-catpill-label">Home</span>
        </a>
        <a href="<?= url('/category/all') ?>" class="gdd-catpill">
          <span class="gdd-catpill-icon emoji">🎁</span>
          <span class="gdd-catpill-label">All Gifts</span>
        </a>
        <?php foreach ($__topNavCats as $__cat):
          $__href = url('/category/' . $__cat['slug']);
          $__isFeature = $__cat['slug'] === 'video-photo-gifts';
          $__catImg = $__cat['image'] ?? '';
        ?>
          <a href="<?= e($__href) ?>" class="gdd-catpill<?= $__isFeature ? ' featured' : '' ?><?= gddNavActive($__href, (string)$__currentPath) ?>">
            <span class="gdd-catpill-icon">
              <?php if ($__catImg): ?>
                <img src="<?= e(asset($__catImg)) ?>" alt="<?= e($__cat['name']) ?>" loading="lazy">
              <?php else: ?>
                <span class="emoji"><?= $__isFeature ? '🎬' : '🎀' ?></span>
              <?php endif; ?>
            </span>
            <span class="gdd-catpill-label"><?= e($__cat['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </nav>
  </div>
</header>

<!-- Mobile slide-in nav -->
<div class="gdd-mobile-nav" id="gddMobileNav">
  <div class="backdrop" data-mobile-nav-close></div>
  <div class="panel">
    <button class="close-btn" data-mobile-nav-close aria-label="Close menu">✕</button>
    <form class="gdd-search" action="<?= url('/category/all') ?>" method="get" role="search">
      <input type="search" name="q" placeholder="Search products…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search products">
      <button type="submit" aria-label="Search">🔍</button>
    </form>
    <a href="<?= url('/') ?>">🏠 Home</a>
    <a href="<?= url('/category/all') ?>">🎁 All Gifts</a>
    <?php foreach ($__navCats as $__cat): ?>
      <a href="<?= url('/category/' . $__cat['slug']) ?>"><?= $__cat['slug'] === 'video-photo-gifts' ? '🎬 ' : '' ?><?= e($__cat['name']) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('/about') ?>">ℹ️ About</a>
    <a href="<?= url('/contact') ?>">💬 Contact</a>
    <a href="<?= url('/account/wishlist') ?>">♡ Wishlist</a>
    <a href="<?= url('/cart') ?>">🛍 Cart<?php if ($__cartCount > 0): ?> (<?= (int)$__cartCount ?>)<?php endif; ?></a>
    <?php if (isLoggedIn()): ?>
      <a href="<?= url('/account') ?>">👤 My Account</a>
    <?php else: ?>
      <a href="<?= url('/account/login') ?>">🔑 Login / Register</a>
    <?php endif; ?>
  </div>
</div>
<?php if ($flashSuccess = flash('success')): ?>
  <div class="flash flash-success container"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError = flash('error')): ?>
  <div class="flash flash-error container"><?= e($flashError) ?></div>
<?php endif; ?>
<main>
