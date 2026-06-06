<?php $isEdit = $coupon !== null; ?>
<div class="admin-card" style="max-width:620px;">
    <form method="post" class="admin-form">
        <?= csrfField() ?>

        <label>Coupon Code
            <input type="text" name="code" required style="text-transform:uppercase;" value="<?= e($coupon['code'] ?? old('code')) ?>">
        </label>

        <div class="admin-form-row">
            <label>Discount Type
                <select name="discount_type">
                    <option value="flat" <?= ($coupon['discount_type'] ?? 'flat') === 'flat' ? 'selected' : '' ?>>Flat Amount (₹)</option>
                    <option value="percent" <?= ($coupon['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                </select>
            </label>
            <label>Discount Value
                <input type="number" step="0.01" min="0" name="discount_value" required value="<?= e($coupon['discount_value'] ?? '') ?>">
            </label>
        </div>

        <div class="admin-form-row">
            <label>Minimum Order Value (₹)
                <input type="number" step="0.01" min="0" name="min_order_value" value="<?= e($coupon['min_order_value'] ?? '0') ?>">
            </label>
            <label>Max Uses <span class="admin-label-hint">Leave blank for unlimited</span>
                <input type="number" min="1" name="max_uses" value="<?= e($coupon['max_uses'] ?? '') ?>">
            </label>
        </div>

        <div class="admin-form-row">
            <label>Valid From
                <input type="date" name="valid_from" required value="<?= e($coupon['valid_from'] ?? date('Y-m-d')) ?>">
            </label>
            <label>Valid To
                <input type="date" name="valid_to" required value="<?= e($coupon['valid_to'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
            </label>
        </div>

        <label class="admin-checkbox">
            <input type="checkbox" name="is_active" value="1" <?= ($coupon['is_active'] ?? 1) ? 'checked' : '' ?>>
            Active
        </label>

        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? 'Update Coupon' : 'Create Coupon' ?></button>
            <a href="<?= url('/admin/coupons') ?>" class="admin-btn">Cancel</a>
        </div>
    </form>
</div>
