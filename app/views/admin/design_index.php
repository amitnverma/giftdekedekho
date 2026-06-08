<?php
/**
 * Renders a production-quality colour-picker widget:
 * visible swatch + hex text input + hidden native picker + reset-to-default button.
 *
 * @param string $name      form field name
 * @param string $label     visible label
 * @param string $hint      small descriptive text below label
 * @param string $current   currently saved hex value (from DB / settings)
 * @param string $default   factory-default hex value
 * @param string $id        unique HTML id prefix (auto-generated from $name if omitted)
 */
function designColorPicker(string $name, string $label, string $hint, string $current, string $default, string $id = ''): void {
    if ($id === '') $id = 'cp_' . preg_replace('/[^a-z0-9]/i', '_', $name) . '_' . substr(md5($name . $default), 0, 6);
    // Ensure current is a valid 6-digit hex; fall back to default
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $current)) $current = $default;
    $esc_cur = htmlspecialchars($current, ENT_QUOTES);
    $esc_def = htmlspecialchars($default, ENT_QUOTES);
    $esc_lbl = htmlspecialchars($label, ENT_QUOTES);
    $esc_hnt = htmlspecialchars($hint,  ENT_QUOTES);
    $esc_nm  = htmlspecialchars($name,  ENT_QUOTES);
    echo <<<HTML
<div class="gdd-color-widget">
  <div class="gdd-color-widget-header">
    <div>
      <div class="gdd-color-widget-label">{$esc_lbl}</div>
      <div class="gdd-color-widget-hint">{$esc_hnt}</div>
    </div>
    <button type="button" class="gdd-color-reset"
      onclick="(function(){
        document.getElementById('{$id}_inp').value='{$esc_def}';
        document.getElementById('{$id}_hex').value='{$esc_def}';
        document.getElementById('{$id}_sw').style.background='{$esc_def}';
      })()"
      title="Reset to default: {$esc_def}">&#x21BA; Reset</button>
  </div>
  <div class="gdd-color-widget-body">
    <div id="{$id}_sw" class="gdd-color-swatch"
         style="background:{$esc_cur}"
         onclick="document.getElementById('{$id}_inp').click()"
         title="Click to open colour picker"></div>
    <input type="color" id="{$id}_inp" name="{$esc_nm}" value="{$esc_cur}"
           style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
           oninput="document.getElementById('{$id}_hex').value=this.value;document.getElementById('{$id}_sw').style.background=this.value;">
    <div class="gdd-color-inputs">
      <input type="text" id="{$id}_hex" class="gdd-color-hex"
             value="{$esc_cur}" maxlength="7" placeholder="#rrggbb"
             oninput="var v=this.value;if(/^#[0-9a-fA-F]{6}$/.test(v)){document.getElementById('{$id}_inp').value=v;document.getElementById('{$id}_sw').style.background=v;}"
             onblur="if(!/^#[0-9a-fA-F]{6}$/.test(this.value)){this.value=document.getElementById('{$id}_inp').value;}">
      <div class="gdd-color-default-label">Default: <code>{$esc_def}</code></div>
    </div>
  </div>
</div>
HTML;
}

/** Alignment dropdown with default marker. */
function designAlignSelect(string $name, string $label, string $current, string $default = 'center'): void {
    $opts = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];
    echo '<label>' . htmlspecialchars($label) . ' <span class="admin-label-hint">Default: ' . ucfirst($default === 'center' ? 'Centre' : $default) . '</span>';
    echo '<select name="' . htmlspecialchars($name) . '">';
    foreach ($opts as $v => $l) {
        $lbl = $l . ($v === $default ? ' (default)' : '');
        echo '<option value="' . $v . '"' . (($current ?: $default) === $v ? ' selected' : '') . '>' . $lbl . '</option>';
    }
    echo '</select></label>';
}

/** Numeric font-size field; blank = responsive CSS default. */
function designNumberField(string $name, string $label, string $hint, $current, string $placeholder = '', int $min = 8, int $max = 80): void {
    echo '<label>' . htmlspecialchars($label) . ' <span class="admin-label-hint">' . htmlspecialchars($hint) . '</span>';
    echo '<input type="number" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$current) . '" min="' . $min . '" max="' . $max . '"'
        . ($placeholder ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '') . '></label>';
}

/**
 * Renders the standard "Appearance & Styling" panel for a section heading:
 * alignment, kicker colour, heading colour + size, subtext colour + size,
 * and (optionally) section background — all field names are prefixed so a
 * single form can carry the whole style[] group.
 */
