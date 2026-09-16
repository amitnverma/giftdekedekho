<?php
/**
 * Create / edit a partner: the branding their page and their customers' scan
 * pages show, what they may sell, and what it costs them.
 */
$isEdit = (int)$partner['id'] > 0;
$durationPrices = ArPartner::durationPrices($partner);
$validityPrices = ArPartner::validityPrices($partner);
$packs = ArPartner::creditPacks($partner);
while (count($packs) < 4) {
    $packs[] = ['price' => '', 'credits' => ''];
}
$action = $isEdit ? '/admin/ar-partners/' . (int)$partner['id'] . '/edit' : '/admin/ar-partners/create';
?>
<form method="post" action="<?= url($action) ?>" enctype="multipart/form-data" class="admin-form" style="max-width:900px">
    <?= csrfField() ?>

    <div class="admin-card">
        <h3 class="admin-card-title">Branding</h3>
        <p class="admin-muted" style="font-size:13px;margin-top:0">
            Shown on the partner's portal, and to their customers on the scan page, the “expired” page and in the browser tab —
            with a small “Powered by <?= e(siteSetting('site_name', SITE_NAME)) ?>” line.
        </p>
        <div class="admin-form-row">
            <label>Partner name *
                <input type="text" name="name" required maxlength="120" value="<?= e($partner['name']) ?>">
            </label>
            <label>Page address <span class="admin-label-hint">/partner/…</span>
                <input type="text" name="slug" maxlength="60" pattern="[a-z0-9][a-z0-9\-]{1,59}" value="<?= e($partner['slug']) ?>" placeholder="narain-jewellers">
            </label>
        </div>
        <?php if ($isEdit): ?>
            <p class="admin-help-text" style="margin-top:-6px">Changing the address breaks the partner's bookmarks. Scan links on printed stickers are not affected.</p>
        <?php endif; ?>
        <label>Tagline <span class="admin-label-hint">Under the name on the sign-in page</span>
            <input type="text" name="tagline" maxlength="200" value="<?= e($partner['tagline']) ?>">
        </label>
        <div class="admin-form-row">
            <label>Logo <span class="admin-label-hint">PNG, JPG or WebP, up to 2MB. A wide logo on a transparent background works best.</span>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
            </label>
            <label>Brand colour <span class="admin-label-hint">Buttons, tabs and accents</span>
                <input type="color" name="brand_color" value="<?= e(ArPartnerService::safeColor($partner['brand_color'])) ?>" style="height:42px;padding:3px;cursor:pointer">
            </label>
        </div>
        <?php if (!empty($partner['logo_path'])): ?>
            <div style="display:flex;gap:14px;align-items:center;margin-bottom:12px">
                <img src="<?= e(ArFrameService::fileUrl($partner['logo_path'])) ?>" alt="" style="max-height:48px;max-width:180px;background:#f4f4f6;padding:6px;border-radius:6px">
                <label class="admin-checkbox"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card admin-mt">
        <h3 class="admin-card-title">Contact</h3>
        <div class="admin-form-row">
            <label>Contact person
                <input type="text" name="contact_name" maxlength="120" value="<?= e($partner['contact_name']) ?>">
            </label>
            <label>Phone
                <input type="text" name="contact_phone" maxlength="20" value="<?= e($partner['contact_phone']) ?>">
            </label>
        </div>
        <div class="admin-form-row">
            <label>WhatsApp
                <input type="text" name="whatsapp" maxlength="20" value="<?= e($partner['whatsapp']) ?>">
            </label>
            <label>Email
                <input type="email" name="contact_email" maxlength="180" value="<?= e($partner['contact_email']) ?>">
            </label>
        </div>
        <label>Website <span class="admin-label-hint">Linked from their customers' “expired / unavailable” page</span>
            <input type="text" name="website_url" maxlength="300" value="<?= e($partner['website_url']) ?>" placeholder="https://">
        </label>
    </div>

    <div class="admin-card admin-mt">
        <h3 class="admin-card-title">What they can create</h3>
        <div class="admin-form-row">
            <label class="admin-checkbox"><input type="checkbox" name="allow_singles" value="1" <?= $partner['allow_singles'] ? 'checked' : '' ?>> Singles (one photo + video)</label>
            <label class="admin-checkbox"><input type="checkbox" name="allow_albums" value="1" <?= $partner['allow_albums'] ? 'checked' : '' ?>> Albums (several photos behind one QR)</label>
        </div>
        <div class="admin-form-row">
            <label>Max pages per album <span class="admin-label-hint">1–<?= ArFrameItem::MAX_PER_FRAME ?></span>
                <input type="number" name="max_album_pages" min="1" max="<?= ArFrameItem::MAX_PER_FRAME ?>" value="<?= (int)$partner['max_album_pages'] ?>">
            </label>
            <label>Max video size (MB) <span class="admin-label-hint">This server accepts up to <?= e((string)ini_get('upload_max_filesize')) ?> per file</span>
                <input type="number" name="max_video_mb" min="1" max="500" value="<?= (int)$partner['max_video_mb'] ?>">
            </label>
            <label>Edit window (days) <span class="admin-label-hint">How long they can swap photos/videos after creating</span>
                <input type="number" name="edit_window_days" min="0" max="365" value="<?= (int)$partner['edit_window_days'] ?>">
            </label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h3 class="admin-card-title">Pricing (credits)</h3>
        <p class="admin-muted" style="font-size:13px;margin-top:0">
            Each item — a single, or one page of an album — costs the base rate + its video-length surcharge + the validity surcharge.
            Untick an option to hide it from this partner.
        </p>
        <label style="max-width:240px">Base rate per item *
            <input type="number" name="base_credits" min="1" required value="<?= (int)$partner['base_credits'] ?>">
        </label>

        <div class="admin-form-row" style="align-items:flex-start">
            <div style="flex:1">
                <p class="admin-section-title" style="margin:6px 0">Video duration surcharge</p>
                <table class="admin-table">
                    <thead><tr><th>Offer</th><th>Length</th><th>+ Credits</th></tr></thead>
                    <tbody>
                        <?php foreach (ArPartner::DURATIONS as $seconds):
                            $offered = array_key_exists($seconds, $durationPrices);
                            $price = $offered ? $durationPrices[$seconds] : (ArPartner::DEFAULT_DURATION_PRICES[(string)$seconds] ?? 0); ?>
                            <tr>
                                <td><input type="checkbox" name="duration_offered[<?= $seconds ?>]" value="1" <?= $offered ? 'checked' : '' ?> aria-label="Offer <?= $seconds ?>s"></td>
                                <td><?= $seconds ?>s</td>
                                <td><input type="number" name="duration_prices[<?= $seconds ?>]" min="0" value="<?= (int)$price ?>" style="width:100px"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="flex:1">
                <p class="admin-section-title" style="margin:6px 0">Validity surcharge</p>
                <table class="admin-table">
                    <thead><tr><th>Offer</th><th>Active for</th><th>+ Credits</th></tr></thead>
                    <tbody>
                        <?php foreach (ArPartner::VALIDITIES as $key => [$label]):
                            $offered = array_key_exists($key, $validityPrices);
                            $price = $offered ? $validityPrices[$key] : (ArPartner::DEFAULT_VALIDITY_PRICES[$key] ?? 0); ?>
                            <tr>
                                <td><input type="checkbox" name="validity_offered[<?= e($key) ?>]" value="1" <?= $offered ? 'checked' : '' ?> aria-label="Offer <?= e($label) ?>"></td>
                                <td><?= e($label) ?></td>
                                <td><input type="number" name="validity_prices[<?= e($key) ?>]" min="0" value="<?= (int)$price ?>" style="width:100px"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="admin-section-title" style="margin:16px 0 6px">Credit packs <span class="admin-label-hint">Shown on their My Credits page. Leave a row blank to drop it.</span></p>
        <table class="admin-table" style="max-width:420px">
            <thead><tr><th>Price (<?= e(GDD_CURRENCY_SYMBOL) ?>)</th><th>Credits given</th></tr></thead>
            <tbody>
                <?php foreach ($packs as $i => $pack): ?>
                    <tr>
                        <td><input type="number" name="packs[<?= $i ?>][price]" min="0" value="<?= e((string)$pack['price']) ?>" style="width:120px"></td>
                        <td><input type="number" name="packs[<?= $i ?>][credits]" min="0" value="<?= e((string)$pack['credits']) ?>" style="width:120px"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$isEdit): ?>
        <div class="admin-card admin-mt">
            <h3 class="admin-card-title">First login &amp; opening credits</h3>
            <p class="admin-muted" style="font-size:13px;margin-top:0">Optional — more logins can be added from the partner's page.</p>
            <div class="admin-form-row">
                <label>Name
                    <input type="text" name="owner_name" maxlength="120">
                </label>
                <label>Login email
                    <input type="email" name="owner_email" maxlength="180" autocomplete="off">
                </label>
                <label>Password <span class="admin-label-hint">Min <?= PASSWORD_MIN_LENGTH ?> characters</span>
                    <input type="text" name="owner_password" autocomplete="new-password">
                </label>
            </div>
            <label style="max-width:240px">Opening credits <span class="admin-label-hint">Recorded as “Joining bonus”</span>
                <input type="number" name="opening_credits" min="0" value="0">
            </label>
        </div>
    <?php endif; ?>

    <div class="admin-card admin-mt">
        <label>Internal notes <span class="admin-label-hint">Never shown to the partner</span>
            <textarea name="notes" rows="3"><?= e($partner['notes']) ?></textarea>
        </label>
        <label class="admin-checkbox">
            <input type="checkbox" name="is_active" value="1" <?= $partner['is_active'] ? 'checked' : '' ?>>
            Active — untick to stop the partner signing in. Their customers' AR keeps working.
        </label>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? 'Save Partner' : 'Create Partner' ?></button>
            <a href="<?= url($isEdit ? '/admin/ar-partners/' . (int)$partner['id'] : '/admin/ar-partners') ?>" class="admin-btn">Cancel</a>
        </div>
    </div>
</form>
