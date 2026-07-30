<?php
/**
 * Printable HTML instruction card — the fallback used when FPDF isn't installed
 * (same arrangement as the order invoice). Sized to A5 and set to auto-open the
 * browser's print dialog, so "Save as PDF" from there gives the same result.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Living Photo card — <?= e($frame['slug']) ?></title>
<style>
  @page { size: A5; margin: 12mm; }
  * { box-sizing: border-box; }
  body { margin:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color:#1e1e23; background:#f4f4f6; }
  .card { width: 148mm; min-height: 210mm; margin: 0 auto; padding: 16mm 14mm; background:#fff; text-align:center; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  .sub { color:#787882; font-size: 13px; margin: 0 0 26px; }
  h2 { font-size: 16px; margin: 0 0 10px; }
  p { color:#46464f; font-size: 13px; line-height: 1.65; margin: 0 0 14px; }
  .link { display:block; border:2px solid #e63946; color:#e63946; border-radius:10px;
          padding: 14px 10px; font-size: 15px; font-weight: 700; word-break: break-all; margin: 22px 0; text-decoration:none; }
  ul { text-align:left; color:#6e6e78; font-size: 12px; line-height: 1.8; padding-left: 20px; margin: 0 0 22px; }
  .code { color:#96969f; font-size: 11px; }
  .noprint { margin: 18px auto; text-align:center; }
  .noprint button { padding: 11px 22px; border:0; border-radius:8px; background:#1e1e23; color:#fff; font-size:14px; cursor:pointer; font-family:inherit; }
  @media print { .noprint { display:none; } body { background:#fff; } .card { width:auto; min-height:0; padding:0; } }
</style>
</head>
<body>
<div class="noprint">
  <button type="button" onclick="window.print()">Print / Save as PDF</button>
  <p style="font-size:12px;color:#787882;margin-top:8px">
    Install FPDF (see <code>libs/fpdf/README.txt</code>) to download this as a PDF directly.
  </p>
</div>

<div class="card">
  <h1><?= e($siteName) ?></h1>
  <p class="sub">Your Living Photo</p>

  <h2>This photo plays a video.</h2>
  <p>
    There is no QR code and nothing printed on the photo itself. Open the link below on your phone,
    allow camera access, then point your camera at the photo in the frame. The video starts on its own.
  </p>

  <a class="link" href="<?= e($scanUrl) ?>"><?= e(preg_replace('#^https?://#i', '', $scanUrl)) ?></a>

  <ul>
    <li>Works in Safari on iPhone and Chrome on Android. No app needed.</li>
    <li>Fit the whole photo in the camera view and hold steady.</li>
    <li>Good, even light helps. Avoid glare on the glass.</li>
    <li>Keep this card safe — it is the only place your personal link is written.</li>
  </ul>

  <p class="code">Frame code: <?= e($frame['slug']) ?></p>
</div>

<script>
// Match the PDF path's behaviour: go straight to the print dialog.
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
</script>
</body>
</html>
