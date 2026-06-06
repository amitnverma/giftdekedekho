<div class="admin-card admin-mt" style="margin-top:0;">
    <p class="admin-help">⚠️ API keys entered here are stored in the database and used by the storefront checkout. Use sandbox/test mode while developing, and switch to live mode only when ready to accept real payments.</p>
</div>

<form method="post" class="admin-form">
    <?= csrfField() ?>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Razorpay</h2>
        <div class="admin-form-row">
            <label>Key ID<input type="text" name="razorpay_key_id" value="<?= e($settings['razorpay_key_id'] ?? '') ?>"></label>
            <label>Key Secret<input type="password" name="razorpay_key_secret" value="<?= e($settings['razorpay_key_secret'] ?? '') ?>"></label>
            <label>Mode
                <select name="razorpay_mode">
                    <option value="test" <?= ($settings['razorpay_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Sandbox / Test</option>
                    <option value="live" <?= ($settings['razorpay_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                </select>
            </label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">PayPal</h2>
        <div class="admin-form-row">
            <label>Client ID<input type="text" name="paypal_client_id" value="<?= e($settings['paypal_client_id'] ?? '') ?>"></label>
            <label>Client Secret<input type="password" name="paypal_client_secret" value="<?= e($settings['paypal_client_secret'] ?? '') ?>"></label>
            <label>Mode
                <select name="paypal_mode">
                    <option value="sandbox" <?= ($settings['paypal_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                    <option value="live" <?= ($settings['paypal_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                </select>
            </label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Stripe</h2>
        <div class="admin-form-row">
            <label>Publishable Key<input type="text" name="stripe_publishable_key" value="<?= e($settings['stripe_publishable_key'] ?? '') ?>"></label>
            <label>Secret Key<input type="password" name="stripe_secret_key" value="<?= e($settings['stripe_secret_key'] ?? '') ?>"></label>
            <label>Mode
                <select name="stripe_mode">
                    <option value="test" <?= ($settings['stripe_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="live" <?= ($settings['stripe_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                </select>
            </label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Cash on Delivery</h2>
        <p class="admin-help">COD is always available as a fallback payment option and requires no API keys.</p>
    </div>

    <button type="submit" class="admin-btn admin-btn-primary admin-mt">Save Payment Settings</button>
</form>
