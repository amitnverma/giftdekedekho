<?php
/**
 * AR frame detail — the working surface for both channels. The pipeline is laid
 * out as explicit steps so it's obvious what still has to happen, and the live
 * scan test is visibly the gate before print/handover.
 *
 * A frame holds one or more photos behind its single QR sticker. Each photo
 * plays its own video, has its own target, and must pass its own live test.
 */
$isVerified = !empty($frame['verified_at']);
$hasTarget = !empty($frame['target_path']);
$isWalkIn = $frame['channel'] === 'in_store';
$flagBadge = ['good' => 'admin-badge-green', 'fair' => 'admin-badge-yellow', 'poor' => 'admin-badge-red'];

$itemCount = count($items);
$isSet = $itemCount > 1;
$missingTargets = count(array_filter($items, fn($i) => empty($i['target_path'])));
$verifiedCount = count(array_filter($items, fn($i) => !empty($i['target_path']) && !empty($i['verified_at'])));
$canAdd = $itemCount < $maxItems;
$frameUrl = '/admin/ar-frames/' . (int)$frame['id'];

/** The same link / upload / playback-mode fields, for adding a photo and for editing one. */
$videoFields = function (?array $item) {
    $type = $item['video_type'] ?? 'youtube';
    $mode = $item['playback_mode'] ?? 'fullscreen';
    ?>
    <div class="admin-form-row">
        <label class="admin-label-hint">Video source</label>
        <select name="video_type" data-video-type>
            <option value="link" <?= $type !== 'upload' ? 'selected' : '' ?>>Video link (YouTube, Vimeo or direct file)</option>
            <option value="upload" <?= $type === 'upload' ? 'selected' : '' ?>>Uploaded video file</option>
        </select>
    </div>
    <div class="admin-form-row" data-ar-video="link">
        <label class="admin-label-hint">Video link</label>
        <input type="url" name="video_url" value="<?= e($type !== 'upload' ? (string)($item['video_url'] ?? '') : '') ?>"
               placeholder="https://www.youtube.com/watch?v=…">
        <p class="admin-help-text">YouTube, Vimeo, or a direct https link to an .mp4 / .webm / .mov file. Must be Public or Unlisted.</p>
    </div>
    <div class="admin-form-row" data-ar-video="upload">
        <label class="admin-label-hint">Video file (MP4/MOV/WebM, max 100MB)</label>
        <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm">
        <?php if (!empty($item['video_path'])): ?>
            <p class="admin-help-text">A file is already attached — leave this blank to keep it.</p>
        <?php endif; ?>
    </div>
    <div class="admin-form-row">
        <label class="admin-label-hint">Playback mode</label>
        <select name="playback_mode">
            <option value="fullscreen" <?= $mode === 'fullscreen' ? 'selected' : '' ?>>Full-screen takeover (recommended)</option>
            <option value="overlay" <?= $mode === 'overlay' ? 'selected' : '' ?>>AR overlay on the photo</option>
        </select>
    </div>
    <?php
};
?>

<div class="admin-flex-between">
    <div>
        <h2 style="margin:0;font-size:20px">
            <code><?= e($frame['slug']) ?></code>
            <span class="admin-badge <?= $isWalkIn ? 'admin-badge-purple' : 'admin-badge-blue' ?>">
                <?= e(ArFrame::channelLabel($frame['channel'])) ?>
            </span>
            <span class="admin-badge admin-badge-gray"><?= e(ArFrame::statusLabel($frame['status'])) ?></span>
        </h2>
        <p class="admin-muted" style="margin:6px 0 0;font-size:13px">
            Created <?= e(timeAgo($frame['created_at'])) ?>
            <?php if (!empty($frame['created_by_name'])): ?> by <?= e($frame['created_by_name']) ?><?php endif; ?>
            <?php if (!empty($frame['order_id'])): ?>
                · Order <a href="<?= url('/admin/orders/' . (int)$frame['order_id']) ?>">#<?= (int)$frame['order_id'] ?></a>
            <?php endif; ?>
            · <?= $itemCount ?> photo<?= $itemCount === 1 ? '' : 's' ?> on one QR sticker
        </p>
    </div>
    <a class="admin-btn" href="<?= url('/admin/ar-frames') ?>">← Back to queue</a>
</div>

<?php if ($isWalkIn && !$isVerified): ?>
    <div class="admin-callout admin-mt">
        <strong>Walk-in sale — do not hand this over yet.</strong>
        Run the live scan test while the customer is still at the counter. That is the whole advantage of an
        in-person sale: you can guarantee the frame works instead of finding out days later that it doesn't.
    </div>
