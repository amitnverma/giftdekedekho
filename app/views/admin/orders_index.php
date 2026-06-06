<form method="get" class="admin-filters">
    <input type="text" name="search" placeholder="Search by order #, name or email" value="<?= e($filters['search']) ?>">
    <select name="status">
        <option value="">All Statuses</option>
        <?php foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $st): ?>
            <option value="<?= $st ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="payment_gateway">
        <option value="">All Gateways</option>
        <?php foreach (['razorpay', 'paypal', 'stripe', 'cod'] as $gw): ?>
            <option value="<?= $gw ?>" <?= $filters['payment_gateway'] === $gw ? 'selected' : '' ?>><?= strtoupper($gw) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
    <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
    <button class="admin-btn" type="submit">Filter</button>
</form>

<div class="admin-card admin-mt">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= e($o['customer_name'] ?? $o['customer_email'] ?? 'Guest') ?></td>
                    <td><?= formatPrice($o['total']) ?></td>
                    <td>
                        <span class="admin-badge <?= $o['payment_status'] === 'paid' ? 'admin-badge-green' : ($o['payment_status'] === 'failed' ? 'admin-badge-red' : 'admin-badge-yellow') ?>"><?= e($o['payment_status']) ?></span>
                        <span class="admin-muted" style="font-size:12px;"><?= strtoupper($o['payment_gateway']) ?></span>
                    </td>
                    <td><span class="admin-badge admin-badge-blue"><?= e($o['order_status']) ?></span></td>
                    <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    <td><a class="admin-btn admin-btn-sm" href="<?= url('/admin/orders/' . $o['id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="admin-muted">No orders found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
    <div class="admin-pagination">
        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($filters['status']) ?>&payment_gateway=<?= urlencode($filters['payment_gateway']) ?>&search=<?= urlencode($filters['search']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>"
               class="<?= $i === $pagination['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
