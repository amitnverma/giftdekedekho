<?php
/**
 * Edit a single or album during its edit window: title, customer, and for each
 * photo its title, playback mode, and a replacement image and/or video.
 *
 * New files upload one at a time as they are picked (partner.js), exactly as on
 * the create form, so this form submits only tokens. What was paid for — video
 * length, validity and the number of pages — is shown but not editable.
 */
$id = (int)$frame['id'];
$isAlbum = $frame['content_kind'] === 'album';
$config = [
    'uploadUrl'     => url($base . '/upload'),
    'maxPhotoBytes' => ArFrameService::MAX_PHOTO_BYTES,
    'maxVideoBytes' => $maxVideoMb * 1024 * 1024,
];
?>
<p style="margin:-8px 0 16px">
    <a class="btn-link" href="<?= url($base . '/content/' . $id) ?>">← Back without saving</a>
</p>

<form class="p-narrow" method="post" action="<?= url($base . '/content/' . $id . '/edit') ?>" data-edit-form="<?= e(json_encode($config, JSON_UNESCAPED_SLASHES)) ?>">
    <?= csrfField() ?>

    <div class="card">
        <div class="card-body">
            <div class="grid-2">
                <div class="field">
                    <label for="e-title"><?= $isAlbum ? 'Album Title' : 'Title' ?></label>
                    <input type="text" id="e-title" name="title" value="<?= e($frame['title']) ?>" maxlength="160">
                </div>
                <div class="field">
                    <label for="e-customer">Customer</label>
                    <select id="e-customer" name="customer_id">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$frame['partner_customer_id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="hint" style="margin:0">
                Editable until <?= e(date('d M Y, h:i A', strtotime($frame['editable_until']))) ?>.
                Active for <?= e(ArPartner::validityLabel($frame['validity'])) ?> — the validity, video lengths and number of pages were paid for and cannot be changed.
            </p>
        </div>

        <div class="card-head" style="border-top:1px solid var(--line)">
            <h2><?= $isAlbum ? 'AR Contents' : 'Photo & video' ?></h2>
        </div>
        <?php foreach ($items as $n => $item):
            $itemId = (int)$item['id'];
            $mode = ArFrameItem::playbackMode($item['playback_mode'] ?? null);
            $maxSeconds = (int)($item['max_seconds'] ?? 0); ?>
            <div class="item" data-edit-item data-max-seconds="<?= $maxSeconds ?>">
                <img class="item-photo" src="<?= e(ArFrameService::fileUrl($item['photo_path'])) ?>" alt="" loading="lazy">
                <div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="i-title-<?= $itemId ?>"><?= $isAlbum ? 'Title of AR content ' . ($n + 1) : 'Photo title' ?> <span class="muted">(optional)</span></label>
                            <input type="text" id="i-title-<?= $itemId ?>" name="items[<?= $itemId ?>][title]" value="<?= e($item['title'] ?? '') ?>" maxlength="120">
                        </div>
                        <div class="field">
                            <span class="field-label">Playback mode</span>
                            <div class="chips">
                                <?php foreach ($playbackModes as $key => $label): ?>
                                    <label class="chip">
                                        <input type="radio" name="items[<?= $itemId ?>][playback_mode]" value="<?= e($key) ?>" <?= $key === $mode ? 'checked' : '' ?>>
                                        <span><?= e($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="item-replace" style="margin-top:0">
                        <span class="small muted">Replace:</span>
                        <label class="upload-btn" data-upload="photo">
                            <span data-label>🖼 New image</span>
                            <input type="file" accept="image/jpeg,image/png">
                            <span class="bar"></span>
                        </label>
                        <label class="upload-btn" data-upload="video">
                            <span data-label>🎬 New video</span>
                            <input type="file" accept="video/mp4,video/quicktime,video/webm">
                            <span class="bar"></span>
                        </label>
                        <span class="small muted">Video plays up to <?= $maxSeconds ?>s<?php if (!empty($item['video_path'])): ?> ·
                            <a class="btn-link" href="<?= e(ArFrameService::fileUrl($item['video_path'])) ?>" target="_blank" rel="noopener">current video ↗</a><?php endif; ?></span>
                        <input type="hidden" name="items[<?= $itemId ?>][photo_token]" data-token="photo">
                        <input type="hidden" name="items[<?= $itemId ?>][video_token]" data-token="video">
                        <span class="upload-status" data-status="row" style="flex-basis:100%"></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card-foot">
            <div class="hint">A new image is prepared for scanning when you save, and replaces the old one straight away — print a new copy of the photo if it changed.</div>
            <div class="toolbar">
                <a class="btn btn-ghost" href="<?= url($base . '/content/' . $id) ?>">Cancel</a>
                <button class="btn" type="submit" data-submit>Save changes</button>
            </div>
        </div>
    </div>
    <p class="hint" data-submit-hint style="text-align:right"></p>
</form>
