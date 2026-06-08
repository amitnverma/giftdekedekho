<?php
$testimonials = $sections['testimonials_section'] ?? [];
if (isset($testimonials['is_active']) && empty($testimonials['is_active']) && empty($testimonials['items'])) return;

$_teStyle  = $testimonials['style'] ?? [];
$testiItems = $testimonials['items'] ?? [
    ['name' => 'Priya Sharma', 'rating' => 5,   'text' => 'The photo frame I customised for my parents\' anniversary was stunning — exactly like the preview! Delivery was quick too.'],
    ['name' => 'Rahul Mehta',  'rating' => 5,   'text' => 'Sent a video-message keychain to my best friend abroad. He scanned the QR and got emotional instantly. Magical experience!'],
    ['name' => 'Ananya Iyer',  'rating' => 4.5, 'text' => 'Beautiful engraving quality on the wooden mug. Packaging was premium and the order tracking kept me updated throughout.'],
];
?>
<section class="section" style="<?= sectionBgStyle($_teStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_teStyle, $testimonials['kicker'] ?? 'Loved By Many', $testimonials['heading'] ?? 'What Our Customers Say', ''); ?>
    <div class="gdd-testi-grid reveal-stagger reveal">
      <?php foreach ($testiItems as $t):
        $tname   = $t['name'] ?? 'Customer';
        $initial = strtoupper(substr($tname, 0, 1));
      ?>
        <div class="gdd-testi-card">
          <div class="quote-mark">&ldquo;</div>
          <div class="stars"><?= starRating((float)($t['rating'] ?? 5)) ?></div>
          <p><?= e($t['text'] ?? '') ?></p>
          <div class="who">
            <div class="avatar"><?= e($initial) ?></div>
            <div><strong><?= e($tname) ?></strong><span>Verified Buyer</span></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
