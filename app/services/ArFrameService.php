<?php
/**
 * Shared pipeline for Living Photo AR frames.
 *
 * Both sales channels come through here — the online queue and the walk-in
 * Quick Create form — so photo handling, video validation, slug allocation and
 * target generation exist exactly once. The only difference between channels is
 * *when* generateTarget() is called: immediately at the counter for walk-ins,
 * whenever an admin works the queue for online orders.
 *
 * A frame is the gift and its QR sticker. What the recipient points the camera
 * at are its items — one or more photos, each playing its own video. Each item
 * compiles to its own target; the frame's target is those merged into one file,
 * because the scan page can load only one, and `target_items` records which item
 * is at which index of it.
 */
class ArFrameService
{
    public const PHOTO_DIR = 'ar-photos';
    public const TARGET_DIR = 'ar-targets';
    public const VIDEO_DIR = 'videos';

    public const MAX_PHOTO_BYTES = 10 * 1024 * 1024;   // 10MB
    public const MAX_VIDEO_BYTES = 100 * 1024 * 1024;  // 100MB, matches the existing QR video limit

    /** Limits on what a customer can upload from the product page. */
    public const MAX_CUSTOMER_VIDEO_BYTES = 20 * 1024 * 1024;  // 20MB each
    public const MAX_CUSTOMER_ITEMS = 5;

