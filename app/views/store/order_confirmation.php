<?php
/** @var array $order */
$addr = json_decode($order['address_snapshot_json'], true) ?: [];
?>
<div class="container" style="max-width:760px;padding:50px 20px">
  <div class="empty-state" style="padding:30px 20px">
    <div class="icon">🎉</div>
    <h2>Thank you! Your order has been placed.</h2>
    <p>Order ID: <strong>#<?= (int)$order['id'] ?></strong></p>
    <p>A confirmation has been sent to your email/phone. You can track your order from My Account.</p>
  </div>

  <h3>Order Summary</h3>
  <table class="data-table">
    <thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead>
    <tbody>
      <?php foreach ($order['items'] as $item): ?>
        <tr>
          <td><?= e($item['product_name_snapshot']) ?></td>
          <td><?= (int)$item['quantity'] ?></td>
          <td><?= formatPrice($item['unit_price'] * $item['quantity']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="order-summary" style="margin-top:20px">
    <div class="row"><span>Subtotal</span><span><?= formatPrice($order['subtotal']) ?></span></div>
    <?php if ((float)$order['discount'] > 0): ?><div class="row"><span>Discount</span><span>−<?= formatPrice($order['discount']) ?></span></div><?php endif; ?>
    <div class="row"><span>Shipping</span><span><?= formatPrice($order['shipping_charge']) ?></span></div>
    <div class="row total-row"><span>Total</span><span><?= formatPrice($order['total']) ?></span></div>
  </div>

  <h3 style="margin-top:24px">Delivery Address</h3>
  <p>
    <?= e($addr['full_name'] ?? '') ?><br>
    <?= e($addr['address_line1'] ?? '') ?> <?= e($addr['address_line2'] ?? '') ?><br>
    <?= e($addr['city'] ?? '') ?>, <?= e($addr['state'] ?? '') ?> - <?= e($addr['pincode'] ?? '') ?><br>
    📞 <?= e($addr['phone'] ?? '') ?>
  </p>

  <a href="<?= url('/category/all') ?>" class="btn btn-primary">Continue Shopping</a>
  <a href="<?= url('/account/orders') ?>" class="btn btn-outline">View My Orders</a>
</div>
