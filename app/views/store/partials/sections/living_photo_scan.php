<?php
/**
 * Home page entry point to the Living Photo scanner.
 *
 * A gift recipient arrives holding a printed frame, not shopping — so the
 * scanner needs to be findable without knowing the code on their card. This
 * links to /scan, which recognises any active frame.
 *
 * Hidden automatically when nothing is scannable yet, so a new shop does not
 * advertise a feature that cannot do anything. Editable from the Design Editor
 * like every other section, but works on defaults with no configuration.
 */
$scan = $sections['living_photo_scan'] ?? [];

// Absent from site_sections means "not configured yet", which should still show
// — only an explicit 0 hides it.
if (array_key_exists('is_active', $scan) && empty($scan['is_active'])) return;

// Nothing to scan? Then there is nothing to promise.
try {
    require_once APP_PATH . '/services/ArFrameService.php';
    $_lpFrames = (new ArFrame())->tableExists()
        ? (new ArFrameService())->scannableFrames()
        : [];
    if (empty($_lpFrames)) return;
} catch (Throwable $e) {
    return;
}

$_lpStyle = $scan['style'] ?? [];
if (empty($_lpStyle['bg_color'])) $_lpStyle['bg_color'] = '#12121a';

$_lpKicker  = $scan['kicker']  ?? 'Living Photo';
$_lpHeading = $scan['heading'] ?? 'Point your camera. Watch it come alive.';
$_lpText    = $scan['text']    ?? 'Got one of our Living Photo frames? Open the scanner, point your camera at the printed photo, and the video plays right there. No app, no QR code — the photo itself is the trigger.';
$_lpCta     = $scan['cta_text'] ?? '📷 Scan your frame';
?>
<section class="section" style="<?= sectionBgStyle($_lpStyle) ?>">
  <div class="container">
    <div class="reveal" style="max-width:760px;margin:0 auto;text-align:center;color:#fff">
      <span class="gdd-kicker" style="color:#ff8a94"><?= e($_lpKicker) ?></span>
      <h2 style="color:#fff;margin:10px 0 14px"><?= e($_lpHeading) ?></h2>
      <p style="color:#b9b9c6;line-height:1.7;margin-bottom:26px"><?= e($_lpText) ?></p>
      <a href="<?= url('/scan') ?>" class="btn btn-primary" style="font-size:16px;padding:14px 32px">
        <?= e($_lpCta) ?>
      </a>
      <p style="color:#7c7c8a;font-size:13px;margin-top:16px">
        Works in Safari on iPhone and Chrome on Android · nothing to install
      </p>
    </div>
  </div>
</section>
