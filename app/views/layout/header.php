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

// Promo strip (admin-managed via Design Studio → Promo Strip)
$__promoRow = Database::getInstance()->prepare('SELECT content_json FROM site_sections WHERE section_key = ? LIMIT 1');
$__promoRow->execute(['promo_strip']);
$__promoSection = ($__r = $__promoRow->fetch()) ? (json_decode($__r['content_json'], true) ?: []) : [];
$__promoActive = !empty($__promoSection['is_active']);
$__promoText = $__promoSection['text'] ?? $__settings->get('promo_strip_text', 'Free Shipping on Orders Above ₹999');

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
$__searchPhrasesRaw  = $__settings->get('search_placeholders', "Search personalised gifts…\nTry \"photo frame\" or \"mug\"…\nBirthday gifts for her…\nAnniversary surprises…\nCustom name gifts…");
$__searchPhrases     = array_values(array_filter(array_map('trim', explode("\n", $__searchPhrasesRaw))));
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
<link rel="stylesheet" href="<?= asset('public/css/main.css') ?>?v=<?= filemtime(BASE_PATH . '/public/css/main.css') ?>">
<style>:root{ --color-primary: <?= e($__primary) ?>; --color-accent: <?= e($__accent) ?>; }</style>
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<script>window.GDD_BASE_URL = <?= json_encode(rtrim(SITE_URL, '/')) ?>;
window.GDD_SEARCH_PHRASES = <?= json_encode($__searchPhrases, JSON_UNESCAPED_UNICODE) ?>;</script>
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
  <?php
    $__topbarButtonsActive = !empty($__topbarSection['is_active']);
    $__showTopbar = $__promoActive || $__topbarButtonsActive;
  ?>
  <?php if ($__showTopbar): ?>
  <div class="gdd-topbar">
    <div class="container gdd-topbar-inner">
      <?php if ($__promoActive): ?>
      <span class="gdd-topbar-msg">🎁 <?= e($__promoText) ?></span>
      <?php else: ?>
      <span class="gdd-topbar-msg"></span>
      <?php endif; ?>
      <nav class="gdd-topbar-links">
        <?php foreach ($__topbarButtons as $__tbi => $__tb): $__lbl = trim((string)($__tb['label'] ?? '')); if ($__lbl === '') continue; ?>
          <a href="<?= e($__tb['url'] ?? '#') ?>" class="gdd-topbar-link">
            <?php if (!empty($__tb['image'])): ?>
              <img src="<?= e(asset($__tb['image'])) ?>" alt="" class="gdd-topbar-link-img">
            <?php elseif (!empty($__tb['emoji'])): ?>
              <span class="gdd-topbar-link-emoji"><?= e($__tb['emoji']) ?></span>
            <?php endif; ?>
            <span><?= e($__lbl) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>
  <?php endif; ?>

  <div class="gdd-header">
    <div class="container gdd-header-main">
      <a href="<?= url('/') ?>" class="gdd-logo">
        <img src="<?= e(asset($__logo)) ?>" alt="<?= e($__siteName) ?>">
      </a>

      <!-- ── Header search bar ── -->
      <div class="gdd-hsearch" id="gddHSearch">
        <form class="gdd-hsearch-form" action="<?= url('/category/all') ?>" method="get" role="search" autocomplete="off">
          <span class="gdd-hsearch-icon">🔍</span>
          <input type="search" name="q" id="gddHSearchInput"
                 placeholder=""
                 value="<?= e($_GET['q'] ?? '') ?>"
                 aria-label="Search gifts" aria-autocomplete="list" aria-controls="gddHSearchDrop">
          <button type="submit" aria-label="Search">→</button>
        </form>

        <!-- category dropdown shown on focus -->
        <div class="gdd-hsearch-drop" id="gddHSearchDrop" role="listbox" aria-label="Browse categories">
          <p class="gdd-hsearch-drop-label">Browse by category</p>
          <div class="gdd-hsearch-cats">
            <a href="<?= url('/category/all') ?>" class="gdd-hsearch-cat">
              <span class="gdd-hsearch-cat-icon">🎁</span>
              <span>All Gifts</span>
            </a>
            <?php foreach ($__navCats as $__hcat):
              $__himg = $__hcat['image'] ?? '';
            ?>
            <a href="<?= e(url('/category/' . $__hcat['slug'])) ?>" class="gdd-hsearch-cat">
              <span class="gdd-hsearch-cat-icon">
                <?php if ($__himg): ?>
                  <img src="<?= e(asset($__himg)) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <?= $__hcat['slug'] === 'video-photo-gifts' ? '🎬' : '🎀' ?>
                <?php endif; ?>
              </span>
              <span><?= e($__hcat['name']) ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

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

  </div><!-- /.gdd-header -->
</header>

<?php
// Category nav bar — outside the sticky header so overflow-x scroll works correctly
$__navBarRow = Database::getInstance()->prepare('SELECT content_json FROM site_sections WHERE section_key = ? LIMIT 1');
$__navBarRow->execute(['nav_category_bar']);
$__navBarSection = ($__r = $__navBarRow->fetch()) ? (json_decode($__r['content_json'], true) ?: []) : [];
$__navBarActive  = !isset($__navBarSection['is_active'])      || !empty($__navBarSection['is_active']);
$__navShowHome   = !isset($__navBarSection['show_home'])      || !empty($__navBarSection['show_home']);
$__navShowAll    = !isset($__navBarSection['show_all_gifts']) || !empty($__navBarSection['show_all_gifts']);
$__navMaxItems   = (int)($__navBarSection['max_items'] ?? 0);
$__navBarItems   = $__navBarSection['items'] ?? [];

