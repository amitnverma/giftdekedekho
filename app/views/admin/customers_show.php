<a href="<?= url('/admin/customers') ?>" class="admin-btn admin-btn-sm">&larr; Back to Customers</a>

<div class="admin-grid admin-grid-2 admin-mt" style="align-items:start;">
    <div class="admin-card">
        <h2 class="admin-card-title"><?= e($customer['name']) ?></h2>
        <p><strong>Email:</strong> <?= e($customer['email']) ?></p>
        <p><strong>Phone:</strong> <?= e($customer['phone'] ?? '—') ?></p>
        <p><strong>Joined:</strong> <?= date('d M Y', strtotime($customer['created_at'])) ?></p>
    </div>

    <div class="admin-card">
        <h2 class="admin-card-title">Saved Addresses</h2>
        <?php if (empty($addresses)): ?>
            <p class="admin-muted">No saved addresses.</p>
        <?php else: ?>
            <?php foreach ($addresses as $addr): ?>
                <p style="border-bottom:1px solid var(--admin-border); padding-bottom:8px; margin-bottom:8px;">
                    <strong><?= e($addr['full_name']) ?></strong><br>
                    <?= e($addr['address_line1']) ?> <?= e($addr['address_line2'] ?? '') ?><br>
                    <?= e($addr['city']) ?>, <?= e($addr['state']) ?> - <?= e($addr['pincode']) ?><br>
                    Phone: <?= e($addr['phone']) ?>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card admin-mt">
    <h2 class="admin-card-title">Order History</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Order #</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= formatPrice($o['total']) ?></td>
                    <td><span class="admin-badge admin-badge-blue"><?= e($o['payment_status']) ?></span></td>
                    <td><span class="admin-badge admin-badge-blue"><?= e($o['order_status']) ?></span></td>
                    <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    <td><a class="admin-btn admin-btn-sm" href="<?= url('/admin/orders/' . $o['id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="6" class="admin-muted">No orders placed yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
