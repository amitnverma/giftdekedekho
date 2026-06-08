<?php
$hero = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];
$featuredSection = $sections['featured_products_section'] ?? [];
$testimonials = $sections['testimonials_section'] ?? [];
$badges = $sections['trust_badges'] ?? [];
$igGallery = $sections['instagram_gallery'] ?? [];
$sigFeature = $sections['signature_feature'] ?? [];
$marqueeSection = $sections['marquee_strip'] ?? [];
$catSection = $sections['shop_by_category'] ?? [];
$whyChoose  = $sections['why_choose_us'] ?? [];
$howItWorks = $sections['how_it_works'] ?? [];
$newsletter = $sections['newsletter'] ?? [];

$heroHeadline = $hero['headline'] ?? '';
$heroSub = $hero['subheadline'] ?? 'Photo frames, engraved keepsakes, custom mugs & video-message gifts — designed by you, crafted by us, delivered with love anywhere in India.';
$heroCtaUrl = $hero['cta_url'] ?? url('/category/all');
$heroCtaText = $hero['cta_text'] ?? 'Start Customising';

// Hero split panels — admin-managed photos; default to heroleft/heroright.
$heroLeftPhoto  = !empty($hero['transform_left_photo'])  ? asset($hero['transform_left_photo'])  : asset('/images/heroleft.png');
$heroRightPhoto = !empty($hero['transform_right_photo']) ? asset($hero['transform_right_photo']) : asset('/images/heroright.png');
?>

<!-- =========================================================
     HERO — Full-bleed split: left text | right before/after reveal
     ========================================================= -->
<?php if (!isset($hero['is_active']) || !empty($hero['is_active'])): ?>
<section class="gdd-ht">

  <!-- ── Left: text column (self-contained, no outer container needed) ── -->
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

  <!-- ── Right: full-bleed before/after drag reveal ── -->
  <div class="gdd-ht-showcase">

    <!-- corner ornament brackets on the image area -->
    <span class="gdd-ht-corner tl"></span>
    <span class="gdd-ht-corner tr"></span>
    <span class="gdd-ht-corner bl"></span>
    <span class="gdd-ht-corner br"></span>

    <!-- LEFT side image -->
    <div class="gdd-ht-plain">
      <img src="<?= e($heroLeftPhoto) ?>" alt="Before personalisation" loading="eager">
    </div>

    <!-- RIGHT side — revealed as divider moves left -->
    <div class="gdd-ht-reveal">
      <img src="<?= e($heroRightPhoto) ?>" alt="After personalisation" loading="eager">
    </div>

    <!-- floating chips -->
    <div class="gdd-ht-chip chip-photo">📸 Add your photo</div>
    <div class="gdd-ht-chip chip-done">✨ Personalised!</div>

    <!-- drag divider -->
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
<?php endif; ?>

<?php
$_mActive  = !isset($marqueeSection['is_active']) || !empty($marqueeSection['is_active']);
$_mText    = $marqueeSection['text']       ?? '🎁 PERSONALISED PHOTO FRAMES <em>•</em> ENGRAVED JEWELLERY <em>•</em> CUSTOM MUGS &amp; CUSHIONS <em>•</em> VIDEO &amp; PHOTO QR GIFTS <em>•</em> SAME-DAY DISPATCH <em>•</em> COD AVAILABLE <em>•</em>';
$_mBg      = $marqueeSection['bg_color']   ?? '';
$_mColor   = $marqueeSection['text_color'] ?? '';
$_mSize    = $marqueeSection['font_size']  ?? '14';
$_mWeight  = $marqueeSection['font_weight']?? '700';
$_mSpeed   = $marqueeSection['speed']      ?? '26';
$_mStyle   = '';
if ($_mBg)    $_mStyle .= "background:{$_mBg};";
if ($_mColor) $_mStyle .= "color:{$_mColor};";
?>
<?php if ($_mActive): ?>
<!-- Scrolling marquee strip -->
<div class="gdd-marquee" aria-hidden="true" <?= $_mStyle ? 'style="' . e($_mStyle) . '"' : '' ?>>
  <div class="gdd-marquee-track" style="animation-duration:<?= (int)$_mSpeed ?>s;font-size:<?= (int)$_mSize ?>px;font-weight:<?= e($_mWeight) ?>;">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span><?= $_mText ?></span>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- =========================================================
     WHY CHOOSE US — USP cards (admin-managed)
     ========================================================= -->
<?php
$_wcActive = !isset($whyChoose['is_active']) || !empty($whyChoose['is_active']);
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
<?php if ($_wcActive): ?>
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
<?php endif; ?>

<!-- =========================================================
     SHOP BY CATEGORY
     PLACEHOLDER: each $cat['image'] is managed from
     Admin → Catalog → Categories. Use bright, square (800×840)
     lifestyle/product shots per category.
     ========================================================= -->
