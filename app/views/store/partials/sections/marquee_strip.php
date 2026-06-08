<?php
$marqueeSection = $sections['marquee_strip'] ?? [];
if (isset($marqueeSection['is_active']) && empty($marqueeSection['is_active'])) return;

$_mText   = $marqueeSection['text']        ?? '🎁 PERSONALISED PHOTO FRAMES <em>•</em> ENGRAVED JEWELLERY <em>•</em> CUSTOM MUGS &amp; CUSHIONS <em>•</em> VIDEO &amp; PHOTO QR GIFTS <em>•</em> SAME-DAY DISPATCH <em>•</em> COD AVAILABLE <em>•</em>';
$_mBg     = $marqueeSection['bg_color']    ?? '';
$_mColor  = $marqueeSection['text_color']  ?? '';
$_mSize   = $marqueeSection['font_size']   ?? '14';
$_mWeight = $marqueeSection['font_weight'] ?? '700';
$_mSpeed  = $marqueeSection['speed']       ?? '26';
$_mStyle  = '';
if ($_mBg)    $_mStyle .= "background:{$_mBg};";
if ($_mColor) $_mStyle .= "color:{$_mColor};";
?>
<div class="gdd-marquee" aria-hidden="true" <?= $_mStyle ? 'style="' . e($_mStyle) . '"' : '' ?>>
  <div class="gdd-marquee-track" style="animation-duration:<?= (int)$_mSpeed ?>s;font-size:<?= (int)$_mSize ?>px;font-weight:<?= e($_mWeight) ?>;">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span><?= $_mText ?></span>
    <?php endfor; ?>
  </div>
</div>
