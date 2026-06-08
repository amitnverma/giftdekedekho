<?php
$sigFeature = $sections['signature_feature'] ?? [];
if (isset($sigFeature['is_active']) && empty($sigFeature['is_active'])) return;

$sigKicker  = $sigFeature['kicker']      ?? 'Signature Feature';
$sigHeading = $sigFeature['heading']     ?? 'Turn any gift into a Video &amp; Photo Memory';
$sigDesc    = $sigFeature['description'] ?? 'Attach a scannable QR code to your gift — recipients scan it with any phone camera to unlock a private video or photo message from you. No app required.';
$sigCtaText = $sigFeature['cta_text']    ?? 'Explore Video &amp; Photo Gifts →';
$sigCtaUrl  = $sigFeature['cta_url']     ?? '/category/video-photo-gifts';
$sigSteps   = $sigFeature['steps']       ?? [
    'Upload your video/photo message while placing the order',
    'We generate a unique, secure QR code for your gift',
    'Recipient scans the QR printed on the packaging',
    'Your personal message plays instantly — straight from the heart',
];
$sigImage = !empty($sigFeature['image']) ? asset($sigFeature['image']) : '';
$sigStyle = $sigFeature['style'] ?? [];
$sigKickerStyle = !empty($sigStyle['kicker_color']) ? 'color:' . e($sigStyle['kicker_color']) . ';' : '';
$sigHeadStyle = '';
if (!empty($sigStyle['heading_color'])) $sigHeadStyle .= 'color:' . e($sigStyle['heading_color']) . ';';
if (!empty($sigStyle['heading_size']))  $sigHeadStyle .= 'font-size:' . (int)$sigStyle['heading_size'] . 'px;';
$sigDescStyle = '';
if (!empty($sigStyle['subtext_color'])) $sigDescStyle .= 'color:' . e($sigStyle['subtext_color']) . ';';
if (!empty($sigStyle['subtext_size']))  $sigDescStyle .= 'font-size:' . (int)$sigStyle['subtext_size'] . 'px;';
?>
<section class="section" style="<?= sectionBgStyle($sigStyle) ?>">
  <div class="container">
    <div class="gdd-spotlight reveal">
      <div>
        <span class="gdd-kicker"<?= $sigKickerStyle ? ' style="' . $sigKickerStyle . '"' : '' ?>><?= e($sigKicker) ?></span>
        <h2<?= $sigHeadStyle ? ' style="' . $sigHeadStyle . '"' : '' ?>><?= e($sigHeading) ?></h2>
        <p<?= $sigDescStyle ? ' style="' . $sigDescStyle . '"' : '' ?>><?= e($sigDesc) ?></p>
        <?php if (!empty($sigSteps)): ?>
        <ul class="feat-list">
          <?php foreach ($sigSteps as $idx => $step): ?>
          <li><i><?= $idx + 1 ?></i> <?= e($step) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <a href="<?= url($sigCtaUrl) ?>" class="btn btn-primary"><?= e($sigCtaText) ?></a>
      </div>
      <div class="gdd-spotlight-art">
        <?php if ($sigImage): ?>
          <img src="<?= e($sigImage) ?>" alt="<?= e($sigHeading) ?>" style="width:100%;max-width:340px;border-radius:20px;object-fit:cover">
        <?php else: ?>
          <div class="gdd-phone"><div class="qr"></div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
