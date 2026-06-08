<?php
$featuredSection = $sections['featured_products_section'] ?? [];
if (isset($featuredSection['is_active']) && empty($featuredSection['is_active'])) return;
if (empty($featured)) return;

$_ftStyle = $featuredSection['style'] ?? [];
if (empty($_ftStyle['bg_color'])) $_ftStyle['bg_color'] = '#f8f9fb';
?>
<section class="section" style="<?= sectionBgStyle($_ftStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_ftStyle, $featuredSection['kicker'] ?? 'Trending Now', $featuredSection['heading'] ?? 'Featured Gifts', $featuredSection['subtext'] ?? 'Hand-picked favourites our customers love'); ?>
    <div class="product-grid reveal-stagger reveal">
      <?php foreach ($featured as $p): ?>
        <?php renderRaw('store/partials/product_card', ['p' => $p, 'inWishlist' => in_array($p['id'], $wishlistIds)]); ?>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:34px">
      <a href="<?= url('/category/all') ?>" class="btn btn-outline">View All Products →</a>
    </div>
  </div>
</section>