<?php
$_csActive    = !isset($catSection['is_active']) || !empty($catSection['is_active']);
// Backwards-compat: older saves stored bg_color/heading_align at top level
$_csStyle     = $catSection['style'] ?? [];
if (!isset($_csStyle['bg_color']) && isset($catSection['bg_color'])) {
    $_csStyle['bg_color'] = $catSection['bg_color'] === 'var(--color-bg-alt)' ? '#f8f9fb' : $catSection['bg_color'];
}
if (!isset($_csStyle['align']) && isset($catSection['heading_align'])) {
    $_csStyle['align'] = $catSection['heading_align'];
}
if (empty($_csStyle['bg_color'])) $_csStyle['bg_color'] = '#f8f9fb';
$_csHeading   = $catSection['heading']  ?? 'Shop by Category';
$_csSubtext   = $catSection['subtext']  ?? 'Find the perfect personalised gift for every occasion';
$_csKicker    = $catSection['kicker']   ?? 'Browse';
$_csNameAlign = $catSection['name_align']   ?? 'left';
$_csNameColor = $catSection['name_color']   ?? '#ffffff';
$_csNameSize  = $catSection['name_size']    ?? '15';
$_csNameWeight= $catSection['name_weight']  ?? '700';
$_csOverlay   = $catSection['overlay_color']?? '#000000';
$_csOrder     = $catSection['category_order']?? [];
// Re-order $categories to match admin-defined order
$_catMap = [];
foreach ($categories as $c) { $_catMap[$c['slug']] = $c; }
$_orderedCats = [];
foreach ($_csOrder as $slug) {
    if (isset($_catMap[$slug])) { $_orderedCats[] = $_catMap[$slug]; unset($_catMap[$slug]); }
}
// append any not in order list at the end
foreach ($_catMap as $c) { $_orderedCats[] = $c; }
// Build overlay gradient from chosen colour
$_csOverlayRgb = sscanf($_csOverlay, "#%02x%02x%02x");
$_csOverlayCss = $_csOverlayRgb
    ? sprintf('linear-gradient(0deg, rgba(%d,%d,%d,.62) 0%%, rgba(%d,%d,%d,0) 55%%)', $_csOverlayRgb[0], $_csOverlayRgb[1], $_csOverlayRgb[2], $_csOverlayRgb[0], $_csOverlayRgb[1], $_csOverlayRgb[2])
    : '';
