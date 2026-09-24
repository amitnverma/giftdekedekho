<?php
/**
 * One single or album: how to hand it over (QR, link, sticker, WhatsApp), how
 * to test it, and — during the edit window — the way into its edit page.
 */
$id = (int)$frame['id'];
$isAlbum = $frame['content_kind'] === 'album';
$frame['item_count'] = count($items);
$frame['target_count'] = count(array_filter($items, fn($i) => !empty($i['target_path'])));
[$stateLabel, $stateTone] = ArPartnerService::contentState($frame);
$missingTargets = $frame['item_count'] - $frame['target_count'];
$tested = !empty($frame['verified_at']);
$flagTone = ['good' => 'live', 'fair' => 'warn', 'poor' => 'used'];
$flagWord = ['good' => 'Scans well', 'fair' => 'May need good light', 'poor' => 'Hard to scan'];

// WhatsApp needs full international digits; a 10-digit Indian mobile gets 91.
$waPhone = preg_replace('/\D/', '', (string)($frame['customer_phone_number'] ?? ''));
if (strlen($waPhone) === 10) {
    $waPhone = '91' . $waPhone;
}
$waText = 'Your DEx gift from ' . $partner['name'] . ' is ready! Scan the QR sticker, or open this link and point your camera at the photo: ' . $scanUrl;

$editUrl = url($base . '/content/' . $id . '/edit');
$headAction = '<div class="toolbar">'
    . '<span class="tag tag-' . $stateTone . '" style="font-size:13px;padding:4px 10px">' . e($stateLabel) . '</span>'
    . ($editable
        ? '<a class="btn btn-sm" href="' . $editUrl . '">Edit</a>'
        : '<span class="btn btn-sm btn-ghost" aria-disabled="true" title="The edit window has closed">Edit</span>')
    . '</div>';
?>
<p style="margin:-8px 0 16px">
    <a class="btn-link" href="<?= url($base . ($isAlbum ? '/albums' : '/singles')) ?>">← <?= $isAlbum ? 'All albums' : 'All singles' ?></a>
</p>

<?php if ($expired): ?>
    <div class="banner banner-amber"><p><strong>This content has expired.</strong> Scanning it now shows a "has expired" page. Contact <?= e($brand['poweredBy']) ?> to renew it.</p></div>
