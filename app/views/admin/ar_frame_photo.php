<?php
/**
 * Full-screen display of a frame's photo, for scanning during the live test.
 *
 * Before a frame is printed there is no physical photo to point a camera at, so
 * this stands in for it: put this on the largest screen you have, then scan it
 * with the phone. Deliberately stripped of all chrome — anything else on screen
 * is just noise in the camera's view, and a busy background makes tracking
 * harder to judge.
 *
 * A frame with several photos shows one at a time; the arrow keys (or the links
 * in the bar) step through them, so each can be tested in turn.
 */
$item = $items[$position] ?? null;
$photoUrl = $item ? ArFrameService::fileUrl($item['photo_path']) : '';
$count = count($items);
$photoLink = function (int $index) use ($items, $frame) {
    return url('/admin/ar-frames/' . (int)$frame['id'] . '/photo?item=' . (int)$items[$index]['id']);
};
$prevUrl = $count > 1 ? $photoLink(($position - 1 + $count) % $count) : null;
$nextUrl = $count > 1 ? $photoLink(($position + 1) % $count) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Scan target<?= $count > 1 ? ' ' . ($position + 1) . '/' . $count : '' ?> · <?= e($frame['slug']) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    /* Mid grey rather than white or black: no glare blowing out the camera's
       exposure, and a clear edge around the photo for the tracker to find. */
    body {
        min-height: 100vh; background: #8a8a8a;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 3vmin;
    }
    img {
        max-width: 92vw; max-height: 84vh;
        display: block; background: #fff;
        box-shadow: 0 4px 30px rgba(0,0,0,.35);
    }
    .bar {
        margin-top: 2.4vmin; display: flex; gap: 14px; align-items: center;
        flex-wrap: wrap; justify-content: center;
        font-size: 13px; color: #f2f2f2;
    }
    .bar code { background: rgba(0,0,0,.35); padding: 4px 9px; border-radius: 6px; }
    .bar a { color: #fff; background: rgba(0,0,0,.4); padding: 7px 15px; border-radius: 999px; text-decoration: none; }
    .hint { margin-top: 1.2vmin; font-size: 12.5px; color: #efefef; opacity: .85; text-align: center; max-width: 46em; }
    /* Hide everything but the photo, for a clean full-screen presentation. */
    body.bare .bar, body.bare .hint { display: none; }
    body.bare img { max-width: 98vw; max-height: 98vh; }
</style>
</head>
<body>
    <?php if ($photoUrl !== ''): ?>
        <img src="<?= e($photoUrl) ?>" alt="Scan target">
    <?php else: ?>
        <p style="color:#fff">This frame has no photo on file.</p>
    <?php endif; ?>

    <div class="bar">
        <code><?= e($frame['slug']) ?><?= $count > 1 ? ' · photo ' . ($position + 1) . ' of ' . $count : '' ?></code>
        <?php if ($prevUrl !== null): ?>
            <a href="<?= e($prevUrl) ?>" id="prevPhoto">← Previous</a>
            <a href="<?= e($nextUrl) ?>" id="nextPhoto">Next photo →</a>
        <?php endif; ?>
        <a href="<?= e($backUrl) ?>">Back to frame</a>
        <a href="#" id="bareToggle">Hide this bar</a>
    </div>
    <p class="hint">
        Point the phone at this photo, filling most of the camera view. Turn your screen brightness up,
        and avoid pointing the phone at it on a steep angle.
        <?php if ($count > 1): ?>
            After its video plays, close it on the phone, move to the next photo here and scan again.
            Arrow keys step through the photos even with this bar hidden.
        <?php endif; ?>
    </p>

<script>
// Some screens show a moiré pattern against a camera at certain zoom levels;
// hiding the surrounding UI gives the tracker the cleanest possible view.
document.getElementById('bareToggle').addEventListener('click', function (e) {
    e.preventDefault();
    document.body.classList.add('bare');
});

// Arrow keys keep working in the bare view, where the links are hidden.
<?php if ($prevUrl !== null): ?>
document.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') window.location.href = <?= json_encode($nextUrl . '&bare=1') ?>;
    if (e.key === 'ArrowLeft') window.location.href = <?= json_encode($prevUrl . '&bare=1') ?>;
});
<?php endif; ?>
if (new URLSearchParams(window.location.search).get('bare') === '1') {
    document.body.classList.add('bare');
}
</script>
</body>
</html>
