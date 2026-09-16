<?php
/** Singles or Albums list. */
$isAlbum = $kind === 'album';
$allowed = $isAlbum ? !empty($partner['allow_albums']) : !empty($partner['allow_singles']);
$createUrl = url($base . ($isAlbum ? '/albums/create' : '/singles/create'));
if ($allowed) {
    $headAction = '<a class="btn" href="' . $createUrl . '">+ ' . ($isAlbum ? 'New Album' : 'New Single') . '</a>';
}
?>
<div class="card">
    <div class="card-head">
        <h2><?= $isAlbum ? 'Albums' : 'Singles' ?> <span class="muted small">(<?= count($content) ?>)</span></h2>
        <form class="toolbar" method="get">
            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search title, code or customer" aria-label="Search">
            <button class="btn btn-ghost btn-sm" type="submit">Search</button>
        </form>
    </div>
    <?php if ($content): ?>
        <?php $rows = $content; require viewPath('partner/_content_table.php'); ?>
    <?php else: ?>
        <div class="empty">
            <h3><?= $search !== '' ? 'Nothing matches that search' : ($isAlbum ? 'No albums yet' : 'No singles yet') ?></h3>
            <p><?= $isAlbum ? 'An album puts several photos, each with its own video, behind one QR code.' : 'A single is one photo that plays one video when scanned.' ?></p>
            <?php if ($allowed && $search === ''): ?>
                <a class="btn" href="<?= $createUrl ?>">+ <?= $isAlbum ? 'New Album' : 'New Single' ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
