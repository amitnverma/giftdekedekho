<?php
/**
 * Printable QR sticker sheet for one Living Photo frame.
 *
 * The sticker is the customer's way in now that the storefront no longer carries
 * a camera button: it is stuck on the physical frame, and scanning it opens this
 * frame's own /scan/{slug} page — the same camera, already pointed at the right
 * target. From there nothing changes: the customer holds the phone up to the
 * photo and the video plays.
 *
 * Sized in millimetres rather than pixels, because the output is a physical
 * label and "40mm wide" is the only measurement that means anything at the
 * printer. Rendered with no admin chrome so what is on screen is what prints.
 */
$isActive = !empty($frame['is_active']);
$isVerified = !empty($frame['verified_at']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR sticker — <?= e($frame['slug']) ?></title>
<style>
  @page { size: A4; margin: 10mm; }
  * { box-sizing: border-box; }
  body { margin:0; padding:0 0 40px; background:#f4f4f6; color:#1e1e23;
         font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  /* ---- on-screen controls ---- */
  .panel { max-width: 760px; margin: 0 auto; padding: 22px 20px 0; }
  .panel h1 { font-size: 19px; margin: 0 0 4px; }
  .panel .sub { color:#787882; font-size: 13px; margin: 0 0 18px; }
  .panel a { color:#e63946; }
  .box { background:#fff; border:1px solid #e4e4ea; border-radius:12px; padding:16px 18px; margin-bottom:16px; }
  .row { display:flex; gap:22px; flex-wrap:wrap; align-items:flex-end; }
  .field { display:flex; flex-direction:column; gap:6px; }
  .field > span { font-size:12px; font-weight:700; color:#6e6e78; text-transform:uppercase; letter-spacing:.03em; }
  .sizes { display:flex; gap:6px; }
  .sizes button { padding:8px 13px; border:1px solid #d5d5dd; background:#fff; border-radius:8px;
                  font-size:13px; cursor:pointer; font-family:inherit; }
  .sizes button[aria-pressed="true"] { background:#1e1e23; color:#fff; border-color:#1e1e23; }
  input[type=number] { width:82px; padding:8px 10px; border:1px solid #d5d5dd; border-radius:8px;
                       font-size:14px; font-family:inherit; }
  label.check { display:flex; gap:8px; align-items:center; font-size:13px; color:#46464f; padding-bottom:9px; }
  .print { padding:11px 24px; border:0; border-radius:8px; background:#e63946; color:#fff;
           font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; }
  .note { font-size:12.5px; color:#6e6e78; line-height:1.7; margin:14px 0 0; }
  .alert { border-radius:10px; padding:12px 14px; font-size:13px; line-height:1.6; margin-bottom:16px; }
  .alert-warn { background:#fff6e0; border:1px solid #f0d089; color:#6d5100; }
  .alert-error { background:#fdecee; border:1px solid #f3bfc5; color:#8c1a26; }
  code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px; }

  /* ---- the stickers ---- */
  .sheet { background:#fff; padding:10mm; margin:0 auto 30px; max-width:210mm;
           display:flex; flex-wrap:wrap; gap:5mm; align-content:flex-start; }
  /* flex: 0 0 auto — a sticker is a physical measurement. A narrow window must
     wrap the sheet, never shrink 40mm into something that prints at 30mm. */
  .sticker { --w: 40;
             flex: 0 0 auto;
             width: calc(var(--w) * 1mm);
             padding: calc(var(--w) * 0.04 * 1mm);
             border: 0.3mm dashed #b8b8c2;
             border-radius: calc(var(--w) * 0.05 * 1mm);
             text-align:center; background:#fff; color:#000;
             break-inside: avoid; page-break-inside: avoid; }
  .sticker img { display:block; width:100%; height:auto;
                 image-rendering: pixelated; image-rendering: crisp-edges; }
  .cap { margin-top: calc(var(--w) * 0.035 * 1mm); line-height:1.25; }
  /* Type scales with the sticker but stops at a legibility floor — below about
     5pt a laser printer stops resolving it, so the small sizes trade a taller
     caption for text that can still be read. */
  .cap b { display:block; letter-spacing:.04em;
           font-size: max(2mm, calc(var(--w) * 0.058 * 1mm)); }
  .cap span { display:block; color:#3a3a42;
              font-size: max(1.7mm, calc(var(--w) * 0.042 * 1mm));
              margin-top: calc(var(--w) * 0.018 * 1mm); }
  .cap em { display:block; font-style:normal; color:#82828c;
            font-size: max(1.6mm, calc(var(--w) * 0.036 * 1mm));
            margin-top: calc(var(--w) * 0.022 * 1mm);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

  @media print {
    body { background:#fff; padding:0; }
    .panel { display:none; }
    .sheet { padding:0; margin:0; max-width:none; }
  }
</style>
</head>
<body>

<div class="panel">
  <h1>QR sticker · <code><?= e($frame['slug']) ?></code></h1>
  <p class="sub">
    Print, cut along the dashed guide and stick one on the frame.
    Scanning it opens <a href="<?= e($scanUrl) ?>" target="_blank"><?= e(preg_replace('#^https?://#i', '', $scanUrl)) ?></a> —
    the camera page for this frame.
  </p>

  <?php if ($qr === null): ?>
    <div class="alert alert-error">
      <strong>The QR code could not be generated.</strong>
      This server has no local QR library and could not reach the fallback service.
      Install phpqrcode — see <code>libs/phpqrcode/README.txt</code> — then reload this page.
    </div>
  <?php endif; ?>

  <?php if (!$isActive): ?>
    <div class="alert alert-error">
      <strong>This frame's scan link is switched off.</strong>
      Anyone scanning this sticker will get a "no longer available" page. Turn
      <em>Scan link active</em> back on before sticking it to anything.
    </div>
  <?php elseif (!$isVerified): ?>
    <div class="alert alert-warn">
      <strong>This frame has not passed the live scan test yet.</strong>
      The sticker will open the camera, but nobody has confirmed the video actually fires.
      Run the test first if you can.
    </div>
  <?php endif; ?>

  <div class="box">
    <div class="row">
      <div class="field">
        <span>Sticker width</span>
        <div class="sizes" id="sizes">
          <button type="button" data-w="25">25 mm</button>
          <button type="button" data-w="30">30 mm</button>
          <button type="button" data-w="40" aria-pressed="true">40 mm</button>
          <button type="button" data-w="50">50 mm</button>
        </div>
      </div>
      <label class="field">
        <span>Copies</span>
        <input type="number" id="copies" value="6" min="1" max="60" step="1">
      </label>
      <label class="check">
        <input type="checkbox" id="withText" checked>
        Include instruction text
      </label>
      <div class="field">
        <button type="button" class="print" onclick="window.print()">Print / Save as PDF</button>
      </div>
    </div>
    <p class="note">
      <strong id="measured">&nbsp;</strong><br>
      Untick the text for the smallest possible label — the code alone still works.
      At 25&nbsp;mm keep the print quality high; below that phones start to struggle.
      <br>
      <a href="<?= e($backUrl) ?>">← Back to the frame</a>
    </p>
  </div>
</div>

<div class="sheet" id="sheet"></div>

<script>
// Build the sheet client-side so size and copies change without a round trip —
// the QR image itself is fixed, only its printed dimensions vary.
(function () {
  var qr = <?= json_encode($qr, JSON_UNESCAPED_SLASHES) ?>;
  var slug = <?= json_encode($frame['slug']) ?>;
  var sheet = document.getElementById('sheet');
  var sizes = document.getElementById('sizes');
  var copies = document.getElementById('copies');
  var withText = document.getElementById('withText');
  var measured = document.getElementById('measured');
  var width = 40;

  // The width is chosen; the height falls out of the caption. Report the finished
  // size so whoever is buying label stock knows what has to fit on it.
  //
  // Height is only real once the QR has been laid out, so this polls rather than
  // awaiting img.decode() — decode() never settles while the tab is in the
  // background, which would leave the readout blank with nothing to show for it.
  function reportSize(attemptsLeft) {
    var el = sheet.firstChild;
    if (!el) return;

    var img = el.querySelector('img');
    if (img && img.getBoundingClientRect().height === 0 && attemptsLeft > 0) {
      setTimeout(function () { reportSize(attemptsLeft - 1); }, 60);
      return;
    }

    var box = el.getBoundingClientRect();
    var mm = 96 / 25.4;   // CSS defines 1mm as exactly this many px
    measured.textContent = 'Each sticker prints at about '
      + Math.round(box.width / mm) + ' × ' + Math.round(box.height / mm) + ' mm.';
  }

  function render() {
    var n = Math.max(1, Math.min(60, parseInt(copies.value, 10) || 1));
    sheet.innerHTML = '';
    for (var i = 0; i < n; i++) {
      var el = document.createElement('div');
      el.className = 'sticker';
      el.style.setProperty('--w', width);

      if (qr) {
        var img = document.createElement('img');
        img.src = qr;
        img.alt = 'Scan code for ' + slug;
        el.appendChild(img);
      }

      if (withText.checked) {
        var cap = document.createElement('div');
        cap.className = 'cap';

        var b = document.createElement('b');
        b.textContent = 'SCAN ME';
        cap.appendChild(b);

        var span = document.createElement('span');
        span.textContent = 'then point your camera at the photo';
        cap.appendChild(span);

        var em = document.createElement('em');
        em.textContent = slug;
        cap.appendChild(em);

        el.appendChild(cap);
      }

      sheet.appendChild(el);
    }

    reportSize(20);
  }

  sizes.addEventListener('click', function (event) {
    var button = event.target.closest('button[data-w]');
    if (!button) return;
    width = parseInt(button.dataset.w, 10);
    Array.prototype.forEach.call(sizes.querySelectorAll('button'), function (other) {
      other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
    });
    render();
  });

  copies.addEventListener('input', render);
  withText.addEventListener('change', render);
  render();
})();
</script>
</body>
</html>