<?php elseif ($missingTargets > 0): ?>
    <div class="banner banner-danger">
        <p><strong><?= $missingTargets ?> photo<?= $missingTargets === 1 ? ' is' : 's are' ?> not ready to scan yet.</strong>
            Press “Prepare photos”. If it keeps failing, <?= $editable ? '<a href="' . $editUrl . '">replace the image</a>' : 'replace the image' ?>.</p>
        <form method="post" action="<?= url($base . '/content/' . $id . '/generate') ?>">
            <?= csrfField() ?>
            <button class="btn btn-sm" type="submit">Prepare photos</button>
        </form>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <div>
        <div class="card">
            <div class="card-head"><h2>Details</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Type</dt><dd><?= $isAlbum ? 'Album · ' . count($items) . ' pages' : 'Single' ?></dd>
                    <dt>Customer</dt><dd><?= e($frame['customer_name'] ?? '—') ?></dd>
                    <dt>Code</dt><dd><code><?= e($frame['slug']) ?></code></dd>
                    <dt>Created</dt><dd><?= e(date('d M Y, h:i A', strtotime($frame['created_at']))) ?></dd>
                    <dt>Active for</dt>
                    <dd>
                        <?php if (!empty($frame['delete_after'])): ?>
                            <span class="deletes-on">Free trial — deleted automatically on <?= e(date('d M Y, h:i A', strtotime($frame['delete_after']))) ?></span>
                        <?php else: ?>
                        <?= e(ArPartner::validityLabel($frame['validity'])) ?>
                        <?php if (!empty($frame['active_until'])): ?>
                            <span class="muted">— until <?= e(date('d M Y', strtotime($frame['active_until']))) ?></span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </dd>
                    <dt>Credits</dt><dd><?= number_format((int)$frame['credits_charged']) ?></dd>
                    <dt>Opens</dt><dd><?= number_format($opens) ?> <span class="muted">(<?= number_format($visitors) ?> unique)</span></dd>
                    <dt>Editable</dt>
                    <dd><?php if ($editable): ?>
                            Until <?= e(date('d M Y, h:i A', strtotime($frame['editable_until']))) ?> ·
                            <a href="<?= $editUrl ?>">Edit title, customer, photos &amp; videos</a>
                        <?php else: ?>
                            No — the edit window closed<?= !empty($frame['editable_until']) ? ' on ' . e(date('d M Y', strtotime($frame['editable_until']))) : '' ?>
                        <?php endif; ?></dd>
                </dl>
            </div>
            <form class="card-foot" method="post" action="<?= url($base . '/content/' . $id . '/update') ?>" style="display:block">
                <?= csrfField() ?>
                <div class="toolbar" style="justify-content:space-between">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1" <?= !empty($frame['is_active']) ? 'checked' : '' ?>>
                        <span>Scan link switched on <span class="muted small">— untick to stop it working without deleting anything</span></span>
                    </label>
                    <button class="btn btn-ghost btn-sm" type="submit">Save</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-head">
                <h2><?= $isAlbum ? 'Pages' : 'Photo & video' ?></h2>
                <?php if ($frame['target_count'] > 0): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= url($base . '/content/' . $id . '/photo') ?>" target="_blank">Show photo full-screen</a>
                <?php endif; ?>
            </div>
            <?php foreach ($items as $n => $item):
                $itemId = (int)$item['id'];
                $flag = $item['trackability_flag'] ?? null; ?>
                <div class="item" id="item-<?= $itemId ?>">
                    <img class="item-photo" src="<?= e(ArFrameService::fileUrl($item['photo_path'])) ?>" alt="" loading="lazy">
                    <div>
                        <h3><?= e($item['title'] ?: ($isAlbum ? 'DEx Content ' . ($n + 1) : ($frame['title'] ?: 'Photo'))) ?></h3>
                        <div class="toolbar small">
                            <?php if (empty($item['target_path'])): ?>
                                <span class="tag tag-used">Not prepared</span>
                            <?php elseif ($flag): ?>
                                <span class="tag tag-<?= $flagTone[$flag] ?? 'off' ?>"><?= e($flagWord[$flag] ?? $flag) ?> · <?= (int)$item['trackability_score'] ?>/100</span>
                            <?php endif; ?>
                            <?php if (!empty($item['verified_at'])): ?><span class="tag tag-live">Tested</span><?php endif; ?>
                            <span class="muted">Plays up to <?= (int)($item['max_seconds'] ?? 0) ?>s · <?= e(ArFrameItem::PLAYBACK_MODES[ArFrameItem::playbackMode($item['playback_mode'] ?? null)]) ?></span>
                            <?php if (!empty($item['video_path'])): ?>
                                <a class="btn-link" href="<?= e(ArFrameService::fileUrl($item['video_path'])) ?>" target="_blank" rel="noopener">View video ↗</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($flag && $flag !== 'good'): ?>
                            <p class="small muted" style="margin:6px 0 0"><?= e(ArTargetService::trackabilityAdvice($flag)) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head"><h2>Hand over</h2></div>
            <div class="card-body qr-box">
                <?php if ($qr): ?>
                    <img src="<?= e($qr) ?>" alt="QR code for <?= e($frame['slug']) ?>">
                <?php endif; ?>
                <div class="copy-row">
                    <input type="text" id="scan-link" value="<?= e($scanUrl) ?>" readonly aria-label="Scan link">
                    <button class="btn btn-ghost btn-sm" type="button" data-copy="scan-link">Copy</button>
                </div>
                <div class="stack">
                    <a class="btn" href="<?= url($base . '/content/' . $id . '/sticker') ?>" target="_blank">Print QR sticker</a>
                    <?php if ($waPhone !== ''): ?>
                        <a class="btn btn-ghost" href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener">Send link on WhatsApp</a>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="<?= e($scanUrl) ?>" target="_blank" rel="noopener">Open scan page</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2>Test it</h2>
                <?php if ($tested): ?><span class="tag tag-live">Tested</span><?php endif; ?>
            </div>
            <div class="card-body small">
                <ol style="margin:0 0 12px;padding-left:18px;line-height:1.7">
                    <li>Press <strong>Show photo full-screen</strong> on this computer (or use the print).</li>
                    <li>Scan the QR code above with your phone.</li>
                    <li>Point the phone at the photo — the video should start.</li>
                </ol>
                <?php if (!$tested && $missingTargets === 0 && !empty($frame['target_path'])): ?>
                    <form method="post" action="<?= url($base . '/content/' . $id . '/confirm-test') ?>">
                        <?= csrfField() ?>
                        <label class="check" style="margin-bottom:10px">
                            <input type="checkbox" name="confirmed" value="1" required>
                            <span>I scanned <?= count($items) > 1 ? 'every photo' : 'it' ?> and the video played</span>
                        </label>
                        <button class="btn btn-ghost btn-sm" type="submit">Record test</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
