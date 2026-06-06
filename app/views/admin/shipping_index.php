<div class="admin-grid admin-grid-2" style="align-items:start;">
    <div class="admin-card">
        <h2 class="admin-card-title">Shipping Rule</h2>
        <form method="post" class="admin-form">
            <?= csrfField() ?>
            <label>Label
                <input type="text" name="label" value="<?= e($rule['label'] ?? 'Standard Shipping') ?>">
            </label>
            <label>Flat Rate (₹)
                <input type="number" step="0.01" min="0" name="flat_rate" value="<?= e($rule['flat_rate'] ?? '0') ?>">
            </label>
            <label>Free Shipping Above Order Value (₹) <span class="admin-label-hint">Leave blank to disable free shipping</span>
                <input type="number" step="0.01" min="0" name="free_above_amount" value="<?= e($rule['free_above_amount'] ?? '') ?>">
            </label>
            <button type="submit" class="admin-btn admin-btn-primary">Save Shipping Settings</button>
        </form>
    </div>

    <div class="admin-card">
        <h2 class="admin-card-title">Bulk Pincode Upload</h2>
        <p class="admin-help">Upload a CSV with columns: <code>pincode, is_serviceable (1/0), estimated_days</code></p>
        <form method="post" action="<?= url('/admin/shipping/pincode-upload') ?>" enctype="multipart/form-data" class="admin-form">
            <?= csrfField() ?>
            <label>CSV File
                <input type="file" name="csv_file" accept=".csv" required>
            </label>
            <button type="submit" class="admin-btn admin-btn-primary">Upload &amp; Import</button>
        </form>
    </div>
</div>

<div class="admin-card admin-mt">
    <h2 class="admin-card-title">Pincode Serviceability (showing up to 500)</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Pincode</th><th>Serviceable</th><th>Estimated Delivery</th></tr></thead>
            <tbody>
            <?php foreach ($pincodes as $p): ?>
                <tr>
                    <td><?= e($p['pincode']) ?></td>
                    <td>
                        <?php if ($p['is_serviceable']): ?>
                            <span class="admin-badge admin-badge-green">Yes</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-red">No</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$p['estimated_days'] ?> days</td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pincodes)): ?>
                <tr><td colspan="3" class="admin-muted">No pincode data uploaded yet. Unlisted pincodes default to serviceable (5 days).</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