// Map alignment to flex justify-content (overlay is display:flex)
$_csJustify = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$_csNameAlign] ?? 'flex-start';
?>
<?php if ($_csActive): ?>
<section class="section" style="<?= sectionBgStyle($_csStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_csStyle, $_csKicker, $_csHeading, $_csSubtext); ?>
    <div class="gdd-cat-grid reveal-stagger reveal">
      <?php foreach ($_orderedCats as $cat): ?>
        <a class="gdd-cat-card" href="<?= url('/category/' . $cat['slug']) ?>">
          <img src="<?= e(asset($cat['image'] ?: '/images/GDKD logo.png')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
          <div class="overlay" style="justify-content:<?= e($_csJustify) ?><?= $_csOverlayCss ? ';background:' . e($_csOverlayCss) : '' ?>">
            <span style="color:<?= e($_csNameColor) ?>;font-size:<?= (int)$_csNameSize ?>px;font-weight:<?= e($_csNameWeight) ?>"><?= e($cat['name']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =========================================================
     HOW IT WORKS (admin-managed)
     ========================================================= -->
<?php
$_hwActive = !isset($howItWorks['is_active']) || !empty($howItWorks['is_active']);
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
<?php if ($_hwActive): ?>
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
<?php endif; ?>

<!-- =========================================================
     FEATURED PRODUCTS
     ========================================================= -->
<?php
$_ftStyle = $featuredSection['style'] ?? [];
if (empty($_ftStyle['bg_color'])) $_ftStyle['bg_color'] = '#f8f9fb';
?>
<?php if (!empty($featured) && (!isset($featuredSection['is_active']) || !empty($featuredSection['is_active']))): ?>
<section class="section" style="<?= sectionBgStyle($_ftStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_ftStyle, $featuredSection['kicker'] ?? 'Trending Now', $featuredSection['heading'] ?? 'Featured Gifts', $featuredSection['subtext'] ?? 'Hand-picked favourites our customers love'); ?>
    <div class="product-grid reveal-stagger reveal">
      <?php foreach ($featured as $p): ?>
        <?php renderRaw('store/partials/product_card', ['p' => $p, 'inWishlist' => in_array($p['id'], $wishlistIds)]); ?>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:34px">
      <a href="<?= url('/category/all') ?>" class="btn btn-outline">View All Products →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =========================================================
     VIDEO & PHOTO QR — flagship feature spotlight (admin-managed)
     ========================================================= -->
<?php if (!isset($sigFeature['is_active']) || !empty($sigFeature['is_active'])): ?>
<?php
  $sigKicker = $sigFeature['kicker'] ?? 'Signature Feature';
  $sigHeading = $sigFeature['heading'] ?? 'Turn any gift into a Video &amp; Photo Memory';
  $sigDesc = $sigFeature['description'] ?? 'Attach a scannable QR code to your gift — recipients scan it with any phone camera to unlock a private video or photo message from you. No app required.';
  $sigCtaText = $sigFeature['cta_text'] ?? 'Explore Video &amp; Photo Gifts →';
  $sigCtaUrl = $sigFeature['cta_url'] ?? '/category/video-photo-gifts';
  $sigSteps = $sigFeature['steps'] ?? [
    'Upload your video/photo message while placing the order',
    'We generate a unique, secure QR code for your gift',
    'Recipient scans the QR printed on the packaging',
    'Your personal message plays instantly — straight from the heart',
  ];
  $sigImage = !empty($sigFeature['image']) ? asset($sigFeature['image']) : '';
  $sigStyle = $sigFeature['style'] ?? [];
  $sigKickerStyle = !empty($sigStyle['kicker_color'])  ? 'color:' . e($sigStyle['kicker_color']) . ';'  : '';
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
<?php endif; ?>

<!-- =========================================================
     TRUST BADGES (admin-managed, optional)
     ========================================================= -->
<?php
$_tbStyle = $badges['style'] ?? [];
if (empty($_tbStyle['bg_color'])) $_tbStyle['bg_color'] = '#f8f9fb';
?>
<?php if (!empty($badges['is_active']) && !empty($badges['items'])): ?>
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
<?php endif; ?>

<!-- =========================================================
     TESTIMONIALS
     ========================================================= -->
<?php $_teStyle = $testimonials['style'] ?? []; ?>
<?php if (!empty($testimonials['is_active']) || !empty($testimonials['items'])): ?>
<section class="section" style="<?= sectionBgStyle($_teStyle) ?>">
  <div class="container">
    <?php renderSectionHeading($_teStyle, $testimonials['kicker'] ?? 'Loved By Many', $testimonials['heading'] ?? 'What Our Customers Say', ''); ?>
    <div class="gdd-testi-grid reveal-stagger reveal">
      <?php
        $testiItems = $testimonials['items'] ?? [
          ['name' => 'Priya Sharma', 'rating' => 5, 'text' => 'The photo frame I customised for my parents’ anniversary was stunning — exactly like the preview! Delivery was quick too.'],
          ['name' => 'Rahul Mehta', 'rating' => 5, 'text' => 'Sent a video-message keychain to my best friend abroad. He scanned the QR and got emotional instantly. Magical experience!'],
          ['name' => 'Ananya Iyer', 'rating' => 4.5, 'text' => 'Beautiful engraving quality on the wooden mug. Packaging was premium and the order tracking kept me updated throughout.'],
        ];
      ?>
      <?php foreach ($testiItems as $t):
        $tname = $t['name'] ?? 'Customer';
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
<?php endif; ?>

<?php
$_igStyle = $igGallery['style'] ?? [];
if (empty($_igStyle['bg_color'])) $_igStyle['bg_color'] = '#f8f9fb';
?>
<?php if (!isset($igGallery['is_active']) || !empty($igGallery['is_active'])): ?>
<section class="section" style="<?= sectionBgStyle($_igStyle) ?>">
  <div class="container">
    <?php renderSectionHeading(
        $_igStyle,
        $igGallery['kicker'] ?? '#GiftDekeDekhoMoments',
        $igGallery['heading'] ?? 'Real gifts, real smiles',
        $igGallery['subtext'] ?? 'Tag @giftdekedekho on Instagram for a chance to be featured here'
    ); ?>
    <?php
      $igItems = $igGallery['items'] ?? [];
      $hasPhotos = !empty(array_filter(array_column($igItems, 'image')));
    ?>
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
<?php endif; ?>

<!-- =========================================================
     NEWSLETTER / CTA (admin-managed)
     ========================================================= -->
<?php
$_nlActive = !isset($newsletter['is_active']) || !empty($newsletter['is_active']);
$_nlHeading = $newsletter['heading'] ?? 'Get 10% off your first customised gift 🎉';
$_nlDesc    = $newsletter['description'] ?? 'Subscribe for festive offers, new design drops, and gifting inspiration — straight to your inbox.';
$_nlBtn     = $newsletter['button_text'] ?? 'Subscribe';
$_nlHColor  = $newsletter['heading_color'] ?? '#ffffff';
$_nlTColor  = $newsletter['text_color'] ?? '#ffffff';
$_nlBg      = trim((string)($newsletter['bg_color'] ?? ''));
?>
<?php if ($_nlActive): ?>
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
<?php endif; ?>
