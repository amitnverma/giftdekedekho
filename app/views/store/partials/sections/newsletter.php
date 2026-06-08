<?php
$newsletter = $sections['newsletter'] ?? [];
if (isset($newsletter['is_active']) && empty($newsletter['is_active'])) return;

$_nlHeading = $newsletter['heading']      ?? 'Get 10% off your first customised gift 🎉';
$_nlDesc    = $newsletter['description']  ?? 'Subscribe for festive offers, new design drops, and gifting inspiration — straight to your inbox.';
$_nlBtn     = $newsletter['button_text']  ?? 'Subscribe';
$_nlHColor  = $newsletter['heading_color']?? '#ffffff';
$_nlTColor  = $newsletter['text_color']   ?? '#ffffff';
$_nlBg      = trim((string)($newsletter['bg_color'] ?? ''));
?>
<section class="section">
  <div class="container">
    <div class="gdd-newsletter reveal"<?= $_nlBg ? ' style="background:' . e($_nlBg) . '"' : '' ?>>
      <div>
        <h3 style="color:<?= e($_nlHColor) ?>"><?= e($_nlHeading) ?></h3>
        <p style="color:<?= e($_nlTColor) ?>"><?= e($_nlDesc) ?></p>
      </div>
      <form id="gddNewsletterForm">
        <input type="email" id="gddNewsletterEmail" placeholder="you@example.com" required>
        <button type="submit" class="btn btn-primary"><?= e($_nlBtn) ?></button>
      </form>
    </div>
    <p id="gddNewsletterMsg" style="text-align:center;margin-top:14px;font-size:14px;font-weight:600"></p>
  </div>
</section>
