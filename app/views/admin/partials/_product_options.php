<?php
/**
 * Shared Customization Options editor — included by both the single-product form
 * (products_form.php) and the bulk-create form (products_bulk_create.php).
 * Expects in scope: $options (array of existing option rows, [] for new) and
 * $charmSets (active charm sets with counts). The enclosing <form> must carry
 * the class "admin-form" for the submit-time serialisation hook below.
 */
?>
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
                        <input type="text" name="option_type[]" value="<?= e($opt['option_type']) ?>" list="optionTypeList" placeholder="e.g. text_engraving">
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
                <div class="charmset-field">
                    <label>Charm Set <span class="admin-label-hint">Only for the <strong>Image Choice (Charm Picker)</strong> type — customers pick one image from this set. <a href="<?= url('/admin/charm-sets') ?>" target="_blank">Manage charm library →</a></span>
                        <select name="option_image_set[]">
                            <option value="">— none —</option>
                            <?php foreach (($charmSets ?? []) as $cs): ?>
                                <option value="<?= (int)$cs['id'] ?>" <?= (int)($opt['image_set_id'] ?? 0) === (int)$cs['id'] ? 'selected' : '' ?>><?= e($cs['name']) ?> (<?= (int)$cs['charm_count'] ?> charms)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="giftwrap-field">
                    <label>Gift Wrap Image <span class="admin-label-hint">Only for the <strong>Gift Wrap</strong> type — shown to customers as the wrapping preview. Leave blank to use the default gift box. JPG, PNG, WebP or GIF, max 5MB.</span></label>
                    <input type="hidden" name="option_gift_image_existing[]" value="<?= e($opt['image_path'] ?? '') ?>">
                    <input type="file" name="option_gift_image[]" accept="image/jpeg,image/png,image/webp,image/gif">
                    <?php if (($opt['option_type'] ?? '') === 'gift_wrap' && !empty($opt['image_path'])): ?>
                        <img src="<?= e(productImage($opt['image_path'])) ?>" alt="Current gift wrap image" style="max-width:100px;margin-top:8px;border-radius:6px;display:block">
                    <?php endif; ?>
                </div>
                <div class="suboption-editor" data-suboption-editor>
                    <label>Choices <span class="admin-label-hint">Optional — add selectable choices (e.g. Gold / Silver). Each can carry its own extra charge and optionally ask the customer for an image upload.</span></label>
                    <input type="hidden" name="option_sub_options[]" class="suboption-json" value="<?= e(is_string($opt['sub_options'] ?? null) ? $opt['sub_options'] : '') ?>">
                    <div class="suboption-rows"></div>
                    <button type="button" class="admin-btn admin-btn-sm" data-add-suboption>+ Add Choice</button>
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
            <option value="ar_frame">Living Photo AR Frame</option>
            <option value="color_choice">Color Choice</option>
            <option value="size_choice">Size Choice</option>
            <option value="font_style">Font Style</option>
            <option value="material">Material</option>
        </datalist>
    </div>
    <button type="button" class="admin-btn admin-mt" data-repeater-add="#optionsRepeater">+ Add Customization Option</button>
</div>

<script>
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

    function initEditor(editor) {
        if (editor._inited) return;
        editor._inited = true;
        var rowsWrap = editor.querySelector('.suboption-rows');
        var raw = editor.querySelector('.suboption-json').value;
        var existing = [];
        if (raw) { try { existing = JSON.parse(raw) || []; } catch (e) { existing = []; } }
        existing.forEach(function (c) {
            // Tolerate legacy plain-string choices.
            rowsWrap.appendChild(buildRow(typeof c === 'string' ? { label: c } : c));
        });
    }

    // Init server-rendered editors.
    document.querySelectorAll('[data-suboption-editor]').forEach(initEditor);

    // Delegated handlers (cover dynamically-cloned option rows too).
    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('[data-add-suboption]');
        if (addBtn) {
            e.preventDefault();
            var editor = addBtn.closest('[data-suboption-editor]');
            initEditor(editor);
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
        if (e.target.closest('.suboption-row')) {
            serialize(e.target.closest('[data-suboption-editor]'));
        }
    });
    document.addEventListener('change', function (e) {
        if (e.target.closest('.suboption-row')) {
            serialize(e.target.closest('[data-suboption-editor]'));
        }
    });
    // Final safety: serialize all editors on submit.
    var pform = document.querySelector('form.admin-form');
    if (pform) pform.addEventListener('submit', function () {
        document.querySelectorAll('[data-suboption-editor]').forEach(serialize);
    });
})();

// ---- Show the Charm Set selector only for the "image_choice" option type ----
// (and hide the unused free-text Choices editor for it, to avoid confusion).
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
        // For image_choice, the price comes from each charm — hide the
        // option-level Extra Charge field to avoid confusion / double-charging.
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
    // New rows are added by the generic repeater; re-sync shortly after a click on its add button.
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-repeater-add]')) setTimeout(syncAll, 0);
    });
    syncAll();
})();
</script>
