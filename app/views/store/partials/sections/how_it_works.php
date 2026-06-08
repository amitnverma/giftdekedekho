<?php
$howItWorks = $sections['how_it_works'] ?? [];
if (isset($howItWorks['is_active']) && empty($howItWorks['is_active'])) return;

$_hwStyle  = $howItWorks['style'] ?? [];
$_hwItems  = $howItWorks['items'] ?? [
    ['title' => 'Pick a Gift',         'desc' => 'Browse frames, mugs, jewellery, cushions & more.'],
    ['title' => 'Personalise It',      'desc' => 'Add photos, names, engravings or a video message.'],
    ['title' => 'We Craft & Pack',     'desc' => 'Our artisans make it by hand and pack it with care.'],
    ['title' => 'You Receive & Smile', 'desc' => 'Track your order and get it delivered to your door.'],
];
$_hwTitleColor = $howItWorks['card_title_color'] ?? '#1d1d1f';
$_hwTextColor  = $howItWorks['card_text_color']  ?? '#6b7280';
?>
<section class="section" style="<?= sectionBgStyle($_hwStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_hwStyle, $howItWorks['kicker'] ?? 'Simple Process', $howItWorks['heading'] ?? 'From idea to doorstep in 4 easy steps', $howItWorks['subtext'] ?? ''); ?>
    <div class="gdd-steps reveal-stagger reveal">
      <?php foreach ($_hwItems as $s): ?>
      <div class="gdd-step">
        <div class="num"></div>
        <h4 style="color:<?= e($_hwTitleColor) ?>"><?= e($s['title'] ?? '') ?></h4>
        <p style="color:<?= e($_hwTextColor) ?>"><?= e($s['desc'] ?? '') ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