    private const PHOTO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
    ];

    private const VIDEO_MIMES = [
        'video/mp4'       => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm'      => 'webm',
    ];

    /**
     * Hosts we accept for a "youtube" video. Anything else is rejected rather
     * than stored, because this URL ends up embedded in a public page.
     */
    private const YOUTUBE_HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com',
        'youtu.be', 'www.youtu.be',
        'youtube-nocookie.com', 'www.youtube-nocookie.com',
    ];

    /** Columns that describe one photo/video pair rather than the frame as a whole. */
    private const ITEM_COLUMNS = [
        'photo_path', 'target_path', 'video_type', 'video_url', 'video_path', 'playback_mode',
        'trackability_score', 'trackability_flag', 'trackability_json', 'verified_at',
    ];

    /**
     * Item columns that exist only once the partner migration has run. Kept out
     * of ITEM_COLUMNS because a frame has a `title` of its own, which
     * createFrame() must not mistake for an item's.
     */
    private const ITEM_EXTRA_COLUMNS = ['title', 'max_seconds'];

    /** Statuses the pipeline may still move a frame between on its own. */
    private const PRE_PRINT_STATUSES = ['pending_setup', 'target_generated', 'verified'];

    private ArFrame $frames;
    private ArFrameItem $items;

    public function __construct()
    {
        $this->frames = new ArFrame();
        $this->items = new ArFrameItem();
    }

    // ---------------------------------------------------------------- uploads

    /**
     * Validate and store an uploaded customer photo.
     *
     * @param array $file One entry from $_FILES
     * @return array{ok: bool, path?: string, error?: string} path is relative to public/uploads
     */
    public function storePhoto(array $file): array
    {
        $error = $this->uploadError($file, self::MAX_PHOTO_BYTES, 'photo');
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!isset(self::PHOTO_MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Unsupported photo format. Please use a JPG or PNG.'];
        }

        // Re-check the pixel dimensions: a file can pass a MIME sniff and still
        // be unusable as a tracking target.
        $dimensions = @getimagesize($file['tmp_name']);
        if ($dimensions === false) {
            return ['ok' => false, 'error' => 'That file could not be read as an image.'];
        }
        if ($dimensions[0] < 240 || $dimensions[1] < 240) {
            return ['ok' => false, 'error' => 'That photo is too small to track reliably. Use one at least 240x240 pixels.'];
        }

        return $this->moveInto(self::PHOTO_DIR, 'arphoto_', self::PHOTO_MIMES[$mime], $file['tmp_name'], 'photo');
    }

    /**
     * Validate and store an uploaded video file.
     *
     * @return array{ok: bool, path?: string, error?: string}
     */
    public function storeVideo(array $file, int $maxBytes = self::MAX_VIDEO_BYTES): array
    {
        $error = $this->uploadError($file, $maxBytes, 'video');
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!isset(self::VIDEO_MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Unsupported video format. Please upload MP4, MOV or WebM.'];
        }

        return $this->moveInto(self::VIDEO_DIR, 'arvideo_', self::VIDEO_MIMES[$mime], $file['tmp_name'], 'video');
    }

    private function uploadError(array $file, int $maxBytes, string $label): ?string
    {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_NO_FILE) {
            return 'Please choose a ' . $label . ' file.';
        }
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return 'That ' . $label . ' is larger than the server allows.';
        }
        if ($code !== UPLOAD_ERR_OK) {
            return 'The ' . $label . ' upload failed. Please try again.';
        }
        if (($file['size'] ?? 0) <= 0) {
            return 'That ' . $label . ' file is empty.';
        }
        if ($file['size'] > $maxBytes) {
            return sprintf('That %s is too large. Maximum is %dMB.', $label, (int)round($maxBytes / 1048576));
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return 'The ' . $label . ' upload could not be verified.';
        }
        return null;
    }

    private function detectMime(string $tmpName): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string)finfo_file($finfo, $tmpName);
        return $mime;
    }

    private function moveInto(string $dir, string $prefix, string $ext, string $tmpName, string $label): array
    {
        $absDir = UPLOAD_PATH . '/' . $dir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create the upload directory.'];
        }

        $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmpName, $absDir . '/' . $filename)) {
            return ['ok' => false, 'error' => 'Could not save the uploaded ' . $label . '.'];
        }

        return ['ok' => true, 'path' => $dir . '/' . $filename];
    }

    // ----------------------------------------------------------------- videos

    private const VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

    /** Extensions accepted for a directly-linked video file. */
    private const DIRECT_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    /**
     * Work out what kind of video a pasted URL is, and reduce it to a safe
     * canonical form.
     *
     * One field for every source: the admin pastes a link and this decides
     * whether it is YouTube, Vimeo or a direct video file. Anything else is
     * rejected rather than stored, because this URL ends up on a public page.
     *
     * @return array{type: string, url: string, id?: string}|null
     */
    public function detectVideoSource(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $id = $this->youtubeId($url);
        if ($id !== null) {
            return ['type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=' . $id, 'id' => $id];
        }

        $vimeoId = $this->vimeoId($url);
        if ($vimeoId !== null) {
            return ['type' => 'vimeo', 'url' => 'https://vimeo.com/' . $vimeoId, 'id' => $vimeoId];
        }

        $direct = $this->directVideoUrl($url);
        if ($direct !== null) {
            return ['type' => 'direct', 'url' => $direct];
        }

        return null;
    }

    /**
     * Kept for the storefront and any caller that only wants YouTube.
     * Prefer detectVideoSource() for admin-facing input.
     */
    public function normaliseYoutubeUrl(string $url): ?string
    {
        $id = $this->youtubeId($url);
        return $id === null ? null : 'https://www.youtube.com/watch?v=' . $id;
    }

    /** Numeric Vimeo id, or null. */
    public function vimeoId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        if (!in_array(strtolower($parts['host']), self::VIMEO_HOSTS, true)) {
            return null;
        }

        // vimeo.com/123456789 and player.vimeo.com/video/123456789
        if (preg_match('#^/(?:video/)?(\d{6,12})#', $parts['path'] ?? '', $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * A direct link to a video file, or null.
     *
     * https only, and the path must end in a known video extension — this URL is
     * handed to a <video> element on a public page, so anything ambiguous is
     * refused rather than guessed at.
     */
    public function directVideoUrl(string $url): ?string
    {
        $url = trim($url);
        if (!preg_match('#^https://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            return null;
        }

        $extension = strtolower((string)pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, self::DIRECT_EXTENSIONS, true)) {
            return null;
        }

        // Rebuild from parsed parts so nothing unexpected (credentials, fragments)
        // survives into the page.
        $rebuilt = 'https://' . $parts['host']
            . (isset($parts['port']) ? ':' . (int)$parts['port'] : '')
            . $parts['path']
            . (isset($parts['query']) ? '?' . $parts['query'] : '');

        return filter_var($rebuilt, FILTER_VALIDATE_URL) ? $rebuilt : null;
    }

    /**
     * The browser-side descriptor for one photo: what to play and how.
     *
     * Built here rather than in each controller so the public scan page, the
     * scan-anything page and the admin live test cannot drift apart. Index order
     * in the caller's array is the anchor index MindAR reports on a match.
     *
     * @param array|null $item An item row (or, before the migration, a frame row —
     *                         the columns are the same). Null keeps an index that
     *                         no longer has an item behind it.
     */
    public function browserTarget(?array $item, string $slug): array
    {
        $playback = $item === null ? null : $this->playback($item);

        $metrics = !empty($item['trackability_json']) ? json_decode((string)$item['trackability_json'], true) : null;
        $aspect = null;
        if (is_array($metrics) && !empty($metrics['compiled_width']) && !empty($metrics['compiled_height'])) {
            $aspect = round((float)$metrics['compiled_width'] / (float)$metrics['compiled_height'], 4);
        }

        $target = [
            'slug' => $slug,
            'itemId' => (int)($item['id'] ?? 0),
            // The photo's own shape, for the overlay plane and the viewfinder.
            'aspect' => $aspect,
            'trackabilityFlag' => $item['trackability_flag'] ?? null,
            'playbackMode' => (string)($item['playback_mode'] ?? 'fullscreen'),
            // Partner content is sold by video length: playback stops here.
            'maxSeconds' => empty($item['max_seconds']) ? null : (int)$item['max_seconds'],
            'videoType' => null,
            'youtubeId' => null,
            'vimeoId' => null,
            'videoUrl' => null,
            'watchUrl' => null,
        ];

        // A recognised photo with no playable video is kept in the list so the
        // anchor indexes still line up with the compiled file.
        if ($playback === null) {
            return $target;
        }

        $playsInVideoElement = in_array($playback['type'], ['upload', 'direct'], true);

        return array_merge($target, [
            'videoType' => $playback['type'],
            'youtubeId' => $playback['youtube_id'] ?? null,
            'vimeoId' => $playback['vimeo_id'] ?? null,
            'videoUrl' => $playsInVideoElement ? $playback['url'] : null,
            // Somewhere to send the recipient if the embed refuses to play.
            'watchUrl' => $playback['url'],
        ]);
    }

    /** Human label for a stored video type. */
    public static function videoTypeLabel(string $type): string
    {
        switch ($type) {
            case 'youtube': return 'YouTube';
            case 'vimeo':   return 'Vimeo';
            case 'direct':  return 'Direct video link';
            case 'upload':  return 'Uploaded file';
            default:        return $type;
        }
    }

    public function youtubeId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        // Tolerate a pasted URL with no scheme.
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        if (!in_array(strtolower($parts['host']), self::YOUTUBE_HOSTS, true)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $candidate = null;

        if (stripos($parts['host'], 'youtu.be') !== false) {
            $candidate = ltrim($path, '/');
        } elseif ($path === '/watch') {
            parse_str($parts['query'] ?? '', $query);
            $candidate = $query['v'] ?? null;
        } elseif (preg_match('#^/(shorts|embed|v|live)/([^/?]+)#', $path, $m)) {
            $candidate = $m[2];
        }

        if (!is_string($candidate) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate)) {
            return null;
        }
        return $candidate;
    }

    // ------------------------------------------------------------ persistence

    /** Whether the multi-photo schema (ar_frame_items) has been migrated in. */
    public function itemsReady(): bool
    {
        return $this->frames->itemsReady();
    }

    /**
     * Create a frame row, allocating its public slug, together with its photos.
     *
     * Item columns may be given either in $items or, for a single photo, mixed
     * into $attrs — they are split out here. Before the multi-photo migration has
     * run there is nowhere to put a second photo, so the first one is stored on
     * the frame row the way it always was.
     *
     * @param array $attrs Frame columns; channel required.
     * @param array[] $items One array of item columns per photo.
     */
    public function createFrame(array $attrs, array $items = []): int
    {
        $inline = array_intersect_key($attrs, array_flip(self::ITEM_COLUMNS));
        $attrs = array_diff_key($attrs, $inline);
        if (!empty($inline['photo_path'])) {
            array_unshift($items, $inline);
        }

        $data = array_merge([
            'slug'          => $this->frames->generateUniqueSlug(),
            'channel'       => 'online',
            'status'        => 'pending_setup',
            'is_active'     => 1,
        ], $attrs);

        if (!$this->itemsReady()) {
            return $this->frames->create(array_merge(
                ['video_type' => 'youtube', 'playback_mode' => 'fullscreen'],
                $data,
                $items[0] ?? []
            ));
        }

        $frameId = $this->frames->create($data);
        foreach ($items as $item) {
            $this->addItem($frameId, $item);
        }
        return $frameId;
    }

    /** Attach one photo/video pair to a frame. Its target still has to be generated. */
    public function addItem(int $frameId, array $attrs): int
    {
        $data = array_intersect_key($attrs, array_flip(array_merge(self::ITEM_COLUMNS, self::ITEM_EXTRA_COLUMNS, ['sort_order'])));
        return $this->items->create(array_merge(
            ['video_type' => 'youtube', 'playback_mode' => 'fullscreen'],
            $data,
            ['frame_id' => $frameId]
        ));
    }

    /**
     * A frame's photos, in scan order.
     *
     * Before the migration a frame's single photo lives on the frame row itself;
     * it is presented as a one-item list (with id 0) so callers need one code path.
     */
    public function items(array $frame): array
    {
        if ($this->itemsReady()) {
            return $this->items->forFrame((int)$frame['id']);
        }
        if (empty($frame['photo_path'])) {
            return [];
        }
        $item = array_intersect_key($frame, array_flip(self::ITEM_COLUMNS));
        return [array_merge($item, ['id' => 0, 'frame_id' => (int)$frame['id'], 'sort_order' => 0])];
    }

    /**
     * Compile one photo into its own target and record the trackability result.
     *
     * Does not rebuild the frame's combined target — callers compiling several
     * photos in a row call refreshFrame() once at the end instead.
     *
     * @return array{ok: bool, error?: string, detail?: string|null, score?: int, flag?: string, metrics?: array}
     */
    public function compileItem(array $frame, array $item): array
    {
        if (empty($item['photo_path'])) {
            return ['ok' => false, 'error' => 'This photo slot has no photo yet.'];
        }

        $photoAbs = $this->absolutePath($item['photo_path']);
        if ($photoAbs === null || !is_file($photoAbs)) {
            return ['ok' => false, 'error' => 'The customer photo is missing from storage.'];
        }

        require_once APP_PATH . '/services/ArTargetService.php';
        $compiler = new ArTargetService();

        $targetRel = self::TARGET_DIR . '/' . $frame['slug'] . '-' . (int)$item['id'] . '.mind';
        $result = $compiler->compile($photoAbs, UPLOAD_PATH . '/' . $targetRel);

        if (empty($result['ok'])) {
            // Regenerating after a failure must not leave a stale target behind
            // that would make the photo look ready when it isn't.
            $this->items->update((int)$item['id'], [
                'target_path'        => null,
                'trackability_score' => null,
                'trackability_flag'  => null,
                'verified_at'        => null,
            ]);
            if (($item['target_path'] ?? null) !== $targetRel) {
                $this->deleteFile($item['target_path'] ?? null);
            }
            return $result;
        }

        // A new target invalidates any earlier live test — the tracking data has
        // changed, so it must be re-verified.
        $this->items->update((int)$item['id'], [
            'target_path'        => $targetRel,
            'trackability_score' => $result['score'],
            'trackability_flag'  => $result['flag'],
            'trackability_json'  => json_encode($result['metrics']),
            'verified_at'        => null,
        ]);
        // Frames migrated from the single-photo schema kept their target under the
        // frame's slug; once replaced it belongs to nothing.
        if (!empty($item['target_path']) && $item['target_path'] !== $targetRel) {
            $this->deleteFile($item['target_path']);
        }

        return $result;
    }

    /**
     * The shared target-generation step for a whole frame: compile its photos,
     * then rebuild the combined target the scan page loads.
     *
     * Called synchronously from the walk-in flow (a customer is waiting) and
     * on demand from the online queue. Identical either way.
     *
     * @param bool $onlyMissing Skip photos that already have a target.
     * @param int  $limit       Compile at most this many photos (0 = all), so a
     *                          large album can be prepared over several requests.
     * @return array{ok: bool, error?: string, compiled?: int, failures?: array, score?: int, flag?: string, advice?: string, metrics?: array}
     */
    public function generateTarget(int $frameId, bool $onlyMissing = false, int $limit = 0): array
    {
        $frame = $this->frames->find($frameId);
        if (!$frame) {
            return ['ok' => false, 'error' => 'That AR frame no longer exists.'];
        }
        if (!$this->itemsReady()) {
            return ['ok' => false, 'error' => 'Run the multi-photo migration (migrations/2026_09_14_ar_frame_items.sql) first.'];
        }

        $items = $this->items->forFrame($frameId);
        if (!$items) {
            return ['ok' => false, 'error' => 'This frame has no photos yet. Add the customer photo first.'];
        }

        $compiled = 0;
        $failures = [];
        $worst = null;
        $metrics = null;

        foreach ($items as $position => $item) {
            if ($onlyMissing && !empty($item['target_path'])) {
                continue;
            }
            if ($limit > 0 && $compiled + count($failures) >= $limit) {
                break;
            }
            $result = $this->compileItem($frame, $item);
            if (empty($result['ok'])) {
                $failures[] = [
                    'position' => $position + 1,
                    'error' => $result['error'],
                    'detail' => $result['detail'] ?? null,
                ];
                continue;
            }
            $compiled++;
            $metrics = $metrics ?? $result['metrics'];
            if ($worst === null || $result['score'] < $worst['score']) {
                $worst = ['score' => $result['score'], 'flag' => $result['flag'], 'position' => $position + 1];
            }
        }

        $refresh = $this->refreshFrame($frameId);

        $summary = [
            'ok' => !$failures && !empty($refresh['ok']),
            'compiled' => $compiled,
            'total' => count($items),
            'failures' => $failures,
            'metrics' => $metrics ?? [],
        ];
        if ($worst !== null) {
            $summary += $worst;
            $summary['advice'] = ArTargetService::trackabilityAdvice($worst['flag']);
        }
        if ($failures) {
            $first = $failures[0];
            $summary['error'] = (count($items) > 1 ? 'Photo ' . $first['position'] . ': ' : '') . $first['error'];
            $summary['detail'] = $first['detail'];
        } elseif (empty($refresh['ok'])) {
            $summary['error'] = $refresh['error'];
        }
        return $summary;
    }

    /**
     * Bring a frame's combined target and status in line with its photos.
     * Call after anything that adds, removes, compiles or verifies a photo.
     *
     * @return array{ok: bool, error?: string}
     */
    public function refreshFrame(int $frameId): array
    {
        $frame = $this->frames->find($frameId);
        if (!$frame) {
            return ['ok' => false, 'error' => 'That AR frame no longer exists.'];
        }
        $items = $this->items->forFrame($frameId);

        $result = $this->rebuildFrameTarget($frame, $items);
        $this->syncFrameStatus($this->frames->find($frameId), $items);
        return $result;
    }

    /**
     * Point the frame at a target file containing every photo that is ready.
     *
     * Photos still waiting for a target are left out rather than holding the
     * others back: adding a photo to a frame that is already with the customer
     * must not stop the existing ones scanning while the new one is prepared.
     */
    private function rebuildFrameTarget(array $frame, array $items): array
    {
        $ready = [];
        foreach ($items as $item) {
            $abs = empty($item['target_path']) ? null : $this->absolutePath((string)$item['target_path']);
            if ($abs !== null && is_file($abs)) {
                $ready[$abs] = $item;
            }
        }

        $setRel = self::TARGET_DIR . '/' . $frame['slug'] . '-set.mind';

        if (count($ready) <= 1) {
            $only = $ready ? reset($ready) : null;
            $this->frames->update((int)$frame['id'], [
                'target_path'  => $only['target_path'] ?? null,
                'target_items' => $only ? json_encode([(int)$only['id']]) : null,
            ]);
            $this->deleteFile($setRel);
            return ['ok' => true];
        }

        require_once APP_PATH . '/services/ArTargetService.php';
        $result = (new ArTargetService())->bundle(array_keys($ready), UPLOAD_PATH . '/' . $setRel);
        if (empty($result['ok'])) {
            // The previous file and its index map still agree with each other, so
            // leave both: a photo that has since been removed just stops playing,
            // rather than every photo on the frame going dark.
            return ['ok' => false, 'error' => 'Could not combine the photos into one scan target: '
                . ($result['error'] ?? 'unknown error')];
        }

        // The bundler reports each entry it wrote, in file order.
        $ids = [];
        foreach (($result['included'] ?? []) as $abs) {
            if (isset($ready[$abs])) {
                $ids[] = (int)$ready[$abs]['id'];
            }
        }

        $this->frames->update((int)$frame['id'], [
            'target_path'  => $setRel,
            'target_items' => json_encode($ids),
        ]);
        return ['ok' => true];
    }

    /**
     * Derive the frame's status and verified_at from its photos.
     *
     * The frame is verified only when every photo has passed its own live test —
     * one photo matching proves nothing about the others. Once printed, the
     * status is the admin's to move, so it is left alone.
     */
    private function syncFrameStatus(array $frame, array $items): void
    {
        $total = count($items);
        $withTarget = 0;
        $verified = 0;
        $latest = null;
        foreach ($items as $item) {
            if (empty($item['target_path'])) {
                continue;
            }
            $withTarget++;
            if (!empty($item['verified_at'])) {
                $verified++;
                $latest = max($latest ?? '', $item['verified_at']);
            }
        }

        $allVerified = $total > 0 && $verified === $total;
        $data = ['verified_at' => $allVerified ? ($frame['verified_at'] ?: $latest) : null];

        if (in_array($frame['status'], self::PRE_PRINT_STATUSES, true)) {
            if ($total === 0 || $withTarget < $total) {
                $data['status'] = 'pending_setup';
            } else {
                $data['status'] = $allVerified ? 'verified' : 'target_generated';
            }
        }

        $this->frames->update((int)$frame['id'], $data);
    }

    /**
     * Record a successful live scan of one photo.
     *
     * @return array{ok: bool, verified?: int, total?: int, error?: string}
     */
    public function markItemVerified(int $frameId, int $itemId): array
    {
        if (!$this->itemsReady()) {
            return $this->markVerified($frameId)
                ? ['ok' => true, 'verified' => 1, 'total' => 1]
                : ['ok' => false, 'error' => 'Generate the target before testing.'];
        }

        $item = $this->items->findForFrame($frameId, $itemId);
        if (!$item || empty($item['target_path'])) {
            return ['ok' => false, 'error' => 'That photo has no target to test.'];
        }
        $this->items->update($itemId, ['verified_at' => date('Y-m-d H:i:s')]);

        $frame = $this->frames->find($frameId);
        $items = $this->items->forFrame($frameId);
        $this->syncFrameStatus($frame, $items);

        return [
            'ok' => true,
            'verified' => count(array_filter($items, fn($i) => !empty($i['verified_at']) && !empty($i['target_path']))),
            'total' => count($items),
        ];
    }

    /**
     * Record a passed live test for the whole frame — every photo that has a
     * target. This is the gate that lets a frame advance toward print/handover.
     */
    public function markVerified(int $frameId): bool
    {
        $frame = $this->frames->find($frameId);
        if (!$frame || empty($frame['target_path'])) {
            return false;
        }

        if (!$this->itemsReady()) {
            return $this->frames->update($frameId, [
                'verified_at' => date('Y-m-d H:i:s'),
                'status'      => 'verified',
            ]);
        }

        $this->items->markAllVerified($frameId);
        $this->syncFrameStatus($frame, $this->items->forFrame($frameId));
        return true;
    }

    /** Remove one photo, its files, and its place in the frame's target. */
    public function deleteItem(int $frameId, array $item): void
    {
        $this->deleteFile($item['photo_path'] ?? null);
        $this->deleteFile($item['target_path'] ?? null);
        $this->deleteFile($item['video_path'] ?? null);
        $this->items->delete((int)$item['id']);
        $this->refreshFrame($frameId);
    }

    /**
     * Delete every file a frame owns: each photo's files and the combined target.
     * The rows are the caller's to remove.
     */
    public function deleteFrameFiles(array $frame): void
    {
        foreach ($this->items($frame) as $item) {
            $this->deleteFile($item['photo_path'] ?? null);
            $this->deleteFile($item['target_path'] ?? null);
            $this->deleteFile($item['video_path'] ?? null);
        }
        // The frame row's own columns: the combined target, plus the single photo
        // a frame from before the migration kept here.
        $this->deleteFile($frame['target_path'] ?? null);
        $this->deleteFile($frame['photo_path'] ?? null);
        $this->deleteFile($frame['video_path'] ?? null);
    }

    // ------------------------------------------------------------- scan pages

    /**
     * What a frame's own scan page loads: one target file, and one browser
     * descriptor per entry in it, in file order.
     *
     * @return array{targetUrl: string, targets: array[], items: array[]}|null null when nothing is scannable yet
     */
    public function frameScan(array $frame): ?array
    {
        if (empty($frame['target_path'])) {
            return null;
        }
        $slug = (string)$frame['slug'];
        $items = $this->items($frame);

        if (!$this->itemsReady()) {
            return [
                'targetUrl' => self::fileUrl($frame['target_path']),
                'targets'   => array_map(fn($item) => $this->browserTarget($item, $slug), $items),
                'items'     => $items,
            ];
        }

        $byId = [];
        foreach ($items as $item) {
            $byId[(int)$item['id']] = $item;
        }

        $ids = json_decode((string)($frame['target_items'] ?? ''), true);
        if (!is_array($ids)) {
            // Only possible if the migration's final UPDATE did not run: the
            // frame's target is then still its one photo's own file.
            $ids = [];
            foreach ($items as $item) {
                if (($item['target_path'] ?? null) === $frame['target_path']) {
                    $ids = [(int)$item['id']];
                    break;
                }
            }
        }
        if (!$ids) {
            return null;
        }

        $targets = [];
        $ordered = [];
        foreach ($ids as $id) {
            $item = $byId[(int)$id] ?? null;
            // A missing item keeps its slot, or every later photo would play the
            // video of the one before it.
            $targets[] = $this->browserTarget($item, $slug);
            if ($item !== null) {
                $ordered[] = $item;
            }
        }

        return [
            'targetUrl' => self::fileUrl($frame['target_path']),
            'targets'   => $targets,
            'items'     => $ordered,
        ];
    }

    // --------------------------------------------------------- scan-all bundle

    public const BUNDLE_FILE = self::TARGET_DIR . '/all-frames.mind';
    public const BUNDLE_MANIFEST = self::TARGET_DIR . '/all-frames.json';

    /** Past this many photos the bundle gets heavy on mobile data (~464KB each). */
    public const BUNDLE_WARN_AT = 25;

    /**
     * Frames the public "scan anything" page can recognise: active, and with a
     * compiled target. Partner content is never included — /scan is
     * GiftDekeDekho's own page, and a partner's customers use their sticker.
     */
    public function scannableFrames(): array
    {
        $sql = "SELECT id, slug, target_path
                FROM ar_frames
                WHERE is_active = 1 AND target_path IS NOT NULL AND target_path <> ''
                  AND channel <> 'partner'
                ORDER BY id ASC";
        return $this->frames->rawQuery($sql);
    }

    /**
     * Every photo the scan-anything page can recognise, each with its frame's
     * slug. Ordered by id so the anchor index a browser reports stays stable
     * between rebuilds for photos that have not changed.
     *
     * `bundle_key` is unique across both schemas, so a bundle built before the
     * migration is never read back as if it listed items.
     */
    public function scannableTargets(): array
    {
        if (!$this->itemsReady()) {
            return $this->frames->rawQuery(
                "SELECT f.*, CONCAT('f', f.id) AS bundle_key
                 FROM ar_frames f
                 WHERE f.is_active = 1 AND f.target_path IS NOT NULL AND f.target_path <> ''
                 ORDER BY f.id ASC"
            );
        }

        return $this->frames->rawQuery(
            "SELECT i.*, f.slug, CONCAT('i', i.id) AS bundle_key
             FROM ar_frame_items i
             JOIN ar_frames f ON f.id = i.frame_id
             WHERE f.is_active = 1 AND i.target_path IS NOT NULL AND i.target_path <> ''
               AND f.channel <> 'partner'
             ORDER BY i.id ASC"
        );
    }

    /**
     * Build (or reuse) the combined target file used by /scan.
     *
     * Rebuilt lazily rather than on every frame change: the manifest records
     * which photos went in, so a rebuild happens only when the set has actually
     * changed. Merging is milliseconds, so this is cheap enough to check on each
     * visit.
     *
     * @return array{ok: bool, error?: string, path?: string, targets?: array, count?: int, bytes?: int, rebuilt?: bool}
     */
    public function scanBundle(): array
    {
        $rows = $this->scannableTargets();
        if (!$rows) {
            return ['ok' => false, 'error' => 'No Living Photos are ready to scan yet.', 'count' => 0];
        }

        $bundleAbs = UPLOAD_PATH . '/' . self::BUNDLE_FILE;
        $manifestAbs = UPLOAD_PATH . '/' . self::BUNDLE_MANIFEST;

        // Any add, removal, deactivation or regenerated target changes this.
        $fingerprint = md5(json_encode(array_map(
            fn($r) => [$r['bundle_key'], $r['target_path']],
            $rows
        )));

        $manifest = is_file($manifestAbs)
            ? json_decode((string)file_get_contents($manifestAbs), true)
            : null;

        $fresh = is_array($manifest)
            && ($manifest['fingerprint'] ?? null) === $fingerprint
            && is_file($bundleAbs);

        if (!$fresh) {
            $result = $this->rebuildBundle($rows, $bundleAbs, $manifestAbs, $fingerprint);
            if (empty($result['ok'])) {
                return $result;
            }
            $manifest = $result['manifest'];
        }

        // Only the photos that actually made it into the file, in file order —
        // that index is what the browser reports on a match.
        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['bundle_key']] = $r;
        }
        $included = [];
        foreach (($manifest['entries'] ?? []) as $key) {
            if (isset($byKey[$key])) {
                $included[] = $byKey[$key];
            }
        }

        return [
            'ok' => true,
            'path' => self::BUNDLE_FILE,
            'targets' => $included,
            'count' => count($included),
            'bytes' => is_file($bundleAbs) ? filesize($bundleAbs) : 0,
            'rebuilt' => !$fresh,
        ];
    }

    private function rebuildBundle(array $rows, string $bundleAbs, string $manifestAbs, string $fingerprint): array
    {
        $paths = [];
        $byPath = [];
        foreach ($rows as $row) {
            $abs = $this->absolutePath((string)$row['target_path']);
            if ($abs !== null && is_file($abs)) {
                $paths[] = $abs;
                $byPath[$abs] = $row['bundle_key'];
            }
        }
        if (!$paths) {
            return ['ok' => false, 'error' => 'None of the compiled targets are present on disk.'];
        }

        require_once APP_PATH . '/services/ArTargetService.php';
        $result = (new ArTargetService())->bundle($paths, $bundleAbs);
        if (empty($result['ok'])) {
            return $result;
        }

        // The bundler reports what it actually included and in what order, which
        // may differ from the request if a file was unreadable.
        $entries = [];
        foreach (($result['included'] ?? []) as $abs) {
            if (isset($byPath[$abs])) { $entries[] = $byPath[$abs]; }
        }

        $manifest = [
            'fingerprint' => $fingerprint,
            'entries' => $entries,
            'built_at' => date('c'),
            'bytes' => $result['metrics']['bytes'] ?? null,
        ];
        @file_put_contents($manifestAbs, json_encode($manifest));

        return ['ok' => true, 'manifest' => $manifest];
    }

    // ------------------------------------------------------------------ paths

    /** Public URL for the scan page printed on the instruction card. */
    public static function scanUrl(string $slug): string
    {
        return rtrim(SITE_URL, '/') . '/scan/' . $slug;
    }

    /**
     * Resolve a stored upload path to an absolute filesystem path.
     * Handles both storage conventions in use on this site: relative to the
     * uploads dir ("ar-photos/x.jpg") and root-relative ("/public/uploads/...",
     * which is what the storefront's customization uploader writes).
     */
    public function absolutePath(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '' || preg_match('#^https?://#i', $stored)) {
            return null;
        }

        $uploadUrl = trim(UPLOAD_URL, '/');
        $candidate = ltrim($stored, '/');
        if (strpos($candidate, $uploadUrl . '/') === 0) {
            $candidate = substr($candidate, strlen($uploadUrl) + 1);
        }

        // Never let a stored value escape the uploads directory.
        $abs = realpath(UPLOAD_PATH . '/' . $candidate);
        $root = realpath(UPLOAD_PATH);
        if ($abs === false || $root === false || strpos($abs, $root . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }
        return $abs;
    }

    /** Public URL for a stored upload path, in either storage convention. */
    public static function fileUrl(?string $stored): string
    {
        if ($stored === null || trim($stored) === '') {
            return '';
        }
        $stored = trim($stored);
        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        if ($stored[0] === '/') {
            return asset($stored);
        }
        return asset(trim(UPLOAD_URL, '/') . '/' . $stored);
    }

    /**
     * The playable video URL for the public page, plus how to play it.
     *
     * @param array $item An item row, or a frame row from before the migration.
     * @return array{type: string, url: string, youtube_id?: string}|null
     */
    public function playback(array $item): ?array
    {
        $type = (string)($item['video_type'] ?? '');

        if ($type === 'upload') {
            if (empty($item['video_path'])) {
                return null;
            }
            return ['type' => 'upload', 'url' => self::fileUrl($item['video_path'])];
        }

        if (empty($item['video_url'])) {
            return null;
        }

        // Re-validated on the way out as well as on the way in, so a row edited
        // directly in the database cannot inject an arbitrary embed or URL.
        switch ($type) {
            case 'vimeo':
                $id = $this->vimeoId((string)$item['video_url']);
                return $id === null ? null : [
                    'type' => 'vimeo',
                    'url' => 'https://vimeo.com/' . $id,
                    'vimeo_id' => $id,
                ];

            case 'direct':
                $url = $this->directVideoUrl((string)$item['video_url']);
                return $url === null ? null : ['type' => 'direct', 'url' => $url];

            case 'youtube':
            default:
                $id = $this->youtubeId((string)$item['video_url']);
                return $id === null ? null : [
                    'type' => 'youtube',
                    'url' => 'https://www.youtube.com/watch?v=' . $id,
                    'youtube_id' => $id,
                ];
        }
    }

    /**
     * Delete a file the frame owns, if it is inside the uploads directory. Used
     * when replacing a photo so old targets don't accumulate.
     */
    public function deleteFile(?string $stored): void
    {
        if ($stored === null || trim($stored) === '') {
            return;
        }
        $abs = $this->absolutePath($stored);
        if ($abs !== null && is_file($abs)) {
            @unlink($abs);
        }
    }
}
