<?php
$hero  = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];

$heroHeadline  = $hero['headline']    ?? '';
$heroSub       = $hero['subheadline'] ?? 'Photo frames, engraved keepsakes, custom mugs & video-message gifts — designed by you, crafted by us, delivered with love anywhere in India.';
$heroCtaUrl    = $hero['cta_url']     ?? url('/category/all');
$heroCtaText   = $hero['cta_text']    ?? 'Start Customising';
$heroLeftPhoto  = !empty($hero['transform_left_photo'])  ? asset($hero['transform_left_photo'])  : asset('/images/heroleft.png');
$heroRightPhoto = !empty($hero['transform_right_photo']) ? asset($hero['transform_right_photo']) : asset('/images/heroright.png');

if (isset($hero['is_active']) && empty($hero['is_active'])) return;
?>
<section class="gdd-ht">
  <div class="gdd-ht-text">
    <div class="gdd-ht-text-inner">
      <?php if (!empty($promo['is_active'])): ?>
      <span class="gdd-ht-eyebrow"><?= e($promo['text'] ?? '✨ Perfect Gifting Made Simple') ?></span>
      <?php endif; ?>
      <?php if ($heroHeadline !== ''): ?>
        <h1><?= e($heroHeadline) ?></h1>
      <?php else: ?>
        <h1>Turn your photos into<br><em class="gdd-ht-grad">unforgettable gifts</em></h1>
      <?php endif; ?>
      <p class="gdd-ht-lead"><?= e($heroSub) ?></p>
      <div class="gdd-ht-ctas">
        <a href="<?= e($heroCtaUrl) ?>" class="btn btn-primary"><?= e($heroCtaText) ?> →</a>
        <a href="<?= url('/category/video-photo-gifts') ?>" class="btn btn-outline">🎬 Video &amp; QR Gifts</a>
      </div>
      <div class="gdd-hero-stats">
        <div><strong><span data-count-to="50" data-suffix="K+">50K+</span></strong><span>Happy customers</span></div>
        <div><strong><span data-count-to="4.8" data-suffix="★">4.8★</span></strong><span>Average rating</span></div>
        <div><strong><span data-count-to="500" data-suffix="+">500+</span></strong><span>Gift designs</span></div>
      </div>
    </div>
  </div>
  <div class="gdd-ht-showcase">
    <span class="gdd-ht-corner tl"></span>
    <span class="gdd-ht-corner tr"></span>
    <span class="gdd-ht-corner bl"></span>
    <span class="gdd-ht-corner br"></span>
    <div class="gdd-ht-plain">
      <img src="<?= e($heroLeftPhoto) ?>" alt="Before personalisation" loading="eager">
    </div>
    <div class="gdd-ht-reveal">
      <img src="<?= e($heroRightPhoto) ?>" alt="After personalisation" loading="eager">
    </div>
    <div class="gdd-ht-chip chip-photo">📸 Add your photo</div>
    <div class="gdd-ht-chip chip-done">✨ Personalised!</div>
    <div class="gdd-ht-divider" aria-hidden="true">
      <div class="gdd-ht-handle">
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
          <path d="M7 11 L2 11 M2 11 L5 8 M2 11 L5 14" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M15 11 L20 11 M20 11 L17 8 M20 11 L17 14" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>
  </div>
</section>
