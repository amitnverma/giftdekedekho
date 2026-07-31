<?php
/**
 * Shared pipeline for Living Photo AR frames.
 *
 * Both sales channels come through here — the online queue and the walk-in
 * Quick Create form — so photo handling, video validation, slug allocation and
 * target generation exist exactly once. The only difference between channels is
 * *when* generateTarget() is called: immediately at the counter for walk-ins,
 * whenever an admin works the queue for online orders.
 */
class ArFrameService
{
    public const PHOTO_DIR = 'ar-photos';
    public const TARGET_DIR = 'ar-targets';
    public const VIDEO_DIR = 'videos';

    public const MAX_PHOTO_BYTES = 10 * 1024 * 1024;   // 10MB
    public const MAX_VIDEO_BYTES = 100 * 1024 * 1024;  // 100MB, matches the existing QR video limit

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

    private ArFrame $frames;

    public function __construct()
    {
        $this->frames = new ArFrame();
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
    public function storeVideo(array $file): array
    {
        $error = $this->uploadError($file, self::MAX_VIDEO_BYTES, 'video');
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
        finfo_close($finfo);
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

    /**
     * Reduce a user-supplied YouTube URL to its canonical form, or null if it
     * isn't one. Rebuilt from the extracted video id rather than passed through,
     * so nothing user-controlled other than an 11-character id reaches the
     * public page's embed.
     */
    public function normaliseYoutubeUrl(string $url): ?string
    {
        $id = $this->youtubeId($url);
        return $id === null ? null : 'https://www.youtube.com/watch?v=' . $id;
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

    /**
     * Create a frame row, allocating its public slug.
     *
     * @param array $attrs Caller-supplied columns; channel and photo_path required.
     */
    public function createFrame(array $attrs): int
    {
        $data = array_merge([
            'slug'          => $this->frames->generateUniqueSlug(),
            'channel'       => 'online',
            'video_type'    => 'youtube',
            'playback_mode' => 'fullscreen',
            'status'        => 'pending_setup',
            'is_active'     => 1,
        ], $attrs);

        return $this->frames->create($data);
    }

    /**
     * The shared target-generation step: compile the frame's photo into a .mind
     * file, record the trackability result, and advance the status.
     *
     * Called synchronously from the walk-in flow (a customer is waiting) and
     * on demand from the online queue. Identical either way.
     *
     * @return array{ok: bool, error?: string, detail?: string|null, score?: int, flag?: string, advice?: string}
     */
    public function generateTarget(int $frameId): array
    {
        $frame = $this->frames->find($frameId);
        if (!$frame) {
            return ['ok' => false, 'error' => 'That AR frame no longer exists.'];
        }
        if (empty($frame['photo_path'])) {
            return ['ok' => false, 'error' => 'This frame has no photo yet. Add the customer photo first.'];
        }

        $photoAbs = $this->absolutePath($frame['photo_path']);
        if ($photoAbs === null || !is_file($photoAbs)) {
            return ['ok' => false, 'error' => 'The customer photo is missing from storage.'];
        }

        require_once APP_PATH . '/services/ArTargetService.php';
        $compiler = new ArTargetService();

        $targetRel = self::TARGET_DIR . '/' . $frame['slug'] . '.mind';
        $result = $compiler->compile($photoAbs, UPLOAD_PATH . '/' . $targetRel);

        if (empty($result['ok'])) {
            // Regenerating after a failure must not leave a stale target behind
            // that would make the frame look ready when it isn't.
            $this->frames->update($frameId, [
                'target_path'        => null,
                'trackability_score' => null,
                'trackability_flag'  => null,
                'status'             => 'pending_setup',
                'verified_at'        => null,
            ]);
            return $result;
        }

        // A new target invalidates any earlier live test — the printed photo and
        // the tracking data have both changed, so it must be re-verified.
        $this->frames->update($frameId, [
            'target_path'        => $targetRel,
            'trackability_score' => $result['score'],
            'trackability_flag'  => $result['flag'],
            'trackability_json'  => json_encode($result['metrics']),
            'status'             => 'target_generated',
            'verified_at'        => null,
        ]);

        $result['advice'] = ArTargetService::trackabilityAdvice($result['flag']);
        return $result;
    }

    /**
     * Record a successful live camera test. This is the gate that lets a frame
     * advance toward print/handover.
     */
    public function markVerified(int $frameId): bool
    {
        $frame = $this->frames->find($frameId);
        if (!$frame || empty($frame['target_path'])) {
            return false;
        }
        return $this->frames->update($frameId, [
            'verified_at' => date('Y-m-d H:i:s'),
            'status'      => 'verified',
        ]);
    }

    // --------------------------------------------------------- scan-all bundle

    public const BUNDLE_FILE = self::TARGET_DIR . '/all-frames.mind';
    public const BUNDLE_MANIFEST = self::TARGET_DIR . '/all-frames.json';

    /** Past this many frames the bundle gets heavy on mobile data (~464KB each). */
    public const BUNDLE_WARN_AT = 25;

    /**
     * Frames the public "scan anything" page can recognise: active, and with a
     * compiled target. Ordered by id so the anchor index a browser reports stays
     * stable between rebuilds for frames that have not changed.
     */
    public function scannableFrames(): array
    {
        $sql = "SELECT id, slug, target_path, video_type, video_url, video_path, playback_mode, photo_path
                FROM ar_frames
                WHERE is_active = 1 AND target_path IS NOT NULL AND target_path <> ''
                ORDER BY id ASC";
        return $this->frames->rawQuery($sql);
    }

    /**
     * Build (or reuse) the combined target file used by /scan.
     *
     * Rebuilt lazily rather than on every frame change: the manifest records
     * which frames went in and when, so a rebuild happens only when the set has
     * actually changed. Merging is milliseconds, so this is cheap enough to
     * check on each visit.
     *
     * @return array{ok: bool, error?: string, path?: string, frames?: array, count?: int, bytes?: int, rebuilt?: bool}
     */
    public function scanBundle(): array
    {
        $frames = $this->scannableFrames();
        if (!$frames) {
            return ['ok' => false, 'error' => 'No Living Photos are ready to scan yet.', 'count' => 0];
        }

        $bundleAbs = UPLOAD_PATH . '/' . self::BUNDLE_FILE;
        $manifestAbs = UPLOAD_PATH . '/' . self::BUNDLE_MANIFEST;

        // Fingerprint the set: ids plus the newest change. Any add, removal,
        // deactivation or regenerated target changes this.
        $fingerprint = md5(json_encode(array_map(
            fn($f) => [$f['id'], $f['target_path']],
            $frames
        )));

        $manifest = is_file($manifestAbs)
            ? json_decode((string)file_get_contents($manifestAbs), true)
            : null;

        $fresh = is_array($manifest)
            && ($manifest['fingerprint'] ?? null) === $fingerprint
            && is_file($bundleAbs);

        if (!$fresh) {
            $result = $this->rebuildBundle($frames, $bundleAbs, $manifestAbs, $fingerprint);
            if (empty($result['ok'])) {
                return $result;
            }
            $manifest = $result['manifest'];
        }

        // Only the frames that actually made it into the file, in file order —
        // that index is what the browser reports on a match.
        $included = [];
        foreach (($manifest['frames'] ?? []) as $id) {
            foreach ($frames as $f) {
                if ((int)$f['id'] === (int)$id) { $included[] = $f; break; }
            }
        }

        return [
            'ok' => true,
            'path' => self::BUNDLE_FILE,
            'frames' => $included,
            'count' => count($included),
            'bytes' => is_file($bundleAbs) ? filesize($bundleAbs) : 0,
            'rebuilt' => !$fresh,
        ];
    }

    private function rebuildBundle(array $frames, string $bundleAbs, string $manifestAbs, string $fingerprint): array
    {
        $paths = [];
        $byPath = [];
        foreach ($frames as $frame) {
            $abs = $this->absolutePath((string)$frame['target_path']);
            if ($abs !== null && is_file($abs)) {
                $paths[] = $abs;
                $byPath[$abs] = (int)$frame['id'];
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
        $includedIds = [];
        foreach (($result['included'] ?? []) as $abs) {
            if (isset($byPath[$abs])) { $includedIds[] = $byPath[$abs]; }
        }

        $manifest = [
            'fingerprint' => $fingerprint,
            'frames' => $includedIds,
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
     * @return array{type: string, url: string, youtube_id?: string}|null
     */
    public function playback(array $frame): ?array
    {
        if (($frame['video_type'] ?? '') === 'upload') {
            if (empty($frame['video_path'])) {
                return null;
            }
            return ['type' => 'upload', 'url' => self::fileUrl($frame['video_path'])];
        }

        if (empty($frame['video_url'])) {
            return null;
        }
        // Re-validate on the way out as well as on the way in, so a row edited
        // directly in the database can't inject an arbitrary embed.
        $id = $this->youtubeId((string)$frame['video_url']);
        if ($id === null) {
            return null;
        }
        return [
            'type' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=' . $id,
            'youtube_id' => $id,
        ];
    }

    /**
     * Delete the files a frame owns. Used when replacing a photo so old
     * targets don't accumulate.
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
