<?php
/**
 * AR frame detail — the working surface for both channels. The pipeline is laid
 * out as explicit steps so it's obvious what still has to happen, and the live
 * scan test is visibly the gate before print/handover.
 */
$photoUrl = ArFrameService::fileUrl($frame['photo_path']);
$isVerified = !empty($frame['verified_at']);
$hasTarget = !empty($frame['target_path']);
$isWalkIn = $frame['channel'] === 'in_store';
$metrics = !empty($frame['trackability_json']) ? json_decode($frame['trackability_json'], true) : null;
$flagBadge = ['good' => 'admin-badge-green', 'fair' => 'admin-badge-yellow', 'poor' => 'admin-badge-red'];
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
    <!-- ---------- The photo + pipeline ---------- -->
    <div class="admin-card">
        <h3 class="admin-card-title">1 · Photo to print</h3>
        <?php if ($photoUrl !== ''): ?>
            <img src="<?= e($photoUrl) ?>" alt="Customer photo"
                 style="width:100%;max-height:320px;object-fit:contain;background:#f3f4f6;border-radius:10px">
        <?php else: ?>
            <p class="admin-muted">No photo on file.</p>
        <?php endif; ?>

        <form method="post" action="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/replace-photo') ?>"
              enctype="multipart/form-data" class="admin-form admin-mt">
            <?= csrfField() ?>
            <label class="admin-label-hint">Replace photo (JPG/PNG, max 10MB)</label>
            <input type="file" name="photo" accept="image/jpeg,image/png" required>
            <p class="admin-help-text">
                Replacing the photo clears the generated target and any passed live test — both have to be redone.
            </p>
            <button class="admin-btn admin-btn-sm" type="submit">Replace photo</button>
        </form>

        <hr class="admin-hr">

        <h3 class="admin-card-title">2 · AR target</h3>
        <?php if (!$compiler['ok']): ?>
            <div class="admin-alert admin-alert-error" style="margin-bottom:12px">
                <?= e($compiler['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($hasTarget): ?>
            <p style="margin:0 0 10px">
                Trackability
                <span class="admin-badge <?= $flagBadge[$frame['trackability_flag']] ?? 'admin-badge-gray' ?>">
                    <?= (int)$frame['trackability_score'] ?>/100 · <?= e(strtoupper((string)$frame['trackability_flag'])) ?>
                </span>
            </p>
            <p class="admin-help-text" style="margin-top:0">
                <?= e(ArTargetService::trackabilityAdvice((string)$frame['trackability_flag'])) ?>
            </p>
            <?php if ($metrics): ?>
                <p class="admin-muted" style="font-size:12px;margin:8px 0 0">
                    <?= (int)($metrics['matching_points'] ?? 0) ?> matching points ·
                    <?= e(implode('/', array_map('intval', $metrics['tracking_points'] ?? []))) ?> tracking points ·
                    compiled <?= (int)($metrics['compiled_width'] ?? 0) ?>×<?= (int)($metrics['compiled_height'] ?? 0) ?>
                    in <?= number_format(((int)($metrics['elapsed_ms'] ?? 0)) / 1000, 1) ?>s
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p class="admin-muted" style="margin-top:0">
                No target generated yet. The photo must be compiled into a MindAR target before it can trigger anything.
            </p>
        <?php endif; ?>

        <form method="post" action="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/generate-target') ?>" class="admin-mt">
            <?= csrfField() ?>
            <button class="admin-btn <?= $hasTarget ? '' : 'admin-btn-primary' ?>" type="submit"
                    <?= $compiler['ok'] ? '' : 'disabled' ?>>
                <?= $hasTarget ? 'Regenerate target' : 'Generate target' ?>
            </button>
            <span class="admin-muted" style="font-size:12px;margin-left:8px">takes about 5 seconds</span>
        </form>

        <hr class="admin-hr">

        <h3 class="admin-card-title">3 · Live scan test <?= $isVerified ? '✅' : '' ?></h3>
        <?php if ($isVerified): ?>
            <p style="margin-top:0">
                <span class="admin-badge admin-badge-green">Passed</span>
                <span class="admin-muted" style="font-size:13px">on <?= e(date('d M Y, g:i a', strtotime($frame['verified_at']))) ?></span>
            </p>
        <?php else: ?>
            <p class="admin-help-text" style="margin-top:0">
                Open the test page on a phone, point it at the photo on screen or at the printed proof, and confirm
                the video actually fires. The frame cannot be marked printed until this passes.
            </p>
        <?php endif; ?>
        <?php if (!$hasTarget): ?>
            <p class="admin-muted" style="margin-top:0">Generate the target first.</p>
        <?php else: ?>
            <div class="admin-callout" style="margin-bottom:14px">
                <strong>The phone is the scanner, so the photo has to be on a different screen.</strong>
                Before printing there is nothing physical to point a camera at — use this computer's screen as the stand-in.
            </div>

            <ol style="margin:0 0 4px 18px;padding:0;font-size:13px;line-height:1.9">
                <li>
                    <a href="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/photo') ?>" target="_blank">
                        <strong>Show photo full-screen ↗</strong>
                    </a>
                    — leave it open on this computer.
                </li>
                <li>
                    On your phone, open the scan link
                    <?php if (!empty($phoneTestUrl)): ?>
                        (<code style="font-size:12px"><?= e($phoneTestUrl) ?></code>)
                    <?php else: ?>
                        shown above
                    <?php endif; ?>
                    and tap <em>Start camera</em>.
                </li>
                <li>Point the phone at the photo on this screen. The video should play on the phone.</li>
            </ol>

            <form method="post" action="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/confirm-test') ?>"
                  class="admin-mt" style="border-top:1px solid var(--admin-border);padding-top:14px">
                <?= csrfField() ?>
                <label class="admin-checkbox">
                    <input type="checkbox" name="confirmed" value="1" required>
                    I pointed a phone at this photo and the video played
                </label>
                <div class="admin-form-actions" style="margin-top:10px">
                    <button class="admin-btn <?= $isVerified ? '' : 'admin-btn-primary' ?>" type="submit">
                        <?= $isVerified ? 'Re-record live test' : 'Record live test as passed' ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <hr class="admin-hr">

        <h3 class="admin-card-title">4 · Handoff</h3>
        <?php if (!empty($transitions)): ?>
            <form method="post" action="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/status') ?>"
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
        <p class="admin-help-text" style="margin-bottom:6px">
            The sticker goes on the frame itself and is how the customer gets in — scanning it opens this
            frame's camera page directly. The card is the paper backup, for anyone who loses the sticker.
        </p>
        <a class="admin-btn admin-btn-sm admin-btn-primary" href="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/sticker') ?>" target="_blank">
            🏷 QR sticker to print
        </a>
        <a class="admin-btn admin-btn-sm" href="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/card') ?>" target="_blank">
            🖨 Instruction card (PDF)
        </a>
    </div>

    <!-- ---------- Video, scan URL, details ---------- -->
    <div>
        <div class="admin-card">
            <h3 class="admin-card-title">Public scan link</h3>
            <p class="admin-help-text" style="margin-top:0">
                This is what goes on the instruction card. The product itself stays unmarked.
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

        <div class="admin-card admin-mt">
            <h3 class="admin-card-title">Video &amp; settings</h3>
            <?php if ($playback === null): ?>
                <div class="admin-alert admin-alert-error">
                    No playable video on this frame yet — the scan will match but nothing will play.
                </div>
            <?php elseif ($playback['type'] === 'youtube'): ?>
                <p style="margin-top:0">
                    <span class="admin-badge admin-badge-red">YouTube</span>
                    <a href="<?= e($playback['url']) ?>" target="_blank" style="font-size:13px">
                        <?= e($playback['url']) ?> ↗
                    </a>
                </p>
            <?php else: ?>
                <p style="margin-top:0"><span class="admin-badge admin-badge-blue">Uploaded file</span></p>
                <video src="<?= e($playback['url']) ?>" controls preload="metadata"
                       style="width:100%;max-height:200px;border-radius:8px;background:#000"></video>
            <?php endif; ?>

            <form method="post" action="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/details') ?>"
                  enctype="multipart/form-data" class="admin-form admin-mt">
                <?= csrfField() ?>

                <div class="admin-form-row">
                    <label class="admin-label-hint">Video source</label>
                    <select name="video_type" id="arVideoType">
                        <option value="link" <?= $frame['video_type'] !== 'upload' ? 'selected' : '' ?>>Video link (YouTube, Vimeo or direct file)</option>
                        <option value="upload" <?= $frame['video_type'] === 'upload' ? 'selected' : '' ?>>Uploaded video file</option>
                    </select>
                </div>

                <div class="admin-form-row" data-ar-video="link">
                    <label class="admin-label-hint">Video link</label>
                    <input type="url" name="video_url" value="<?= e((string)$frame['video_url']) ?>"
                           placeholder="https://www.youtube.com/watch?v=…">
                    <p class="admin-help-text">YouTube, Vimeo, or a direct https link to an .mp4 / .webm / .mov file. Must be Public or Unlisted.</p>
                </div>

                <div class="admin-form-row" data-ar-video="upload">
                    <label class="admin-label-hint">Video file (MP4/MOV/WebM, max 100MB)</label>
                    <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm">
                    <?php if (!empty($frame['video_path'])): ?>
                        <p class="admin-help-text">A file is already attached — leave this blank to keep it.</p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-row">
                    <label class="admin-label-hint">Playback mode</label>
                    <select name="playback_mode">
                        <option value="fullscreen" <?= $frame['playback_mode'] === 'fullscreen' ? 'selected' : '' ?>>
                            Full-screen takeover (recommended)
                        </option>
                        <option value="overlay" <?= $frame['playback_mode'] === 'overlay' ? 'selected' : '' ?>>
                            AR overlay on the photo
                        </option>
                    </select>
                    <p class="admin-help-text">
                        Full-screen is more reliable and reads better for a gift moment. Overlay anchors the video to
                        the photo in the camera view — impressive, but fussier about light and angle.
                    </p>
                </div>

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
// a card the customer already holds, so the warning says so before they click.
$inCirculation = in_array($frame['status'], ['printed', 'shipped', 'handed_over'], true);
$deleteConfirm = $inCirculation
    ? "Delete {$frame['slug']}?\n\nThis frame has already been printed or handed over. "
      . "Deleting it permanently breaks the scan link on that customer's card — their video will stop working.\n\n"
      . "To stop it working temporarily instead, untick \"Scan link active\" and save."
    : "Delete {$frame['slug']}?\n\nThe photo and generated target are deleted too. This cannot be undone.";
?>
<div class="admin-card admin-mt" style="border-color:#f1c4c4">
    <h3 class="admin-card-title" style="color:#b03a3a">Delete this frame</h3>
    <p class="admin-help-text" style="margin-top:0">
        Removes the record along with its photo and generated target.
        <?php if ($inCirculation): ?>
            <strong>This frame is already with the customer</strong> — deleting it breaks the scan link on their
            printed card. To disable it temporarily instead, untick <em>Scan link active</em> above and save.
        <?php else: ?>
            To keep the record but stop the link working, untick <em>Scan link active</em> above instead.
        <?php endif; ?>
    </p>
    <form method="post" action="<?= url('/admin/ar-frames/' . (int)$frame['id'] . '/delete') ?>"
          onsubmit="return confirm(<?= e(json_encode($deleteConfirm)) ?>)">
        <?= csrfField() ?>
        <button class="admin-btn admin-btn-danger" type="submit">Delete <?= e($frame['slug']) ?></button>
    </form>
</div>

<script>
// Show only the fields for the selected video source.
(function () {
    var select = document.getElementById('arVideoType');
    if (!select) return;
    function sync() {
        document.querySelectorAll('[data-ar-video]').forEach(function (row) {
            row.style.display = row.getAttribute('data-ar-video') === select.value ? '' : 'none';
        });
    }
    select.addEventListener('change', sync);
    sync();
})();
</script>
