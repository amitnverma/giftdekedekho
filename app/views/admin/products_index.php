<div class="admin-flex-between">
    <form method="get" class="admin-filters">
        <input type="text" name="search" placeholder="Search by name or SKU" value="<?= e($filters['search']) ?>">
        <select name="category_id">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= (string)$filters['category_id'] === (string)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Status</option>
            <option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button class="admin-btn" type="submit">Filter</button>
    </form>
    <a href="<?= url('/admin/products/create') ?>" class="admin-btn admin-btn-primary">+ Add Product</a>
</div>

<form method="post" action="<?= url('/admin/products/bulk') ?>">
    <?= csrfField() ?>
    <div class="admin-card admin-mt">
        <div class="admin-flex-between" style="margin-bottom:14px;">
            <select name="bulk_action">
                <option value="">Bulk Action…</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="feature">Mark Featured</option>
                <option value="unfeature">Remove Featured</option>
                <option value="delete">Delete</option>
            </select>
            <button type="submit" class="admin-btn admin-btn-sm" data-confirm="Apply this action to selected products?">Apply</button>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" data-select-all="products"></th>
                        <th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" data-bulk="products"></td>
                        <td>
                            <?php if (!empty($p['thumbnail'])): ?>
                                <img class="admin-thumb" src="<?= asset('uploads/' . $p['thumbnail']) ?>" alt="">
                            <?php else: ?><span class="admin-muted">—</span><?php endif; ?>
                        </td>
                        <td><?= e($p['name']) ?><?php if ($p['is_featured']): ?> <span class="admin-badge admin-badge-purple">Featured</span><?php endif; ?></td>
                        <td><?= e($p['category_name']) ?></td>
                        <td>
                            <?php if (!empty($p['sale_price'])): ?>
                                <strong><?= formatPrice($p['sale_price']) ?></strong> <span class="admin-muted" style="text-decoration:line-through;"><?= formatPrice($p['base_price']) ?></span>
                            <?php else: ?>
                                <?= formatPrice($p['base_price']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$p['stock_qty'] ?></td>
                        <td>
                            <?php if ($p['is_active']): ?>
                                <span class="admin-badge admin-badge-green">Active</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-gray">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="admin-btn admin-btn-sm" href="<?= url('/admin/products/' . $p['id'] . '/edit') ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="admin-muted">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<?php if ($pagination['totalPages'] > 1): ?>
    <div class="admin-pagination">
        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($filters['search']) ?>&category_id=<?= urlencode((string)$filters['category_id']) ?>&status=<?= urlencode((string)$filters['status']) ?>"
               class="<?= $i === $pagination['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
