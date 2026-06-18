<div class="admin-flex-between">
    <p class="admin-muted">Reusable image sets (charms) that customers pick from while customising a product.</p>
    <a href="<?= url('/admin/charm-sets/create') ?>" class="admin-btn admin-btn-primary">+ New Charm Set</a>
</div>

<div class="admin-card admin-mt">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Charms</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($sets as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= (int)$s['charm_count'] ?></td>
                    <td>
                        <?php if ($s['is_active']): ?>
                            <span class="admin-badge admin-badge-green">Active</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-gray">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="admin-btn admin-btn-sm" href="<?= url('/admin/charm-sets/' . $s['id'] . '/edit') ?>">Edit &amp; Upload</a>
                        <form method="post" action="<?= url('/admin/charm-sets/' . $s['id'] . '/delete') ?>" style="display:inline">
                            <?= csrfField() ?>
                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Delete this set and all its charms? Products using it will fall back to no charms.">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sets)): ?>
                <tr><td colspan="4" class="admin-muted">No charm sets yet. Create one, then upload your charm images.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
