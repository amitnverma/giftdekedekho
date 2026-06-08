<?php
$catSection = $sections['shop_by_category'] ?? [];
if (isset($catSection['is_active']) && empty($catSection['is_active'])) return;

$_csStyle     = $catSection['style'] ?? [];
if (!isset($_csStyle['bg_color']) && isset($catSection['bg_color'])) {
    $_csStyle['bg_color'] = $catSection['bg_color'] === 'var(--color-bg-alt)' ? '#f8f9fb' : $catSection['bg_color'];
}
if (!isset($_csStyle['align']) && isset($catSection['heading_align'])) {
    $_csStyle['align'] = $catSection['heading_align'];
}
if (empty($_csStyle['bg_color'])) $_csStyle['bg_color'] = '#f8f9fb';

$_csHeading   = $catSection['heading']       ?? 'Shop by Category';
$_csSubtext   = $catSection['subtext']       ?? 'Find the perfect personalised gift for every occasion';
$_csKicker    = $catSection['kicker']        ?? 'Browse';
$_csNameAlign = $catSection['name_align']    ?? 'left';
$_csNameColor = $catSection['name_color']    ?? '#ffffff';
$_csNameSize  = $catSection['name_size']     ?? '15';
$_csNameWeight= $catSection['name_weight']   ?? '700';
$_csOverlay   = $catSection['overlay_color'] ?? '#000000';
$_csCardStyle = $catSection['card_style']    ?? 'boxed';
$_csOrder     = $catSection['category_order']?? [];

$_catMap = [];
foreach ($categories as $c) { $_catMap[$c['slug']] = $c; }
$_orderedCats = [];
foreach ($_csOrder as $slug) {
    if (isset($_catMap[$slug])) { $_orderedCats[] = $_catMap[$slug]; unset($_catMap[$slug]); }
}
foreach ($_catMap as $c) { $_orderedCats[] = $c; }

$_csOverlayRgb = sscanf($_csOverlay, "#%02x%02x%02x");
$_csOverlayCss = $_csOverlayRgb
    ? sprintf('linear-gradient(0deg, rgba(%d,%d,%d,.62) 0%%, rgba(%d,%d,%d,0) 55%%)', $_csOverlayRgb[0], $_csOverlayRgb[1], $_csOverlayRgb[2], $_csOverlayRgb[0], $_csOverlayRgb[1], $_csOverlayRgb[2])
    : '';
$_csJustify = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$_csNameAlign] ?? 'flex-start';
?>
<section class="section" style="<?= sectionBgStyle($_csStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_csStyle, $_csKicker, $_csHeading, $_csSubtext); ?>
    <div class="gdd-cat-grid<?= $_csCardStyle === 'plain' ? ' gdd-cat-grid--plain' : '' ?> reveal-stagger reveal">
      <?php foreach ($_orderedCats as $cat): ?>
        <?php if ($_csCardStyle === 'plain'): ?>
        <a class="gdd-cat-card gdd-cat-card--plain" href="<?= url('/category/' . $cat['slug']) ?>">
          <div class="cat-img-wrap">
            <img src="<?= e(asset($cat['image'] ?: '/images/GDKD logo.png')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
          </div>
          <span class="cat-label" style="color:<?= e($_csNameColor !== '#ffffff' ? $_csNameColor : '#1d1d1f') ?>;font-size:<?= (int)$_csNameSize ?>px;font-weight:<?= e($_csNameWeight) ?>"><?= e($cat['name']) ?></span>
        </a>
        <?php else: ?>
        <a class="gdd-cat-card" href="<?= url('/category/' . $cat['slug']) ?>">
          <img src="<?= e(asset($cat['image'] ?: '/images/GDKD logo.png')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
          <div class="overlay" style="justify-content:<?= e($_csJustify) ?><?= $_csOverlayCss ? ';background:' . e($_csOverlayCss) : '' ?>">
            <span style="color:<?= e($_csNameColor) ?>;font-size:<?= (int)$_csNameSize ?>px;font-weight:<?= e($_csNameWeight) ?>"><?= e($cat['name']) ?></span>
          </div>
        </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
