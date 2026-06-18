<?php $isEdit = !empty($set); ?>
<div class="admin-flex-between">
    <p class="admin-muted"><?= $isEdit ? 'Edit this charm set and upload more charms.' : 'Name your set, then upload charms after saving.' ?></p>
    <a href="<?= url('/admin/charm-sets') ?>" class="admin-btn">&larr; Back to Library</a>
</div>

<form method="post" enctype="multipart/form-data" class="admin-form"
      action="<?= url($isEdit ? '/admin/charm-sets/' . $set['id'] . '/edit' : '/admin/charm-sets/create') ?>">
    <?= csrfField() ?>

    <div class="admin-card admin-mt">
        <div class="admin-form-row">
            <label>Set Name
                <input type="text" name="name" value="<?= e($set['name'] ?? '') ?>" placeholder="e.g. Wallet Charms" required>
            </label>
            <label class="admin-checkbox" style="align-self:flex-end;">
                <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || $set['is_active']) ? 'checked' : '' ?>>
                Active (available to attach to products)
            </label>
        </div>
    </div>

    <?php if ($isEdit): ?>
    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Upload Charms</h2>
        <p class="admin-help">Select multiple images at once — each becomes a selectable charm. The filename is used as the default label (you can rename below). JPEG, PNG, WebP or GIF, up to 5MB each.</p>
        <input type="file" name="charm_images[]" accept="image/*" multiple>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Charms in this set (<?= count($charms) ?>)</h2>
        <?php if (empty($charms)): ?>
            <p class="admin-muted">No charms yet. Upload some above and save.</p>
        <?php else: ?>
            <p class="admin-help">Edit the label, set an optional extra charge, toggle availability, or mark for removal. Changes apply when you click Save.</p>
            <div class="admin-charm-grid">
                <?php foreach ($charms as $c): ?>
                    <div class="admin-charm-card">
                        <img src="<?= e(asset($c['image_path'])) ?>" alt="<?= e($c['label']) ?>" loading="lazy">
                        <input type="text" name="charm_label[<?= (int)$c['id'] ?>]" value="<?= e($c['label']) ?>" placeholder="Label">
                        <label class="admin-charm-price">+ ₹
                            <input type="number" step="0.01" min="0" name="charm_price[<?= (int)$c['id'] ?>]" value="<?= e($c['extra_charge']) ?>">
                        </label>
                        <label class="admin-charm-flag">
                            <input type="checkbox" name="charm_active[<?= (int)$c['id'] ?>]" value="1" <?= $c['is_active'] ? 'checked' : '' ?>> Active
                        </label>
                        <label class="admin-charm-flag admin-charm-del">
                            <input type="checkbox" name="charm_delete[<?= (int)$c['id'] ?>]" value="1"> Delete
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="admin-form-actions admin-mt">
        <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? 'Save Charm Set' : 'Create Set' ?></button>
        <a href="<?= url('/admin/charm-sets') ?>" class="admin-btn">Cancel</a>
    </div>
</form>

<style>
.admin-charm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; margin-top:12px; }
.admin-charm-card { border:1px solid var(--admin-border,#e5e7eb); border-radius:10px; padding:10px; display:flex; flex-direction:column; gap:6px; background:#fff; }
.admin-charm-card img { width:100%; height:110px; object-fit:contain; background:#f8fafc; border-radius:6px; }
.admin-charm-card input[type=text], .admin-charm-card input[type=number] { width:100%; padding:5px 7px; font-size:13px; }
.admin-charm-price { display:flex; align-items:center; gap:4px; font-size:12px; color:#6b7280; }
.admin-charm-flag { display:flex; align-items:center; gap:5px; font-size:12px; font-weight:500; }
.admin-charm-del { color:#b91c1c; }
</style>
