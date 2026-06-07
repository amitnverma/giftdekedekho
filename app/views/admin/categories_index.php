<div class="admin-flex-between admin-mt" style="margin-top:0;">
    <p class="admin-muted">Manage product categories and sub-categories.</p>
    <a href="<?= url('/admin/categories/create') ?>" class="admin-btn admin-btn-primary">+ Add Category</a>
</div>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>Image</th><th>Name</th><th>Parent</th><th>Products</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <?php if (!empty($cat['image'])): ?>
                            <img class="admin-thumb" src="<?= asset($cat['image']) ?>" alt="">
                        <?php else: ?>
                            <span class="admin-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($cat['name']) ?></td>
                    <td><?= $cat['parent_id'] ? '↳ Sub-category' : '<span class="admin-badge admin-badge-purple">Top-level</span>' ?></td>
                    <td><?= (int)$cat['product_count'] ?></td>
                    <td>
                        <?php if ($cat['is_active']): ?>
                            <span class="admin-badge admin-badge-green">Active</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-gray">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="admin-btn admin-btn-sm" href="<?= url('/admin/categories/' . $cat['id'] . '/edit') ?>">Edit</a>
                        <form method="post" action="<?= url('/admin/categories/' . $cat['id'] . '/delete') ?>" style="display:inline">
                            <?= csrfField() ?>
                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Delete this category?">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
                <tr><td colspan="6" class="admin-muted">No categories yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
