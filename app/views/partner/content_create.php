<?php
/**
 * New single / new album.
 *
 * Files upload one at a time as soon as they are picked (partner.js), so the
 * final submit carries only tokens. The price and the "not enough credits"
 * warning are worked out in the browser from the same numbers the server
 * charges with; the server re-quotes and is the one that decides.
 */
$isAlbum = $kind === 'album';
$defaultDuration = isset($durationPrices[30]) ? 30 : (int)array_key_first($durationPrices);
$oldValidity = $_SESSION['_old']['validity'] ?? '';
$defaultValidity = isset($validityPrices[$oldValidity]) ? $oldValidity : (isset($validityPrices['5y']) ? '5y' : (string)array_key_first($validityPrices));
$defaultTitle = ($isAlbum ? 'Album' : 'Single Frame') . ' - ' . date('d M Y, h:i A');
$title = trim((string)($_SESSION['_old']['title'] ?? '')) ?: $defaultTitle;
unset($_SESSION['_old']);

$config = [
    'kind'           => $kind,
    'base'           => (int)$partner['base_credits'],
    'balance'        => (int)$partner['credit_balance'],
    'durationPrices' => (object)$durationPrices,
    'validityPrices' => (object)$validityPrices,
    'maxPages'       => (int)($maxPages ?? 0),   // 0 = no limit
    'maxVideoBytes'  => $maxVideoMb * 1024 * 1024,
    'maxPhotoBytes'  => ArFrameService::MAX_PHOTO_BYTES,
    'uploadUrl'      => url($base . '/upload'),
];

$defaultMode = 'fullscreen';

$durationOption = function (int $seconds, int $price): string {
    return $seconds . 's' . ($price > 0 ? ' (+' . number_format($price) . ' credits)' : '');
};
?>

<?php if (!$compiler): ?>
    <div class="banner banner-danger">
        <p><strong>AR processing is temporarily unavailable.</strong> Content you create now is saved and charged, but its
            photos cannot be prepared until this is fixed. Please contact <?= e($brand['poweredBy']) ?> before creating content.</p>
    </div>
<?php endif; ?>