function designAppearancePanel(array $style, bool $withBg = true, bool $withSubtext = true): void {
    $d = sectionStyleDefaults();
    $align = $style['align'] ?? $d['align'];
    echo '<div class="gdd-appearance">';
    echo '<h4 class="gdd-appearance-title">🎨 Appearance &amp; Styling</h4>';
    echo '<div class="admin-form-row" style="align-items:stretch">';
    designAlignSelect('style[align]', 'Heading Alignment', $align, 'center');
    designColorPicker('style[kicker_color]', 'Kicker / Eyebrow Color', 'Small label above the heading', $style['kicker_color'] ?? $d['kicker_color'], $d['kicker_color']);
    echo '</div>';
    echo '<div class="admin-form-row" style="align-items:stretch">';
    designColorPicker('style[heading_color]', 'Heading Color', 'Main section title colour', $style['heading_color'] ?? $d['heading_color'], $d['heading_color']);
    designNumberField('style[heading_size]', 'Heading Font Size', 'Pixels — leave blank for responsive default (~28–40px)', $style['heading_size'] ?? '', 'auto', 14, 72);
    echo '</div>';
    if ($withSubtext) {
        echo '<div class="admin-form-row" style="align-items:stretch">';
        designColorPicker('style[subtext_color]', 'Subtext Color', 'Paragraph below the heading', $style['subtext_color'] ?? $d['subtext_color'], $d['subtext_color']);
        designNumberField('style[subtext_size]', 'Subtext Font Size', 'Pixels — leave blank for default (~16px)', $style['subtext_size'] ?? '', 'auto', 11, 32);
        echo '</div>';
    }
    if ($withBg) {
        echo '<div class="admin-form-row" style="align-items:stretch">';
        $bg = $style['bg_color'] ?? '';
        if ($bg === 'var(--color-bg-alt)') $bg = '#f8f9fb';
        designColorPicker('style[bg_color]', 'Section Background', 'Background colour for this whole section', $bg ?: '#ffffff', '#ffffff');
        echo '<div style="flex:1"></div>';
        echo '</div>';
    }
    echo '</div>';
}

