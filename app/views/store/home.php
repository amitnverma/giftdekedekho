<?php
$hero = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];
$featuredSection = $sections['featured_products_section'] ?? [];
$testimonials = $sections['testimonials_section'] ?? [];
$badges = $sections['trust_badges'] ?? [];
$igGallery = $sections['instagram_gallery'] ?? [];
$sigFeature = $sections['signature_feature'] ?? [];

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

<!-- Scrolling marquee strip -->
<div class="gdd-marquee" aria-hidden="true">
  <div class="gdd-marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>🎁 PERSONALISED PHOTO FRAMES <em>•</em> ENGRAVED JEWELLERY <em>•</em> CUSTOM MUGS &amp; CUSHIONS <em>•</em> VIDEO &amp; PHOTO QR GIFTS <em>•</em> SAME-DAY DISPATCH <em>•</em> COD AVAILABLE <em>•</em></span>
    <?php endfor; ?>
  </div>
</div>

<!-- =========================================================
     WHY CHOOSE US — USP cards
     ========================================================= -->
<section class="section">
  <div class="container">
    <div class="section-heading reveal">
      <span class="gdd-kicker">Why GiftDekeDekho</span>
      <h2>Crafted with care, delivered with a smile</h2>
      <p>Every order is handmade-to-order — no two gifts are exactly alike</p>
    </div>
    <div class="gdd-usp-grid reveal-stagger reveal">
      <div class="gdd-usp-card"><div class="ico">🎨</div><h4>Fully Personalised</h4><p>Add names, photos, dates &amp; messages with our live preview customiser.</p></div>
      <div class="gdd-usp-card"><div class="ico">📦</div><h4>Pan-India Delivery</h4><p>Reliable doorstep delivery across India with real-time order tracking.</p></div>
      <div class="gdd-usp-card"><div class="ico">💳</div><h4>Secure Payments</h4><p>Razorpay, PayPal, Stripe &amp; Cash on Delivery — pay your way, safely.</p></div>
      <div class="gdd-usp-card"><div class="ico">💬</div><h4>Friendly Support</h4><p>Real humans ready to help with design tweaks, tracking &amp; returns.</p></div>
    </div>
  </div>
</section>

<!-- =========================================================
     SHOP BY CATEGORY
     PLACEHOLDER: each $cat['image'] is managed from
     Admin → Catalog → Categories. Use bright, square (800×840)
     lifestyle/product shots per category.
     ========================================================= -->
<section class="section" style="background:var(--color-bg-alt)">
  <div class="container">
    <div class="section-heading reveal">
      <span class="gdd-kicker">Browse</span>
      <h2>Shop by Category</h2>
      <p>Find the perfect personalised gift for every occasion</p>
    </div>
    <div class="gdd-cat-grid reveal-stagger reveal">
      <?php foreach ($categories as $cat): ?>
        <a class="gdd-cat-card" href="<?= url('/category/' . $cat['slug']) ?>">
          <img src="<?= e(asset($cat['image'] ?: '/images/GDKD logo.png')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
          <div class="overlay"><span><?= e($cat['name']) ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- =========================================================
     HOW IT WORKS
     ========================================================= -->
<section class="section">
  <div class="container">
    <div class="section-heading reveal">
      <span class="gdd-kicker">Simple Process</span>
      <h2>From idea to doorstep in 4 easy steps</h2>
    </div>
    <div class="gdd-steps reveal-stagger reveal">
      <div class="gdd-step"><div class="num"></div><h4>Pick a Gift</h4><p>Browse frames, mugs, jewellery, cushions &amp; more.</p></div>
      <div class="gdd-step"><div class="num"></div><h4>Personalise It</h4><p>Add photos, names, engravings or a video message.</p></div>
      <div class="gdd-step"><div class="num"></div><h4>We Craft &amp; Pack</h4><p>Our artisans make it by hand and pack it with care.</p></div>
      <div class="gdd-step"><div class="num"></div><h4>You Receive &amp; Smile</h4><p>Track your order and get it delivered to your door.</p></div>
    </div>
  </div>
</section>

<!-- =========================================================
     FEATURED PRODUCTS
     ========================================================= -->
<?php if (!empty($featured) && (!isset($featuredSection['is_active']) || !empty($featuredSection['is_active']))): ?>
<section class="section" style="background:var(--color-bg-alt)">
  <div class="container">
    <div class="section-heading reveal">
      <span class="gdd-kicker">Trending Now</span>
      <h2><?= e($featuredSection['heading'] ?? 'Featured Gifts') ?></h2>
      <p>Hand-picked favourites our customers love</p>
    </div>
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
?>
<section class="section">
  <div class="container">
    <div class="gdd-spotlight reveal">
      <div>
        <span class="gdd-kicker"><?= e($sigKicker) ?></span>
        <h2><?= e($sigHeading) ?></h2>
        <p><?= e($sigDesc) ?></p>
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
<?php if (!empty($badges['is_active']) && !empty($badges['items'])): ?>
<section class="section" style="background:var(--color-bg-alt)">
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
<?php if (!empty($testimonials['is_active']) || !empty($testimonials['items'])): ?>
<section class="section">
  <div class="container">
    <div class="section-heading reveal">
      <span class="gdd-kicker">Loved By Many</span>
      <h2><?= e($testimonials['heading'] ?? 'What Our Customers Say') ?></h2>
    </div>
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

<?php if (!isset($igGallery['is_active']) || !empty($igGallery['is_active'])): ?>
<section class="section" style="background:var(--color-bg-alt)">
  <div class="container">
    <div class="section-heading reveal">
      <?php if (!empty($igGallery['kicker'])): ?>
        <span class="gdd-kicker"><?= e($igGallery['kicker']) ?></span>
      <?php else: ?>
        <span class="gdd-kicker">#GiftDekeDekhoMoments</span>
      <?php endif; ?>
      <h2><?= e($igGallery['heading'] ?? 'Real gifts, real smiles') ?></h2>
      <?php if (!empty($igGallery['subtext'])): ?>
        <p><?= e($igGallery['subtext']) ?></p>
      <?php else: ?>
        <p>Tag <strong>@giftdekedekho</strong> on Instagram for a chance to be featured here</p>
      <?php endif; ?>
    </div>
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
     NEWSLETTER / CTA
     ========================================================= -->
<section class="section">
  <div class="container">
    <div class="gdd-newsletter reveal">
      <div>
        <h3>Get 10% off your first customised gift 🎉</h3>
        <p>Subscribe for festive offers, new design drops, and gifting inspiration — straight to your inbox.</p>
      </div>
      <form id="gddNewsletterForm">
        <input type="email" id="gddNewsletterEmail" placeholder="you@example.com" required>
        <button type="submit" class="btn btn-primary">Subscribe</button>
      </form>
    </div>
    <p id="gddNewsletterMsg" style="text-align:center;margin-top:14px;font-size:14px;font-weight:600"></p>
  </div>
</section>
