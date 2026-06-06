<?php
/** @var array $product, $images, $options, $related, $reviews, $ratingSummary */
$price = $product['sale_price'] !== null ? (float)$product['sale_price'] : (float)$product['base_price'];
$hasDiscount = $product['sale_price'] !== null && (float)$product['sale_price'] < (float)$product['base_price'];
$stock = (int)$product['stock_qty'];
$stockClass = $stock <= 0 ? 'stock-out' : ($stock <= 5 ? 'stock-low' : 'stock-in');
$stockLabel = $stock <= 0 ? 'Out of Stock' : ($stock <= 5 ? "Only {$stock} left in stock!" : 'In Stock');
$primaryImg = !empty($images) ? $images[0]['image_path'] : '/images/GDKD logo.png';
$whatsappNum = (new Settings())->get('whatsapp_number', '');
$shareUrl = url('/product/' . $product['slug']);
?>
<div class="container">
  <div class="breadcrumbs">
    <a href="<?= url('/') ?>">Home</a> /
    <a href="<?= url('/category/' . $product['category_slug']) ?>"><?= e($product['category_name']) ?></a> /
    <?= e($product['name']) ?>
  </div>

  <div class="product-detail">
    <div>
      <div class="gallery-main">
        <img id="galleryMainImg" src="<?= e(asset($primaryImg)) ?>" alt="<?= e($product['name']) ?>">
      </div>
      <?php if (count($images) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($images as $i => $img): ?>
          <img src="<?= e(asset($img['image_path'])) ?>" data-full="<?= e(asset($img['image_path'])) ?>" class="<?= $i === 0 ? 'active' : '' ?>" alt="">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="product-info">
      <h1><?= e($product['name']) ?></h1>
      <div class="sku">SKU: <?= e($product['sku'] ?: 'N/A') ?></div>
      <?php if ((float)$ratingSummary['average'] > 0): ?>
        <div class="stars"><?= starRating((float)$ratingSummary['average']) ?> <?= $ratingSummary['average'] ?> (<?= $ratingSummary['total'] ?> reviews)</div>
      <?php endif; ?>
      <div class="price-row">
        <span class="price-now"><?= formatPrice($price) ?></span>
        <?php if ($hasDiscount): ?><span class="price-old"><?= formatPrice($product['base_price']) ?></span><?php endif; ?>
      </div>
      <span class="stock-badge <?= $stockClass ?>"><?= e($stockLabel) ?></span>
      <p style="margin-top:14px"><?= e($product['short_description']) ?></p>

      <div class="pincode-check">
        <input type="text" id="pincodeInput" placeholder="Enter pincode to check delivery" maxlength="6" inputmode="numeric">
        <button class="btn btn-outline btn-sm" id="pincodeCheckBtn" type="button">Check</button>
      </div>
      <div id="pincodeResult"></div>

      <form action="<?= url('/cart/add') ?>" method="post" enctype="multipart/form-data" id="addToCartForm">
        <?= csrfField() ?>
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

        <?php if (!empty($options)): ?>
        <div class="customize-panel">
          <h4>Customize Your Gift</h4>
          <?php foreach ($options as $opt): ?>
            <div class="customize-option">
              <label>
                <?= e($opt['label']) ?> <?= $opt['is_required'] ? '<span style="color:var(--color-primary)">*</span>' : '(optional)' ?>
                <?php if ((float)$opt['extra_charge'] > 0): ?>
                  <span class="extra-charge">+ <?= formatPrice($opt['extra_charge']) ?></span>
                <?php endif; ?>
              </label>

              <?php $fieldName = 'customization[' . $opt['id'] . ']'; $inputId = 'opt_' . $opt['id']; ?>

              <?php if ($opt['option_type'] === 'text_engraving'): ?>
                <input type="text" id="<?= $inputId ?>" name="<?= $fieldName ?>[value]" maxlength="<?= (int)($opt['char_limit'] ?: 100) ?>"
                       data-char-limit="<?= (int)($opt['char_limit'] ?: 100) ?>" <?= $opt['extra_charge'] > 0 ? 'data-extra="' . (float)$opt['extra_charge'] . '"' : '' ?>
                       placeholder="Type your engraving text…" <?= $opt['is_required'] ? 'required' : '' ?>>
                <div class="char-count" data-counter-for="<?= $inputId ?>">0 / <?= (int)($opt['char_limit'] ?: 100) ?></div>
                <div class="engraving-preview" data-preview-for="<?= $inputId ?>">Your custom text preview will appear here…</div>

              <?php elseif ($opt['option_type'] === 'photo_upload'): ?>
                <input type="file" id="<?= $inputId ?>" class="photo-upload-input" name="<?= $fieldName ?>[file]" accept="image/jpeg,image/png" <?= $opt['is_required'] ? 'required' : '' ?>>
                <small style="color:var(--color-muted)">JPG or PNG, max 5MB</small>
                <div class="crop-preview-wrap" data-crop-for="<?= $inputId ?>"></div>

              <?php elseif ($opt['option_type'] === 'gift_wrap'): ?>
                <label style="font-weight:400;display:flex;align-items:center;gap:8px">
                  <input type="checkbox" name="<?= $fieldName ?>[checked]" value="1" <?= $opt['extra_charge'] > 0 ? 'data-extra="' . (float)$opt['extra_charge'] . '"' : '' ?>>
                  Yes, add gift wrapping
                </label>
                <img src="<?= e(asset('/images/Gemini_Generated_Image_jnnwcqjnnwcqjnnw.png')) ?>" alt="Gift wrap preview" style="max-width:140px;margin-top:8px;border-radius:8px" loading="lazy">

              <?php elseif ($opt['option_type'] === 'message_card'): ?>
                <textarea name="<?= $fieldName ?>[value]" rows="3" placeholder="Write your personal message…"></textarea>

              <?php elseif ($opt['option_type'] === 'video_photo'): ?>
                <label style="font-weight:400;display:flex;align-items:center;gap:8px">
                  <input type="checkbox" name="<?= $fieldName ?>[checked]" value="1" <?= $opt['extra_charge'] > 0 ? 'data-extra="' . (float)$opt['extra_charge'] . '"' : '' ?>>
                  Add a scannable photo that plays your personal video
                </label>
                <p style="font-size:13px;color:var(--color-muted);margin-top:6px">
                  We will print a special photo embedded with a QR code. When anyone scans it with a regular phone camera,
                  it instantly plays your personal video. Our team will contact you after order confirmation to collect your video.
                </p>
              <?php endif; ?>
              <input type="hidden" name="<?= $fieldName ?>[type]" value="<?= e($opt['option_type']) ?>">
              <input type="hidden" name="<?= $fieldName ?>[label]" value="<?= e($opt['label']) ?>">
              <input type="hidden" name="<?= $fieldName ?>[extra_charge]" value="<?= (float)$opt['extra_charge'] ?>">
            </div>
          <?php endforeach; ?>
          <div id="customExtraTotal" style="font-weight:600;color:var(--color-primary);margin-top:8px"></div>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:14px;align-items:center;margin-top:20px">
          <input type="number" name="quantity" value="1" min="1" max="<?= max(1,$stock) ?>" style="width:80px;padding:10px;border:1px solid var(--color-border);border-radius:8px">
          <button type="submit" class="btn btn-primary" <?= $stock <= 0 ? 'disabled' : '' ?>>Customise &amp; Buy</button>
        </div>
      </form>

      <?php if ($whatsappNum): ?>
      <a href="https://wa.me/?text=<?= urlencode('Check out this gift: ' . $product['name'] . ' — ' . $shareUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm" style="margin-top:14px">
        Share on WhatsApp
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="tabs">
    <div class="tab-buttons">
      <button class="active" data-tab="description">Description</button>
      <button data-tab="reviews">Reviews (<?= $ratingSummary['total'] ?>)</button>
      <button data-tab="shipping">Shipping Info</button>
    </div>

    <div class="tab-panel active" data-tab="description">
      <?= $product['description'] ?>
    </div>

    <div class="tab-panel" data-tab="reviews">
      <div class="rating-summary">
        <div class="rating-avg">
          <div class="num"><?= $ratingSummary['average'] ?></div>
          <div class="stars"><?= starRating((float)$ratingSummary['average']) ?></div>
          <div style="color:var(--color-muted);font-size:13px"><?= $ratingSummary['total'] ?> reviews</div>
        </div>
        <div class="rating-bars">
          <?php for ($star = 5; $star >= 1; $star--):
            $count = $ratingSummary['distribution'][$star] ?? 0;
            $pct = $ratingSummary['total'] > 0 ? round(($count / $ratingSummary['total']) * 100) : 0; ?>
            <div class="rating-bar-row">
              <span><?= $star ?> ★</span>
              <div class="rating-bar-track"><div class="rating-bar-fill" style="width:<?= $pct ?>%"></div></div>
              <span><?= $count ?></span>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <?php if (empty($reviews)): ?>
        <p style="color:var(--color-muted)">No reviews yet. Be the first to review this product!</p>
      <?php else: ?>
        <?php foreach ($reviews as $r): ?>
          <div class="review-card">
            <div class="stars"><?= starRating((float)$r['rating']) ?></div>
            <strong><?= e($r['title'] ?: 'Verified Buyer') ?></strong>
            <p><?= e($r['body']) ?></p>
            <div class="meta"><?= e($r['user_name']) ?> · <?= timeAgo($r['created_at']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (isLoggedIn()): ?>
        <h4 style="margin-top:24px">Write a Review</h4>
        <form action="<?= url('/account/orders') ?>#review" method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="submit_review">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
          <input type="hidden" id="ratingValue" name="rating" value="5">
          <div class="star-input" data-target="ratingValue" style="font-size:24px;cursor:pointer;margin-bottom:10px">
            <span class="star star-full">★</span><span class="star star-full">★</span><span class="star star-full">★</span><span class="star star-full">★</span><span class="star star-full">★</span>
          </div>
          <div class="form-group"><input type="text" name="title" placeholder="Review title"></div>
          <div class="form-group"><textarea name="body" rows="3" placeholder="Share your experience…" required></textarea></div>
          <button type="submit" class="btn btn-primary btn-sm">Submit Review</button>
        </form>
      <?php else: ?>
        <p><a href="<?= url('/account/login') ?>">Login</a> to write a review.</p>
      <?php endif; ?>
    </div>

    <div class="tab-panel" data-tab="shipping">
      <p>Standard delivery in 3–6 business days depending on your location. Free shipping on orders above ₹999.</p>
      <p>All items are gift-wrapped and shipped in secure, eco-friendly packaging.</p>
      <p>Need help? <a href="<?= url('/contact') ?>">Contact our support team</a>.</p>
    </div>
  </div>

  <?php if (!empty($related)): ?>
  <div class="section-heading" style="margin-top:50px"><h2>You May Also Like</h2></div>
  <div class="product-grid">
    <?php foreach ($related as $rp): ?>
      <?php renderRaw('store/partials/product_card', ['p' => $rp, 'inWishlist' => false]); ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="zoom-modal" id="zoomModal"><img id="zoomImg" src="" alt="Zoomed product image"></div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
