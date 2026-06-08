<?php
$igGallery = $sections['instagram_gallery'] ?? [];
if (isset($igGallery['is_active']) && empty($igGallery['is_active'])) return;

$_igStyle = $igGallery['style'] ?? [];
if (empty($_igStyle['bg_color'])) $_igStyle['bg_color'] = '#f8f9fb';

$igItems   = $igGallery['items'] ?? [];
$hasPhotos = !empty(array_filter(array_column($igItems, 'image')));
?>
<section class="section" style="<?= sectionBgStyle($_igStyle) ?>">
  <div class="container">
    <?php renderSectionHeading(
        $_igStyle,
        $igGallery['kicker']  ?? '#GiftDekeDekhoMoments',
        $igGallery['heading'] ?? 'Real gifts, real smiles',
        $igGallery['subtext'] ?? 'Tag @giftdekedekho on Instagram for a chance to be featured here'
    ); ?>
    <div class="gdd-gallery-grid reveal-stagger reveal">
      <?php if ($hasPhotos): ?>
        <?php foreach ($igItems as $idx => $item):
          if (empty($item['image'])) continue; ?>
          <a href="<?= e(!empty($item['link']) ? $item['link'] : '#') ?>" <?= !empty($item['link']) ? 'target="_blank" rel="noopener"' : '' ?> title="<?= e($item['caption'] ?? '') ?>">
            <img src="<?= e(asset($item['image'])) ?>" alt="<?= e($item['caption'] ?? 'Gallery photo ' . ($idx + 1)) ?>" loading="lazy">
            <?php if (!empty($item['caption'])): ?>
              <span class="tag">📷 <?= e($item['caption']) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <a href="#" title="Customer moment #<?= $i ?>">
            <img src="<?= e(asset('/images/GDKD logo.png')) ?>" alt="Gallery placeholder <?= $i ?>" loading="lazy" style="object-fit:contain;background:#fff;padding:30px">
            <span class="tag">📷 Placeholder #<?= $i ?></span>
          </a>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