<form class="p-narrow" method="post" action="<?= url($base . ($isAlbum ? '/albums/create' : '/singles/create')) ?>" data-create-form>
    <?= csrfField() ?>
    <script type="application/json" data-create-config><?= json_encode($config, JSON_UNESCAPED_SLASHES) ?></script>

    <div class="banner banner-danger" data-short hidden>
        <p><strong>You can't create this <?= $isAlbum ? 'album' : 'item' ?> yet.</strong> <span data-short-text></span>
            <a href="<?= url($base . '/credits') ?>">Add credits</a></p>
    </div>

    <?php if ($packs): ?>
        <div class="recharge">
            <p class="recharge-title">⚡ Recharge Credits</p>
            <div class="recharge-packs">
                <?php foreach ($packs as $pack): ?>
                    <a href="<?= url($base . '/credits') ?>">
                        <strong><?= e(GDD_CURRENCY_SYMBOL . number_format($pack['price'])) ?></strong>
                        <small>→ <?= e(GDD_CURRENCY_SYMBOL . number_format(ArPartnerService::packItemPrice($pack, $partner), 2)) ?>/item</small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="<?= $isAlbum ? 'grid-2' : '' ?>">
                <div class="field">
                    <label for="customer">Customer *</label>
                    <select id="customer" name="customer_id" required data-customer-select>
                        <option value="">— Select customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $preselect === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="new">+ New customer…</option>
                    </select>
                </div>
                <div class="field">
                    <label for="title"><?= $isAlbum ? 'Album Title' : 'Title' ?></label>
                    <input type="text" id="title" name="title" value="<?= e($title) ?>" maxlength="160">
                </div>
            </div>
            <div class="grid-2" data-new-customer hidden>
                <div class="field">
                    <label for="nc-name">New customer's name *</label>
                    <input type="text" id="nc-name" name="new_customer_name" maxlength="120">
                </div>
                <div class="field">
                    <label for="nc-phone">Mobile</label>
                    <input type="tel" id="nc-phone" name="new_customer_phone" maxlength="20">
                </div>
            </div>

            <?php if (!$isAlbum): ?>
                <div data-page>
                    <div class="field">
                        <span class="field-label">Target Image * <span class="muted">— the photo that will be printed and scanned (JPG or PNG)</span></span>
                        <div class="upload">
                            <label class="upload-btn" data-upload="photo">
                                <span data-label>Choose file</span>
                                <input type="file" accept="image/jpeg,image/png">
                                <span class="bar"></span>
                            </label>
                            <span class="upload-status" data-status="photo">No file chosen</span>
                        </div>
                        <input type="hidden" name="pages[0][photo_token]" data-token="photo">
                    </div>
                    <div class="field">
                        <span class="field-label">Video * <span class="muted">(plays up to the duration selected below; MP4, MOV or WebM, max <?= (int)$maxVideoMb ?>MB)</span></span>
                        <div class="upload">
                            <label class="upload-btn" data-upload="video">
                                <span data-label>Choose file</span>
                                <input type="file" accept="video/mp4,video/quicktime,video/webm">
                                <span class="bar"></span>
                            </label>
                            <span class="upload-status" data-status="video">No file chosen</span>
                        </div>
                        <input type="hidden" name="pages[0][video_token]" data-token="video">
                    </div>
                </div>
            <?php endif; ?>

            <div class="<?= $isAlbum ? 'grid-2' : '' ?>">
                <div class="field">
                    <span class="field-label">
                        <?= $isAlbum ? 'Default Video Duration <span class="muted">(for new AR contents — each can be changed below)</span>' : 'Video Duration' ?>
                    </span>
                    <div class="chips">
                        <?php foreach ($durationPrices as $seconds => $price): ?>
                            <label class="chip">
                                <input type="radio" name="<?= $isAlbum ? 'default_duration' : 'duration' ?>" value="<?= (int)$seconds ?>"
                                    <?= $seconds === $defaultDuration ? 'checked' : '' ?> data-duration-choice>
                                <span><?= (int)$seconds ?>s<?php if ($price > 0): ?> <small>(+<?= number_format($price) ?> credits)</small><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="field">
                    <span class="field-label"><?= $isAlbum ? 'Album Active For' : 'Active For' ?></span>
                    <div class="chips">
                        <?php foreach ($validityPrices as $key => $price): ?>
                            <label class="chip">
                                <input type="radio" name="validity" value="<?= e($key) ?>" <?= $key === $defaultValidity ? 'checked' : '' ?> data-validity-choice>
                                <span><?= e(ArPartner::validityLabel($key)) ?><?php if ($price > 0): ?> <small>(+<?= number_format($price) ?> credits)</small><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="field" style="margin-bottom:0">
                <span class="field-label">
                    <?= $isAlbum ? 'Default Playback Mode <span class="muted">(for new AR contents — each can be changed below)</span>' : 'Playback Mode' ?>
                </span>
                <div class="chips">
                    <?php foreach ($playbackModes as $key => $label): ?>
                        <label class="chip">
                            <input type="radio" name="<?= $isAlbum ? 'default_playback_mode' : 'playback_mode' ?>" value="<?= e($key) ?>" <?= $key === $defaultMode ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="hint" style="margin:6px 0 0">Full screen opens the video over the whole phone screen once the photo is found. On the photo plays it inside the printed photo, moving with it.</p>
            </div>
        </div>

        <?php if ($isAlbum): ?>
            <div class="card-head" style="border-top:1px solid var(--line)">
                <h2>AR Contents <span class="tag tag-off" data-page-count>1</span></h2>
                <button type="button" class="btn-link" data-add-page>+ Add AR Content</button>
            </div>
            <div class="pages-list" data-pages></div>
            <template data-page-template>
                <div class="page-row" data-page>
                    <span class="n" data-n>1</span>
                    <input class="input" type="text" name="pages[__i__][title]" maxlength="120" aria-label="Content title" data-title>
                    <select class="input page-dur" name="pages[__i__][duration]" aria-label="Video duration" data-page-duration>
                        <?php foreach ($durationPrices as $seconds => $price): ?>
                            <option value="<?= (int)$seconds ?>"><?= e($durationOption((int)$seconds, (int)$price)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="input page-mode" name="pages[__i__][playback_mode]" aria-label="Playback mode" data-page-mode>
                        <?php foreach ($playbackModes as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="upload-btn" data-upload="photo">
                        <span data-label>🖼 Image *</span>
                        <input type="file" accept="image/jpeg,image/png">
                        <span class="bar"></span>
                    </label>
                    <label class="upload-btn" data-upload="video">
                        <span data-label>🎬 Video *</span>
                        <input type="file" accept="video/mp4,video/quicktime,video/webm">
                        <span class="bar"></span>
                    </label>
                    <button type="button" class="icon-btn" data-remove-page aria-label="Remove">×</button>
                    <input type="hidden" name="pages[__i__][photo_token]" data-token="photo">
                    <input type="hidden" name="pages[__i__][video_token]" data-token="video">
                    <div class="row-status" data-status="row"></div>
                </div>
            </template>
        <?php endif; ?>

        <div class="card-foot">
            <div>
                <strong data-price-text>…</strong>
                <div class="hint">You can edit this <?= $isAlbum ? 'album' : 'content' ?> within <?= (int)$partner['edit_window_days'] ?> days of creation.</div>
            </div>
            <div class="toolbar">
                <a class="btn btn-ghost" href="<?= url($base . ($isAlbum ? '/albums' : '/singles')) ?>">Cancel</a>
                <button class="btn" type="submit" data-submit><?= $isAlbum ? 'Create Album' : 'Upload &amp; Create' ?></button>
            </div>
        </div>
    </div>
    <p class="hint" data-submit-hint style="text-align:right"></p>
</form>
