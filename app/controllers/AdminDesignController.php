<?php

class AdminDesignController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $settings = new Settings();

        $this->viewAdmin('admin/design_index', [
            'metaTitle' => 'Design Editor',
            'settings' => $settings->getAll(),
            'sections' => $this->loadSections(),
        ]);
    }

    public function save(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $settings = new Settings();

        $section = (string)$this->input('section', '');

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
                    'heading' => trim((string)$this->input('heading')),
                    'is_active' => $this->input('is_active') ? true : false,
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
                ]);
                break;

            case 'topbar_buttons':
                $items = [];
                $labels = (array)($_POST['tb_label'] ?? []);
                $urls = (array)($_POST['tb_url'] ?? []);
                $existingImages = (array)($_POST['tb_existing_image'] ?? []);
                $files = $_FILES['tb_image'] ?? null;
                foreach ($labels as $i => $label) {
                    $label = trim((string)$label);
                    $url = trim((string)($urls[$i] ?? ''));
                    if ($label === '' && $url === '') continue;
                    $image = trim((string)($existingImages[$i] ?? ''));
                    if ($files && isset($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                        $singleFile = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i],
                        ];
                        $uploaded = $this->handleImageUpload($singleFile, 'topbar', 'btn');
                        if ($uploaded) $image = $uploaded;
                    }
                    $items[] = ['label' => $label, 'url' => $url ?: '#', 'image' => $image];
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
                    'is_active' => $this->input('is_active') ? true : false,
                    'items' => $items,
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

            case 'about_us':
                $settings->set('about_us_text', (string)$this->input('about_us_text', ''));
                break;
        }

        flash('success', 'Design changes saved successfully.');
        redirect('/admin/design');
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
        return $out;
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
