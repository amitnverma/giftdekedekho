<?php $isEdit = $product !== null; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.snow.css">

<form method="post" enctype="multipart/form-data" class="admin-form">
    <?= csrfField() ?>

    <div class="admin-grid admin-grid-2" style="align-items:start;">
        <div class="admin-card">
            <h2 class="admin-card-title">Basic Information</h2>

            <label>Product Name
                <input type="text" name="name" required value="<?= e($product['name'] ?? old('name')) ?>">
            </label>

            <label>Category
                <select name="category_id" required>
                    <option value="">— Select Category —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= isset($product['category_id']) && (int)$product['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Short Description
                <textarea name="short_description" rows="2"><?= e($product['short_description'] ?? '') ?></textarea>
            </label>

            <label>Full Description
                <textarea name="description_hidden" id="descriptionHidden" style="display:none;"><?= e($product['description'] ?? '') ?></textarea>
                <div id="descriptionEditor" data-quill="#descriptionHidden" style="height:220px;background:#fff;"><?= $product['description'] ?? '' ?></div>
            </label>
        </div>

        <div class="admin-card">
            <h2 class="admin-card-title">Pricing &amp; Stock</h2>
            <div class="admin-form-row">
                <label>Base Price (₹)
                    <input type="number" step="0.01" min="0" name="base_price" required value="<?= e($product['base_price'] ?? '0') ?>">
                </label>
                <label>Sale Price (₹) <span class="admin-label-hint">Leave blank for no discount</span>
                    <input type="number" step="0.01" min="0" name="sale_price" value="<?= e($product['sale_price'] ?? '') ?>">
                </label>
            </div>
            <div class="admin-form-row">
                <label>Stock Quantity
                    <input type="number" min="0" name="stock_qty" value="<?= e($product['stock_qty'] ?? '0') ?>">
                </label>
                <label>SKU
                    <input type="text" name="sku" value="<?= e($product['sku'] ?? '') ?>">
                </label>
            </div>
            <label>Weight (grams)
                <input type="number" min="0" name="weight_grams" value="<?= e($product['weight_grams'] ?? '') ?>">
            </label>
            <label class="admin-checkbox">
                <input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?>>
                Featured Product (shown on homepage)
            </label>
            <label class="admin-checkbox">
                <input type="checkbox" name="is_active" value="1" <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?>>
                Active (visible on storefront)
            </label>

            <h2 class="admin-card-title admin-mt">SEO</h2>
            <label>Meta Title
                <input type="text" name="meta_title" value="<?= e($product['meta_title'] ?? '') ?>">
            </label>
            <label>Meta Description
                <textarea name="meta_description" rows="2"><?= e($product['meta_description'] ?? '') ?></textarea>
            </label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Product Images</h2>
        <?php if (!empty($images)): ?>
            <div class="admin-image-grid">
                <?php foreach ($images as $img): ?>
                    <label class="admin-image-item" style="cursor:pointer;">
                        <img src="<?= asset('uploads/' . $img['image_path']) ?>" alt="">
                        <input type="radio" name="primary_image_id" value="<?= (int)$img['id'] ?>" <?= $img['is_primary'] ? 'checked' : '' ?> style="position:absolute;top:6px;left:6px;">
                        <?php if ($img['is_primary']): ?><span class="admin-image-primary-tag">Primary</span><?php endif; ?>
                        <button type="button" class="admin-image-remove" data-confirm="Remove this image?" onclick="event.preventDefault(); gddRemoveImage(<?= (int)$img['id'] ?>, this)">&times;</button>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="admin-help">Select the radio on an image to make it the primary listing image.</p>
        <?php endif; ?>
        <label>Upload New Images
            <input type="file" name="images[]" accept="image/*" multiple>
            <span class="admin-label-hint">JPEG, PNG, WebP or GIF — up to 5MB each. The first uploaded image becomes primary if none exists.</span>
        </label>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Customization Options</h2>
        <p class="admin-help">Define the personalization options customers can choose for this product (text engraving, photo upload, gift wrap, message card, video/photo QR).</p>

        <div id="optionsRepeater">
            <?php
            $rows = !empty($options) ? $options : [];
            foreach ($rows as $i => $opt):
            ?>
                <div class="admin-option-row">
                    <div class="admin-form-row">
                        <label>Option Type
                            <select name="option_type[]">
                                <?php foreach (['text_engraving' => 'Text Engraving', 'photo_upload' => 'Photo Upload', 'gift_wrap' => 'Gift Wrap', 'message_card' => 'Message Card', 'video_photo' => 'Video / Photo QR'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $opt['option_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Label
                            <input type="text" name="option_label[]" value="<?= e($opt['label']) ?>" placeholder="e.g. Engrave a name">
                        </label>
                        <label>Extra Charge (₹)
                            <input type="number" step="0.01" min="0" name="option_charge[]" value="<?= e($opt['extra_charge']) ?>">
                        </label>
                        <label>Character Limit <span class="admin-label-hint">(text options only)</span>
                            <input type="number" min="0" name="option_char_limit[]" value="<?= e($opt['char_limit'] ?? '') ?>">
                        </label>
                    </div>
                    <label class="admin-checkbox">
                        <input type="checkbox" name="option_required[]" value="1" <?= !empty($opt['is_required']) ? 'checked' : '' ?>>
                        Required
                    </label>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove Option</button>
                </div>
            <?php endforeach; ?>

            <div class="admin-option-row" data-repeater-template style="display:none;">
                <div class="admin-form-row">
                    <label>Option Type
                        <select name="option_type[__INDEX__]">
                            <option value="text_engraving">Text Engraving</option>
                            <option value="photo_upload">Photo Upload</option>
                            <option value="gift_wrap">Gift Wrap</option>
                            <option value="message_card">Message Card</option>
                            <option value="video_photo">Video / Photo QR</option>
                        </select>
                    </label>
                    <label>Label
                        <input type="text" name="option_label[__INDEX__]" placeholder="e.g. Engrave a name">
                    </label>
                    <label>Extra Charge (₹)
                        <input type="number" step="0.01" min="0" name="option_charge[__INDEX__]" value="0">
                    </label>
                    <label>Character Limit <span class="admin-label-hint">(text options only)</span>
                        <input type="number" min="0" name="option_char_limit[__INDEX__]">
                    </label>
                </div>
                <label class="admin-checkbox">
                    <input type="checkbox" name="option_required[__INDEX__]" value="1">
                    Required
                </label>
                <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove Option</button>
            </div>
        </div>
        <button type="button" class="admin-btn admin-mt" data-repeater-add="#optionsRepeater">+ Add Customization Option</button>
    </div>

    <div class="admin-form-actions admin-mt">
        <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? 'Update Product' : 'Create Product' ?></button>
        <a href="<?= url('/admin/products') ?>" class="admin-btn">Cancel</a>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.min.js"></script>
<script>
(function () {
    var el = document.getElementById('descriptionEditor');
    if (window.Quill && el) {
        var hidden = document.getElementById('descriptionHidden');
        var quill = new Quill(el, { theme: 'snow' });
        if (hidden.value) quill.root.innerHTML = hidden.value;
        quill.on('text-change', function () { hidden.value = quill.root.innerHTML; });
        var form = el.closest('form');
        form.addEventListener('submit', function () {
            hidden.value = quill.root.innerHTML;
            hidden.name = 'description';
        });
    }
})();
window.gddRemoveImage = function (imageId, btn) {
    if (!confirm('Remove this image?')) return;
    fetch((window.GDD_BASE_URL || '') + '/admin/products/' + imageId + '/image-delete', {
        method: 'POST',
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').getAttribute('content'))
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok) { btn.closest('.admin-image-item').remove(); }
    });
};
</script>
