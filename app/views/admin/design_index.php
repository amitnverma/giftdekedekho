<?php
$hero = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];
$featuredSection = $sections['featured_products_section'] ?? [];
$badges = $sections['trust_badges'] ?? ['items' => []];
$testimonials = $sections['testimonials_section'] ?? ['items' => []];
$topbarButtons = $sections['topbar_buttons'] ?? ['items' => []];
$igGallery = $sections['instagram_gallery'] ?? ['items' => []];
$sigFeature = $sections['signature_feature'] ?? [];

// Build URL datalist options: static pages + all active categories
$urlOptions = [
    ['url' => '/',                        'label' => 'Home'],
    ['url' => '/category/all',            'label' => 'All Products'],
    ['url' => '/cart',                    'label' => 'Cart'],
    ['url' => '/wishlist',                'label' => 'Wishlist'],
    ['url' => '/account',                 'label' => 'My Account'],
    ['url' => '/account/orders',          'label' => 'Track / My Orders'],
    ['url' => '/contact',                 'label' => 'Contact Us'],
    ['url' => '/about',                   'label' => 'About Us'],
    ['url' => '/category/video-photo-gifts', 'label' => 'Video & QR Gifts (category)'],
];
foreach (($categories ?? []) as $cat) {
    $urlOptions[] = ['url' => '/category/' . $cat['slug'], 'label' => $cat['name'] . ' (category)'];
}
?>
<!-- Shared datalist of known site URLs for all link-URL fields -->
<datalist id="siteUrlOptions">
    <?php foreach ($urlOptions as $opt): ?>
        <option value="<?= e($opt['url']) ?>"><?= e($opt['label']) ?></option>
    <?php endforeach; ?>
</datalist>

