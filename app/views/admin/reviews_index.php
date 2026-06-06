<div class="admin-flex-between">
    <div class="admin-tabs" style="border:0; margin:0;">
        <a href="?filter=pending" class="admin-btn admin-btn-sm <?= $filter === 'pending' ? 'admin-btn-primary' : '' ?>">Pending</a>
        <a href="?filter=all" class="admin-btn admin-btn-sm <?= $filter === 'all' ? 'admin-btn-primary' : '' ?>">All Reviews</a>
    </div>
</div>

<form method="post" action="<?= url('/admin/reviews/bulk-approve') ?>">
    <?= csrfField() ?>
    <div class="admin-card admin-mt">
        <?php if ($filter === 'pending' && !empty($reviews)): ?>
            <div class="admin-flex-between" style="margin-bottom:14px;">
                <span class="admin-muted"><?= count($reviews) ?> review(s) awaiting moderation</span>
                <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary" data-confirm="Approve all selected reviews?">Bulk Approve Selected</button>
            </div>
        <?php endif; ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <?php if ($filter === 'pending'): ?><th><input type="checkbox" data-select-all="reviews"></th><?php endif; ?>
                        <th>Product</th><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr>
                        <?php if ($filter === 'pending'): ?>
                            <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" data-bulk="reviews"></td>
                        <?php endif; ?>
                        <td><a href="<?= url('/product/' . $r['product_slug']) ?>" target="_blank"><?= e($r['product_name']) ?></a></td>
                        <td><?= e($r['user_name']) ?></td>
                        <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
                        <td>
                            <?php if (!empty($r['title'])): ?><strong><?= e($r['title']) ?></strong><br><?php endif; ?>
                            <?= e($r['body'] ?? '') ?>
                        </td>
                        <td><?= timeAgo($r['created_at']) ?></td>
                        <td>
                            <?php if ($r['is_approved']): ?>
                                <span class="admin-badge admin-badge-green">Approved</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-yellow">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$r['is_approved']): ?>
                                <form method="post" action="<?= url('/admin/reviews/' . $r['id'] . '/approve') ?>" style="display:inline">
                                    <?= csrfField() ?>
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">Approve</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= url('/admin/reviews/' . $r['id'] . '/reject') ?>" style="display:inline">
                                <?= csrfField() ?>
                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Reject and delete this review?">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($reviews)): ?>
                    <tr><td colspan="8" class="admin-muted">No reviews found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
