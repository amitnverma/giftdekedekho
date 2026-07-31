<?php
/**
 * Quick Create — the counter flow for walk-in customers.
 *
 * Deliberately one screen and no order object: payment is cash/card and happens
 * entirely outside this system. Submitting compiles the target synchronously
 * (about 5 seconds) and drops straight onto the frame's page with the
 * trackability verdict and the live-test button, so the whole thing can be done
 * while the customer waits.
 *
 * The file input uses capture="environment" so a phone or tablet at the counter
 * offers the camera directly.
 */
?>

<?php if (!$compiler['ok']): ?>
    <div class="admin-alert admin-alert-error">
        <strong>Target compiler unavailable — walk-in frames cannot be completed right now.</strong><br>
        <?= e($compiler['message']) ?> See <code>tools/mindar-compile/README.txt</code>.
    </div>
<?php endif; ?>

<div class="admin-callout">
    <strong>Counter flow:</strong> take or upload the photo → paste the video link → save.
    The AR target is built immediately and checked for trackability, then you run the live scan test
    with the customer present. Only hand the frame over once that test passes.
    <br><br>
    No payment is recorded here — cash/card is handled outside this system.
</div>

<div class="admin-card admin-mt" style="max-width:720px">
    <form method="post" action="<?= url('/admin/ar-frames/quick-create') ?>" enctype="multipart/form-data" class="admin-form">
        <?= csrfField() ?>

        <h3 class="admin-card-title">Photo to print</h3>
        <div class="admin-form-row">
            <input type="file" name="photo" id="arQuickPhoto" accept="image/jpeg,image/png" capture="environment" required>
            <p class="admin-help-text">
                JPG or PNG, at least 240×240, max 10MB. Sharp, detailed, high-contrast photos track best —
                plain backgrounds, heavy blur and very dark photos are the usual failures.
            </p>
            <div id="arQuickPreview" style="display:none;margin-top:10px">
                <img alt="Selected photo" style="max-width:260px;max-height:260px;border-radius:10px;border:1px solid var(--admin-border)">
            </div>
        </div>

        <hr class="admin-hr">

        <h3 class="admin-card-title">Video to play</h3>
        <div class="admin-form-row">
            <label class="admin-label-hint">Video source</label>
            <select name="video_type" id="arQuickVideoType">
                <option value="link">Video link (YouTube, Vimeo or direct file)</option>
                <option value="upload">Upload a video file</option>
            </select>
        </div>

        <div class="admin-form-row" data-ar-video="link">
            <label class="admin-label-hint">Video link</label>
            <input type="url" name="video_url" value="<?= old('video_url') ?>" placeholder="https://www.youtube.com/watch?v=…">
            <p class="admin-help-text">
                YouTube, Vimeo, or a direct https link to an .mp4 / .webm / .mov file.
                Check the video is Public or Unlisted — a Private one will not play for the recipient.
            </p>
        </div>

        <div class="admin-form-row" data-ar-video="upload" style="display:none">
            <label class="admin-label-hint">Video file (MP4/MOV/WebM, max 100MB)</label>
            <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm">
        </div>

        <div class="admin-form-row">
            <label class="admin-label-hint">Playback mode</label>
            <select name="playback_mode">
                <option value="fullscreen">Full-screen takeover (recommended)</option>
                <option value="overlay">AR overlay on the photo</option>
            </select>
        </div>

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
                Create frame &amp; build target
            </button>
            <span class="admin-muted" style="font-size:13px;margin-left:10px" id="arQuickHint">Takes about 5 seconds</span>
        </div>
    </form>
</div>

<script>
(function () {
    var typeSelect = document.getElementById('arQuickVideoType');
    function syncVideoRows() {
        document.querySelectorAll('[data-ar-video]').forEach(function (row) {
            row.style.display = row.getAttribute('data-ar-video') === typeSelect.value ? '' : 'none';
        });
    }
    typeSelect.addEventListener('change', syncVideoRows);
    syncVideoRows();

    // Immediate thumbnail so staff can see they grabbed the right photo.
    var fileInput = document.getElementById('arQuickPhoto');
    var preview = document.getElementById('arQuickPreview');
    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) { preview.style.display = 'none'; return; }
        preview.querySelector('img').src = URL.createObjectURL(file);
        preview.style.display = '';
    });

    // Compilation is synchronous — make it obvious the page is working rather
    // than letting someone double-submit and queue a second compile.
    var form = fileInput.closest('form');
    var submit = document.getElementById('arQuickSubmit');
    var hint = document.getElementById('arQuickHint');
    form.addEventListener('submit', function () {
        submit.disabled = true;
        submit.textContent = 'Building AR target…';
        hint.textContent = 'Analysing the photo — this takes a few seconds, please don’t close the page.';
    });
})();
</script>
