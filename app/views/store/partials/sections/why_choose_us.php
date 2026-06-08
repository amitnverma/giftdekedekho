<?php
$whyChoose = $sections['why_choose_us'] ?? [];
if (isset($whyChoose['is_active']) && empty($whyChoose['is_active'])) return;

$_wcStyle  = $whyChoose['style'] ?? [];
$_wcItems  = $whyChoose['items'] ?? [
    ['icon' => '🎨', 'title' => 'Fully Personalised', 'desc' => 'Add names, photos, dates & messages with our live preview customiser.'],
    ['icon' => '📦', 'title' => 'Pan-India Delivery', 'desc' => 'Reliable doorstep delivery across India with real-time order tracking.'],
    ['icon' => '💳', 'title' => 'Secure Payments',    'desc' => 'Razorpay, PayPal, Stripe & Cash on Delivery — pay your way, safely.'],
    ['icon' => '💬', 'title' => 'Friendly Support',   'desc' => 'Real humans ready to help with design tweaks, tracking & returns.'],
];
$_wcCardTitleColor = $whyChoose['card_title_color'] ?? '#1d1d1f';
$_wcCardTextColor  = $whyChoose['card_text_color']  ?? '#6b7280';
$_wcCardAlign      = $whyChoose['card_align']       ?? 'left';
?>
<section class="section" style="<?= sectionBgStyle($_wcStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_wcStyle, $whyChoose['kicker'] ?? 'Why GiftDekeDekho', $whyChoose['heading'] ?? 'Crafted with care, delivered with a smile', $whyChoose['subtext'] ?? 'Every order is handmade-to-order — no two gifts are exactly alike'); ?>
    <div class="gdd-usp-grid reveal-stagger reveal">
      <?php foreach ($_wcItems as $u): ?>
      <div class="gdd-usp-card" style="text-align:<?= e($_wcCardAlign) ?>">
        <div class="ico"><?= e($u['icon'] ?? '✨') ?></div>
        <h4 style="color:<?= e($_wcCardTitleColor) ?>"><?= e($u['title'] ?? '') ?></h4>
        <p style="color:<?= e($_wcCardTextColor) ?>"><?= e($u['desc'] ?? '') ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
