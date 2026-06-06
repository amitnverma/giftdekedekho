<?php
/** @var array $p */
$inWishlist = $inWishlist ?? false;
$price = $p['sale_price'] !== null ? (float)$p['sale_price'] : (float)$p['base_price'];
$hasDiscount = $p['sale_price'] !== null && (float)$p['sale_price'] < (float)$p['base_price'];
$rating = isset($p['avg_rating']) ? (float)$p['avg_rating'] : 0;
$reviewCount = (int)($p['review_count'] ?? 0);
?>
<div class="product-card">
  <a href="<?= url('/product/' . $p['slug']) ?>">
    <div class="thumb-wrap">
      <img src="<?= e(asset($p['thumbnail'] ?: '/images/GDKD logo.png')) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    </div>
  </a>
  <button class="wishlist-toggle <?= $inWishlist ? 'active' : '' ?>" data-product-id="<?= (int)$p['id'] ?>" title="Add to wishlist" type="button">
    <?= $inWishlist ? '♥' : '♡' ?>
  </button>
  <div class="info">
    <a href="<?= url('/product/' . $p['slug']) ?>"><h3><?= e($p['name']) ?></h3></a>
    <?php if ($rating > 0): ?>
      <div class="stars"><?= starRating($rating) ?> <span style="color:var(--color-muted);font-size:12px">(<?= $reviewCount ?>)</span></div>
    <?php endif; ?>
    <div class="price-row">
      <span class="price-now"><?= formatPrice($price) ?></span>
      <?php if ($hasDiscount): ?><span class="price-old"><?= formatPrice($p['base_price']) ?></span><?php endif; ?>
    </div>
    <a href="<?= url('/product/' . $p['slug']) ?>" class="btn btn-outline btn-sm btn-block">Customise &amp; Buy</a>
  </div>
</div>
