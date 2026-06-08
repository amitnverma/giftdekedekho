<?php
$hero = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];
$featuredSection = $sections['featured_products_section'] ?? [];
$badges = $sections['trust_badges'] ?? ['items' => []];
$testimonials = $sections['testimonials_section'] ?? ['items' => []];
$topbarButtons = $sections['topbar_buttons'] ?? ['items' => []];
$igGallery = $sections['instagram_gallery'] ?? ['items' => []];
$sigFeature = $sections['signature_feature'] ?? [];
?>
<div data-tab-container>
    <div class="admin-tabs">
        <span class="admin-tab active" data-tab="branding">Branding</span>
        <span class="admin-tab" data-tab="hero">Hero Banner</span>
        <span class="admin-tab" data-tab="topbar">Topbar Buttons</span>
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
                        <input type="text" name="cta_url" value="<?= e($hero['cta_url'] ?? '') ?>">
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
                      <img id="transformPreview_<?= $slot['key'] ?>" src="<?= e($previewSrc) ?>" alt="">
                      <div class="admin-transform-badge"><?= $slot['emoji'] ?> <?= e($slot['label']) ?></div>
                    </div>
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
            <p class="admin-help-text">These appear as text links in the dark utility bar at the very top of every storefront page. The <strong>Label</strong> is the text shown to visitors, so keep it clear (e.g. "Track Order", "Help &amp; Support"). The image is optional — if set, a small round icon appears before the label.</p>
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="topbar_buttons">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($topbarButtons['is_active']) ? 'checked' : '' ?>>
                    Show topbar buttons
                </label>
                <div id="topbarRepeater">
                    <?php foreach (($topbarButtons['items'] ?? []) as $tb): ?>
                        <div class="admin-option-row">
                            <div class="admin-form-row">
                                <label>Image
                                    <input type="file" name="tb_image[]" accept="image/*" data-image-preview="#tbPreview">
                                    <input type="hidden" name="tb_existing_image[]" value="<?= e($tb['image'] ?? '') ?>">
                                    <?php if (!empty($tb['image'])): ?>
                                        <img src="<?= e(asset($tb['image'])) ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;margin-top:6px">
                                    <?php endif; ?>
                                </label>
                                <label>Label (tooltip)
                                    <input type="text" name="tb_label[]" value="<?= e($tb['label'] ?? '') ?>" placeholder="e.g. Track Order">
                                </label>
                                <label>Link URL
                                    <input type="text" name="tb_url[]" value="<?= e($tb['url'] ?? '') ?>" placeholder="/account/orders">
                                </label>
                            </div>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                        </div>
                    <?php endforeach; ?>
                    <div class="admin-option-row" data-repeater-template style="display:none;">
                        <div class="admin-form-row">
                            <label>Image<input type="file" name="tb_image[__INDEX__]" accept="image/*"><input type="hidden" name="tb_existing_image[__INDEX__]" value=""></label>
                            <label>Label (tooltip)<input type="text" name="tb_label[__INDEX__]" placeholder="e.g. Track Order"></label>
                            <label>Link URL<input type="text" name="tb_url[__INDEX__]" placeholder="/account/orders"></label>
                        </div>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                    </div>
                </div>
                <button type="button" class="admin-btn" data-repeater-add="#topbarRepeater">+ Add Button</button>
                <div class="admin-mt"><button type="submit" class="admin-btn admin-btn-primary">Save Topbar Buttons</button></div>
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
                    <input type="text" name="cta_url" value="<?= e($sigFeature['cta_url'] ?? '/category/video-photo-gifts') ?>">
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
                    <img id="sigImgPreview" src="<?= e(asset($sigFeature['image'])) ?>" style="height:120px;border-radius:8px;margin-bottom:14px;object-fit:cover">
                <?php else: ?>
                    <img id="sigImgPreview" src="" style="display:none;height:120px;border-radius:8px;margin-bottom:14px;object-fit:cover">
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
