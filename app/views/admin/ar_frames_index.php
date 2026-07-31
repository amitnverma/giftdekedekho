<?php
/**
 * AR Frame Orders — the queue. Covers both channels; the Channel filter
 * separates online orders from walk-ins when you want just one.
 */
$statusBadge = [
    'pending_setup'    => 'admin-badge-gray',
    'target_generated' => 'admin-badge-yellow',
    'verified'         => 'admin-badge-green',
    'printed'          => 'admin-badge-blue',
    'shipped'          => 'admin-badge-purple',
    'handed_over'      => 'admin-badge-purple',
];
$flagBadge = ['good' => 'admin-badge-green', 'fair' => 'admin-badge-yellow', 'poor' => 'admin-badge-red'];
?>

<?php if (!$compiler['ok']): ?>
    <div class="admin-alert admin-alert-error">
        <strong>Target compiler unavailable.</strong> <?= e($compiler['message']) ?><br>
        New targets cannot be generated until this is fixed. See <code>tools/mindar-compile/README.txt</code>.
    </div>
<?php endif; ?>

<?php if (!empty($scanAll['heavy'])): ?>
    <div class="admin-callout">
        <strong>The public scanner page is getting heavy.</strong>
        <?= (int)$scanAll['count'] ?> active frames means visitors to
        <a href="<?= url('/scan') ?>" target="_blank">/scan</a> download about
        <?= e($scanAll['approx_mb']) ?>MB before they can scan anything.
        Frames scanned from the link on their own printed card are unaffected — those stay small and instant.
        Consider deactivating frames that are no longer in circulation.
    </div>
<?php endif; ?>

<div class="admin-flex-between">
    <div>
        <p class="admin-muted" style="margin:0 0 4px;font-size:13px">
            A printed photo that plays a video when any phone camera looks at it. No QR code on the product.
        </p>
        <?php if (!empty($scanAll['count'])): ?>
            <p class="admin-muted" style="margin:0;font-size:12.5px">
                <a href="<?= url('/scan') ?>" target="_blank">/scan</a> recognises
                <?= (int)$scanAll['count'] ?> active frame<?= $scanAll['count'] === 1 ? '' : 's' ?>
                (~<?= e($scanAll['approx_mb']) ?>MB)
            </p>
        <?php endif; ?>
    </div>
    <a class="admin-btn admin-btn-primary" href="<?= url('/admin/ar-frames/quick-create') ?>">⚡ Quick Create (Walk-in)</a>
</div>

