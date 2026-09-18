<?php
/** Partner home: balance warning, headline numbers, shortcuts, latest content. */
$base_credits = (int)$partner['base_credits'];
$canCreate = ArPartnerService::itemsAffordable($partner);
?>

<?php if ($canCreate === 0): ?>
    <div class="banner banner-amber">
        <p>
            <strong>You need credits to create DEx content.</strong>
            Each item starts at <?= number_format($base_credits) ?> credits and you have <?= number_format($balance) ?>.
        </p>
        <a class="btn btn-amber btn-sm" href="<?= url($base . '/credits') ?>">Buy Credits</a>
    </div>
<?php elseif ($canCreate <= 3): ?>
    <div class="banner banner-amber">
        <p><strong>Credits running low</strong> — enough for about <?= $canCreate ?> more basic item<?= $canCreate === 1 ? '' : 's' ?>.</p>
        <a class="btn btn-amber btn-sm" href="<?= url($base . '/credits') ?>">Buy Credits</a>
    </div>
<?php endif; ?>

<div class="stats">
    <?php
    $tiles = [
        ['coin', 'green', 'Per DEx item', number_format($base_credits), 'credits'],
        ['users', 'pink', 'Customers', number_format($customers), ''],
        ['image', 'yellow', 'Singles', number_format($counts['singles']), ''],
        ['album', 'violet', 'Albums', number_format($counts['albums']), ''],
        ['eye', 'teal', 'Total opens', number_format($scans['opens']), ''],
        ['clock', 'blue', 'Today', number_format($scans['today']), ''],
    ];
    foreach ($tiles as [$icon, $tone, $label, $value, $unit]): ?>
        <div class="stat">
            <span class="ic ic-<?= $tone ?>"><?= partnerIcon($icon) ?></span>
            <div>
                <div class="label"><?= e($label) ?></div>
                <div class="value"><?= $value ?><?php if ($unit): ?><small><?= e($unit) ?></small><?php endif; ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="actions">
    <a class="action" href="<?= url($base . '/customers#add') ?>">
        <span class="ic ic-pink"><?= partnerIcon('user-add') ?></span>
        <span><span class="t">Add Customer</span><br><span class="s">New client for DEx content</span></span>
    </a>
    <?php if (!empty($partner['allow_singles'])): ?>
        <a class="action" href="<?= url($base . '/singles/create') ?>">
            <span class="ic ic-yellow"><?= partnerIcon('image') ?></span>
            <span><span class="t">New Single</span><br><span class="s">Single image + video</span></span>
        </a>
    <?php endif; ?>
    <?php if (!empty($partner['allow_albums'])): ?>
        <a class="action" href="<?= url($base . '/albums/create') ?>">
            <span class="ic ic-violet"><?= partnerIcon('album') ?></span>
            <span><span class="t">New Album</span><br><span class="s">Multi-page DEx album</span></span>
        </a>
    <?php endif; ?>
</div>

<?php if ($recent): ?>
    <div class="card">
        <div class="card-head"><h2>Recent DEx content</h2></div>
        <?php $rows = $recent; require viewPath('partner/_content_table.php'); ?>
    </div>
<?php endif; ?>
