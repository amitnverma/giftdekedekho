<div class="container account-layout">
  <?php renderRaw('store/partials/account_nav', ['active' => $active]); ?>
  <div>
    <h1>My Wishlist</h1>
    <?php if (empty($items)): ?>
      <div class="empty-state"><div class="icon">♡</div><h3>Your wishlist is empty</h3><p><a href="<?= url('/category/all') ?>">Discover gifts you'll love</a>.</p></div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($items as $item):
          $price = $item['sale_price'] !== null ? (float)$item['sale_price'] : (float)$item['base_price']; ?>
          <div class="product-card">
            <a href="<?= url('/product/' . $item['slug']) ?>">
              <div class="thumb-wrap"><img src="<?= e(asset($item['thumbnail'] ?: '/images/GDKD logo.png')) ?>" alt="<?= e($item['name']) ?>" loading="lazy"></div>
            </a>
            <div class="info">
              <a href="<?= url('/product/' . $item['slug']) ?>"><h3><?= e($item['name']) ?></h3></a>
              <div class="price-row"><span class="price-now"><?= formatPrice($price) ?></span></div>
              <form method="post" action="<?= url('/account/wishlist') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-block">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
