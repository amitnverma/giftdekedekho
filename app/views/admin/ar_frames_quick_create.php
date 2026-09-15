<?php
/**
 * Quick Create — the counter flow for walk-in customers.
 *
 * Deliberately one screen and no order object: payment is cash/card and happens
 * entirely outside this system. Submitting compiles every photo's target
 * synchronously (about 5 seconds each) and drops straight onto the frame's page
 * with the trackability verdict and the live-test steps, so the whole thing can
 * be done while the customer waits.
 *
 * One frame can hold several photos, each with its own video, all behind one
 * QR sticker. Rows are added in the browser from a template; the server ignores
 * any row left completely blank.
 *
 * The file inputs use capture="environment" so a phone or tablet at the counter
 * offers the camera directly.
 */

/** One photo + video row. $key is the array index, or a placeholder inside the template. */
$itemRow = function (string $key) {
    ?>
    <div class="admin-card" data-item-row style="margin-bottom:14px;box-shadow:none;border:1px solid var(--admin-border)">
        <div class="admin-flex-between" style="margin-bottom:8px">
            <h3 class="admin-card-title" style="margin:0" data-item-title>Photo</h3>
            <button type="button" class="admin-btn admin-btn-sm" data-remove-item>Remove</button>
        </div>

        <div class="admin-form-row">
            <label class="admin-label-hint">Photo to print</label>
            <input type="file" name="item_photo[<?= $key ?>]" accept="image/jpeg,image/png" capture="environment" data-photo-input>
            <div data-photo-preview style="display:none;margin-top:10px">
                <img alt="Selected photo" style="max-width:200px;max-height:200px;border-radius:10px;border:1px solid var(--admin-border)">
            </div>
        </div>

        <div class="admin-form-row">
            <label class="admin-label-hint">Video it plays</label>
            <select name="items[<?= $key ?>][video_type]" data-video-type>
                <option value="link">Video link (YouTube, Vimeo or direct file)</option>
                <option value="upload">Upload a video file</option>
            </select>
        </div>

        <div class="admin-form-row" data-ar-video="link">
            <input type="url" name="items[<?= $key ?>][video_url]" placeholder="https://www.youtube.com/watch?v=…">
            <p class="admin-help-text">
                YouTube, Vimeo, or a direct https link to an .mp4 / .webm / .mov file.
                Check the video is Public or Unlisted — a Private one will not play for the recipient.
            </p>
        </div>

        <div class="admin-form-row" data-ar-video="upload" style="display:none">
            <label class="admin-label-hint">Video file (MP4/MOV/WebM, max 100MB)</label>
            <input type="file" name="item_video[<?= $key ?>]" accept="video/mp4,video/quicktime,video/webm">
        </div>

        <div class="admin-form-row" style="margin-bottom:0">
            <label class="admin-label-hint">Playback mode</label>
            <select name="items[<?= $key ?>][playback_mode]">
                <option value="fullscreen">Full-screen takeover (recommended)</option>
                <option value="overlay">AR overlay on the photo</option>
            </select>
        </div>
    </div>
    <?php
};
?>

<?php if (!$compiler['ok']): ?>
    <div class="admin-alert admin-alert-error">
        <strong>Target compiler unavailable — walk-in frames cannot be completed right now.</strong><br>
        <?= e($compiler['message']) ?> See <code>tools/mindar-compile/README.txt</code>.
    </div>
<?php endif; ?>

<div class="admin-callout">
    <strong>Counter flow:</strong> take or upload each photo → paste the video it should play → save.
    Every photo shares one QR sticker; scanning a photo plays its own video. The AR targets are built
    immediately and checked for trackability, then you run the live scan test on every photo with the
    customer present. Only hand the frame over once that test passes.
    <br><br>
    No payment is recorded here — cash/card is handled outside this system.
</div>

