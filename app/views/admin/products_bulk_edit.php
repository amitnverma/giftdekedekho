<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.snow.css">

<form method="post" action="<?= url('/admin/products/bulk-edit-save') ?>" enctype="multipart/form-data" class="admin-form" id="bulkEditForm">
    <?= csrfField() ?>
    <?php foreach ($ids as $pid): ?>
        <input type="hidden" name="ids[]" value="<?= (int)$pid ?>">
    <?php endforeach; ?>

    <div class="admin-card">
        <h2 class="admin-card-title">Editing <?= count($products) ?> product(s)</h2>
        <p class="admin-help">Only the sections you tick below will be changed. Unticked sections are left exactly as they are on every product. This applies to all the products listed here:</p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($products as $p): ?>
                <span class="admin-badge admin-badge-gray"><?= e($p['name']) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-card admin-mt" data-bulk-section>
        <label class="admin-checkbox" style="font-weight:600;">
            <input type="checkbox" name="update_short_description" value="1" data-bulk-toggle>
            Update Short Description
        </label>
        <div data-bulk-body style="margin-top:10px;">
            <textarea name="short_description" rows="2" placeholder="New short description for all selected products"></textarea>
        </div>
    </div>

    <div class="admin-card admin-mt" data-bulk-section>
        <label class="admin-checkbox" style="font-weight:600;">
            <input type="checkbox" name="update_description" value="1" data-bulk-toggle>
            Update Full Description
        </label>
        <div data-bulk-body style="margin-top:10px;">
            <textarea name="description" id="bulkDescriptionHidden" style="display:none;"></textarea>
            <div id="bulkDescriptionEditor" data-quill="#bulkDescriptionHidden" style="height:220px;background:#fff;"></div>
        </div>
    </div>

    <div class="admin-card admin-mt" data-bulk-section>
        <label class="admin-checkbox" style="font-weight:600;">
            <input type="checkbox" name="update_options" value="1" data-bulk-toggle>
            Replace Customization Options
        </label>
        <div data-bulk-body style="margin-top:10px;">
            <p class="admin-help" style="color:#c00;">Warning: this <strong>replaces all</strong> existing customization options on every selected product with the ones defined below. Leave this unticked to keep each product's current options.</p>

            <div id="optionsRepeater">
                <div class="admin-option-row" data-repeater-template style="display:none;">
                    <div class="admin-form-row">
                        <label>Option Type
                            <input type="text" name="option_type[__INDEX__]" list="optionTypeList" placeholder="e.g. text_engraving">
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
                    <div class="charmset-field">
                        <label>Charm Set <span class="admin-label-hint">Only for the <strong>Image Choice (Charm Picker)</strong> type — customers pick one image from this set. <a href="<?= url('/admin/charm-sets') ?>" target="_blank">Manage charm library →</a></span>
                            <select name="option_image_set[__INDEX__]">
                                <option value="">— none —</option>
                                <?php foreach (($charmSets ?? []) as $cs): ?>
                                    <option value="<?= (int)$cs['id'] ?>"><?= e($cs['name']) ?> (<?= (int)$cs['charm_count'] ?> charms)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="giftwrap-field">
                        <label>Gift Wrap Image <span class="admin-label-hint">Only for the <strong>Gift Wrap</strong> type — shown to customers as the wrapping preview. Leave blank to use the default gift box. JPG, PNG, WebP or GIF, max 5MB.</span></label>
                        <input type="hidden" name="option_gift_image_existing[__INDEX__]" value="">
                        <input type="file" name="option_gift_image[__INDEX__]" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                    <div class="suboption-editor" data-suboption-editor>
                        <label>Choices <span class="admin-label-hint">Optional — add selectable choices (e.g. Gold / Silver). Each can carry its own extra charge and optionally ask the customer for an image upload.</span></label>
                        <input type="hidden" name="option_sub_options[__INDEX__]" class="suboption-json" value="">
                        <div class="suboption-rows"></div>
                        <button type="button" class="admin-btn admin-btn-sm" data-add-suboption>+ Add Choice</button>
                    </div>
                    <label class="admin-checkbox">
                        <input type="checkbox" name="option_required[__INDEX__]" value="1">
                        Required
                    </label>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove Option</button>
                </div>
                <datalist id="optionTypeList">
                    <option value="text_engraving">Text Engraving</option>
                    <option value="image_choice">Image Choice (Charm Picker)</option>
                    <option value="photo_upload">Photo Upload</option>
                    <option value="gift_wrap">Gift Wrap</option>
                    <option value="message_card">Message Card</option>
                    <option value="video_photo">Video / Photo QR</option>
                    <option value="color_choice">Color Choice</option>
                    <option value="size_choice">Size Choice</option>
                    <option value="font_style">Font Style</option>
                    <option value="material">Material</option>
                </datalist>
            </div>
            <button type="button" class="admin-btn admin-mt" data-repeater-add="#optionsRepeater">+ Add Customization Option</button>
        </div>
    </div>

    <div class="admin-form-actions admin-mt">
        <button type="submit" class="admin-btn admin-btn-primary" data-confirm="Apply these changes to all selected products?">Apply to Selected Products</button>
        <a href="<?= url('/admin/products') ?>" class="admin-btn">Cancel</a>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.min.js"></script>
