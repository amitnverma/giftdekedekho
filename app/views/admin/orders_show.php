<div class="admin-flex-between" style="margin-bottom:18px;">
    <a href="<?= url('/admin/orders') ?>" class="admin-btn admin-btn-sm">&larr; Back to Orders</a>
    <a href="<?= url('/admin/orders/' . $order['id'] . '/invoice') ?>" target="_blank" class="admin-btn admin-btn-primary">Download Invoice (PDF)</a>
</div>

<div class="admin-grid admin-grid-2" style="align-items:start;">
    <div class="admin-card">
        <h2 class="admin-card-title">Order #<?= (int)$order['id'] ?></h2>
        <p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
        <p><strong>Customer:</strong> <?= e($address['full_name'] ?? 'Guest') ?> (<?= e($order['guest_email'] ?? $address['email'] ?? '—') ?>)</p>
        <p><strong>Phone:</strong> <?= e($address['phone'] ?? $order['guest_phone'] ?? '—') ?></p>
        <p><strong>Shipping Address:</strong><br>
            <?= e($address['address_line1'] ?? '') ?> <?= e($address['address_line2'] ?? '') ?><br>
            <?= e($address['city'] ?? '') ?>, <?= e($address['state'] ?? '') ?> - <?= e($address['pincode'] ?? '') ?>
        </p>
        <p><strong>Payment:</strong> <?= strtoupper($order['payment_gateway']) ?> &middot;
            <span class="admin-badge <?= $order['payment_status'] === 'paid' ? 'admin-badge-green' : ($order['payment_status'] === 'failed' ? 'admin-badge-red' : 'admin-badge-yellow') ?>"><?= e($order['payment_status']) ?></span>
            <?php if (!empty($order['payment_reference'])): ?> <span class="admin-muted">Ref: <?= e($order['payment_reference']) ?></span><?php endif; ?>
        </p>
        <?php if (!empty($order['shiprocket_order_id'])): ?>
            <p><strong>Shiprocket Order:</strong> <?= e($order['shiprocket_order_id']) ?>
                <?php if (!empty($order['tracking_url'])): ?> &middot; <a href="<?= e($order['tracking_url']) ?>" target="_blank">Track Shipment</a><?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($order['notes'])): ?>
            <p><strong>Notes:</strong> <?= e($order['notes']) ?></p>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <h2 class="admin-card-title">Update Order Status</h2>
        <form method="post" action="<?= url('/admin/orders/' . $order['id'] . '/update-status') ?>" class="admin-form">
            <?= csrfField() ?>
            <label>Order Status
                <select name="order_status">
                    <?php foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $st): ?>
                        <option value="<?= $st ?>" <?= $order['order_status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="admin-btn admin-btn-primary">Update Status</button>
        </form>

        <h2 class="admin-card-title admin-mt">Tracking Information</h2>
        <form method="post" action="<?= url('/admin/orders/' . $order['id'] . '/tracking') ?>" class="admin-form">
            <?= csrfField() ?>
            <div class="admin-form-row">
                <label>Tracking Number
                    <input type="text" name="tracking_number" value="<?= e($order['tracking_number'] ?? '') ?>">
                </label>
                <label>Tracking URL
                    <input type="url" name="tracking_url" value="<?= e($order['tracking_url'] ?? '') ?>">
                </label>
            </div>
            <button type="submit" class="admin-btn">Save Tracking Info</button>
        </form>
    </div>
</div>

<div class="admin-card admin-mt">
    <h2 class="admin-card-title">Order Items</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Product</th><th>Customization</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Video / Photo QR</th></tr></thead>
            <tbody>
            <?php foreach ($order['items'] as $item):
                $custom = json_decode($item['customization_json'] ?? '[]', true) ?: [];
                $hasVideoOption = false;
                foreach ($custom as $c) { if (($c['option_type'] ?? '') === 'video_photo') $hasVideoOption = true; }
            ?>
                <tr>
                    <td><?= e($item['product_name_snapshot']) ?></td>
                    <td>
                        <?php if (!empty($custom)): ?>
                            <ul style="margin:0; padding-left:16px; font-size:13px;">
                                <?php foreach ($custom as $c): ?>
                                    <li><strong><?= e($c['label'] ?? $c['option_type'] ?? '') ?>:</strong>
                                        <?php if (($c['option_type'] ?? '') === 'photo_upload' && is_string($c['value'] ?? null) && $c['value'] !== ''): ?>
                                            <a href="<?= e(asset($c['value'])) ?>" target="_blank">View Photo</a>
                                        <?php else: ?>
                                            <?= e(is_array($c['value'] ?? null) ? json_encode($c['value']) : (string)($c['value'] ?? '')) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="admin-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td><?= formatPrice($item['unit_price']) ?></td>
                    <td><?= formatPrice((float)$item['unit_price'] * (int)$item['quantity']) ?></td>
                    <td>
                        <?php if ($hasVideoOption): ?>
                            <?php if (!empty($item['video_photo_id'])): ?>
                                <p class="admin-help" style="margin:0 0 6px;">
                                    Status:
                                    <span class="admin-badge <?= $item['video_photo_active'] ? 'admin-badge-green' : 'admin-badge-gray' ?>" id="vpStatus<?= (int)$item['id'] ?>">
                                        <?= $item['video_photo_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </p>
                                <button type="button" class="admin-btn admin-btn-sm" onclick="gddToggleVideo(<?= (int)$item['id'] ?>, this)">Toggle Active</button>
                            <?php endif; ?>
                            <details class="admin-mt">
                                <summary class="admin-btn admin-btn-sm" style="display:inline-block; cursor:pointer;"><?= !empty($item['video_photo_id']) ? 'Replace Video' : 'Upload Video' ?></summary>
                                <form method="post" action="<?= url('/admin/order-items/' . $item['id'] . '/upload-video') ?>" enctype="multipart/form-data" class="admin-form admin-mt" style="min-width:220px;">
                                    <?= csrfField() ?>
                                    <label>Video File (MP4/MOV/WebM, max 100MB)
                                        <input type="file" name="video" accept="video/*" required>
                                    </label>
                                    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Upload &amp; Generate QR</button>
                                </form>
                            </details>
                        <?php else: ?>
                            <span class="admin-muted">Not requested</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card admin-mt" style="max-width:380px;">
    <h2 class="admin-card-title">Order Summary</h2>
    <p class="admin-flex-between"><span>Subtotal</span> <strong><?= formatPrice($order['subtotal']) ?></strong></p>
    <p class="admin-flex-between"><span>Discount</span> <strong>- <?= formatPrice($order['discount']) ?></strong></p>
    <p class="admin-flex-between"><span>Shipping</span> <strong><?= formatPrice($order['shipping_charge']) ?></strong></p>
    <hr>
    <p class="admin-flex-between" style="font-size:18px;"><span>Total</span> <strong><?= formatPrice($order['total']) ?></strong></p>
</div>

<script>
function gddToggleVideo(itemId, btn) {
    fetch((window.GDD_BASE_URL || '') + '/admin/order-items/' + itemId + '/toggle-video', {
        method: 'POST',
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok) {
            var badge = document.getElementById('vpStatus' + itemId);
            badge.textContent = data.is_active ? 'Active' : 'Inactive';
            badge.className = 'admin-badge ' + (data.is_active ? 'admin-badge-green' : 'admin-badge-gray');
        }
    });
}
</script>
