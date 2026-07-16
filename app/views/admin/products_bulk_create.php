<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.snow.css">

<form method="post" enctype="multipart/form-data" class="admin-form" action="<?= url('/admin/products/bulk-create') ?>">
    <?= csrfField() ?>

    <p class="admin-help" style="margin-bottom:14px;">Upload several images that share the same description, category, pricing and options. <strong>Each image becomes its own product</strong> on the storefront — only the image differs.</p>

    <div class="admin-grid admin-grid-2" style="align-items:start;">
        <div class="admin-card">
            <h2 class="admin-card-title">Basic Information</h2>

            <label>Base Product Name <span class="admin-label-hint">Applied to every product, numbered (e.g. “Wooden Frame 1”). You can override each name below.</span>
                <input type="text" name="name" required value="<?= e(old('name')) ?>">
            </label>

            <label>Categories <span class="admin-label-hint">Check all that apply — the first checked is the primary</span></label>
            <div class="admin-checkbox-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px 12px;margin-bottom:14px;">
                <?php foreach ($categories as $cat): ?>
                    <label class="admin-checkbox" style="margin:0;">
                        <input type="checkbox" name="category_ids[]" value="<?= (int)$cat['id'] ?>">
                        <?= e($cat['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p id="catValidationMsg" style="color:#c00;font-size:13px;display:none;margin:-8px 0 10px;">Please select at least one category.</p>

            <label>Short Description
                <textarea name="short_description" rows="2"></textarea>
            </label>

            <label>Full Description
                <textarea name="description_hidden" id="descriptionHidden" style="display:none;"></textarea>
                <div id="descriptionEditor" data-quill="#descriptionHidden" style="height:220px;background:#fff;"></div>
            </label>
        </div>

        <div class="admin-card">
            <h2 class="admin-card-title">Pricing &amp; Stock <span class="admin-label-hint" style="font-weight:400;">shared by all</span></h2>
            <div class="admin-form-row">
                <label>Base Price (₹)
                    <input type="number" step="0.01" min="0" name="base_price" required value="0">
                </label>
                <label>Sale Price (₹) <span class="admin-label-hint">Leave blank for no discount</span>
                    <input type="number" step="0.01" min="0" name="sale_price" value="">
                </label>
            </div>
            <div class="admin-form-row">
                <label>Stock Quantity
                    <input type="number" min="0" name="stock_qty" value="0">
                </label>
                <label>Base SKU <span class="admin-label-hint">Numbered per product (e.g. FRAME-1); override each below</span>
                    <input type="text" name="sku" value="">
                </label>
            </div>
            <label>Weight (grams)
                <input type="number" min="0" name="weight_grams" value="">
            </label>
            <label class="admin-checkbox">
                <input type="checkbox" name="is_featured" value="1">
                Featured Product (shown on homepage)
            </label>
            <label class="admin-checkbox">
                <input type="checkbox" name="is_active" value="1" checked>
                Active (visible on storefront)
            </label>

            <h2 class="admin-card-title admin-mt">SEO <span class="admin-label-hint" style="font-weight:400;">shared by all</span></h2>
            <label>Meta Title
                <input type="text" name="meta_title" value="">
            </label>
            <label>Meta Description
                <textarea name="meta_description" rows="2"></textarea>
            </label>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Images → Products</h2>
        <label>Upload Images
            <input type="file" id="bulkImages" name="images[]" accept="image/*" multiple>
            <span class="admin-label-hint">JPEG, PNG, WebP or GIF — up to 5MB each. One product is created per image, with that image as its primary listing photo.</span>
        </label>
        <p id="bulkCount" class="admin-help" style="margin-top:10px;font-weight:600;"></p>
        <div id="bulkImageGrid" class="admin-image-grid" style="margin-top:12px;"></div>
    </div>

    <?php include __DIR__ . '/partials/_product_options.php'; ?>

    <div class="admin-form-actions admin-mt">
        <button type="submit" class="admin-btn admin-btn-primary">Create Products</button>
        <a href="<?= url('/admin/products') ?>" class="admin-btn">Cancel</a>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.min.js"></script>
<script>
// ---- Quill full-description editor + category validation on submit ----
(function () {
    var el = document.getElementById('descriptionEditor');
    var form = document.querySelector('form.admin-form');
    var quill = null;
    if (window.Quill && el) {
        var hidden = document.getElementById('descriptionHidden');
        quill = new Quill(el, { theme: 'snow' });
        if (hidden.value) quill.root.innerHTML = hidden.value;
        quill.on('text-change', function () { hidden.value = quill.root.innerHTML; });
    }
    form.addEventListener('submit', function (e) {
        if (quill) {
            var hidden = document.getElementById('descriptionHidden');
            hidden.value = quill.root.innerHTML;
            hidden.name = 'description';
        }
        var checked = form.querySelectorAll('input[name="category_ids[]"]:checked');
        if (checked.length === 0) {
            e.preventDefault();
            document.getElementById('catValidationMsg').style.display = 'block';
            form.querySelector('input[name="category_ids[]"]').closest('.admin-checkbox-grid').scrollIntoView({behavior:'smooth', block:'center'});
            return;
        }
        var fileInput = document.getElementById('bulkImages');
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('Please upload at least one image — each becomes its own product.');
        }
    });
})();

// ---- Bulk image → product grid: preview + per-image name/SKU, removable ----
(function () {
    var input = document.getElementById('bulkImages');
    var grid = document.getElementById('bulkImageGrid');
    var countEl = document.getElementById('bulkCount');

    // In-memory model kept in step with the file input so images[],
    // product_name[] and product_sku[] always share the same index order.
    var rows = [];

    function syncInput() {
        var dt = new DataTransfer();
        rows.forEach(function (r) { dt.items.add(r.file); });
        input.files = dt.files;
    }

    function updateCount() {
        var n = rows.length;
        countEl.textContent = n ? (n + ' product' + (n === 1 ? '' : 's') + ' will be created.') : '';
    }

    function captureValues() {
        // Preserve whatever the admin has typed before we re-render.
        grid.querySelectorAll('.admin-image-item').forEach(function (item, i) {
            if (!rows[i]) return;
            rows[i].name = item.querySelector('.bulk-name').value;
            rows[i].sku = item.querySelector('.bulk-sku').value;
        });
    }

    function render() {
        grid.innerHTML = '';
        rows.forEach(function (r, i) {
            var item = document.createElement('div');
            item.className = 'admin-image-item';
            item.style.cssText = 'position:relative;display:flex;flex-direction:column;gap:6px;padding:8px;';

            var img = document.createElement('img');
            img.style.cssText = 'width:100%;height:120px;object-fit:cover;border-radius:6px;';
            img.src = URL.createObjectURL(r.file);
            img.onload = function () { URL.revokeObjectURL(img.src); };

            var nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.name = 'product_name[]';
            nameInput.className = 'bulk-name';
            nameInput.placeholder = 'Name (optional)';
            nameInput.value = r.name || '';
            nameInput.style.cssText = 'width:100%;font-size:13px;';

            var skuInput = document.createElement('input');
            skuInput.type = 'text';
            skuInput.name = 'product_sku[]';
            skuInput.className = 'bulk-sku';
            skuInput.placeholder = 'SKU (optional)';
            skuInput.value = r.sku || '';
            skuInput.style.cssText = 'width:100%;font-size:13px;';

            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'admin-image-remove';
            rm.innerHTML = '&times;';
            rm.addEventListener('click', function () {
                captureValues();
                rows.splice(i, 1);
                syncInput();
                render();
            });

            item.appendChild(rm);
            item.appendChild(img);
            item.appendChild(nameInput);
            item.appendChild(skuInput);
            grid.appendChild(item);
        });
        updateCount();
    }

    input.addEventListener('change', function () {
        captureValues();
        Array.prototype.slice.call(input.files).forEach(function (f) {
            rows.push({ file: f, name: '', sku: '' });
        });
        syncInput(); // de-dupes back into a single FileList in row order
        render();
    });
})();
</script>
