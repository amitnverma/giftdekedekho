<?php
$hero = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];
$featuredSection = $sections['featured_products_section'] ?? [];
$testimonials = $sections['testimonials_section'] ?? [];
$badges = $sections['trust_badges'] ?? [];

$heroHeadline = $hero['headline'] ?? '';
$heroSub = $hero['subheadline'] ?? 'Photo frames, engraved keepsakes, custom mugs & video-message gifts — designed by you, crafted by us, delivered with love anywhere in India.';
$heroCtaUrl = $hero['cta_url'] ?? url('/category/all');
$heroCtaText = $hero['cta_text'] ?? 'Start Customising';

// Floating product photos — managed in Admin → Design Studio → Hero Banner.
// Falls back to real category product shots so the hero always looks complete.
$heroFloaters = array_values(array_filter($hero['floaters'] ?? []));
$heroUsingDefaults = false;
if (empty($heroFloaters)) {
    $heroUsingDefaults = true;
    $heroFloaters = array_values(array_filter(array_map(
        fn($c) => $c['image'] ?? '',
        $categories
    )));
}
// Cutout mode = transparent PNGs floated in true 3D (admin toggle).
// Default category photos have backgrounds, so they render as tidy "cards".
$heroCutout = !empty($hero['floaters_cutout']) && !$heroUsingDefaults;
$floaterMode = $heroCutout ? 'is-cutout' : 'is-card';
// Ensure we have 6 slots (cycle through whatever images we have)
$heroFloaterImgs = [];
if (!empty($heroFloaters)) {
    for ($i = 0; $i < 6; $i++) { $heroFloaterImgs[] = $heroFloaters[$i % count($heroFloaters)]; }
}
?>

<!-- =========================================================
     HERO v2 — centered headline with floating product photo cards
     confined to the left/right gutter columns (never overlap text).
     Floater images come from Admin → Design Studio → Hero Banner;
     they fall back to category product photos automatically.
     ========================================================= -->
<section class="gdd-hero2">
  <div class="gdd-hero2-grid">
    <!-- LEFT gutter floaters -->
    <div class="gdd-hero2-side left" aria-hidden="true">
      <?php foreach ([['p1',26],['p2',16],['p3',34]] as $idx => $f):
        [$cls, $depth] = $f; $img = $heroFloaterImgs[$idx] ?? ''; ?>
        <div class="gdd-floater <?= $cls ?> <?= $img ? $floaterMode : 'placeholder' ?>" data-depth="<?= $depth ?>">
          <?php if ($img): ?><img src="<?= e(asset($img)) ?>" alt="" loading="lazy"><?php else: ?><span>Add hero<br>cutout PNG</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- CENTER content -->
    <div class="gdd-hero2-content">
      <span class="gdd-hero2-kicker reveal is-visible"><?= e($promo['text'] ?? 'Perfect Gifting Made Simple.') ?></span>
      <?php if ($heroHeadline !== ''): ?>
        <h1 class="reveal is-visible"><?= e($heroHeadline) ?></h1>
      <?php else: ?>
        <h1 class="reveal is-visible">Make every moment<br><span class="grad">memorable, every time.</span></h1>
      <?php endif; ?>
      <p class="lead reveal is-visible"><?= e($heroSub) ?></p>
      <form class="gdd-hero2-bar reveal is-visible" action="<?= url('/category/all') ?>" method="get">
        <span class="icon-orb">🎁</span>
        <input type="search" name="q" placeholder="Curious about gift ideas? Try “photo frame”…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search for gift ideas">
        <button type="submit" aria-label="Search">→</button>
      </form>
      <div class="gdd-hero-cta" style="justify-content:center;margin-top:24px">
        <a href="<?= e($heroCtaUrl) ?>" class="btn btn-primary"><?= e($heroCtaText) ?> →</a>
        <a href="<?= url('/category/video-photo-gifts') ?>" class="btn btn-outline">🎬 Try Video &amp; Photo QR Gifts</a>
      </div>
      <div class="gdd-hero-stats" style="justify-content:center">
        <div><strong><span data-count-to="50" data-suffix="K+">0</span></strong><span>Happy customers</span></div>
        <div><strong><span data-count-to="4.8" data-suffix="★">0</span></strong><span>Average rating</span></div>
        <div><strong><span data-count-to="500" data-suffix="+">0</span></strong><span>Gift designs</span></div>
      </div>
    </div>

    <!-- RIGHT gutter floaters -->
    <div class="gdd-hero2-side right" aria-hidden="true">
      <?php foreach ([['p4',30],['p5',18],['p6',38]] as $idx => $f):
        [$cls, $depth] = $f; $img = $heroFloaterImgs[$idx + 3] ?? ''; ?>
        <div class="gdd-floater <?= $cls ?> <?= $img ? $floaterMode : 'placeholder' ?>" data-depth="<?= $depth ?>">
          <?php if ($img): ?><img src="<?= e(asset($img)) ?>" alt="" loading="lazy"><?php else: ?><span>Add hero<br>cutout PNG</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

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
<?php if (!empty($featured)): ?>
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
     VIDEO & PHOTO QR — flagship feature spotlight
     PLACEHOLDER: replace the phone "QR" mock with a real photo of the
     printed QR sticker on packaging / a phone scanning it (900×1000).
     ========================================================= -->
<section class="section">
  <div class="container">
    <div class="gdd-spotlight reveal">
      <div>
        <span class="gdd-kicker">Signature Feature</span>
        <h2>Turn any gift into a Video &amp; Photo Memory</h2>
        <p>Attach a scannable QR code to your gift — recipients scan it with any phone camera to unlock a private video or photo message from you. No app required.</p>
        <ul class="feat-list">
          <li><i>1</i> Upload your video/photo message while placing the order</li>
          <li><i>2</i> We generate a unique, secure QR code for your gift</li>
          <li><i>3</i> Recipient scans the QR printed on the packaging</li>
          <li><i>4</i> Your personal message plays instantly — straight from the heart</li>
        </ul>
        <a href="<?= url('/category/video-photo-gifts') ?>" class="btn btn-primary">Explore Video &amp; Photo Gifts →</a>
      </div>
      <div class="gdd-spotlight-art">
        <div class="gdd-phone"><div class="qr"></div></div>
      </div>
    </div>
  </div>
</section>

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

<!-- =========================================================
     #UNWRAPPED GALLERY — UGC / Instagram-style strip
     PLACEHOLDER: 6 square (500×500) real customer-unboxing photos
     or styled product shots — drop files into /images/ and update
     the src attributes below, or wire up an Instagram feed API later.
     ========================================================= -->
<section class="section" style="background:var(--color-bg-alt)">
  <div class="container">
    <div class="section-heading reveal">
      <span class="gdd-kicker">#GiftDekeDekhoMoments</span>
      <h2>Real gifts, real smiles</h2>
      <p>Tag <strong>@giftdekedekho</strong> on Instagram for a chance to be featured here</p>
    </div>
    <div class="gdd-gallery-grid reveal-stagger reveal">
      <?php for ($i = 1; $i <= 6; $i++): ?>
        <a href="#" title="Customer moment #<?= $i ?> — placeholder, replace with a real UGC photo">
          <img src="<?= e(asset('/images/GDKD logo.png')) ?>" alt="Customer moment placeholder <?= $i ?>" loading="lazy" style="object-fit:contain;background:#fff;padding:30px">
          <span class="tag">📷 Placeholder #<?= $i ?></span>
        </a>
      <?php endfor; ?>
    </div>
  </div>
</section>

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