<script>
// ---- Dim each section until its "update this" toggle is ticked ----
(function () {
    document.querySelectorAll('[data-bulk-section]').forEach(function (section) {
        var toggle = section.querySelector('[data-bulk-toggle]');
        var body = section.querySelector('[data-bulk-body]');
        if (!toggle || !body) return;
        function sync() {
            body.style.opacity = toggle.checked ? '1' : '0.45';
            body.style.pointerEvents = toggle.checked ? '' : 'none';
        }
        toggle.addEventListener('change', sync);
        sync();
    });
})();

// ---- Customization sub-option (choice) editor ----
(function () {
    function buildRow(choice) {
        choice = choice || {};
        var row = document.createElement('div');
        row.className = 'suboption-row';
        row.style.cssText = 'display:flex;gap:8px;align-items:center;margin:6px 0;flex-wrap:wrap';
        row.innerHTML =
            '<input type="text" class="so-label" placeholder="Choice label e.g. Gold" style="flex:2;min-width:140px">' +
            '<input type="number" step="0.01" min="0" class="so-price" placeholder="Extra ₹" style="flex:1;min-width:90px">' +
            '<label style="display:flex;align-items:center;gap:6px;font-weight:400;white-space:nowrap;margin:0">' +
              '<input type="checkbox" class="so-image"> Ask for image upload' +
            '</label>' +
            '<button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-remove-suboption>&times;</button>';
        row.querySelector('.so-label').value = choice.label || '';
        row.querySelector('.so-price').value = (choice.price !== undefined && choice.price !== null) ? choice.price : '';
        row.querySelector('.so-image').checked = !!choice.image;
        return row;
    }

    function serialize(editor) {
        var choices = [];
        editor.querySelectorAll('.suboption-row').forEach(function (row) {
            var label = row.querySelector('.so-label').value.trim();
            if (!label) return;
            choices.push({
                label: label,
                price: parseFloat(row.querySelector('.so-price').value) || 0,
                image: row.querySelector('.so-image').checked
            });
        });
        editor.querySelector('.suboption-json').value = choices.length ? JSON.stringify(choices) : '';
    }

    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('[data-add-suboption]');
        if (addBtn) {
            e.preventDefault();
            var editor = addBtn.closest('[data-suboption-editor]');
            editor.querySelector('.suboption-rows').appendChild(buildRow());
            serialize(editor);
            return;
        }
        var remBtn = e.target.closest('[data-remove-suboption]');
        if (remBtn) {
            e.preventDefault();
            var editor = remBtn.closest('[data-suboption-editor]');
            remBtn.closest('.suboption-row').remove();
            serialize(editor);
        }
    });
    document.addEventListener('input', function (e) {
        if (e.target.closest('.suboption-row')) serialize(e.target.closest('[data-suboption-editor]'));
    });
    document.addEventListener('change', function (e) {
        if (e.target.closest('.suboption-row')) serialize(e.target.closest('[data-suboption-editor]'));
    });
})();

// ---- Show the Charm Set selector only for "image_choice"; gift image only for "gift_wrap" ----
(function () {
    function syncRow(row) {
        var typeInput = row.querySelector('[name^="option_type"]');
        if (!typeInput) return;
        var typeVal = typeInput.value.trim();
        var isImageChoice = typeVal === 'image_choice';
        var isGiftWrap = typeVal === 'gift_wrap';
        var charmField = row.querySelector('.charmset-field');
        var giftField = row.querySelector('.giftwrap-field');
        var choices = row.querySelector('[data-suboption-editor]');
        if (charmField) charmField.style.display = isImageChoice ? '' : 'none';
        if (giftField) giftField.style.display = isGiftWrap ? '' : 'none';
        if (choices) choices.style.display = (isImageChoice || isGiftWrap) ? 'none' : '';
        var chargeInput = row.querySelector('[name^="option_charge"]');
        if (chargeInput) {
            var chargeLabel = chargeInput.closest('label');
            if (chargeLabel) chargeLabel.style.display = isImageChoice ? 'none' : '';
            if (isImageChoice) chargeInput.value = '0';
        }
    }
    function syncAll() {
        document.querySelectorAll('.admin-option-row').forEach(function (row) {
            if (row.hasAttribute('data-repeater-template')) return;
            syncRow(row);
        });
    }
    document.addEventListener('input', function (e) {
        if (e.target.matches('[name^="option_type"]')) {
            var row = e.target.closest('.admin-option-row');
            if (row) syncRow(row);
        }
    });
    document.addEventListener('change', function (e) {
        if (e.target.matches('[name^="option_type"]')) {
            var row = e.target.closest('.admin-option-row');
            if (row) syncRow(row);
        }
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-repeater-add]')) setTimeout(syncAll, 0);
    });
    syncAll();
})();
</script>
