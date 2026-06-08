<?php
$badges = $sections['trust_badges'] ?? [];
if (empty($badges['is_active']) || empty($badges['items'])) return;

$_tbStyle = $badges['style'] ?? [];
if (empty($_tbStyle['bg_color'])) $_tbStyle['bg_color'] = '#f8f9fb';
?>
<section class="section" style="<?= sectionBgStyle($_tbStyle) ?>">
  <div class="container">
    <div class="gdd-trust-row reveal-stagger reveal">
      <?php foreach ($badges['items'] as $b): ?>
        <div class="gdd-trust-item">
          <div class="ico"><?= e($b['icon'] ?? '✅') ?></div>
          <div><h4><?= e($b['title'] ?? '') ?></h4><p><?= e($b['desc'] ?? '') ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