<div class="admin-grid admin-grid-4 admin-mt">
    <?php
    $tiles = [
        ['Pending Setup', $statusCounts['pending_setup'], 'pending_setup', 'Awaiting target generation'],
        ['Target Generated', $statusCounts['target_generated'], 'target_generated', 'Needs the live scan test'],
        ['Verified', $statusCounts['verified'], 'verified', 'Tested — ready to print'],
        ['Printed', $statusCounts['printed'], 'printed', 'Ready to ship or hand over'],
    ];
    foreach ($tiles as [$label, $count, $status, $sub]): ?>
        <a class="admin-card" style="text-decoration:none;color:inherit" href="<?= url('/admin/ar-frames?status=' . $status) ?>">
            <p class="admin-kpi-label"><?= e($label) ?></p>
            <p class="admin-kpi-value"><?= (int)$count ?></p>
            <p class="admin-kpi-sub"><?= e($sub) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<form method="get" class="admin-filters admin-mt">
    <input type="text" name="search" placeholder="Search slug, customer, phone or order #" value="<?= e($filters['search']) ?>">
    <select name="status">
        <option value="">All Statuses</option>
        <?php foreach (ArFrame::STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="channel">
        <option value="">All Channels</option>
        <?php foreach (ArFrame::CHANNELS as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filters['channel'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="unverified">
        <option value="">Verified &amp; unverified</option>
        <option value="1" <?= !empty($filters['unverified']) ? 'selected' : '' ?>>Not yet live-tested</option>
    </select>
    <button class="admin-btn" type="submit">Filter</button>
</form>

<div class="admin-card admin-mt">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr>
                <th>Photo</th><th>Slug</th><th>Channel</th><th>Customer</th>
                <th>Trackability</th><th>Live Test</th><th>Status</th><th>Created</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($frames as $f): ?>
                <tr>
                    <td>
                        <?php $photo = ArFrameService::fileUrl($f['photo_path']); ?>
                        <?php if ($photo !== ''): ?>
                            <img class="admin-thumb" src="<?= e($photo) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?= e($f['slug']) ?></code>
                        <?php if (empty($f['is_active'])): ?>
                            <span class="admin-badge admin-badge-red" style="font-size:10px">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="admin-badge <?= $f['channel'] === 'in_store' ? 'admin-badge-purple' : 'admin-badge-blue' ?>">
                            <?= e(ArFrame::channelLabel($f['channel'])) ?>
                        </span>
                        <?php if (!empty($f['order_id'])): ?>
                            <a href="<?= url('/admin/orders/' . (int)$f['order_id']) ?>" style="font-size:12px">#<?= (int)$f['order_id'] ?></a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($f['display_customer'] ?? '—') ?>
                        <?php if (!empty($f['display_phone'])): ?>
                            <div class="admin-muted" style="font-size:12px"><?= e($f['display_phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($f['trackability_flag'] === null): ?>
                            <span class="admin-muted">—</span>
                        <?php else: ?>
                            <span class="admin-badge <?= $flagBadge[$f['trackability_flag']] ?? 'admin-badge-gray' ?>">
                                <?= (int)$f['trackability_score'] ?>/100
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($f['verified_at'])): ?>
                            <span class="admin-badge admin-badge-green" title="<?= e($f['verified_at']) ?>">Passed</span>
                        <?php elseif (!empty($f['target_path'])): ?>
                            <span class="admin-badge admin-badge-yellow">Not tested</span>
                        <?php else: ?>
                            <span class="admin-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="admin-badge <?= $statusBadge[$f['status']] ?? 'admin-badge-gray' ?>">
                            <?= e(ArFrame::statusLabel($f['status'])) ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                    <td style="white-space:nowrap">
                        <a class="admin-btn admin-btn-sm" href="<?= url('/admin/ar-frames/' . (int)$f['id']) ?>">Open</a>
                        <?php
                        // A frame past 'verified' may already be in a customer's
                        // hands, so its confirmation says what deleting breaks.
                        $inCirculation = in_array($f['status'], ['printed', 'shipped', 'handed_over'], true);
                        $confirm = $inCirculation
                            ? "Delete {$f['slug']}?\n\nThis frame has already been printed or handed over. "
                              . "Deleting it permanently breaks the scan link on that customer's card — their video will stop working.\n\n"
                              . "To stop it working temporarily instead, open the frame and untick \"Scan link active\"."
                            : "Delete {$f['slug']}?\n\nThe photo and generated target are deleted too. This cannot be undone.";
                        ?>
                        <form method="post" action="<?= url('/admin/ar-frames/' . (int)$f['id'] . '/delete') ?>"
                              style="display:inline" onsubmit="return confirm(<?= e(json_encode($confirm)) ?>)">
                            <?= csrfField() ?>
                            <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($frames)): ?>
                <tr><td colspan="9" class="admin-muted">
                    No AR frames yet. Walk-in customers start with
                    <a href="<?= url('/admin/ar-frames/quick-create') ?>">Quick Create</a>; online orders appear here
                    automatically when a customer buys a product with the "Living Photo AR Frame" option.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
    <div class="admin-pagination">
        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($filters['status']) ?>&channel=<?= urlencode($filters['channel']) ?>&search=<?= urlencode($filters['search']) ?>&unverified=<?= urlencode($filters['unverified']) ?>"
               class="<?= $i === $pagination['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
