<?php

class AdminDesignController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $settings = new Settings();

        $categories = Database::getInstance()
            ->query('SELECT slug, name, image FROM categories WHERE is_active = 1 ORDER BY sort_order, name')
            ->fetchAll();

        $this->viewAdmin('admin/design_index', [
            'metaTitle'  => 'Design Editor',
            'settings'   => $settings->getAll(),
            'sections'   => $this->loadSections(),
            'categories' => $categories,
        ]);
    }

    public function save(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $settings = new Settings();

        $section = (string)$this->input('section', '');

        // Standard appearance/style block, posted as style[...] by designAppearancePanel()
        $style = $this->collectStyle();

        switch ($section) {
            case 'branding':
                $settings->setMany([
                    'site_name'          => trim((string)$this->input('site_name')),
                    'site_tagline'       => trim((string)$this->input('site_tagline')),
                    'primary_color'      => trim((string)$this->input('primary_color', '#e63946')),
                    'accent_color'       => trim((string)$this->input('accent_color', '#457b9d')),
                    'search_placeholders' => trim((string)$this->input('search_placeholders', "Search personalised gifts…\nTry \"photo frame\" or \"mug\"…")),
                ]);
                if (!empty($_FILES['logo']['name'])) {
                    $path = $this->handleImageUpload($_FILES['logo'], 'branding', 'logo');
                    if ($path) $settings->set('logo_path', $path);
                }
                break;

            case 'hero_banner':
                $existing = $this->sectionContent('hero_banner');
                $imagePath = $existing['image'] ?? '';
                if (!empty($_FILES['hero_image']['name'])) {
                    $uploaded = $this->handleImageUpload($_FILES['hero_image'], 'sections', 'hero');
                    if ($uploaded) $imagePath = $uploaded;
                }

                // Hero split panel photos (left / right)
                $transformPhotos = [];
                foreach (['left', 'right'] as $slot) {
                    $photoKey = 'transform_' . $slot . '_photo';
                    $current  = trim((string)($_POST[$photoKey . '_existing'] ?? ($existing[$photoKey] ?? '')));
                    if (!empty($_FILES[$photoKey]['name']) && $_FILES[$photoKey]['error'] === UPLOAD_ERR_OK) {
                        $up = $this->handleImageUpload($_FILES[$photoKey], 'sections', 'hero_' . $slot);
                        if ($up) $current = $up;
                    }
                    $transformPhotos[$photoKey] = $current;
                }

                $this->saveSection('hero_banner', array_merge([
                    'headline'    => trim((string)$this->input('headline')),
                    'subheadline' => trim((string)$this->input('subheadline')),
                    'cta_text'    => trim((string)$this->input('cta_text')),
                    'cta_url'     => trim((string)$this->input('cta_url')),
                    'image'       => $imagePath,
                    'is_active'   => $this->input('is_active') ? true : false,
                ], $transformPhotos));
                break;

            case 'promo_strip':
                $this->saveSection('promo_strip', [
                    'text' => trim((string)$this->input('text')),
                    'is_active' => $this->input('is_active') ? true : false,
                ]);
                break;

            case 'featured_products_section':
                $this->saveSection('featured_products_section', [
                    'heading'  => trim((string)$this->input('heading')),
                    'kicker'   => trim((string)$this->input('kicker', 'Trending Now')),
                    'subtext'  => trim((string)$this->input('subtext', 'Hand-picked favourites our customers love')),
                    'is_active' => $this->input('is_active') ? true : false,
                    'style'    => $style,
                ]);
                break;

            case 'signature_feature':
                $existingSig = $this->sectionContent('signature_feature');
                $sigImage = trim((string)($_POST['sig_image_existing'] ?? ($existingSig['image'] ?? '')));
                if (!empty($_FILES['sig_image']['name']) && $_FILES['sig_image']['error'] === UPLOAD_ERR_OK) {
                    $up = $this->handleImageUpload($_FILES['sig_image'], 'sections', 'signature');
                    if ($up) $sigImage = $up;
                }
                $rawSteps = (array)($_POST['steps'] ?? []);
                $steps = [];
                foreach ($rawSteps as $s) {
                    $s = trim((string)$s);
                    if ($s !== '') $steps[] = $s;
                }
                $this->saveSection('signature_feature', [
                    'kicker'      => trim((string)$this->input('kicker', 'Signature Feature')),
                    'heading'     => trim((string)$this->input('heading')),
                    'description' => trim((string)$this->input('description')),
                    'cta_text'    => trim((string)$this->input('cta_text')),
                    'cta_url'     => trim((string)$this->input('cta_url')),
                    'steps'       => $steps,
                    'image'       => $sigImage,
                    'is_active'   => $this->input('is_active') ? true : false,
                    'style'       => $style,
                ]);
                break;

            case 'trust_badges':
                $items = [];
                $icons = (array)($_POST['badge_icon'] ?? []);
                $titles = (array)($_POST['badge_title'] ?? []);
                $descs = (array)($_POST['badge_desc'] ?? []);
                foreach ($titles as $i => $title) {
                    $title = trim((string)$title);
                    if ($title === '') continue;
                    $items[] = ['icon' => trim((string)($icons[$i] ?? '')), 'title' => $title, 'desc' => trim((string)($descs[$i] ?? ''))];
                }
                $this->saveSection('trust_badges', [
                    'is_active' => $this->input('is_active') ? true : false,
                    'items' => $items,
                    'style' => $style,
                ]);
                break;

            case 'topbar_buttons':
                $items = [];
                $labels = (array)($_POST['tb_label'] ?? []);
                $urls   = (array)($_POST['tb_url']   ?? []);
                $emojis = (array)($_POST['tb_emoji'] ?? []);
                $existingImages = (array)($_POST['tb_existing_image'] ?? []);
                $files = $_FILES['tb_image'] ?? null;
                foreach ($labels as $i => $label) {
                    $label = trim((string)$label);
                    $url   = trim((string)($urls[$i] ?? ''));
                    if ($label === '' && $url === '') continue;
                    $image = trim((string)($existingImages[$i] ?? ''));
                    if ($files && isset($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                        $singleFile = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];
                        $uploaded = $this->handleImageUpload($singleFile, 'topbar', 'btn');
                        if ($uploaded) $image = $uploaded;
                    }
                    $items[] = [
                        'label' => $label,
                        'url'   => $url ?: '#',
                        'emoji' => trim((string)($emojis[$i] ?? '')),
                        'image' => $image,
                    ];
                }
                $this->saveSection('topbar_buttons', [
                    'is_active' => $this->input('is_active') ? true : false,
                    'items' => $items,
                ]);
                break;

            case 'testimonials_section':
                $items = [];
                $names = (array)($_POST['testi_name'] ?? []);
                $texts = (array)($_POST['testi_text'] ?? []);
                $ratings = (array)($_POST['testi_rating'] ?? []);
                foreach ($names as $i => $name) {
                    $name = trim((string)$name);
                    if ($name === '') continue;
                    $items[] = ['name' => $name, 'text' => trim((string)($texts[$i] ?? '')), 'rating' => (int)($ratings[$i] ?? 5), 'avatar' => ''];
                }
                $this->saveSection('testimonials_section', [
                    'heading' => trim((string)$this->input('heading')),
                    'kicker'  => trim((string)$this->input('kicker', 'Loved By Many')),
                    'is_active' => $this->input('is_active') ? true : false,
                    'items' => $items,
                    'style' => $style,
                ]);
                break;

            case 'instagram_gallery':
                $existingIg = $this->sectionContent('instagram_gallery');
                $existingItems = $existingIg['items'] ?? [];
                $files = $_FILES['ig_image'] ?? null;
                $captions = (array)($_POST['ig_caption'] ?? []);
                $links    = (array)($_POST['ig_link'] ?? []);
                $existingImages = (array)($_POST['ig_existing'] ?? []);
                $items = [];
                for ($i = 0; $i < 6; $i++) {
                    $image = trim((string)($existingImages[$i] ?? ($existingItems[$i]['image'] ?? '')));
                    if ($files && isset($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                        $singleFile = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];
                        $up = $this->handleImageUpload($singleFile, 'gallery', 'ig_' . $i);
                        if ($up) $image = $up;
                    }
                    $items[] = [
                        'image'   => $image,
                        'caption' => trim((string)($captions[$i] ?? '')),
                        'link'    => trim((string)($links[$i] ?? '')),
                    ];
                }
                $this->saveSection('instagram_gallery', [
                    'kicker'    => trim((string)$this->input('kicker', '#GiftDekeDekhoMoments')),
                    'heading'   => trim((string)$this->input('heading', 'Real gifts, real smiles')),
                    'subtext'   => trim((string)$this->input('subtext', '')),
                    'is_active' => $this->input('is_active') ? true : false,
                    'items'     => $items,
                    'style'     => $style,
                ]);
                break;

            case 'footer':
                $settings->setMany([
                    'footer_copyright' => trim((string)$this->input('footer_copyright')),
                    'social_facebook' => trim((string)$this->input('social_facebook')),
                    'social_instagram' => trim((string)$this->input('social_instagram')),
                    'social_twitter' => trim((string)$this->input('social_twitter')),
                    'social_youtube' => trim((string)$this->input('social_youtube')),
                    'whatsapp_number' => trim((string)$this->input('whatsapp_number')),
                    'site_email' => trim((string)$this->input('site_email')),
                    'site_phone' => trim((string)$this->input('site_phone')),
                    'site_address' => trim((string)$this->input('site_address')),
                ]);
                break;

            case 'nav_category_bar':
                $slugs   = (array)($_POST['nav_slug']    ?? []);
                $labels  = (array)($_POST['nav_label']   ?? []);
                $emojis  = (array)($_POST['nav_emoji']   ?? []);
                $visible = (array)($_POST['nav_visible'] ?? []);
                $items = [];
                foreach ($slugs as $i => $slug) {
                    $slug = trim((string)$slug);
                    if ($slug === '') continue;
                    $items[] = [
                        'slug'    => $slug,
                        'label'   => trim((string)($labels[$i] ?? '')),
                        'emoji'   => trim((string)($emojis[$i] ?? '')),
                        'visible' => isset($visible[$i]),
                    ];
                }
                $maxItems = (int)$this->input('max_items', 0);
                $this->saveSection('nav_category_bar', [
                    'is_active'      => $this->input('is_active') ? true : false,
                    'show_home'      => $this->input('show_home') ? true : false,
                    'show_all_gifts' => $this->input('show_all_gifts') ? true : false,
                    'max_items'      => $maxItems > 0 ? $maxItems : 0,
                    'items'          => $items,
                ]);
                break;

            case 'about_us':
                $settings->set('about_us_text', (string)$this->input('about_us_text', ''));
                break;

            case 'page_theme':
                $settings->setMany([
                    'primary_color'  => trim((string)$this->input('primary_color', '#e63946')),
                    'accent_color'   => trim((string)$this->input('accent_color',  '#457b9d')),
                    'color_text'     => trim((string)$this->input('color_text',    '#1d1d1f')),
                    'color_muted'    => trim((string)$this->input('color_muted',   '#6b7280')),
                    'color_bg'       => trim((string)$this->input('color_bg',      '#ffffff')),
                    'color_bg_alt'   => trim((string)$this->input('color_bg_alt',  '#f8f9fb')),
                    'color_border'   => trim((string)$this->input('color_border',  '#e5e7eb')),
                ]);
                break;

            case 'marquee_strip':
                $this->saveSection('marquee_strip', [
                    'text'        => trim((string)$this->input('text')),
                    'bg_color'    => trim((string)$this->input('bg_color',    '')),
                    'text_color'  => trim((string)$this->input('text_color',  '')),
                    'font_size'   => trim((string)$this->input('font_size',   '14')),
                    'font_weight' => trim((string)$this->input('font_weight', '700')),
                    'speed'       => trim((string)$this->input('speed',       '26')),
                    'is_active'   => $this->input('is_active') ? true : false,
                ]);
                break;

            case 'shop_by_category':
                $orderSlugs = array_values(array_filter(array_map('trim', (array)($_POST['cat_order'] ?? []))));
                $this->saveSection('shop_by_category', [
                    'is_active'      => $this->input('is_active') ? true : false,
                    'heading'        => trim((string)$this->input('heading',        'Shop by Category')),
                    'subtext'        => trim((string)$this->input('subtext',        'Find the perfect personalised gift for every occasion')),
                    'kicker'         => trim((string)$this->input('kicker',         'Browse')),
                    'style'          => $style,
                    // Card-label specific styling
                    'name_align'     => in_array($this->input('name_align', 'left'), ['left','center','right'], true) ? $this->input('name_align', 'left') : 'left',
                    'name_color'     => trim((string)$this->input('name_color',  '#ffffff')),
                    'name_size'      => trim((string)$this->input('name_size',   '15')),
                    'name_weight'    => trim((string)$this->input('name_weight', '700')),
                    'overlay_color'  => trim((string)$this->input('overlay_color', '#000000')),
                    'category_order' => $orderSlugs,
                ]);
                break;

            case 'why_choose_us':
                $icons  = (array)($_POST['usp_icon'] ?? []);
                $titles = (array)($_POST['usp_title'] ?? []);
                $descs  = (array)($_POST['usp_desc'] ?? []);
                $items = [];
                foreach ($titles as $i => $title) {
                    $title = trim((string)$title);
                    if ($title === '') continue;
                    $items[] = [
                        'icon'  => trim((string)($icons[$i] ?? '')),
                        'title' => $title,
                        'desc'  => trim((string)($descs[$i] ?? '')),
                    ];
                }
                $this->saveSection('why_choose_us', [
                    'is_active' => $this->input('is_active') ? true : false,
                    'kicker'    => trim((string)$this->input('kicker',  'Why GiftDekeDekho')),
                    'heading'   => trim((string)$this->input('heading', 'Crafted with care, delivered with a smile')),
                    'subtext'   => trim((string)$this->input('subtext', 'Every order is handmade-to-order — no two gifts are exactly alike')),
                    'card_title_color' => trim((string)$this->input('card_title_color', '#1d1d1f')),
                    'card_text_color'  => trim((string)$this->input('card_text_color',  '#6b7280')),
                    'card_align'       => in_array($this->input('card_align', 'left'), ['left','center','right'], true) ? $this->input('card_align', 'left') : 'left',
                    'items'     => $items,
                    'style'     => $style,
                ]);
                break;

            case 'how_it_works':
                $titles = (array)($_POST['step_title'] ?? []);
                $descs  = (array)($_POST['step_desc'] ?? []);
                $items = [];
                foreach ($titles as $i => $title) {
                    $title = trim((string)$title);
                    if ($title === '') continue;
                    $items[] = ['title' => $title, 'desc' => trim((string)($descs[$i] ?? ''))];
                }
                $this->saveSection('how_it_works', [
                    'is_active' => $this->input('is_active') ? true : false,
                    'kicker'    => trim((string)$this->input('kicker',  'Simple Process')),
                    'heading'   => trim((string)$this->input('heading', 'From idea to doorstep in 4 easy steps')),
                    'subtext'   => trim((string)$this->input('subtext', '')),
                    'card_title_color' => trim((string)$this->input('card_title_color', '#1d1d1f')),
                    'card_text_color'  => trim((string)$this->input('card_text_color',  '#6b7280')),
                    'items'     => $items,
                    'style'     => $style,
                ]);
                break;

            case 'newsletter':
                $this->saveSection('newsletter', [
                    'is_active'   => $this->input('is_active') ? true : false,
                    'heading'     => trim((string)$this->input('heading', 'Get 10% off your first customised gift 🎉')),
                    'description' => trim((string)$this->input('description', 'Subscribe for festive offers, new design drops, and gifting inspiration — straight to your inbox.')),
                    'button_text' => trim((string)$this->input('button_text', 'Subscribe')),
                    'heading_color' => trim((string)$this->input('heading_color', '#ffffff')),
                    'text_color'    => trim((string)$this->input('text_color',    '#ffffff')),
                    'bg_color'      => trim((string)$this->input('bg_color',      '')),
                ]);
                break;
        }

        flash('success', 'Design changes saved successfully.');
        redirect('/admin/design');
    }

    /** Normalises the posted style[...] appearance block into a clean array. */
    private function collectStyle(): array
    {
        $raw = (array)($_POST['style'] ?? []);
        $hex = function ($v, $fallback = '') {
            $v = trim((string)$v);
            return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $fallback;
        };
        $num = function ($v) {
            $v = trim((string)$v);
            return ($v !== '' && is_numeric($v)) ? (string)(int)$v : '';
        };
        $align = in_array($raw['align'] ?? '', ['left', 'center', 'right'], true) ? $raw['align'] : 'center';
        return [
            'align'         => $align,
            'kicker_color'  => $hex($raw['kicker_color']  ?? '', '#e63946'),
            'heading_color' => $hex($raw['heading_color'] ?? '', '#1d1d1f'),
            'heading_size'  => $num($raw['heading_size']  ?? ''),
            'subtext_color' => $hex($raw['subtext_color'] ?? '', '#6b7280'),
            'subtext_size'  => $num($raw['subtext_size']  ?? ''),
            'bg_color'      => $hex($raw['bg_color']      ?? '', ''),
        ];
    }

    private function sectionContent(string $key): array
    {
        $stmt = Database::getInstance()->prepare('SELECT content_json FROM site_sections WHERE section_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (json_decode($row['content_json'], true) ?: []) : [];
    }

    private function saveSection(string $key, array $content): void
    {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO site_sections (section_key, content_json) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE content_json = VALUES(content_json)'
        );
        $stmt->execute([$key, json_encode($content, JSON_UNESCAPED_UNICODE)]);
    }

    private function loadSections(): array
    {
        $rows = Database::getInstance()->query('SELECT section_key, content_json FROM site_sections')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['section_key']] = json_decode($row['content_json'], true) ?: [];
        }
        // Inject defaults for sections not yet saved to DB so admin always has something visible
        foreach ($this->getSectionDefaults() as $key => $defaults) {
            if (!isset($out[$key])) {
                $out[$key] = $defaults;
            }
        }
        return $out;
    }

    private function getSectionDefaults(): array
    {
        return [
            'hero_banner' => [
                'headline'    => '',
                'subheadline' => 'Photo frames, engraved keepsakes, custom mugs & video-message gifts — designed by you, crafted by us, delivered with love anywhere in India.',
                'cta_text'    => 'Start Customising',
                'cta_url'     => '/category/all',
                'is_active'   => true,
            ],
            'promo_strip' => [
                'text'      => '✨ Perfect Gifting Made Simple',
                'is_active' => true,
            ],
            'featured_products_section' => [
                'heading'   => 'Featured Gifts',
                'is_active' => true,
            ],
            'signature_feature' => [
                'kicker'      => 'Signature Feature',
                'heading'     => 'Turn any gift into a Video & Photo Memory',
                'description' => 'Attach a scannable QR code to your gift — recipients scan it with any phone camera to unlock a private video or photo message from you. No app required.',
                'cta_text'    => 'Explore Video & Photo Gifts →',
                'cta_url'     => '/category/video-photo-gifts',
                'steps'       => [
                    'Upload your video/photo message while placing the order',
                    'We generate a unique, secure QR code for your gift',
                    'Recipient scans the QR printed on the packaging',
                    'Your personal message plays instantly — straight from the heart',
                ],
                'is_active'   => true,
            ],
            'trust_badges' => [
                'is_active' => true,
                'items'     => [
                    ['icon' => '🔒', 'title' => 'Secure Payments',   'desc' => 'Razorpay, UPI, Cards & COD — pay your way'],
                    ['icon' => '🚚', 'title' => 'Pan-India Delivery', 'desc' => 'Fast dispatch, real-time tracking'],
                    ['icon' => '🎨', 'title' => 'Fully Personalised', 'desc' => 'Every item made just for you'],
                    ['icon' => '💬', 'title' => '24/7 Support',       'desc' => 'Friendly help whenever you need it'],
                ],
            ],
            'testimonials_section' => [
                'heading'   => 'What Our Customers Say',
                'is_active' => true,
                'items'     => [
                    ['name' => 'Priya Sharma', 'rating' => 5, 'text' => 'The photo frame I customised for my parents\' anniversary was stunning — exactly like the preview! Delivery was quick too.'],
                    ['name' => 'Rahul Mehta',  'rating' => 5, 'text' => 'Sent a video-message keychain to my best friend abroad. He scanned the QR and got emotional instantly. Magical experience!'],
                    ['name' => 'Ananya Iyer',  'rating' => 4, 'text' => 'Beautiful engraving quality on the wooden mug. Packaging was premium and the order tracking kept me updated throughout.'],
                ],
            ],
            'topbar_buttons' => [
                'is_active' => true,
                'items'     => [
                    ['label' => 'Video & Photo QR Gifts', 'url' => '/category/video-photo-gifts', 'emoji' => '🎬', 'image' => ''],
                    ['label' => 'Track Order',            'url' => '/account/orders',             'emoji' => '📦', 'image' => ''],
                    ['label' => 'Help & Support',         'url' => '/contact',                    'emoji' => '💬', 'image' => ''],
                ],
            ],
            'instagram_gallery' => [
                'kicker'    => '#GiftDekeDekhoMoments',
                'heading'   => 'Real gifts, real smiles',
                'subtext'   => 'Tag @giftdekedekho on Instagram for a chance to be featured here',
                'is_active' => true,
                'items'     => [],
            ],
            'nav_category_bar' => [
                'is_active'      => true,
                'show_home'      => true,
                'show_all_gifts' => true,
                'max_items'      => 8,
                'items'          => [],
            ],
            'marquee_strip' => [
                'text'        => '🎁 PERSONALISED PHOTO FRAMES <em>•</em> ENGRAVED JEWELLERY <em>•</em> CUSTOM MUGS &amp; CUSHIONS <em>•</em> VIDEO &amp; PHOTO QR GIFTS <em>•</em> SAME-DAY DISPATCH <em>•</em> COD AVAILABLE <em>•</em>',
                'bg_color'    => '',
                'text_color'  => '',
                'font_size'   => '14',
                'font_weight' => '700',
                'speed'       => '26',
                'is_active'   => true,
            ],
            'shop_by_category' => [
                'is_active'      => true,
                'heading'        => 'Shop by Category',
                'subtext'        => 'Find the perfect personalised gift for every occasion',
                'kicker'         => 'Browse',
                'style'          => array_merge(sectionStyleDefaults(), ['bg_color' => '#f8f9fb']),
                'name_align'     => 'left',
                'name_color'     => '#ffffff',
                'name_size'      => '15',
                'name_weight'    => '700',
                'overlay_color'  => '#000000',
                'category_order' => [],
            ],
            'why_choose_us' => [
                'is_active' => true,
                'kicker'    => 'Why GiftDekeDekho',
                'heading'   => 'Crafted with care, delivered with a smile',
                'subtext'   => 'Every order is handmade-to-order — no two gifts are exactly alike',
                'card_title_color' => '#1d1d1f',
                'card_text_color'  => '#6b7280',
                'card_align'       => 'left',
                'style'     => sectionStyleDefaults(),
                'items'     => [
                    ['icon' => '🎨', 'title' => 'Fully Personalised', 'desc' => 'Add names, photos, dates & messages with our live preview customiser.'],
                    ['icon' => '📦', 'title' => 'Pan-India Delivery', 'desc' => 'Reliable doorstep delivery across India with real-time order tracking.'],
                    ['icon' => '💳', 'title' => 'Secure Payments',    'desc' => 'Razorpay, PayPal, Stripe & Cash on Delivery — pay your way, safely.'],
                    ['icon' => '💬', 'title' => 'Friendly Support',   'desc' => 'Real humans ready to help with design tweaks, tracking & returns.'],
                ],
            ],
            'how_it_works' => [
                'is_active' => true,
                'kicker'    => 'Simple Process',
                'heading'   => 'From idea to doorstep in 4 easy steps',
                'subtext'   => '',
                'card_title_color' => '#1d1d1f',
                'card_text_color'  => '#6b7280',
                'style'     => sectionStyleDefaults(),
                'items'     => [
                    ['title' => 'Pick a Gift',          'desc' => 'Browse frames, mugs, jewellery, cushions & more.'],
                    ['title' => 'Personalise It',       'desc' => 'Add photos, names, engravings or a video message.'],
                    ['title' => 'We Craft & Pack',      'desc' => 'Our artisans make it by hand and pack it with care.'],
                    ['title' => 'You Receive & Smile',  'desc' => 'Track your order and get it delivered to your door.'],
                ],
            ],
            'newsletter' => [
                'is_active'     => true,
                'heading'       => 'Get 10% off your first customised gift 🎉',
                'description'   => 'Subscribe for festive offers, new design drops, and gifting inspiration — straight to your inbox.',
                'button_text'   => 'Subscribe',
                'heading_color' => '#ffffff',
                'text_color'    => '#ffffff',
                'bg_color'      => '',
            ],
        ];
    }

    private function handleImageUpload(array $file, string $folder, string $prefix): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) return null;

        $dir = UPLOAD_PATH . '/' . $folder;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            flash('error', 'Upload folder is not writable: ' . $folder . '. Please give the web server write access to public/uploads.');
            return null;
        }

        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            flash('error', 'Could not save the uploaded image. Please check folder permissions on public/uploads.');
            return null;
        }

        return UPLOAD_URL . '/' . $folder . '/' . $filename;
    }
}