<div class="admin-card admin-mt" style="max-width:720px">
    <form method="post" action="<?= url('/admin/ar-frames/quick-create') ?>" enctype="multipart/form-data" class="admin-form" id="arQuickForm">
        <?= csrfField() ?>

        <h3 class="admin-card-title">Photos &amp; videos</h3>
        <p class="admin-help-text" style="margin-top:0">
            JPG or PNG, at least 240×240, max 10MB each. Sharp, detailed, high-contrast photos track best —
            plain backgrounds, heavy blur and very dark photos are the usual failures. Photos that look alike
            (the same people, the same background) are harder to tell apart, so choose visibly different ones.
        </p>

        <div id="arItems"><?php $itemRow('0'); ?></div>

        <template id="arItemTemplate"><?php $itemRow('__KEY__'); ?></template>

        <button type="button" class="admin-btn" id="arAddItem">+ Add another photo</button>
        <p class="admin-help-text">
            Up to <?= (int)$maxItems ?> photos per frame. Uploaded video files are sent together in one save, and the
            server accepts <?= e($uploadLimit) ?> in total — for several videos, links are the safer choice.
        </p>

        <hr class="admin-hr">

        <h3 class="admin-card-title">Customer reference <span class="admin-muted" style="font-weight:400;font-size:13px">(optional)</span></h3>
        <div class="admin-form-row">
            <label class="admin-label-hint">Name</label>
            <input type="text" name="customer_name" maxlength="120" value="<?= old('customer_name') ?>" placeholder="For your own records">
        </div>
        <div class="admin-form-row">
            <label class="admin-label-hint">Phone</label>
            <input type="text" name="customer_phone" maxlength="15" value="<?= old('customer_phone') ?>" placeholder="In case you need to reach them later">
        </div>
        <div class="admin-form-row">
            <label class="admin-label-hint">Notes</label>
            <textarea name="notes" rows="2" placeholder="Frame size, occasion, anything worth remembering"><?= old('notes') ?></textarea>
        </div>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn-primary" type="submit" id="arQuickSubmit" <?= $compiler['ok'] ? '' : 'disabled' ?>>
                Create frame &amp; build targets
            </button>
            <span class="admin-muted" style="font-size:13px;margin-left:10px" id="arQuickHint">About 5 seconds per photo</span>
        </div>
    </form>
</div>

<script>
(function () {
    var maxItems = <?= (int)$maxItems ?>;
    var list = document.getElementById('arItems');
    var template = document.getElementById('arItemTemplate');
    var addButton = document.getElementById('arAddItem');
    // Array keys only need to be unique, not consecutive, so a removed row
    // never has to renumber the others.
    var nextKey = 1;

    function rows() { return list.querySelectorAll('[data-item-row]'); }

    function renumber() {
        var all = rows();
        all.forEach(function (row, index) {
            row.querySelector('[data-item-title]').textContent = all.length > 1 ? 'Photo ' + (index + 1) : 'Photo';
            row.querySelector('[data-remove-item]').style.display = all.length > 1 ? '' : 'none';
        });
        addButton.disabled = all.length >= maxItems;
    }

    function wire(row) {
        var select = row.querySelector('[data-video-type]');
        function syncVideo() {
            row.querySelectorAll('[data-ar-video]').forEach(function (field) {
                field.style.display = field.getAttribute('data-ar-video') === select.value ? '' : 'none';
            });
        }
        select.addEventListener('change', syncVideo);
        syncVideo();

        // Immediate thumbnail so staff can see they grabbed the right photo.
        var input = row.querySelector('[data-photo-input]');
        var preview = row.querySelector('[data-photo-preview]');
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { preview.style.display = 'none'; return; }
            preview.querySelector('img').src = URL.createObjectURL(file);
            preview.style.display = '';
        });

        row.querySelector('[data-remove-item]').addEventListener('click', function () {
            row.remove();
            renumber();
        });
    }

    addButton.addEventListener('click', function () {
        if (rows().length >= maxItems) return;
        var html = template.innerHTML.replace(/__KEY__/g, String(nextKey++));
        var holder = document.createElement('div');
        holder.innerHTML = html.trim();
        var row = holder.firstElementChild;
        list.appendChild(row);
        wire(row);
        renumber();
    });

    rows().forEach(wire);
    renumber();

    // Compilation is synchronous — make it obvious the page is working rather
    // than letting someone double-submit and queue a second compile.
    var form = document.getElementById('arQuickForm');
    var submit = document.getElementById('arQuickSubmit');
    var hint = document.getElementById('arQuickHint');
    form.addEventListener('submit', function () {
        var count = rows().length;
        submit.disabled = true;
        submit.textContent = count > 1 ? 'Building ' + count + ' AR targets…' : 'Building AR target…';
        hint.textContent = 'Analysing the photos — this takes a few seconds each, please don’t close the page.';
    });
})();
</script>
