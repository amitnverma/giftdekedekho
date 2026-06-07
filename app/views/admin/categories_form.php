<?php $isEdit = $category !== null; ?>
<div class="admin-card" style="max-width:680px;">
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <?= csrfField() ?>

        <label>Category Name
            <input type="text" name="name" required value="<?= e($category['name'] ?? old('name')) ?>">
        </label>

        <label>Description
            <textarea name="description" rows="4"><?= e($category['description'] ?? '') ?></textarea>
        </label>

        <div class="admin-form-row">
            <label>Parent Category
                <select name="parent_id">
                    <option value="">— None (Top-level) —</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= isset($category['parent_id']) && (int)$category['parent_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Sort Order
                <input type="number" name="sort_order" value="<?= (int)($category['sort_order'] ?? 0) ?>">
            </label>
        </div>

        <label>Category Image
            <input type="file" name="image" accept="image/*" data-image-preview="#catImgPreview">
            <span class="admin-label-hint">JPEG, PNG, WebP or GIF.</span>
        </label>
        <?php if (!empty($category['image'])): ?>
            <img id="catImgPreview" src="<?= asset($category['image']) ?>" style="width:90px;height:90px;object-fit:cover;border-radius:8px;margin-bottom:14px;">
        <?php else: ?>
            <img id="catImgPreview" style="width:90px;height:90px;object-fit:cover;border-radius:8px;margin-bottom:14px;display:none;">
        <?php endif; ?>

        <label class="admin-checkbox">
            <input type="checkbox" name="is_active" value="1" <?= ($category['is_active'] ?? 1) ? 'checked' : '' ?>>
            Active (visible on storefront)
        </label>

        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? 'Update Category' : 'Create Category' ?></button>
            <a href="<?= url('/admin/categories') ?>" class="admin-btn">Cancel</a>
        </div>
    </form>
</div>
