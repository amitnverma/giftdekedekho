<?php
$addr = json_decode($order['address_snapshot_json'], true) ?: [];
?>
<div class="container account-layout">
  <?php renderRaw('store/partials/account_nav', ['active' => $active]); ?>
  <div>
    <div class="breadcrumbs"><a href="<?= url('/account/orders') ?>">My Orders</a> / Order #<?= (int)$order['id'] ?></div>
    <h1>Order #<?= (int)$order['id'] ?></h1>
    <p>
      <span class="badge badge-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span>
      <span class="badge badge-<?= e($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span>
      Placed on <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?>
    </p>

    <?php if (!empty($order['tracking_number'])): ?>
      <p>📦 Tracking Number: <strong><?= e($order['tracking_number']) ?></strong>
        <?php if (!empty($order['tracking_url'])): ?> — <a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener">Track Shipment</a><?php endif; ?>
      </p>
    <?php endif; ?>

    <h3>Items</h3>
    <table class="data-table">
      <thead><tr><th>Item</th><th>Customization</th><th>Qty</th><th>Price</th></tr></thead>
      <tbody>
      <?php foreach ($order['items'] as $item):
        $custom = json_decode($item['customization_json'] ?? '[]', true) ?: []; ?>
        <tr>
          <td><?= e($item['product_name_snapshot']) ?></td>
          <td>
            <?php foreach ($custom as $c): ?>
              <div style="font-size:13px">• <?= e($c['label']) ?>: <?= is_string($c['value'] ?? '') ? e($c['value']) : (($c['value'] ?? false) === true ? 'Yes' : '') ?></div>
            <?php endforeach; ?>
            <?php if (!empty($item['video_photo_id'])): ?>
              <div style="margin-top:6px">
                <span class="badge <?= $item['video_photo_active'] ? 'badge-delivered' : 'badge-pending' ?>">
                  <?= $item['video_photo_active'] ? 'Video-Photo Ready' : 'Video-Photo Pending' ?>
                </span>
              </div>
            <?php endif; ?>
          </td>
          <td><?= (int)$item['quantity'] ?></td>
          <td><?= formatPrice($item['unit_price'] * $item['quantity']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="order-summary" style="margin-top:20px;max-width:360px">
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
  </div>
</div>
