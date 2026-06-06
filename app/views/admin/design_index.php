<?php
$hero = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];
$featuredSection = $sections['featured_products_section'] ?? [];
$badges = $sections['trust_badges'] ?? ['items' => []];
$testimonials = $sections['testimonials_section'] ?? ['items' => []];
$topbarButtons = $sections['topbar_buttons'] ?? ['items' => []];
?>
<div data-tab-container>
    <div class="admin-tabs">
        <span class="admin-tab active" data-tab="branding">Branding</span>
        <span class="admin-tab" data-tab="hero">Hero Banner</span>
        <span class="admin-tab" data-tab="topbar">Topbar Buttons</span>
        <span class="admin-tab" data-tab="promo">Promo Strip</span>
        <span class="admin-tab" data-tab="featured">Featured Section</span>
        <span class="admin-tab" data-tab="badges">Trust Badges</span>
        <span class="admin-tab" data-tab="testimonials">Testimonials</span>
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
                <h4 style="margin:0 0 4px">Floating Product Photos</h4>
                <p class="admin-help-text">Up to 6 images float around the hero headline (3 on each side). Leave a slot empty to hide it. If none are set, your category photos are used automatically.</p>
                <div class="admin-callout">
                  <strong>💡 For the 3D “floating product” look</strong> (like a premium gifting site), upload <strong>transparent PNG cut-outs</strong> — product photos with the background removed. Then tick the box below so they float with a realistic shadow instead of sitting in a photo box.
                  <br>Quick way to make cut-outs: drop your product photo into a free background-remover (e.g. <em>remove.bg</em>, Canva “Remove background”, or Photoshop) and export as <strong>PNG</strong> (~600–900px, transparent).
                </div>
                <label class="admin-checkbox" style="margin:10px 0 16px">
                    <input type="checkbox" name="floaters_cutout" value="1" <?= !empty($hero['floaters_cutout']) ? 'checked' : '' ?>>
                    My hero images are transparent PNG cut-outs — float them in 3D (no photo box)
                </label>
                <?php
                  $heroFloaters = (array)($hero['floaters'] ?? []);
                  $floaterHints = ['Left · top', 'Left · middle', 'Left · bottom', 'Right · top', 'Right · middle', 'Right · bottom'];
                ?>
                <div class="admin-floater-grid">
                  <?php for ($i = 0; $i < 6; $i++): $fimg = $heroFloaters[$i] ?? ''; ?>
                    <div class="admin-floater-slot">
                      <span class="admin-floater-hint"><?= e($floaterHints[$i]) ?></span>
                      <div class="admin-floater-preview">
                        <?php if ($fimg): ?><img src="<?= e(asset($fimg)) ?>" alt=""><?php else: ?><span>Empty</span><?php endif; ?>
                      </div>
                      <input type="file" name="hero_floater[<?= $i ?>]" accept="image/*">
                      <input type="hidden" name="hero_floater_existing[<?= $i ?>]" value="<?= e($fimg) ?>">
                    </div>
                  <?php endfor; ?>
                </div>
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
            <p class="admin-help-text">These appear as text links in the dark utility bar at the very top of every storefront page. The <strong>Label</strong> is the text shown to visitors, so keep it clear (e.g. “Track Order”, “Help &amp; Support”). The image is optional — if set, a small round icon appears before the label.</p>
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
