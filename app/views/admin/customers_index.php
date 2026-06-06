<div class="admin-flex-between">
    <form method="get" class="admin-filters">
        <input type="text" name="search" placeholder="Search by name, email or phone" value="<?= e($search) ?>">
        <button class="admin-btn" type="submit">Search</button>
    </form>
    <a href="<?= url('/admin/customers/export') ?>" class="admin-btn admin-btn-primary">Export CSV</a>
</div>

<div class="admin-card admin-mt">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e($c['phone'] ?? '—') ?></td>
                    <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                    <td><a class="admin-btn admin-btn-sm" href="<?= url('/admin/customers/' . $c['id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($customers)): ?>
                <tr><td colspan="5" class="admin-muted">No customers found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
