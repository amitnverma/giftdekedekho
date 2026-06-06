<div class="container account-layout">
  <?php renderRaw('store/partials/account_nav', ['active' => $active]); ?>
  <div>
    <h1>Hi, <?= e($_SESSION['user_name'] ?? 'there') ?> 👋</h1>
    <div class="trust-grid" style="margin:24px 0">
      <div class="trust-item" style="background:var(--color-bg-alt);border-radius:var(--radius)">
        <h3><?= count($recentOrders) ?>+</h3><p>Recent Orders</p>
      </div>
      <div class="trust-item" style="background:var(--color-bg-alt);border-radius:var(--radius)">
        <h3><?= (int)$wishlistCount ?></h3><p>Wishlist Items</p>
      </div>
      <div class="trust-item" style="background:var(--color-bg-alt);border-radius:var(--radius)">
        <h3><?= (int)$addressCount ?></h3><p>Saved Addresses</p>
      </div>
    </div>

    <h3>Recent Orders</h3>
    <?php if (empty($recentOrders)): ?>
      <p style="color:var(--color-muted)">You haven't placed any orders yet. <a href="<?= url('/category/all') ?>">Start shopping</a>.</p>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>Order ID</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
            <td><?= formatPrice($o['total']) ?></td>
            <td><span class="badge badge-<?= e($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
            <td><a href="<?= url('/account/orders/' . $o['id']) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