$hero = $sections['hero_banner'] ?? [];
$promo = $sections['promo_strip'] ?? [];
$featuredSection = $sections['featured_products_section'] ?? [];
$badges = $sections['trust_badges'] ?? ['items' => []];
$testimonials = $sections['testimonials_section'] ?? ['items' => []];
$topbarButtons = $sections['topbar_buttons'] ?? ['items' => []];
$igGallery = $sections['instagram_gallery'] ?? ['items' => []];
$sigFeature = $sections['signature_feature'] ?? [];
$marqueeSection = $sections['marquee_strip'] ?? [];
$catSection = $sections['shop_by_category'] ?? [];
$whyChoose = $sections['why_choose_us'] ?? [];
$howItWorks = $sections['how_it_works'] ?? [];
$newsletter = $sections['newsletter'] ?? [];

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
        <span class="admin-tab" data-tab="theme">Page Theme</span>
        <span class="admin-tab" data-tab="hero">Hero Banner</span>
        <span class="admin-tab" data-tab="topbar">Topbar Buttons</span>
        <span class="admin-tab" data-tab="catnav">Category Nav Bar</span>
        <span class="admin-tab" data-tab="marquee">Marquee Strip</span>
        <span class="admin-tab" data-tab="why">Why Choose Us</span>
        <span class="admin-tab" data-tab="shopcat">Shop by Category</span>
        <span class="admin-tab" data-tab="howitworks">How It Works</span>
        <span class="admin-tab" data-tab="promo">Promo Strip</span>
        <span class="admin-tab" data-tab="featured">Featured Section</span>
        <span class="admin-tab" data-tab="signature">Signature Feature</span>
        <span class="admin-tab" data-tab="badges">Trust Badges</span>
        <span class="admin-tab" data-tab="testimonials">Testimonials</span>
        <span class="admin-tab" data-tab="instagram">Instagram Gallery</span>
        <span class="admin-tab" data-tab="newsletter">Newsletter</span>
        <span class="admin-tab" data-tab="footer">Footer &amp; Social</span>
        <span class="admin-tab" data-tab="about">About Us</span>
        <span class="admin-tab" data-tab="layout">📐 Page Layout</span>
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
                <div class="admin-form-row" style="align-items:stretch">
                    <?php designColorPicker('primary_color', 'Primary Color', 'Buttons, links, highlights, prices', $settings['primary_color'] ?? '#e63946', '#e63946') ?>
                    <?php designColorPicker('accent_color',  'Accent Color',  'Secondary actions, hover states',   $settings['accent_color']  ?? '#457b9d', '#457b9d') ?>
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

    <!-- Page Theme -->
    <div class="admin-tab-pane" data-pane="theme">
        <div class="admin-card">
            <p style="color:#888;margin-bottom:20px">Control the full site colour palette. Changes apply globally to every page and component. Use <strong>Reset</strong> on any swatch to restore its factory default.</p>
            <?php
            $themeDefaults = [
                'primary_color' => ['default' => '#e63946', 'label' => 'Primary Color',               'hint' => 'Buttons, links, highlights, prices'],
                'accent_color'  => ['default' => '#457b9d', 'label' => 'Accent Color',                'hint' => 'Secondary actions, hover states'],
                'color_text'    => ['default' => '#1d1d1f', 'label' => 'Body Text Color',             'hint' => 'Main readable text across all pages'],
                'color_muted'   => ['default' => '#6b7280', 'label' => 'Muted / Secondary Text',      'hint' => 'Descriptions, placeholders, metadata'],
                'color_bg'      => ['default' => '#ffffff', 'label' => 'Page Background',             'hint' => 'Main site background colour'],
                'color_bg_alt'  => ['default' => '#f8f9fb', 'label' => 'Alternate Section Background','hint' => 'Alternating section backgrounds, cards'],
                'color_border'  => ['default' => '#e5e7eb', 'label' => 'Border / Divider Color',      'hint' => 'Card borders, input outlines, dividers'],
            ];
            ?>
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form" id="themeForm">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="page_theme">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
                <?php foreach ($themeDefaults as $key => $meta):
                    $current = $settings[$key] ?? $meta['default'];
                ?>
                <div class="gdd-theme-row" style="background:#f8f9fb;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:8px">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                        <div>
                            <div style="font-weight:600;font-size:14px"><?= e($meta['label']) ?></div>
                            <div style="font-size:12px;color:#888;margin-top:1px"><?= e($meta['hint']) ?></div>
                        </div>
                        <button type="button"
                            onclick="document.getElementById('inp_<?= $key ?>').value='<?= $meta['default'] ?>';document.getElementById('hex_<?= $key ?>').value='<?= $meta['default'] ?>';document.getElementById('swatch_<?= $key ?>').style.background='<?= $meta['default'] ?>'"
                            style="font-size:11px;padding:3px 9px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#666;cursor:pointer;white-space:nowrap;flex-shrink:0"
                            title="Reset to factory default: <?= $meta['default'] ?>">
                            ↺ Reset
                        </button>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px">
                        <!-- Large visible colour swatch that opens the picker -->
                        <div id="swatch_<?= $key ?>"
                             style="width:44px;height:44px;border-radius:8px;border:2px solid #d1d5db;cursor:pointer;flex-shrink:0;background:<?= e($current) ?>"
                             onclick="document.getElementById('inp_<?= $key ?>').click()"
                             title="Click to pick colour"></div>
                        <!-- Hidden native colour picker -->
                        <input type="color" id="inp_<?= $key ?>" name="<?= $key ?>"
                               value="<?= e($current) ?>"
                               style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                               oninput="document.getElementById('hex_<?= $key ?>').value=this.value;document.getElementById('swatch_<?= $key ?>').style.background=this.value">
                        <!-- Editable hex text box -->
                        <div style="flex:1;display:flex;flex-direction:column;gap:3px">
                            <input type="text" id="hex_<?= $key ?>"
                                   value="<?= e($current) ?>"
                                   maxlength="7"
                                   placeholder="#rrggbb"
                                   style="font-family:monospace;font-size:14px;padding:7px 10px;border:1px solid #d1d5db;border-radius:7px;width:100%;box-sizing:border-box"
                                   oninput="var v=this.value;if(/^#[0-9a-fA-F]{6}$/.test(v)){document.getElementById('inp_<?= $key ?>').value=v;document.getElementById('swatch_<?= $key ?>').style.background=v;}"
                                   onblur="var v=this.value;if(!/^#[0-9a-fA-F]{6}$/.test(v)){this.value=document.getElementById('inp_<?= $key ?>').value;}">
                            <div style="font-size:11px;color:#aaa">Default: <code><?= $meta['default'] ?></code></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>

                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <button type="submit" class="admin-btn admin-btn-primary">Save Theme</button>
                    <button type="button" class="admin-btn" style="background:#fff;border:1px solid #d1d5db;color:#555"
                        onclick="if(confirm('Reset ALL colours to factory defaults?')){
                            <?php foreach ($themeDefaults as $key => $meta): ?>
                            document.getElementById('inp_<?= $key ?>').value='<?= $meta['default'] ?>';
                            document.getElementById('hex_<?= $key ?>').value='<?= $meta['default'] ?>';
                            document.getElementById('swatch_<?= $key ?>').style.background='<?= $meta['default'] ?>';
                            <?php endforeach; ?>
                        }">
                        ↺ Reset All to Defaults
                    </button>
                </div>
                <p style="font-size:12px;color:#aaa;margin-top:10px">Note: Primary &amp; Accent Color are also editable under the Branding tab — changes here sync automatically.</p>
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

    <!-- Marquee Strip -->
    <div class="admin-tab-pane" data-pane="marquee">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="marquee_strip">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($marqueeSection['is_active']) ? 'checked' : '' ?>>
                    Show scrolling marquee strip
                </label>
                <label>Marquee Text <small style="font-weight:400;color:#888">HTML allowed: use <code>&lt;em&gt;•&lt;/em&gt;</code> for styled dots</small>
                    <textarea name="text" rows="3"><?= htmlspecialchars($marqueeSection['text'] ?? '🎁 PERSONALISED PHOTO FRAMES <em>•</em> ENGRAVED JEWELLERY <em>•</em> CUSTOM MUGS &amp; CUSHIONS <em>•</em> VIDEO &amp; PHOTO QR GIFTS <em>•</em> SAME-DAY DISPATCH <em>•</em> COD AVAILABLE <em>•</em>') ?></textarea>
                </label>
                <div class="admin-form-row" style="align-items:stretch">
                    <?php designColorPicker('bg_color',   'Background Color', 'Strip background — default is near-black (#1d1d1f)', $marqueeSection['bg_color']   ?: '#1d1d1f', '#1d1d1f') ?>
                    <?php designColorPicker('text_color', 'Text Color',       'Strip text — default is white (#ffffff)',             $marqueeSection['text_color'] ?: '#ffffff', '#ffffff') ?>
                </div>
                <div class="admin-form-row">
                    <label>Font Size (px)
                        <input type="number" name="font_size" value="<?= e($marqueeSection['font_size'] ?? '14') ?>" min="10" max="32" step="1">
                        <span class="admin-label-hint">Default: 14 px</span>
                    </label>
                    <label>Font Weight
                        <select name="font_weight">
                            <?php foreach (['400' => 'Normal (400)', '500' => 'Medium (500)', '600' => 'Semi-Bold (600)', '700' => 'Bold (700) — default', '800' => 'Extra-Bold (800)'] as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= ($marqueeSection['font_weight'] ?? '700') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Scroll Speed <span class="admin-label-hint">Seconds per full loop — lower = faster. Default: 26 s</span>
                        <input type="number" name="speed" value="<?= e($marqueeSection['speed'] ?? '26') ?>" min="5" max="120" step="1">
                    </label>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary">Save Marquee Strip</button>
            </form>
        </div>
    </div>

    <!-- Why Choose Us -->
    <div class="admin-tab-pane" data-pane="why">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="why_choose_us">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($whyChoose['is_active']) || !empty($whyChoose['is_active'])) ? 'checked' : '' ?>>
                    Show "Why Choose Us" section on homepage
                </label>

                <h4 class="gdd-section-subtitle">Section Content</h4>
                <div class="admin-form-row">
                    <label>Kicker
                        <input type="text" name="kicker" value="<?= e($whyChoose['kicker'] ?? 'Why GiftDekeDekho') ?>">
                    </label>
                    <label>Heading
                        <input type="text" name="heading" value="<?= e($whyChoose['heading'] ?? 'Crafted with care, delivered with a smile') ?>">
                    </label>
                </div>
                <label>Subtext
                    <input type="text" name="subtext" value="<?= e($whyChoose['subtext'] ?? 'Every order is handmade-to-order — no two gifts are exactly alike') ?>">
                </label>

                <?php designAppearancePanel($whyChoose['style'] ?? []); ?>

                <h4 class="gdd-section-subtitle">Card Styling</h4>
                <div class="admin-form-row" style="align-items:stretch">
                    <?php designAlignSelect('card_align', 'Card Text Alignment', $whyChoose['card_align'] ?? 'left', 'left'); ?>
                    <?php designColorPicker('card_title_color', 'Card Title Color', 'The bold heading on each card', $whyChoose['card_title_color'] ?? '#1d1d1f', '#1d1d1f'); ?>
                    <?php designColorPicker('card_text_color', 'Card Text Color', 'The description text on each card', $whyChoose['card_text_color'] ?? '#6b7280', '#6b7280'); ?>
                </div>

                <h4 class="gdd-section-subtitle">Cards</h4>
                <div id="uspRepeater">
                    <?php foreach (($whyChoose['items'] ?? []) as $u): ?>
                        <div class="admin-option-row">
                            <div class="admin-form-row">
                                <label>Icon (emoji)<input type="text" name="usp_icon[]" value="<?= e($u['icon'] ?? '') ?>" style="max-width:90px"></label>
                                <label>Title<input type="text" name="usp_title[]" value="<?= e($u['title'] ?? '') ?>"></label>
                            </div>
                            <label>Description<input type="text" name="usp_desc[]" value="<?= e($u['desc'] ?? '') ?>"></label>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                        </div>
                    <?php endforeach; ?>
                    <div class="admin-option-row" data-repeater-template style="display:none;">
                        <div class="admin-form-row">
                            <label>Icon (emoji)<input type="text" name="usp_icon[__INDEX__]" style="max-width:90px"></label>
                            <label>Title<input type="text" name="usp_title[__INDEX__]"></label>
                        </div>
                        <label>Description<input type="text" name="usp_desc[__INDEX__]"></label>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                    </div>
                </div>
                <button type="button" class="admin-btn" data-repeater-add="#uspRepeater">+ Add Card</button>
                <div class="admin-mt"><button type="submit" class="admin-btn admin-btn-primary">Save Why Choose Us</button></div>
            </form>
        </div>
    </div>

    <!-- Shop by Category -->
    <div class="admin-tab-pane" data-pane="shopcat">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form" id="shopByCatForm">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="shop_by_category">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($catSection['is_active']) ? 'checked' : '' ?>>
                    Show "Shop by Category" section
                </label>

                <h4 class="gdd-section-subtitle">Section Content</h4>
                <div class="admin-form-row">
                    <label>Kicker (small label above heading)
                        <input type="text" name="kicker" value="<?= e($catSection['kicker'] ?? 'Browse') ?>">
                    </label>
                    <label>Heading
                        <input type="text" name="heading" value="<?= e($catSection['heading'] ?? 'Shop by Category') ?>">
                    </label>
                </div>
                <label>Subtext
                    <input type="text" name="subtext" value="<?= e($catSection['subtext'] ?? 'Find the perfect personalised gift for every occasion') ?>">
                </label>

                <?php
                // Backwards-compat: migrate legacy top-level style keys into the style block
                $catStyle = $catSection['style'] ?? [];
                if (!isset($catStyle['bg_color']) && isset($catSection['bg_color'])) {
                    $catStyle['bg_color'] = $catSection['bg_color'] === 'var(--color-bg-alt)' ? '#f8f9fb' : $catSection['bg_color'];
                }
                if (!isset($catStyle['align']) && isset($catSection['heading_align'])) {
                    $catStyle['align'] = $catSection['heading_align'];
                }
                if (empty($catStyle['bg_color'])) $catStyle['bg_color'] = '#f8f9fb';
                designAppearancePanel($catStyle);
                ?>

                <h4 class="gdd-section-subtitle">Category Card Style</h4>
                <div class="admin-form-row" style="align-items:stretch">
                    <label>Card Display Mode <span class="admin-label-hint">Controls whether category cards show a box or just the image</span>
                        <select name="card_style">
                            <option value="boxed"  <?= ($catSection['card_style'] ?? 'boxed') === 'boxed'  ? 'selected' : '' ?>>Boxed card (image fills card with overlay label)</option>
                            <option value="plain"  <?= ($catSection['card_style'] ?? 'boxed') === 'plain'  ? 'selected' : '' ?>>Image only (circular image, label below — no box)</option>
                        </select>
                    </label>
                </div>
                <p style="font-size:12px;color:#888;margin-top:-8px;margin-bottom:16px">
                    <strong>Image only:</strong> label text appears below the image — long category names wrap neatly and never overlap the image.
                    <strong>Boxed:</strong> label sits on a gradient overlay at the bottom of the card (current default).
                </p>

                <h4 class="gdd-section-subtitle">Category Card Labels</h4>
                <div class="admin-form-row" style="align-items:stretch">
                    <?php designAlignSelect('name_align', 'Label Text Alignment', $catSection['name_align'] ?? 'left', 'left'); ?>
                    <?php designColorPicker('name_color', 'Label Text Color', 'Category name on card overlay — default white', $catSection['name_color'] ?? '#ffffff', '#ffffff') ?>
                </div>
                <div class="admin-form-row" style="align-items:stretch">
                    <?php designNumberField('name_size', 'Label Font Size', 'Pixels — default 15', $catSection['name_size'] ?? '15', '15', 10, 28); ?>
                    <label>Label Font Weight <span class="admin-label-hint">Default: Bold (700)</span>
                        <select name="name_weight">
                            <?php foreach (['400' => 'Normal (400)', '500' => 'Medium (500)', '600' => 'Semi-Bold (600)', '700' => 'Bold (700) — default', '800' => 'Extra-Bold (800)'] as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= ($catSection['name_weight'] ?? '700') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php designColorPicker('overlay_color', 'Card Overlay Color', 'Gradient shade behind the label — default black', $catSection['overlay_color'] ?? '#000000', '#000000') ?>
                </div>

                <h4 class="gdd-section-subtitle">Category Display Order</h4>
                <p style="color:#888;font-size:13px;margin-bottom:12px">Drag to reorder. All active categories appear; hidden ones are listed last.</p>
                <div id="catSortList" style="display:flex;flex-direction:column;gap:6px;max-width:480px">
                    <?php
                    // Build ordered list: admin-defined order first, then remaining
                    $adminOrder = $catSection['category_order'] ?? [];
                    $catMap = [];
                    foreach (($categories ?? []) as $c) { $catMap[$c['slug']] = $c; }
                    $orderedForAdmin = [];
                    foreach ($adminOrder as $slug) {
                        if (isset($catMap[$slug])) { $orderedForAdmin[] = $catMap[$slug]; unset($catMap[$slug]); }
                    }
                    foreach ($catMap as $c) { $orderedForAdmin[] = $c; }
                    ?>
                    <?php foreach ($orderedForAdmin as $c): ?>
                    <div class="cat-sort-item" data-slug="<?= e($c['slug']) ?>" style="display:flex;align-items:center;gap:10px;background:#f5f5f7;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;cursor:grab;user-select:none">
                        <span style="color:#aaa;font-size:18px">⠿</span>
                        <img src="<?= e(asset($c['image'] ?: '/images/GDKD logo.png')) ?>" style="width:32px;height:32px;border-radius:6px;object-fit:cover">
                        <span style="flex:1;font-weight:500"><?= e($c['name']) ?></span>
                        <input type="hidden" name="cat_order[]" value="<?= e($c['slug']) ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <script>
                (function() {
                    var list = document.getElementById('catSortList');
                    if (!list) return;
                    var dragging = null;
                    list.addEventListener('dragstart', function(e) {
                        dragging = e.target.closest('.cat-sort-item');
                        if (dragging) { dragging.style.opacity = '.4'; e.dataTransfer.effectAllowed = 'move'; }
                    });
                    list.addEventListener('dragend', function() {
                        if (dragging) dragging.style.opacity = '';
                        dragging = null;
                        // Re-sync hidden inputs to current DOM order
                        list.querySelectorAll('.cat-sort-item').forEach(function(item) {
                            item.querySelector('input[name="cat_order[]"]').value = item.dataset.slug;
                        });
                    });
                    list.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        var target = e.target.closest('.cat-sort-item');
                        if (target && target !== dragging) {
                            var rect = target.getBoundingClientRect();
                            var after = e.clientY > rect.top + rect.height / 2;
                            list.insertBefore(dragging, after ? target.nextSibling : target);
                        }
                    });
                    list.querySelectorAll('.cat-sort-item').forEach(function(item) {
                        item.setAttribute('draggable', 'true');
                    });
                })();
                </script>

                <div style="margin-top:20px">
                    <button type="submit" class="admin-btn admin-btn-primary">Save Shop by Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- How It Works -->
    <div class="admin-tab-pane" data-pane="howitworks">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="how_it_works">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($howItWorks['is_active']) || !empty($howItWorks['is_active'])) ? 'checked' : '' ?>>
                    Show "How It Works" section on homepage
                </label>

                <h4 class="gdd-section-subtitle">Section Content</h4>
                <div class="admin-form-row">
                    <label>Kicker
                        <input type="text" name="kicker" value="<?= e($howItWorks['kicker'] ?? 'Simple Process') ?>">
                    </label>
                    <label>Heading
                        <input type="text" name="heading" value="<?= e($howItWorks['heading'] ?? 'From idea to doorstep in 4 easy steps') ?>">
                    </label>
                </div>
                <label>Subtext <span class="admin-label-hint">Leave blank to hide</span>
                    <input type="text" name="subtext" value="<?= e($howItWorks['subtext'] ?? '') ?>">
                </label>

                <?php designAppearancePanel($howItWorks['style'] ?? []); ?>

                <h4 class="gdd-section-subtitle">Step Card Styling</h4>
                <div class="admin-form-row" style="align-items:stretch">
                    <?php designColorPicker('card_title_color', 'Step Title Color', 'The bold title on each step', $howItWorks['card_title_color'] ?? '#1d1d1f', '#1d1d1f'); ?>
                    <?php designColorPicker('card_text_color', 'Step Text Color', 'The description on each step', $howItWorks['card_text_color'] ?? '#6b7280', '#6b7280'); ?>
                </div>

                <h4 class="gdd-section-subtitle">Steps</h4>
                <div id="stepRepeater">
                    <?php foreach (($howItWorks['items'] ?? []) as $s): ?>
                        <div class="admin-option-row">
                            <label>Step Title<input type="text" name="step_title[]" value="<?= e($s['title'] ?? '') ?>"></label>
                            <label>Step Description<input type="text" name="step_desc[]" value="<?= e($s['desc'] ?? '') ?>"></label>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                        </div>
                    <?php endforeach; ?>
                    <div class="admin-option-row" data-repeater-template style="display:none;">
                        <label>Step Title<input type="text" name="step_title[__INDEX__]"></label>
                        <label>Step Description<input type="text" name="step_desc[__INDEX__]"></label>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" data-repeater-remove>Remove</button>
                    </div>
                </div>
                <button type="button" class="admin-btn" data-repeater-add="#stepRepeater">+ Add Step</button>
                <div class="admin-mt"><button type="submit" class="admin-btn admin-btn-primary">Save How It Works</button></div>
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
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($featuredSection['is_active']) ? 'checked' : '' ?>>
                    Show featured products on homepage
                </label>
                <h4 class="gdd-section-subtitle">Section Content</h4>
                <div class="admin-form-row">
                    <label>Kicker
                        <input type="text" name="kicker" value="<?= e($featuredSection['kicker'] ?? 'Trending Now') ?>">
                    </label>
                    <label>Heading
                        <input type="text" name="heading" value="<?= e($featuredSection['heading'] ?? 'Featured Gifts') ?>">
                    </label>
                </div>
                <label>Subtext
                    <input type="text" name="subtext" value="<?= e($featuredSection['subtext'] ?? 'Hand-picked favourites our customers love') ?>">
                </label>
                <?php designAppearancePanel($featuredSection['style'] ?? ['bg_color' => '#f8f9fb']); ?>
                <button type="submit" class="admin-btn admin-btn-primary">Save Featured Section</button>
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
                <?php designAppearancePanel($sigFeature['style'] ?? [], true, true); ?>
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
                <?php designAppearancePanel($badges['style'] ?? ['bg_color' => '#f8f9fb'], true, false); ?>
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
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($testimonials['is_active']) ? 'checked' : '' ?>>
                    Show testimonials
                </label>
                <div class="admin-form-row">
                    <label>Kicker
                        <input type="text" name="kicker" value="<?= e($testimonials['kicker'] ?? 'Loved By Many') ?>">
                    </label>
                    <label>Section Heading
                        <input type="text" name="heading" value="<?= e($testimonials['heading'] ?? 'What Our Customers Say') ?>">
                    </label>
                </div>
                <?php designAppearancePanel($testimonials['style'] ?? [], true, false); ?>
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

                <?php designAppearancePanel($igGallery['style'] ?? ['bg_color' => '#f8f9fb']); ?>

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

    <!-- Newsletter -->
    <div class="admin-tab-pane" data-pane="newsletter">
        <div class="admin-card">
            <form method="post" action="<?= url('/admin/design/save') ?>" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="newsletter">
                <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($newsletter['is_active']) || !empty($newsletter['is_active'])) ? 'checked' : '' ?>>
                    Show newsletter / CTA section on homepage
                </label>

                <h4 class="gdd-section-subtitle">Content</h4>
                <label>Heading
                    <input type="text" name="heading" value="<?= e($newsletter['heading'] ?? 'Get 10% off your first customised gift 🎉') ?>">
                </label>
                <label>Description
                    <textarea name="description" rows="2"><?= e($newsletter['description'] ?? 'Subscribe for festive offers, new design drops, and gifting inspiration — straight to your inbox.') ?></textarea>
                </label>
                <label>Button Text
                    <input type="text" name="button_text" value="<?= e($newsletter['button_text'] ?? 'Subscribe') ?>">
                </label>

                <h4 class="gdd-section-subtitle">Styling</h4>
                <div class="admin-form-row" style="align-items:stretch">
                    <?php designColorPicker('heading_color', 'Heading Color', 'Default white (sits on coloured panel)', $newsletter['heading_color'] ?? '#ffffff', '#ffffff'); ?>
                    <?php designColorPicker('text_color', 'Description Text Color', 'Default white', $newsletter['text_color'] ?? '#ffffff', '#ffffff'); ?>
                    <?php
                    $nlBg = trim((string)($newsletter['bg_color'] ?? ''));
                    designColorPicker('bg_color', 'Panel Background', 'Solid colour — only used if gradient is off', $nlBg ?: '#e63946', '#e63946');
                    ?>
                </div>
                <label class="admin-checkbox" style="margin-top:4px">
                    <input type="checkbox" id="nlGradient" <?= $nlBg === '' ? 'checked' : '' ?>>
                    Use the theme gradient for the panel background (default)
                </label>
                <p class="admin-help">When ticked, the panel keeps the brand gradient and the solid colour above is ignored.</p>
                <script>
                document.querySelector('[name="section"][value="newsletter"]').closest('form').addEventListener('submit', function() {
                    if (document.getElementById('nlGradient').checked) {
                        // Disable so the field is omitted entirely → server stores empty (gradient)
                        this.querySelector('[name="bg_color"]').disabled = true;
                    }
                });
                </script>
                <div class="admin-mt"><button type="submit" class="admin-btn admin-btn-primary">Save Newsletter</button></div>
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

    <!-- ============================================================
         PAGE LAYOUT — drag-and-drop section order
         ============================================================ -->
    <div class="admin-tab-pane" data-pane="layout">
        <div class="admin-card">
            <h3 style="margin:0 0 6px">Page Layout</h3>
            <p style="margin:0 0 20px;color:#6b7280;font-size:14px">Drag the sections below to reorder them on the homepage. Sections that are toggled off in their own tabs will still be hidden even if they appear here.</p>

            <?php
            $_layoutDefault = [
                'hero_banner'               => 'Hero Banner',
                'marquee_strip'             => 'Marquee Strip',
                'why_choose_us'             => 'Why Choose Us',
                'shop_by_category'          => 'Shop by Category',
                'how_it_works'              => 'How It Works',
                'featured_products_section' => 'Featured Products',
                'signature_feature'         => 'Signature Feature / QR',
                'trust_badges'              => 'Trust Badges',
                'testimonials_section'      => 'Testimonials',
                'instagram_gallery'         => 'Instagram Gallery',
                'newsletter'                => 'Newsletter / CTA',
            ];
            // Build ordered list: saved order first, then any not yet saved
            $_layoutOrdered = [];
            foreach (($savedSectionOrder ?? []) as $_lk) {
                if (isset($_layoutDefault[$_lk])) {
                    $_layoutOrdered[$_lk] = $_layoutDefault[$_lk];
                }
            }
            foreach ($_layoutDefault as $_lk => $_lv) {
                if (!isset($_layoutOrdered[$_lk])) $_layoutOrdered[$_lk] = $_lv;
            }
            ?>

            <form method="post" action="<?= url('/admin/design/layout/save') ?>" id="layoutForm">
                <?= csrfField() ?>
                <ul id="layoutSortable" style="list-style:none;margin:0 0 24px;padding:0;display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($_layoutOrdered as $_lk => $_lv): ?>
                    <li data-key="<?= e($_lk) ?>"
                        style="display:flex;align-items:center;gap:12px;background:#f8f9fb;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;cursor:grab;user-select:none">
                        <span style="color:#9ca3af;font-size:18px;line-height:1">⠿</span>
                        <span style="font-weight:600;font-size:14px;flex:1"><?= e($_lv) ?></span>
                        <input type="hidden" name="section_order[]" value="<?= e($_lk) ?>">
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                    <button type="submit" class="admin-btn admin-btn-primary">Save Layout</button>
                    <button type="button" id="layoutResetBtn" class="admin-btn" style="background:#f3f4f6;color:#374151">Reset to Default</button>
                </div>
            </form>

            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
            <script>
            (function () {
                var list = document.getElementById('layoutSortable');
                if (!list || typeof Sortable === 'undefined') return;

                Sortable.create(list, {
                    animation: 150,
                    handle: 'li',
                    ghostClass: 'gdd-layout-ghost',
                    onEnd: function () {
                        // Re-sync hidden input values to current DOM order
                        list.querySelectorAll('li').forEach(function (li) {
                            li.querySelector('input[type=hidden]').value = li.dataset.key;
                        });
                    }
                });

                // Reset to default order
                var defaultOrder = <?= json_encode(array_keys($_layoutDefault)) ?>;
                document.getElementById('layoutResetBtn').addEventListener('click', function () {
                    var items = {};
                    list.querySelectorAll('li').forEach(function (li) { items[li.dataset.key] = li; });
                    defaultOrder.forEach(function (key) {
                        if (items[key]) list.appendChild(items[key]);
                    });
                    // Re-sync hidden inputs
                    list.querySelectorAll('li').forEach(function (li) {
                        li.querySelector('input[type=hidden]').value = li.dataset.key;
                    });
                });
            })();
            </script>
            <style>
            .gdd-layout-ghost { opacity: .4; background: #e0e7ff !important; border-color: #6366f1 !important; }
            #layoutSortable li:active { cursor: grabbing; }
            #layoutSortable li:hover { border-color: #6366f1; background: #f5f3ff; }
            </style>
        </div>
    </div>
</div>
