<div class="container account-layout">
  <?php renderRaw('store/partials/account_nav', ['active' => $active]); ?>
  <div>
    <h1>My Orders</h1>
    <?php if (empty($orders)): ?>
      <div class="empty-state"><div class="icon">📦</div><h3>No orders yet</h3><p><a href="<?= url('/category/all') ?>">Browse our gifts</a> and place your first order.</p></div>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>Order ID</th><th>Date</th><th>Items Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
            <td><?= formatPrice($o['total']) ?></td>
            <td><span class="badge badge-<?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span></td>
            <td><span class="badge badge-<?= e($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
            <td><a href="<?= url('/account/orders/' . $o['id']) ?>">View Details</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
