<div class="admin-flex-between">
    <p class="admin-muted">Manage discount coupons.</p>
    <a href="<?= url('/admin/coupons/create') ?>" class="admin-btn admin-btn-primary">+ Add Coupon</a>
</div>

<div class="admin-card admin-mt">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Usage</th><th>Validity</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($coupons as $c): ?>
                <tr>
                    <td><strong><?= e($c['code']) ?></strong></td>
                    <td><?= $c['discount_type'] === 'percent' ? (int)$c['discount_value'] . '%' : formatPrice($c['discount_value']) ?></td>
                    <td><?= formatPrice($c['min_order_value']) ?></td>
                    <td><?= (int)$c['used_count'] ?> / <?= $c['max_uses'] !== null ? (int)$c['max_uses'] : '∞' ?></td>
                    <td><?= date('d M Y', strtotime($c['valid_from'])) ?> – <?= date('d M Y', strtotime($c['valid_to'])) ?></td>
                    <td>
                        <?php if ($c['is_active']): ?>
                            <span class="admin-badge admin-badge-green">Active</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-gray">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="admin-btn admin-btn-sm" href="<?= url('/admin/coupons/' . $c['id'] . '/edit') ?>">Edit</a>
                        <form method="post" action="<?= url('/admin/coupons/' . $c['id'] . '/delete') ?>" style="display:inline">
                            <?= csrfField() ?>
                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Delete this coupon?">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($coupons)): ?>
                <tr><td colspan="7" class="admin-muted">No coupons yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
