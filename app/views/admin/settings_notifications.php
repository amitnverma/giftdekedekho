<form method="post" class="admin-form">
    <?= csrfField() ?>

    <div class="admin-card">
        <h2 class="admin-card-title">SMTP / Email (PHPMailer)</h2>
        <div class="admin-form-row">
            <label>SMTP Host<input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>"></label>
            <label>SMTP Port<input type="number" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>"></label>
        </div>
        <div class="admin-form-row">
            <label>SMTP Username<input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>"></label>
            <label>SMTP Password<input type="password" name="smtp_pass" value="<?= e($settings['smtp_pass'] ?? '') ?>"></label>
        </div>
        <div class="admin-form-row">
            <label>From Name<input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? '') ?>"></label>
            <label>From Email<input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email'] ?? '') ?>"></label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">SMS (MSG91)</h2>
        <div class="admin-form-row">
            <label>API Key<input type="text" name="msg91_api_key" value="<?= e($settings['msg91_api_key'] ?? '') ?>"></label>
            <label>Sender ID<input type="text" name="msg91_sender_id" value="<?= e($settings['msg91_sender_id'] ?? '') ?>"></label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Notification Templates</h2>
        <p class="admin-help">Available placeholders: <code>{{name}}</code>, <code>{{order_id}}</code>, <code>{{total}}</code>, <code>{{tracking_number}}</code>, <code>{{tracking_url}}</code></p>
        <label>Order Confirmed
            <textarea name="order_confirmed_template" rows="3"><?= e($settings['order_confirmed_template'] ?? '') ?></textarea>
        </label>
        <label>Order Shipped
            <textarea name="order_shipped_template" rows="3"><?= e($settings['order_shipped_template'] ?? '') ?></textarea>
        </label>
        <label>Order Delivered
            <textarea name="order_delivered_template" rows="3"><?= e($settings['order_delivered_template'] ?? '') ?></textarea>
        </label>
    </div>

    <button type="submit" class="admin-btn admin-btn-primary admin-mt">Save Notification Settings</button>
</form>