<?php endif; ?>

<div class="admin-grid admin-grid-2 admin-mt">
    <!-- ---------- Photos + pipeline ---------- -->
    <div class="admin-card">
        <h3 class="admin-card-title">1 · Photos &amp; videos (<?= $itemCount ?>)</h3>
        <p class="admin-help-text" style="margin-top:0">
            Every photo here opens from the same QR sticker. Pointing the camera at a photo plays that photo's video.
        </p>

        <?php if (!$compiler['ok']): ?>
            <div class="admin-alert admin-alert-error" style="margin-bottom:12px">
                <?= e($compiler['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="admin-alert admin-alert-error">This frame has no photos. Add one to make the sticker do anything.</div>
        <?php endif; ?>

        <?php foreach ($items as $position => $item):
            $itemId = (int)$item['id'];
            $itemUrl = $frameUrl . '/items/' . $itemId;
            $photoUrl = ArFrameService::fileUrl($item['photo_path']);
            $video = $playback[$itemId] ?? null;
            $metrics = !empty($item['trackability_json']) ? json_decode($item['trackability_json'], true) : null;
        ?>
            <div id="item-<?= $itemId ?>" style="border:1px solid var(--admin-border);border-radius:10px;padding:12px;margin-bottom:12px">
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <?php if ($photoUrl !== ''): ?>
                        <a href="<?= url($frameUrl . '/photo?item=' . $itemId) ?>" target="_blank" title="Show full-screen">
                            <img src="<?= e($photoUrl) ?>" alt="Photo <?= $position + 1 ?>"
                                 style="width:96px;height:96px;object-fit:contain;background:#f3f4f6;border-radius:8px;flex:none">
                        </a>
                    <?php endif; ?>
                    <div style="flex:1;min-width:0">
                        <strong>Photo <?= $position + 1 ?></strong>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin:6px 0">
                            <?php if (!empty($item['target_path'])): ?>
                                <span class="admin-badge <?= $flagBadge[$item['trackability_flag']] ?? 'admin-badge-gray' ?>"
                                      title="<?= e(ArTargetService::trackabilityAdvice((string)$item['trackability_flag'])) ?>">
                                    <?= (int)$item['trackability_score'] ?>/100 · <?= e(strtoupper((string)$item['trackability_flag'])) ?>
                                </span>
                                <?php if (!empty($item['verified_at'])): ?>
                                    <span class="admin-badge admin-badge-green" title="<?= e($item['verified_at']) ?>">Live test passed</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-yellow">Not tested</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-red">No target — won't scan yet</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($video === null): ?>
                            <span class="admin-badge admin-badge-red">No playable video</span>
                        <?php elseif (in_array($video['type'], ['upload', 'direct'], true)): ?>
                            <video src="<?= e($video['url']) ?>" controls preload="metadata"
                                   style="width:100%;max-height:140px;border-radius:8px;background:#000"></video>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-blue"><?= e(ArFrameService::videoTypeLabel($video['type'])) ?></span>
                            <a href="<?= e($video['url']) ?>" target="_blank" style="font-size:13px;word-break:break-all"><?= e($video['url']) ?> ↗</a>
                        <?php endif; ?>
                        <?php if ($metrics && !empty($item['target_path'])): ?>
                            <p class="admin-muted" style="font-size:12px;margin:6px 0 0">
                                <?= (int)($metrics['matching_points'] ?? 0) ?> matching points ·
                                <?= e(implode('/', array_map('intval', $metrics['tracking_points'] ?? []))) ?> tracking points ·
                                <?= (int)($metrics['compiled_width'] ?? 0) ?>×<?= (int)($metrics['compiled_height'] ?? 0) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <details style="margin-top:10px">
                    <summary style="cursor:pointer;font-size:13px;font-weight:600">Edit photo <?= $position + 1 ?></summary>

                    <form method="post" action="<?= url($itemUrl . '/replace-photo') ?>" enctype="multipart/form-data" class="admin-form admin-mt">
                        <?= csrfField() ?>
                        <label class="admin-label-hint">Replace photo (JPG/PNG, max 10MB)</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png" required>
                        <p class="admin-help-text">Its target is rebuilt straight away, and its live test has to be redone.</p>
                        <button class="admin-btn admin-btn-sm" type="submit" <?= $compiler['ok'] ? '' : 'disabled' ?>>Replace photo</button>
                    </form>

                    <hr class="admin-hr">

                    <form method="post" action="<?= url($itemUrl . '/video') ?>" enctype="multipart/form-data" class="admin-form" data-video-form>
                        <?= csrfField() ?>
                        <?php $videoFields($item); ?>
                        <button class="admin-btn admin-btn-sm admin-btn-primary" type="submit">Save video</button>
                    </form>

                    <hr class="admin-hr">

                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <form method="post" action="<?= url($itemUrl . '/generate-target') ?>">
                            <?= csrfField() ?>
                            <button class="admin-btn admin-btn-sm" type="submit" <?= $compiler['ok'] ? '' : 'disabled' ?>>
                                <?= empty($item['target_path']) ? 'Generate target' : 'Regenerate target' ?>
                            </button>
                        </form>
                        <?php if ($isSet): ?>
                            <form method="post" action="<?= url($itemUrl . '/delete') ?>"
                                  onsubmit="return confirm(<?= e(json_encode('Remove photo ' . ($position + 1) . ' and its video from ' . $frame['slug'] . '?' . (in_array($frame['status'], ['printed', 'shipped', 'handed_over'], true) ? "\n\nThis frame is already with the customer — that photo will stop playing its video." : ''))) ?>)">
                                <?= csrfField() ?>
                                <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit">Remove photo</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        <?php endforeach; ?>

        <?php if ($missingTargets > 0 && $items): ?>
            <form method="post" action="<?= url($frameUrl . '/generate-target') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="only_missing" value="1">
                <button class="admin-btn admin-btn-primary" type="submit" <?= $compiler['ok'] ? '' : 'disabled' ?>>
                    Generate <?= $missingTargets === 1 ? 'the missing target' : 'the ' . $missingTargets . ' missing targets' ?>
                </button>
                <span class="admin-muted" style="font-size:12px;margin-left:8px">about 5 seconds per photo</span>
            </form>
        <?php endif; ?>

        <hr class="admin-hr">

        <h3 class="admin-card-title">2 · Live scan test <?= $isVerified ? '✅' : '' ?></h3>
        <?php if ($isVerified): ?>
            <p style="margin-top:0">
                <span class="admin-badge admin-badge-green">Passed<?= $isSet ? ' · all ' . $itemCount . ' photos' : '' ?></span>
                <span class="admin-muted" style="font-size:13px">on <?= e(date('d M Y, g:i a', strtotime($frame['verified_at']))) ?></span>
            </p>
        <?php else: ?>
            <p class="admin-help-text" style="margin-top:0">
                Open the scan link on a phone, point it at <?= $isSet ? 'each photo' : 'the photo' ?> on screen or at the
                printed proof, and confirm the video actually fires. The frame cannot be marked printed until
                <?= $isSet ? 'every photo passes' : 'this passes' ?>.
                <?php if ($isSet): ?>
                    <br><strong><?= $verifiedCount ?> of <?= $itemCount ?></strong> photos passed so far.
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (!$hasTarget): ?>
            <p class="admin-muted" style="margin-top:0">Generate a target first.</p>
        <?php else: ?>
            <div class="admin-callout" style="margin-bottom:14px">
                <strong>The phone is the scanner, so the photo has to be on a different screen.</strong>
                Before printing there is nothing physical to point a camera at — use this computer's screen as the stand-in.
            </div>

            <ol style="margin:0 0 4px 18px;padding:0;font-size:13px;line-height:1.9">
                <li>
                    <a href="<?= url($frameUrl . '/photo') ?>" target="_blank">
                        <strong>Show <?= $isSet ? 'photos' : 'photo' ?> full-screen ↗</strong>
                    </a>
                    — leave it open on this computer<?= $isSet ? ', and step through the photos with its arrows' : '' ?>.
                </li>
                <li>
                    On your phone, open the scan link
                    <?php if (!empty($phoneTestUrl)): ?>
                        (<code style="font-size:12px"><?= e($phoneTestUrl) ?></code>)
                    <?php else: ?>
                        shown alongside
                    <?php endif; ?>
                    and tap <em>Start camera</em>.
                </li>
                <li>
                    Point the phone at the photo on this screen. The video should play on the phone.
                    <?php if ($isSet): ?>Close it, show the next photo, and repeat — make sure each photo plays <em>its own</em> video.<?php endif; ?>
                </li>
            </ol>

            <?php if ($missingTargets > 0): ?>
                <p class="admin-muted" style="font-size:13px">
                    <?= $missingTargets ?> photo<?= $missingTargets === 1 ? ' has' : 's have' ?> no target yet, so
                    the test cannot be recorded until <?= $missingTargets === 1 ? 'it is' : 'they are' ?> generated.
                </p>
            <?php else: ?>
                <form method="post" action="<?= url($frameUrl . '/confirm-test') ?>"
                      class="admin-mt" style="border-top:1px solid var(--admin-border);padding-top:14px">
                    <?= csrfField() ?>
                    <label class="admin-checkbox">
                        <input type="checkbox" name="confirmed" value="1" required>
                        <?= $isSet
                            ? 'I pointed a phone at every one of these ' . $itemCount . ' photos and each played its own video'
                            : 'I pointed a phone at this photo and the video played' ?>
                    </label>
                    <div class="admin-form-actions" style="margin-top:10px">
                        <button class="admin-btn <?= $isVerified ? '' : 'admin-btn-primary' ?>" type="submit">
                            <?= $isVerified ? 'Re-record live test' : 'Record live test as passed' ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <hr class="admin-hr">

        <h3 class="admin-card-title">3 · Handoff</h3>
        <?php if (!empty($transitions)): ?>
            <form method="post" action="<?= url($frameUrl . '/status') ?>"
                  style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <?= csrfField() ?>
                <select name="status" style="max-width:220px">
                    <?php foreach ($transitions as $next): ?>
                        <option value="<?= e($next) ?>"><?= e(ArFrame::statusLabel($next)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="admin-btn admin-btn-primary" type="submit">Update status</button>
            </form>
        <?php elseif (!$isVerified): ?>
            <p class="admin-muted" style="margin-top:0">
                Locked until the live scan test passes.
            </p>
        <?php else: ?>
            <p class="admin-muted" style="margin-top:0">
                <?= e(ArFrame::statusLabel($frame['status'])) ?> — nothing further to do.
            </p>
        <?php endif; ?>
        <?php $scannable = !empty($frame['target_path']); ?>
        <?php if (!$scannable): ?>
            <div class="admin-alert admin-alert-error" style="margin-bottom:10px">
                <strong>This sticker will not scan yet.</strong> No target has been generated, so scanning it
                shows &ldquo;still being prepared&rdquo;. Generate the target above first, then print.
            </div>
        <?php endif; ?>
        <p class="admin-help-text" style="margin-bottom:6px">
            The sticker goes on the frame itself and is how the customer gets in — scanning it opens this
            frame's camera page directly<?= $isSet ? ', ready for any of its photos' : '' ?>. The card is the paper
            backup, for anyone who loses the sticker.
        </p>
        <a class="admin-btn admin-btn-sm <?= $scannable ? 'admin-btn-primary' : '' ?>" href="<?= url($frameUrl . '/sticker') ?>" target="_blank">
            🏷 QR sticker to print
        </a>
        <a class="admin-btn admin-btn-sm" href="<?= url($frameUrl . '/card') ?>" target="_blank">
            🖨 Instruction card (PDF)
        </a>
    </div>

    <!-- ---------- Scan link, add photo, details ---------- -->
    <div>
        <div class="admin-card">
            <h3 class="admin-card-title">Public scan link</h3>
            <p class="admin-help-text" style="margin-top:0">
                This is what the QR sticker and the instruction card open.
            </p>
            <input type="text" readonly value="<?= e($scanUrl) ?>"
                   onclick="this.select()" style="width:100%;font-family:monospace;font-size:13px">

            <?php if (!empty($phoneTestUrl)): ?>
                <!-- Dev only: the address a phone on the same Wi-Fi can actually
                     open. localhost is unreachable from a phone, and the camera
                     needs https, so both parts have to change. -->
                <p class="admin-label-hint admin-mt" style="margin-bottom:4px">Test on your phone (same Wi-Fi)</p>
                <input type="text" readonly value="<?= e($phoneTestUrl) ?>"
                       onclick="this.select()" style="width:100%;font-family:monospace;font-size:13px">
                <p class="admin-help-text">
                    Local testing only. The certificate is self-signed, so tap through the browser's
                    "Not Private" warning once — after that the camera works normally.
                </p>
            <?php endif; ?>

            <p class="admin-mt" style="margin-bottom:0">
                <a class="admin-btn admin-btn-sm" href="<?= e($scanUrl) ?>" target="_blank">Open scan page ↗</a>
                <?php if (empty($frame['is_active'])): ?>
                    <span class="admin-badge admin-badge-red">Disabled — visitors see a "not available" page</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="admin-card admin-mt" id="add-photo">
            <h3 class="admin-card-title">Add another photo &amp; video</h3>
            <?php if (!$canAdd): ?>
                <p class="admin-muted" style="margin:0">
                    This frame already has <?= $itemCount ?> photos, the most one sticker can hold. Every photo is
                    downloaded when the sticker is scanned, so more would make the camera slow to start.
                </p>
            <?php else: ?>
                <p class="admin-help-text" style="margin-top:0">
                    Shares this frame's QR sticker — nothing new to print except the photo itself.
                    Its target is built as soon as you save.
                    <?php if (in_array($frame['status'], ['printed', 'shipped', 'handed_over'], true)): ?>
                        <br><strong>This frame is already <?= e(strtolower(ArFrame::statusLabel($frame['status']))) ?>.</strong>
                        Its existing photos keep working while the new one is added.
                    <?php endif; ?>
                </p>
                <form method="post" action="<?= url($frameUrl . '/items') ?>" enctype="multipart/form-data"
                      class="admin-form" data-video-form data-busy-label="Building AR target…">
                    <?= csrfField() ?>
                    <div class="admin-form-row">
                        <label class="admin-label-hint">Photo to print (JPG/PNG, max 10MB)</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png" required>
                    </div>
                    <?php $videoFields(null); ?>
                    <p class="admin-help-text">An uploaded video counts toward the server's <?= e($uploadLimit) ?> limit per save.</p>
                    <div class="admin-form-actions">
                        <button class="admin-btn admin-btn-primary" type="submit" <?= $compiler['ok'] ? '' : 'disabled' ?>>
                            Add photo &amp; build target
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="admin-card admin-mt">
            <h3 class="admin-card-title">Frame details</h3>
            <form method="post" action="<?= url($frameUrl . '/details') ?>" class="admin-form">
                <?= csrfField() ?>
                <div class="admin-form-row">
                    <label class="admin-label-hint">Customer name (walk-in reference)</label>
                    <input type="text" name="customer_name" maxlength="120" value="<?= e((string)$frame['customer_name']) ?>">
                </div>
                <div class="admin-form-row">
                    <label class="admin-label-hint">Customer phone</label>
                    <input type="text" name="customer_phone" maxlength="15" value="<?= e((string)$frame['customer_phone']) ?>">
                </div>
                <div class="admin-form-row">
                    <label class="admin-label-hint">Internal notes</label>
                    <textarea name="notes" rows="2"><?= e((string)$frame['notes']) ?></textarea>
                </div>
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($frame['is_active']) ? 'checked' : '' ?>>
                    Scan link active
                </label>

                <div class="admin-form-actions">
                    <button class="admin-btn admin-btn-primary" type="submit">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Deleting a frame that has been printed or handed over breaks the scan link on
// a sticker the customer already has, so the warning says so before they click.
$inCirculation = in_array($frame['status'], ['printed', 'shipped', 'handed_over'], true);
$deleteConfirm = $inCirculation
    ? "Delete {$frame['slug']}?\n\nThis frame has already been printed or handed over. "
      . "Deleting it permanently breaks the scan link on that customer's sticker — their videos will stop working.\n\n"
      . "To stop it working temporarily instead, untick \"Scan link active\" and save."
    : "Delete {$frame['slug']}?\n\nEvery photo, video and generated target is deleted too. This cannot be undone.";
?>
<div class="admin-card admin-mt" style="border-color:#f1c4c4">
    <h3 class="admin-card-title" style="color:#b03a3a">Delete this frame</h3>
    <p class="admin-help-text" style="margin-top:0">
        Removes the record along with all its photos and generated targets.
        <?php if ($inCirculation): ?>
            <strong>This frame is already with the customer</strong> — deleting it breaks the scan link on their
            sticker. To disable it temporarily instead, untick <em>Scan link active</em> above and save.
        <?php else: ?>
            To keep the record but stop the link working, untick <em>Scan link active</em> above instead.
        <?php endif; ?>
    </p>
    <form method="post" action="<?= url($frameUrl . '/delete') ?>"
          onsubmit="return confirm(<?= e(json_encode($deleteConfirm)) ?>)">
        <?= csrfField() ?>
        <button class="admin-btn admin-btn-danger" type="submit">Delete <?= e($frame['slug']) ?></button>
    </form>
</div>

<script>
(function () {
    // Each video form shows only the fields for its own selected source.
    document.querySelectorAll('[data-video-form]').forEach(function (form) {
        var select = form.querySelector('[data-video-type]');
        if (!select) return;
        function sync() {
            form.querySelectorAll('[data-ar-video]').forEach(function (row) {
                row.style.display = row.getAttribute('data-ar-video') === select.value ? '' : 'none';
            });
        }
        select.addEventListener('change', sync);
        sync();
    });

    // Compiling is synchronous — make it obvious the page is working rather
    // than letting someone double-submit and queue a second compile.
    document.querySelectorAll('[data-busy-label]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type=submit]');
            if (!button) return;
            button.disabled = true;
            button.textContent = form.getAttribute('data-busy-label');
        });
    });
})();
</script>
