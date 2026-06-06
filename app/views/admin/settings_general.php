<div class="admin-card" style="max-width:760px;">
    <form method="post" class="admin-form">
        <?= csrfField() ?>

        <h2 class="admin-card-title">Site Information</h2>
        <div class="admin-form-row">
            <label>Site Name<input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>"></label>
            <label>Tagline<input type="text" name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>"></label>
        </div>
        <div class="admin-form-row">
            <label>Contact Email<input type="email" name="site_email" value="<?= e($settings['site_email'] ?? '') ?>"></label>
            <label>Contact Phone<input type="text" name="site_phone" value="<?= e($settings['site_phone'] ?? '') ?>"></label>
        </div>
        <label>Address<input type="text" name="site_address" value="<?= e($settings['site_address'] ?? '') ?>"></label>

        <h2 class="admin-card-title admin-mt">Currency &amp; Inventory</h2>
        <div class="admin-form-row">
            <label>Currency Symbol<input type="text" name="currency_symbol" value="<?= e($settings['currency_symbol'] ?? '₹') ?>"></label>
            <label>Currency Code<input type="text" name="currency_code" value="<?= e($settings['currency_code'] ?? 'INR') ?>"></label>
            <label>Low Stock Threshold<input type="number" min="0" name="low_stock_threshold" value="<?= e($settings['low_stock_threshold'] ?? '5') ?>"></label>
        </div>

        <h2 class="admin-card-title admin-mt">SEO</h2>
        <div class="admin-form-row">
            <label>Meta Title Suffix<input type="text" name="meta_title_suffix" value="<?= e($settings['meta_title_suffix'] ?? '') ?>"></label>
            <label>Google Analytics ID<input type="text" name="google_analytics_id" value="<?= e($settings['google_analytics_id'] ?? '') ?>"></label>
        </div>

        <h2 class="admin-card-title admin-mt">Security</h2>
        <label>Admin IP Whitelist <span class="admin-label-hint">Comma-separated IP addresses. Leave blank to allow all.</span>
            <input type="text" name="admin_ip_whitelist" value="<?= e($settings['admin_ip_whitelist'] ?? '') ?>">
        </label>
        <div class="admin-form-row">
            <label>Max Login Attempts<input type="number" min="1" name="max_login_attempts" value="<?= e($settings['max_login_attempts'] ?? '5') ?>"></label>
            <label>Lockout Duration (minutes)<input type="number" min="1" name="login_lockout_minutes" value="<?= e($settings['login_lockout_minutes'] ?? '15') ?>"></label>
        </div>

        <h2 class="admin-card-title admin-mt">Shiprocket Integration</h2>
        <div class="admin-form-row">
            <label>Shiprocket Email<input type="email" name="shiprocket_email" value="<?= e($settings['shiprocket_email'] ?? '') ?>"></label>
            <label>Shiprocket Password<input type="password" name="shiprocket_password" value="<?= e($settings['shiprocket_password'] ?? '') ?>"></label>
        </div>

        <button type="submit" class="admin-btn admin-btn-primary admin-mt">Save Settings</button>
    </form>
</div>