<div data-tab-container>
    <div class="admin-tabs">
        <span class="admin-tab active" data-tab="branding">Branding</span>
        <span class="admin-tab" data-tab="hero">Hero Banner</span>
        <span class="admin-tab" data-tab="topbar">Topbar Buttons</span>
        <span class="admin-tab" data-tab="catnav">Category Nav Bar</span>
        <span class="admin-tab" data-tab="promo">Promo Strip</span>
        <span class="admin-tab" data-tab="featured">Featured Section</span>
        <span class="admin-tab" data-tab="signature">Signature Feature</span>
        <span class="admin-tab" data-tab="badges">Trust Badges</span>
        <span class="admin-tab" data-tab="testimonials">Testimonials</span>
        <span class="admin-tab" data-tab="instagram">Instagram Gallery</span>
        <span class="admin-tab" data-tab="footer">Footer &amp; Social</span>
        <span class="admin-tab" data-tab="about">About Us</span>
    </div>

    <!-- Branding -->
    <div class="admin-tab-pane active" data-pane="branding">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" enctype="multipart/form-data" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="branding">
                <div class="admin-form-row">
                    <label>Site Name
                        <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>">
                    </label>
                    <label>Tagline
                        <input type="text" name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>">
                    </label>
                </div>
                <div class="admin-form-row">
                    <label>Primary Color
                        <input type="color" name="primary_color" value="<?= e($settings['primary_color'] ?? '#e63946') ?>">
                    </label>
                    <label>Accent Color
                        <input type="color" name="accent_color" value="<?= e($settings['accent_color'] ?? '#457b9d') ?>">
                    </label>
                </div>
                <label>Search Bar Placeholder Texts <small style="font-weight:400;color:#888">(one per line — they cycle with a typing animation)</small>
                    <textarea name="search_placeholders" rows="5" placeholder="Search personalised gifts…&#10;Try &quot;photo frame&quot; or &quot;mug&quot;…&#10;Birthday gifts for her…&#10;Anniversary surprises…"><?= e($settings['search_placeholders'] ?? "Search personalised gifts…\nTry \"photo frame\" or \"mug\"…\nBirthday gifts for her…\nAnniversary surprises…\nCustom name gifts…") ?></textarea>
                    <small class="admin-help-text" style="display:block;margin-top:4px">Each line is typed in and out in sequence inside the header search bar.</small>
                </label>
                <label>Logo
                    <input type="file" name="logo" accept="image/*" data-image-preview="#logoPreview">
                </label>
                <img id="logoPreview" src="<?= asset($settings['logo_path'] ?? '/images/GDKD logo.png') ?>" style="height:50px;margin-bottom:14px;">
                <button type="submit" class="admin-btn admin-btn-primary">Save Branding</button>
            </form>
        </div>
    </div>

    <!-- Hero Banner -->
    <div class="admin-tab-pane" data-pane="hero">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" enctype="multipart/form-data" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="hero_banner">
                <label>Headline
                    <input type="text" name="headline" value="<?= e($hero['headline'] ?? '') ?>">
                </label>
                <label>Subheadline
                    <input type="text" name="subheadline" value="<?= e($hero['subheadline'] ?? '') ?>">
                </label>
                <div class="admin-form-row">
                    <label>Button Text
                        <input type="text" name="cta_text" value="<?= e($hero['cta_text'] ?? '') ?>">
                    </label>
                    <label>Button Link
                        <input type="text" name="cta_url" value="<?= e($hero['cta_url'] ?? '') ?>" list="siteUrlOptions" placeholder="Select or type a URL…" autocomplete="off">
                    </label>
                </div>
                <hr class="admin-hr">
                <!-- ── Transformation Showcase Photos ── -->
                <h4 style="margin:0 0 6px">🖼️ Hero Split Panel Photos</h4>
                <p class="admin-help-text">
                  The hero section shows two photos side by side — <strong>Left Panel</strong> and <strong>Right Panel</strong> — filling the right half of the hero. Upload one image for each side.
                </p>
                <div class="admin-callout" style="margin-bottom:20px">
                  💡 Use portrait-oriented images (e.g. <strong>600×700 px</strong>) for the best fit. Both panels will fill their half equally.
                </div>

                <?php
                  $transformSlots = [
                    ['key'=>'left',  'default_img'=>'/images/heroleft.png',  'label'=>'Left Panel',  'emoji'=>'◀️', 'color'=>'#f2496b'],
                    ['key'=>'right', 'default_img'=>'/images/heroright.png', 'label'=>'Right Panel', 'emoji'=>'▶️', 'color'=>'#457bdb'],
                  ];
                ?>
                <div class="admin-transform-grid">
                  <?php foreach ($transformSlots as $slot):
                    $photoKey   = 'transform_' . $slot['key'] . '_photo';
                    $savedPhoto = $hero[$photoKey] ?? '';
                    $previewSrc = $savedPhoto ? asset($savedPhoto) : asset($slot['default_img']);
                  ?>
                  <div class="admin-transform-slot" style="--slot-color:<?= e($slot['color']) ?>">
                    <div class="admin-transform-preview">
                      <img id="transformPreview_<?= $slot['key'] ?>" src="<?= e($previewSrc) ?>" alt="<?= e($slot['label']) ?>" style="width:100%;max-height:200px;object-fit:cover;border-radius:8px;display:block;background:#f0f0f0">
                      <div class="admin-transform-badge"><?= $slot['emoji'] ?> <?= e($slot['label']) ?></div>
                    </div>
                    <?php if (!$savedPhoto): ?>
                      <p style="font-size:12px;color:#888;margin:6px 0 0;text-align:center">⬆ Default image — upload to replace</p>
                    <?php else: ?>
                      <p style="font-size:12px;color:#27ae60;margin:6px 0 0;text-align:center">✅ Custom image saved</p>
                    <?php endif; ?>
                    <label style="margin-top:10px">
                      Photo — <?= e($slot['label']) ?> <small style="font-weight:400;color:#888">(portrait recommended)</small>
                      <input type="file" name="transform_<?= $slot['key'] ?>_photo" accept="image/*"
                             data-image-preview="#transformPreview_<?= $slot['key'] ?>">
                    </label>
                    <input type="hidden" name="transform_<?= $slot['key'] ?>_photo_existing" value="<?= e($savedPhoto) ?>">
                  </div>
                  <?php endforeach; ?>
                </div>

                <hr class="admin-hr" style="margin:28px 0 20px">
                <input type="hidden" name="hero_image" value="">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($hero['is_active']) ? 'checked' : '' ?>>
                    Show on homepage
                </label>
                <button type="submit" class="admin-btn admin-btn-primary">Save Hero Banner</button>
            </form>
        </div>
    </div>

    <!-- Topbar round image buttons -->
    <div class="admin-tab-pane" data-pane="topbar">
        <div class="admin-card">
            <p class="admin-help-text">These are the quick-access links shown in the <strong>dark bar at the very top</strong> of every page on your storefront. Each link has a label, an emoji icon (shown on the website), and an optional uploaded photo that overrides the emoji.</p>

            <!-- Live preview of the topbar bar as it appears on site -->
            <div style="background:#1a1a2e;border-radius:8px;padding:10px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <span style="color:#f3c94f;font-size:13px;font-weight:500">🎁 Your promo message here (from Promo Strip tab)</span>
                <nav id="topbarLivePreview" style="display:flex;gap:14px;flex-wrap:wrap">
                    <?php foreach (($topbarButtons['items'] ?? []) as $tb):
                        $tbEmoji = $tb['emoji'] ?? '';
                        $tbImg   = $tb['image'] ?? '';
                        $tbLabel = $tb['label'] ?? '';
                        if ($tbLabel === '') continue;
                    ?>
                    <span style="display:flex;align-items:center;gap:6px;color:#fff;font-size:13px;font-weight:500;opacity:0.9">
                        <?php if ($tbImg): ?>
                            <img src="<?= e(asset($tbImg)) ?>" style="width:24px;height:24px;border-radius:50%;object-fit:cover">
                        <?php elseif ($tbEmoji): ?>
                            <span><?= e($tbEmoji) ?></span>
                        <?php endif; ?>
                        <?= e($tbLabel) ?>
                    </span>
                    <?php endforeach; ?>
                </nav>
            </div>
            <p style="font-size:12px;color:#888;margin:-12px 0 18px">↑ Live preview of how your topbar links look on the website</p>

            <div class="admin-callout" style="margin-bottom:16px">
                💡 <strong>Topbar links are independent of the Promo Strip.</strong> They appear even when the Promo Strip is turned off.
            </div>

            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="topbar_buttons">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($topbarButtons['is_active']) ? 'checked' : '' ?>>
                    Show this bar on every storefront page
                </label>

                <div id="topbarRepeater">
                    <?php $tbIdx = 0; foreach (($topbarButtons['items'] ?? []) as $tb):
                        $tbImg   = $tb['image'] ?? '';
                        $tbEmoji = $tb['emoji'] ?? '';
                    ?>
                    <div class="admin-option-row" style="background:#f9f9f9;border-radius:8px;padding:14px;margin-bottom:10px">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                            <!-- Icon preview circle -->
                            <div style="width:48px;height:48px;border-radius:50%;background:#1a1a2e;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;overflow:hidden;border:2px solid #ddd" id="tbCircle_<?= $tbIdx ?>">
                                <?php if ($tbImg): ?>
                                    <img src="<?= e(asset($tbImg)) ?>" id="tbCircleImg_<?= $tbIdx ?>" style="width:100%;height:100%;object-fit:cover">
                                <?php else: ?>
                                    <img id="tbCircleImg_<?= $tbIdx ?>" src="" style="display:none;width:100%;height:100%;object-fit:cover">
                                    <span id="tbCircleEmoji_<?= $tbIdx ?>" style="color:#fff;font-size:20px"><?= e($tbEmoji ?: '🔗') ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong style="font-size:14px"><?= e($tb['label'] ?? 'Link #' . ($tbIdx + 1)) ?></strong>
                                <div style="font-size:12px;color:#888"><?= e($tb['url'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label>Emoji Icon <small style="font-weight:400;color:#888">(shown on site)</small>
                                <input type="text" name="tb_emoji[]" value="<?= e($tbEmoji) ?>" placeholder="e.g. 🎬" maxlength="4"
                                       oninput="(function(el){var c=document.getElementById('tbCircleEmoji_<?= $tbIdx ?>');if(c)c.textContent=el.value||'🔗'})(this)"
                                       style="width:80px">
                            </label>
                            <label>Display Label
                                <input type="text" name="tb_label[]" value="<?= e($tb['label'] ?? '') ?>" placeholder="e.g. Track Order">
                            </label>
                            <label>Link URL
                                <input type="text" name="tb_url[]" value="<?= e($tb['url'] ?? '') ?>" list="siteUrlOptions" placeholder="Select or type a URL…" autocomplete="off">
                            </label>
                        </div>
                        <label style="margin-top:8px;display:block">Photo Icon <small style="font-weight:400;color:#888">(optional — overrides emoji; use a square image)</small>
                            <input type="file" name="tb_image[]" accept="image/*"
                                   onchange="(function(el,i){var img=document.getElementById('tbCircleImg_'+i);var em=document.getElementById('tbCircleEmoji_'+i);if(el.files[0]&&img){var r=new FileReader();r.onload=function(e){img.src=e.target.result;img.style.display='block';if(em)em.style.display='none';};r.readAsDataURL(el.files[0]);}})(this,<?= $tbIdx ?>)">
                            <input type="hidden" name="tb_existing_image[]" value="<?= e($tbImg) ?>">
                            <?php if ($tbImg): ?>
                                <small style="color:#27ae60;margin-top:4px;display:block">✅ Custom photo set — upload new to replace</small>
                            <?php else: ?>
                                <small style="color:#aaa;margin-top:4px;display:block">No photo — emoji icon is used instead</small>
                            <?php endif; ?>
                        </label>
                        <div style="margin-top:10px">
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                        </div>
                    </div>
                    <?php $tbIdx++; endforeach; ?>

                    <!-- Template for new rows (hidden) -->
                    <div class="admin-option-row" data-repeater-template style="display:none;background:#f9f9f9;border-radius:8px;padding:14px;margin-bottom:10px">
                        <div class="admin-form-row">
                            <label>Emoji Icon<input type="text" name="tb_emoji[__INDEX__]" placeholder="🔗" maxlength="4" style="width:80px"></label>
                            <label>Display Label<input type="text" name="tb_label[__INDEX__]" placeholder="e.g. Track Order"></label>
                            <label>Link URL<input type="text" name="tb_url[__INDEX__]" list="siteUrlOptions" placeholder="Select or type a URL…" autocomplete="off"></label>
                        </div>
                        <label style="margin-top:8px;display:block">Photo Icon (optional)
                            <input type="file" name="tb_image[__INDEX__]" accept="image/*">
                            <input type="hidden" name="tb_existing_image[__INDEX__]" value="">
                        </label>
                        <div style="margin-top:10px"><button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button></div>
                    </div>
                </div>

                <button type="button" class="admin-btn" data-repeater-add="#topbarRepeater">+ Add Link</button>
                <div class="admin-mt"><button type="submit" class="admin-btn admin-btn-primary">Save Topbar Links</button></div>
            </form>
        </div>
    </div>

    <!-- Category Nav Bar -->
    <div class="admin-tab-pane" data-pane="catnav">
        <div class="admin-card">
            <p class="admin-help-text">Control the horizontal icon strip below the main header — the pills with round images for each category. Add/remove categories, change labels, set emoji icons, and reorder.</p>

            <?php
            // Build a lookup of category data by slug
            $catBySlug = [];
            foreach (($categories ?? []) as $cat) {
                $catBySlug[$cat['slug']] = $cat;
            }
            $navSection  = $sections['nav_category_bar'] ?? [];
            $savedItems  = $navSection['items'] ?? [];
            $showHome    = !isset($navSection['show_home'])      || !empty($navSection['show_home']);
            $showAll     = !isset($navSection['show_all_gifts']) || !empty($navSection['show_all_gifts']);
            $navActive   = !isset($navSection['is_active'])      || !empty($navSection['is_active']);
            $navMaxItems = (int)($navSection['max_items'] ?? 0); // 0 = show all visible

            // If no items saved yet, use all categories as defaults (all visible)
            if (empty($savedItems)) {
                $savedItems = array_map(fn($c) => [
                    'slug'    => $c['slug'],
                    'label'   => $c['name'],
                    'emoji'   => '',
                    'visible' => true,
                ], array_values($categories ?? []));
            }
            // Also add any categories not in saved items (at the bottom, hidden)
            $savedSlugs = array_column($savedItems, 'slug');
            foreach (($categories ?? []) as $cat) {
                if (!in_array($cat['slug'], $savedSlugs)) {
                    $savedItems[] = ['slug' => $cat['slug'], 'label' => $cat['name'], 'emoji' => '', 'visible' => false];
                }
            }
            ?>

            <!-- Live preview -->
            <div style="background:#fff;border:1px solid #e8e8e8;border-radius:10px;padding:12px 16px;margin-bottom:20px;overflow-x:auto">
                <p style="font-size:11px;color:#aaa;margin:0 0 10px;text-transform:uppercase;letter-spacing:.5px">↓ Live Preview — Category Nav Bar</p>
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px">
                    <?php if ($showHome): ?>
                    <span style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:56px">
                        <span style="width:44px;height:44px;border-radius:50%;background:#f5f5f5;display:flex;align-items:center;justify-content:center;font-size:20px">🏠</span>
                        <span style="font-size:11px;font-weight:600;color:#333">Home</span>
                    </span>
                    <?php endif; ?>
                    <?php if ($showAll): ?>
                    <span style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:56px">
                        <span style="width:44px;height:44px;border-radius:50%;background:#f5f5f5;display:flex;align-items:center;justify-content:center;font-size:20px">🎁</span>
                        <span style="font-size:11px;font-weight:600;color:#333">All Gifts</span>
                    </span>
                    <?php endif; ?>
                    <?php foreach ($savedItems as $ni): if (empty($ni['visible'])) continue;
                        $niCat = $catBySlug[$ni['slug']] ?? null;
                        $niImg = $niCat['image'] ?? '';
                        $niEmoji = $ni['emoji'] ?? '';
                        $niLabel = $ni['label'] ?: ($niCat['name'] ?? $ni['slug']);
                    ?>
                    <span style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:56px">
                        <span style="width:44px;height:44px;border-radius:50%;overflow:hidden;border:1px solid #eee;display:flex;align-items:center;justify-content:center;background:#f5f5f5">
                            <?php if ($niImg): ?>
                                <img src="<?= e(asset($niImg)) ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                            <?php else: ?>
                                <span style="font-size:20px"><?= e($niEmoji ?: '🎀') ?></span>
                            <?php endif; ?>
                        </span>
                        <span style="font-size:11px;font-weight:600;color:#333;text-align:center;max-width:60px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= e($niLabel) ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="nav_category_bar">

                <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:16px">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="is_active" value="1" <?= $navActive ? 'checked' : '' ?>>
                        Show category nav bar
                    </label>
                    <label class="admin-checkbox">
                        <input type="checkbox" name="show_home" value="1" <?= $showHome ? 'checked' : '' ?>>
                        Show "Home" pill
                    </label>
                    <label class="admin-checkbox">
                        <input type="checkbox" name="show_all_gifts" value="1" <?= $showAll ? 'checked' : '' ?>>
                        Show "All Gifts" pill
                    </label>
                    <label style="display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:600">
                        Max categories to show
                        <input type="number" name="max_items" value="<?= $navMaxItems ?: '' ?>" min="1" max="20" placeholder="8"
                               style="width:80px;padding:6px 8px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                        <small style="font-weight:400;color:#888">Default: 8. Leave blank to show all checked items.</small>
                    </label>
                </div>

                <p style="font-weight:600;margin-bottom:8px">Category Pills <small style="font-weight:400;color:#888">— check to show, uncheck to hide. Order is preserved.</small></p>

                <div style="border:1px solid #e8e8e8;border-radius:8px;overflow:hidden">
                    <!-- Header row -->
                    <div style="display:grid;grid-template-columns:44px 1fr 1fr 80px 70px;gap:12px;align-items:center;padding:8px 14px;background:#f5f5f5;border-bottom:1px solid #e8e8e8;font-size:12px;font-weight:600;color:#666">
                        <span>Image</span><span>Category</span><span>Custom Label</span><span>Emoji</span><span style="text-align:center">Show</span>
                    </div>
                    <?php $loop2 = 0; foreach ($savedItems as $ni):
                        $niCat   = $catBySlug[$ni['slug']] ?? null;
                        $niImg   = $niCat['image'] ?? '';
                        $niName  = $niCat['name'] ?? $ni['slug'];
                        $niLabel = $ni['label'] ?? $niName;
                        $niEmoji = $ni['emoji'] ?? '';
                        $niVis   = !empty($ni['visible']);
                    ?>
                    <div style="display:grid;grid-template-columns:44px 1fr 1fr 80px 70px;gap:12px;align-items:center;padding:10px 14px;border-bottom:1px solid #f0f0f0">
                        <!-- Image preview -->
                        <span style="width:40px;height:40px;border-radius:50%;overflow:hidden;border:1px solid #e0e0e0;display:flex;align-items:center;justify-content:center;background:#f9f9f9;flex-shrink:0">
                            <?php if ($niImg): ?>
                                <img src="<?= e(asset($niImg)) ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                            <?php else: ?>
                                <span style="font-size:18px"><?= e($niEmoji ?: '🎀') ?></span>
                            <?php endif; ?>
                        </span>
                        <!-- Category name (readonly) + hidden slug -->
                        <span style="font-size:14px;font-weight:500;color:#333">
                            <?= e($niName) ?>
                            <input type="hidden" name="nav_slug[]" value="<?= e($ni['slug']) ?>">
                            <?php if ($niImg): ?>
                                <div style="font-size:11px;color:#27ae60;margin-top:2px">✅ Has category image</div>
                            <?php else: ?>
                                <div style="font-size:11px;color:#aaa;margin-top:2px">No image — set in Categories</div>
                            <?php endif; ?>
                        </span>
                        <!-- Custom label -->
                        <input type="text" name="nav_label[]" value="<?= e($niLabel) ?>" placeholder="<?= e($niName) ?>" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:100%">
                        <!-- Emoji -->
                        <input type="text" name="nav_emoji[]" value="<?= e($niEmoji) ?>" placeholder="🎀" maxlength="4" style="padding:6px 8px;border:1px solid #ddd;border-radius:6px;font-size:16px;width:70px;text-align:center">
                        <!-- Visibility checkbox -->
                        <div style="text-align:center">
                            <input type="checkbox" name="nav_visible[<?= $loop2 ?>]" value="1" <?= $niVis ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer">
                        </div>
                    </div>
                    <?php $loop2++; endforeach; ?>
                </div>

                <div class="admin-mt">
                    <button type="submit" class="admin-btn admin-btn-primary">Save Category Nav Bar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Promo strip -->
    <div class="admin-tab-pane" data-pane="promo">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="promo_strip">
                <label>Promo Text
                    <input type="text" name="text" value="<?= e($promo['text'] ?? '') ?>">
                </label>
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($promo['is_active']) ? 'checked' : '' ?>>
                    Show promo strip
                </label>
                <button type="submit" class="admin-btn admin-btn-primary">Save Promo Strip</button>
            </form>
        </div>
    </div>

    <!-- Featured section -->
    <div class="admin-tab-pane" data-pane="featured">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="featured_products_section">
                <label>Section Heading
                    <input type="text" name="heading" value="<?= e($featuredSection['heading'] ?? 'Featured Gifts') ?>">
                </label>
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($featuredSection['is_active']) ? 'checked' : '' ?>>
                    Show featured products on homepage
                </label>
                <button type="submit" class="admin-btn admin-btn-primary">Save</button>
            </form>
        </div>
    </div>

    <!-- Signature Feature -->
    <div class="admin-tab-pane" data-pane="signature">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" enctype="multipart/form-data" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="signature_feature">
                <label>Kicker Label <small style="font-weight:400;color:#888">(e.g. "Signature Feature")</small>
                    <input type="text" name="kicker" value="<?= e($sigFeature['kicker'] ?? 'Signature Feature') ?>">
                </label>
                <label>Heading
                    <input type="text" name="heading" value="<?= e($sigFeature['heading'] ?? 'Turn any gift into a Video &amp; Photo Memory') ?>">
                </label>
                <label>Description
                    <textarea name="description" rows="3"><?= e($sigFeature['description'] ?? 'Attach a scannable QR code to your gift — recipients scan it with any phone camera to unlock a private video or photo message from you. No app required.') ?></textarea>
                </label>
                <label>CTA Button Text
                    <input type="text" name="cta_text" value="<?= e($sigFeature['cta_text'] ?? 'Explore Video &amp; Photo Gifts →') ?>">
                </label>
                <label>CTA Button Link
                    <input type="text" name="cta_url" value="<?= e($sigFeature['cta_url'] ?? '/category/video-photo-gifts') ?>" list="siteUrlOptions" placeholder="Select or type a URL…" autocomplete="off">
                </label>
                <hr class="admin-hr">
                <p style="font-weight:600;margin-bottom:.5rem">How It Works — Steps (up to 4)</p>
                <?php
                $defaultSteps = [
                    'Upload your video/photo message while placing the order',
                    'We generate a unique, secure QR code for your gift',
                    'Recipient scans the QR printed on the packaging',
                    'Your personal message plays instantly — straight from the heart',
                ];
                $steps = $sigFeature['steps'] ?? $defaultSteps;
                for ($i = 0; $i < 4; $i++):
                ?>
                <label>Step <?= $i + 1 ?>
                    <input type="text" name="steps[]" value="<?= e($steps[$i] ?? '') ?>">
                </label>
                <?php endfor; ?>
                <hr class="admin-hr">
                <label>Right-side Image <small style="font-weight:400;color:#888">(optional — replaces the phone/QR mock; use a portrait image ~600×700 px)</small>
                    <input type="file" name="sig_image" accept="image/*" data-image-preview="#sigImgPreview">
                </label>
                <?php if (!empty($sigFeature['image'])): ?>
                    <img id="sigImgPreview" src="<?= e(asset($sigFeature['image'])) ?>" style="height:120px;border-radius:8px;margin-bottom:4px;object-fit:cover">
                    <p style="font-size:12px;color:#27ae60;margin:0 0 14px">✅ Custom image saved</p>
                <?php else: ?>
                    <img id="sigImgPreview" src="" style="display:none;height:120px;border-radius:8px;margin-bottom:14px;object-fit:cover">
                    <p style="font-size:12px;color:#888;margin:4px 0 14px">No image uploaded — the animated phone/QR graphic will be shown instead.</p>
                <?php endif; ?>
                <input type="hidden" name="sig_image_existing" value="<?= e($sigFeature['image'] ?? '') ?>">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($sigFeature['is_active']) || !empty($sigFeature['is_active'])) ? 'checked' : '' ?>>
                    Show Signature Feature section on homepage
                </label>
                <button type="submit" class="admin-btn admin-btn-primary">Save Signature Feature</button>
            </form>
        </div>
    </div>

    <!-- Trust badges -->
    <div class="admin-tab-pane" data-pane="badges">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="trust_badges">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($badges['is_active']) ? 'checked' : '' ?>>
                    Show trust badges
                </label>
                <div id="badgesRepeater">
                    <?php foreach (($badges['items'] ?? []) as $b): ?>
                        <div class="admin-option-row">
                            <div class="admin-form-row">
                                <label>Icon (emoji)
                                    <input type="text" name="badge_icon[]" value="<?= e($b['icon'] ?? '') ?>">
                                </label>
                                <label>Title
                                    <input type="text" name="badge_title[]" value="<?= e($b['title'] ?? '') ?>">
                                </label>
                                <label>Description
                                    <input type="text" name="badge_desc[]" value="<?= e($b['desc'] ?? '') ?>">
                                </label>
                            </div>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                        </div>
                    <?php endforeach; ?>
                    <div class="admin-option-row" data-repeater-template style="display:none;">
                        <div class="admin-form-row">
                            <label>Icon (emoji)<input type="text" name="badge_icon[__INDEX__]"></label>
                            <label>Title<input type="text" name="badge_title[__INDEX__]"></label>
                            <label>Description<input type="text" name="badge_desc[__INDEX__]"></label>
                        </div>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                    </div>
                </div>
                <button type="button" class="admin-btn" data-repeater-add="#badgesRepeater">+ Add Badge</button>
                <div class="admin-mt"><button type="submit" class="admin-btn admin-btn-primary">Save Trust Badges</button></div>
            </form>
        </div>
    </div>

    <!-- Testimonials -->
    <div class="admin-tab-pane" data-pane="testimonials">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="testimonials_section">
                <label>Section Heading
                    <input type="text" name="heading" value="<?= e($testimonials['heading'] ?? 'What Our Customers Say') ?>">
                </label>
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($testimonials['is_active']) ? 'checked' : '' ?>>
                    Show testimonials
                </label>
                <div id="testiRepeater">
                    <?php foreach (($testimonials['items'] ?? []) as $t): ?>
                        <div class="admin-option-row">
                            <div class="admin-form-row">
                                <label>Customer Name
                                    <input type="text" name="testi_name[]" value="<?= e($t['name'] ?? '') ?>">
                                </label>
                                <label>Rating (1-5)
                                    <input type="number" min="1" max="5" name="testi_rating[]" value="<?= (int)($t['rating'] ?? 5) ?>">
                                </label>
                            </div>
                            <label>Testimonial Text
                                <textarea name="testi_text[]" rows="2"><?= e($t['text'] ?? '') ?></textarea>
                            </label>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                        </div>
                    <?php endforeach; ?>
                    <div class="admin-option-row" data-repeater-template style="display:none;">
                        <div class="admin-form-row">
                            <label>Customer Name<input type="text" name="testi_name[__INDEX__]"></label>
                            <label>Rating (1-5)<input type="number" min="1" max="5" name="testi_rating[__INDEX__]" value="5"></label>
                        </div>
                        <label>Testimonial Text<textarea name="testi_text[__INDEX__]" rows="2"></textarea></label>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                    </div>
                </div>
                <button type="button" class="admin-btn" data-repeater-add="#testiRepeater">+ Add Testimonial</button>
                <div class="admin-mt"><button type="submit" class="admin-btn admin-btn-primary">Save Testimonials</button></div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <!-- ── Instagram / UGC Gallery ── -->
    <div class="admin-tab-pane" data-pane="instagram">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="instagram_gallery">

                <label>Section Hashtag / Kicker
                    <input type="text" name="kicker" value="<?= e($igGallery['kicker'] ?? '#GiftDekeDekhoMoments') ?>">
                </label>
                <label>Section Heading
                    <input type="text" name="heading" value="<?= e($igGallery['heading'] ?? 'Real gifts, real smiles') ?>">
                </label>
                <label>Sub-text
                    <input type="text" name="subtext" value="<?= e($igGallery['subtext'] ?? 'Tag @giftdekedekho on Instagram for a chance to be featured here') ?>">
                </label>
                <label class="admin-toggle-label">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($igGallery['is_active']) || !empty($igGallery['is_active'])) ? 'checked' : '' ?>> Show this section on homepage
                </label>

                <hr style="margin:1.5rem 0">
                <p style="font-weight:600;margin-bottom:.75rem">Gallery Photos (up to 6 — square images work best)</p>

                <?php
                $igItems = $igGallery['items'] ?? [];
                for ($i = 0; $i < 6; $i++):
                    $item = $igItems[$i] ?? [];
                    $existing = $item['image'] ?? '';
                    $caption  = $item['caption'] ?? '';
                    $link     = $item['link'] ?? '';
                ?>
                <div class="admin-card" style="padding:1rem;margin-bottom:1rem;background:#f8f8f8">
                    <p style="font-weight:600;margin-bottom:.5rem">Photo <?= $i + 1 ?></p>
                    <?php if (!empty($existing)): ?>
                        <img src="<?= e($existing) ?>" style="height:90px;width:90px;object-fit:cover;border-radius:6px;margin-bottom:.5rem" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <input type="hidden" name="ig_existing[<?= $i ?>]" value="<?= e($existing) ?>">
                    <label>Upload Image <input type="file" name="ig_image[<?= $i ?>]" accept="image/*"></label>
                    <label>Caption <input type="text" name="ig_caption[<?= $i ?>]" value="<?= e($caption) ?>" placeholder="e.g. @username"></label>
                    <label>Link (optional) <input type="text" name="ig_link[<?= $i ?>]" value="<?= e($link) ?>" placeholder="https://instagram.com/p/..."></label>
                </div>
                <?php endfor; ?>

                <button type="submit" class="admin-btn admin-btn-primary admin-mt">Save Gallery</button>
            </form>
        </div>
    </div>

    <div class="admin-tab-pane" data-pane="footer">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="footer">
                <label>Footer Copyright Text
                    <input type="text" name="footer_copyright" value="<?= e($settings['footer_copyright'] ?? '') ?>">
                </label>
                <div class="admin-form-row">
                    <label>Contact Email<input type="text" name="site_email" value="<?= e($settings['site_email'] ?? '') ?>"></label>
                    <label>Contact Phone<input type="text" name="site_phone" value="<?= e($settings['site_phone'] ?? '') ?>"></label>
                </div>
                <label>Address<input type="text" name="site_address" value="<?= e($settings['site_address'] ?? '') ?>"></label>
                <label>WhatsApp Number (with country code)<input type="text" name="whatsapp_number" value="<?= e($settings['whatsapp_number'] ?? '') ?>"></label>
                <div class="admin-form-row">
                    <label>Facebook URL<input type="text" name="social_facebook" value="<?= e($settings['social_facebook'] ?? '') ?>"></label>
                    <label>Instagram URL<input type="text" name="social_instagram" value="<?= e($settings['social_instagram'] ?? '') ?>"></label>
                </div>
                <div class="admin-form-row">
                    <label>Twitter URL<input type="text" name="social_twitter" value="<?= e($settings['social_twitter'] ?? '') ?>"></label>
                    <label>YouTube URL<input type="text" name="social_youtube" value="<?= e($settings['social_youtube'] ?? '') ?>"></label>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary">Save Footer Settings</button>
            </form>
        </div>
    </div>

    <!-- About Us -->
    <div class="admin-tab-pane" data-pane="about">
        <div class="admin-card">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.snow.css">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="about_us">
                <label>About Us Page Content
                    <textarea name="about_us_text" id="aboutHidden" style="display:none;"><?= e($settings['about_us_text'] ?? '') ?></textarea>
                    <div id="aboutEditor" style="height:260px;background:#fff;"><?= $settings['about_us_text'] ?? '' ?></div>
                </label>
                <button type="submit" class="admin-btn admin-btn-primary admin-mt">Save About Us</button>
            </form>
            <script src="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.min.js"></script>
            <script>
            (function () {
                var el = document.getElementById('aboutEditor');
                if (window.Quill && el) {
                    var hidden = document.getElementById('aboutHidden');
                    var quill = new Quill(el, { theme: 'snow' });
                    if (hidden.value) quill.root.innerHTML = hidden.value;
                    quill.on('text-change', function () { hidden.value = quill.root.innerHTML; });
                    el.closest('form').addEventListener('submit', function () { hidden.value = quill.root.innerHTML; });
                }
            })();
            </script>
        </div>
    </div>
</div>