// If not configured yet, fall back to the top N active categories
if (empty($__navBarItems)) {
    $__navBarItems = array_map(fn($c) => [
        'slug'    => $c['slug'],
        'label'   => $c['name'],
        'emoji'   => '',
        'visible' => true,
    ], $__topNavCats);
}

// Apply max_items limit (only count visible items)
if ($__navMaxItems > 0) {
    $__limited = []; $__visCount = 0;
    foreach ($__navBarItems as $__nbi) {
        if (!empty($__nbi['visible'])) {
            if ($__visCount >= $__navMaxItems) { $__nbi['visible'] = false; }
            else $__visCount++;
        }
        $__limited[] = $__nbi;
    }
    $__navBarItems = $__limited;
}

// Build category lookup
$__catLookup = [];
foreach ($__navCats as $__nc) { $__catLookup[$__nc['slug']] = $__nc; }
?>
<?php if ($__navBarActive): ?>
<nav class="gdd-catnav">
  <button type="button" class="gdd-catnav-arrow gdd-catnav-arrow-left" aria-label="Scroll left" hidden>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
  <div class="gdd-catnav-inner">
    <?php if ($__navShowHome): ?>
    <a href="<?= url('/') ?>" class="gdd-catpill <?= ($__currentPath === '/' || $__currentPath === '') ? 'active' : '' ?>">
      <span class="gdd-catpill-icon emoji">🏠</span>
      <span class="gdd-catpill-label">Home</span>
    </a>
    <?php endif; ?>
    <?php if ($__navShowAll): ?>
    <a href="<?= url('/category/all') ?>" class="gdd-catpill">
      <span class="gdd-catpill-icon emoji">🎁</span>
      <span class="gdd-catpill-label">All Gifts</span>
    </a>
    <?php endif; ?>
    <?php foreach ($__navBarItems as $__nbi):
      if (empty($__nbi['visible'])) continue;
      $__nbiSlug  = $__nbi['slug'] ?? '';
      $__nbiLabel = $__nbi['label'] ?: ($__catLookup[$__nbiSlug]['name'] ?? $__nbiSlug);
      $__nbiEmoji = $__nbi['emoji'] ?? '';
      $__nbiCat   = $__catLookup[$__nbiSlug] ?? null;
      $__nbiImg   = $__nbiCat['image'] ?? '';
      $__nbiHref  = url('/category/' . $__nbiSlug);
      $__isFeat   = $__nbiSlug === 'video-photo-gifts';
    ?>
      <a href="<?= e($__nbiHref) ?>" class="gdd-catpill<?= $__isFeat ? ' featured' : '' ?><?= gddNavActive($__nbiHref, (string)$__currentPath) ?>">
        <span class="gdd-catpill-icon">
          <?php if ($__nbiImg): ?>
            <img src="<?= e(asset($__nbiImg)) ?>" alt="<?= e($__nbiLabel) ?>" loading="lazy">
          <?php else: ?>
            <span class="emoji"><?= e($__nbiEmoji ?: ($__isFeat ? '🎬' : '🎀')) ?></span>
          <?php endif; ?>
        </span>
        <span class="gdd-catpill-label"><?= e($__nbiLabel) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <button type="button" class="gdd-catnav-arrow gdd-catnav-arrow-right" aria-label="Scroll right" hidden>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
</nav>
<script>
(function(){
  var nav = document.querySelector('.gdd-catnav');
  if (!nav) return;
  var el    = nav.querySelector('.gdd-catnav-inner');
  var left  = nav.querySelector('.gdd-catnav-arrow-left');
  var right = nav.querySelector('.gdd-catnav-arrow-right');
  if (!el) return;

  function update() {
    var maxScroll = el.scrollWidth - el.clientWidth;
    var x = el.scrollLeft;
    // Show left arrow once scrolled away from start
    if (left)  left.hidden  = x <= 1;
    // Show right arrow while there is more to scroll
    if (right) right.hidden = x >= maxScroll - 1;
    // Edge fades
    nav.classList.toggle('has-left-fade',  x > 1);
    nav.classList.toggle('has-right-fade', x < maxScroll - 1);
  }

  function scrollByStep(dir) {
    el.scrollBy({ left: dir * Math.max(240, el.clientWidth * 0.6), behavior: 'smooth' });
  }

  if (left)  left.addEventListener('click',  function(){ scrollByStep(-1); });
  if (right) right.addEventListener('click', function(){ scrollByStep(1); });

  // Mouse-wheel scrolls horizontally on desktop
  el.addEventListener('wheel', function(e) {
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
      e.preventDefault();
      el.scrollLeft += e.deltaY;
    }
  }, { passive: false });

  el.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  // Run after layout settles (images can change widths)
  update();
  window.addEventListener('load', update);
  setTimeout(update, 300);
})();
</script>
<?php endif; ?>

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
