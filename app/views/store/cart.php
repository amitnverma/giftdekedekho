<?php
/** @var array $items */
?>
<div class="container">
  <h1 class="page-title">Your Cart</h1>

  <?php if (empty($items)): ?>
    <div class="empty-state">
      <div class="icon">🛒</div>
      <h3>Your cart is empty</h3>
      <p>Looks like you haven't added anything to your cart yet.</p>
      <a href="<?= url('/category/all') ?>" class="btn btn-primary">Continue Shopping</a>
    </div>
  <?php else: ?>
  <div class="cart-layout">
    <div>
      <?php foreach ($items as $item):
        $price = $item['sale_price'] !== null ? (float)$item['sale_price'] : (float)$item['base_price'];
        $custom = json_decode($item['customization_json'] ?? '[]', true) ?: [];
        $lineTotal = $cartModel->lineTotal($item);
      ?>
        <div class="cart-item">
          <img src="<?= e(asset($item['thumbnail'] ?: '/images/GDKD logo.png')) ?>" alt="<?= e($item['name']) ?>">
          <div>
            <a href="<?= url('/product/' . $item['slug']) ?>"><strong><?= e($item['name']) ?></strong></a>
            <div><?= formatPrice($price) ?> × <?= (int)$item['quantity'] ?></div>
            <?php if (!empty($custom)): ?>
              <div class="custom-summary">
                <?php foreach ($custom as $c): ?>
                  <div>• <?= e($c['label']) ?>:
                    <?php if (is_string($c['value']) && str_starts_with($c['value'], '/public/')): ?>
                      <a href="<?= e(asset($c['value'])) ?>" target="_blank" rel="noopener">View uploaded photo</a>
                    <?php elseif ($c['value'] === true): ?> Yes
                    <?php else: ?> "<?= e($c['value']) ?>"
                    <?php endif; ?>
                    <?php if (!empty($c['extra_charge'])): ?> (+<?= formatPrice($c['extra_charge']) ?>)<?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="qty-stepper" data-cart-id="<?= (int)$item['id'] ?>" style="margin-top:10px">
              <button type="button" data-action="dec">−</button>
              <input type="text" value="<?= (int)$item['quantity'] ?>" readonly>
              <button type="button" data-action="inc">+</button>
            </div>
          </div>
          <div style="text-align:right">
            <div style="font-weight:700;margin-bottom:10px"><?= formatPrice($lineTotal) ?></div>
            <form action="<?= url('/cart/remove') ?>" method="post">
              <?= csrfField() ?>
              <input type="hidden" name="cart_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm">Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="order-summary">
      <h3>Order Summary</h3>
      <div class="coupon-box">
        <input type="text" id="couponInput" placeholder="Coupon code" value="<?= e($couponCode ?? '') ?>">
        <button class="btn btn-primary btn-sm" id="applyCouponBtn" type="button">Apply</button>
      </div>
      <div id="couponMessage" style="font-size:13px;margin-bottom:12px"></div>

      <div class="row"><span>Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
      <?php if ($discount > 0): ?>
        <div class="row" style="color:#1b7a43"><span>Discount <?= $couponCode ? '(' . e($couponCode) . ')' : '' ?></span><span>−<?= formatPrice($discount) ?></span></div>
      <?php endif; ?>
      <div class="row"><span>Shipping</span><span><?= $shipping > 0 ? formatPrice($shipping) : 'FREE' ?></span></div>
      <div class="row total-row"><span>Total</span><span><?= formatPrice($total) ?></span></div>
      <a href="<?= url('/checkout') ?>" class="btn btn-primary btn-block" style="margin-top:14px">Proceed to Checkout</a>
    </div>
  </div>
  <?php endif; ?>
</div>
