<?php
/**
 * Admin side of the Living Photo AR frame feature.
 *
 * Two entry points into one pipeline:
 *   index()/show()  — the queue for online orders (pending → generated → verified → printed → shipped)
 *   quickCreate()   — the single-page walk-in form used at the counter
 *
 * Both call the same ArFrameService for photo handling and target generation,
 * and both produce frames served by the same public /scan/{slug} page.
 *
 * A frame holds one or more photos, each with its own video. They share the
 * frame's QR sticker; each photo has its own target and its own live test.
 */
class AdminArFrameController extends BaseController
{
    private const PER_PAGE = 20;

    /** Shown whenever a pasted video link is not one we can play. */
    private const VIDEO_URL_HELP =
        'That video link was not recognised. Paste a YouTube link (youtube.com or youtu.be), '
        . 'a Vimeo link, or a direct https link to an .mp4, .webm or .mov file.';

    /** Target generation is CPU-heavy, so cap it per admin session. */
    private const GENERATE_LIMIT = 20;
    private const GENERATE_WINDOW_SECONDS = 300;

    /**
     * Sticker QR settings. Level Q and a generous module size because the code
     * is printed small, stuck to a product and then handled — it has to survive
     * scuffs and a phone held at an angle in poor light. Shared between printing
     * the sticker and cleaning up its cached PNG, which must agree on the key.
     */
    private const QR_EC_LEVEL = 'Q';
    private const QR_PIXEL_SIZE = 12;

    private ArFrame $frames;
    private ArFrameItem $items;
    private ArFrameService $service;

    public function __construct()
    {
        require_once APP_PATH . '/services/ArFrameService.php';
        $this->frames = new ArFrame();
        $this->items = new ArFrameItem();
        $this->service = new ArFrameService();
    }

    // ------------------------------------------------------------------ queue

    public function index(): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;

        $filters = [
            'status'     => (string)$this->input('status', ''),
            'channel'    => (string)$this->input('channel', ''),
            'search'     => (string)$this->input('search', ''),
            'unverified' => (string)$this->input('unverified', ''),
        ];

        $page = max(1, (int)$this->input('page', 1));
        $total = $this->frames->countFiltered($filters);
        $pagination = paginate($total, self::PER_PAGE, $page);

        $this->viewAdmin('admin/ar_frames_index', [
            'metaTitle'    => 'AR Frame Orders',
            'frames'       => $this->frames->paginated($filters, self::PER_PAGE, $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
            'statusCounts' => $this->frames->statusCounts(),
            'compiler'     => $this->compilerStatus(),
            'scanAll'      => $this->scanAllStatus(),
        ]);
    }

    /**
     * Size of the public "scan anything" bundle.
     *
     * Every active photo's target is downloaded by /scan, so this grows with the
     * catalogue. Reported here rather than left to surprise a customer on mobile
     * data — the per-frame link printed on each sticker is unaffected either way.
     */
    private function scanAllStatus(): array
    {
        $count = count($this->service->scannableTargets());
        $bytes = $count * 464 * 1024;   // measured average per compiled target

        return [
            'count' => $count,
            'approx_mb' => round($bytes / 1048576, 1),
            'heavy' => $count > ArFrameService::BUNDLE_WARN_AT,
            'warn_at' => ArFrameService::BUNDLE_WARN_AT,
        ];
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;

        $frame = $this->frames->findWithContext($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        $items = $this->service->items($frame);
        $playback = [];
        foreach ($items as $item) {
            $playback[(int)$item['id']] = $this->service->playback($item);
        }

        $this->viewAdmin('admin/ar_frames_show', [
            'metaTitle'    => 'AR Frame ' . $frame['slug'],
            'frame'        => $frame,
            'items'        => $items,
            'playback'     => $playback,
            'transitions'  => ArFrame::allowedTransitions($frame),
            'scanUrl'      => ArFrameService::scanUrl($frame['slug']),
            'phoneTestUrl' => $this->phoneTestUrl($frame['slug']),
            'compiler'     => $this->compilerStatus(),
            'maxItems'     => ArFrameItem::MAX_PER_FRAME,
            'uploadLimit'  => (string)ini_get('post_max_size'),
        ]);
    }

    // ------------------------------------------------- shared pipeline actions

    /**
     * Generate targets for a frame's photos. Used by both the online queue and
     * the walk-in flow. With only_missing set, photos that already have a target
     * are left as they are.
     */
    public function generateTarget(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $frame = $this->findFrameOrRedirect($id);

        $onlyMissing = (bool)$this->input('only_missing');
        $pending = array_filter(
            $this->service->items($frame),
            fn($item) => !$onlyMissing || empty($item['target_path'])
        );

        if (!$this->throttleGeneration(max(1, count($pending)))) {
            flash('error', 'Too many target generations in a short time. Please wait a minute and try again.');
            redirect('/admin/ar-frames/' . $id);
        }

        @set_time_limit(30 + 20 * count($pending));
        $this->flashGeneration($this->service->generateTarget($id, $onlyMissing), 'Target generated.');
        redirect('/admin/ar-frames/' . $id);
    }

    /** Regenerate the target for one photo, then rebuild the frame's combined target. */
    public function generateItemTarget(int $id, int $itemId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $frame = $this->findFrameOrRedirect($id);
        $item = $this->findItemOrRedirect($id, $itemId);

        if (!$this->throttleGeneration()) {
            flash('error', 'Too many target generations in a short time. Please wait a minute and try again.');
            redirect('/admin/ar-frames/' . $id);
        }

        $this->compileAndFlash($frame, $item, 'Target regenerated.');
        redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
    }

    /**
     * Recorded by the live-test page (JSON) once MindAR reports a real match
     * against one of the frame's photos. Every photo has to pass before the
     * frame can be printed.
     */
    public function verify(int $id): void
    {
        $this->requireAdmin();

        // This endpoint is called by fetch() from the live-test page, so it
        // answers in JSON rather than using the shared HTML CSRF failure page —
        // otherwise the caller gets an unparseable body and can only report a
        // generic failure.
        if (!verifyCsrf()) {
            jsonResponse(['ok' => false, 'message' => 'Your session expired. Reload the page and test again.'], 419);
        }

        $frame = $this->frames->find($id);
        if (!$frame) {
            jsonResponse(['ok' => false, 'message' => 'AR frame not found.'], 404);
        }
        if (empty($frame['target_path'])) {
            jsonResponse(['ok' => false, 'message' => 'Generate the target before testing.'], 422);
        }

        $result = $this->service->markItemVerified($id, (int)$this->input('item_id', 0));
        if (empty($result['ok'])) {
            jsonResponse(['ok' => false, 'message' => $result['error'] ?? 'Could not record the test result.'], 422);
        }

        if ($result['verified'] >= $result['total']) {
            $message = $result['total'] > 1
                ? 'All ' . $result['total'] . ' photos passed the live scan test. This frame is ready to print.'
                : 'Live scan test passed. This frame is ready to print.';
        } else {
            $message = 'That photo passed — ' . $result['verified'] . ' of ' . $result['total']
                . ' verified. Close the video and scan the next photo.';
        }

        jsonResponse([
            'ok' => true,
            'message' => $message,
            'verified' => $result['verified'],
            'total' => $result['total'],
            'redirect' => url('/admin/ar-frames/' . $id),
        ]);
    }

    /**
     * Record the live test after the admin watched it work on a phone.
     *
     * The scanner has to be a different device from the screen showing the
     * photo, and the phone uses the public /scan/{slug} page — which needs no
     * login. So the pass is confirmed here, on the machine the admin is already
     * signed in on, instead of requiring a second login on the phone.
     *
     * Requires an explicit tick: this is the gate that lets a frame be printed,
     * so it must not be possible to clear by accident. It covers every photo, so
     * it is refused while any photo still has no target to have been tested.
     */
    public function confirmTest(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $frame = $this->findFrameOrRedirect($id);

        $items = $this->service->items($frame);
        $untargeted = count(array_filter($items, fn($item) => empty($item['target_path'])));
        if (!$items || empty($frame['target_path']) || $untargeted > 0) {
            flash('error', $untargeted > 0
                ? 'Generate a target for every photo before recording the live test.'
                : 'Generate the target before recording a live test.');
            redirect('/admin/ar-frames/' . $id);
        }
        if (!$this->input('confirmed')) {
            flash('error', 'Tick the confirmation box to record the live test.');
            redirect('/admin/ar-frames/' . $id);
        }

        if (!$this->service->markVerified($id)) {
            flash('error', 'Could not record the live test.');
            redirect('/admin/ar-frames/' . $id);
        }

        flash('success', 'Live test recorded. This frame can now be printed and handed over.');
        redirect('/admin/ar-frames/' . $id);
    }

    /** Advance the status along the pipeline (print / ship / hand over). */
    public function updateStatus(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $frame = $this->findFrameOrRedirect($id);

        $next = (string)$this->input('status', '');
        if (!in_array($next, ArFrame::allowedTransitions($frame), true)) {
            flash('error', empty($frame['verified_at'])
                ? 'This frame must pass the live scan test before it can move forward.'
                : 'That status change is not allowed from ' . ArFrame::statusLabel($frame['status']) . '.');
            redirect('/admin/ar-frames/' . $id);
        }

        $this->frames->update($id, ['status' => $next]);
        flash('success', 'Status updated to ' . ArFrame::statusLabel($next) . '.');
        redirect('/admin/ar-frames/' . $id);
    }

    /**
     * Delete a frame and the files it owns.
     *
     * Permanent, and it breaks the scan link printed on that customer's sticker —
     * so the confirmation in the UI names the slug and says so plainly. Use the
     * "Scan link active" toggle instead when a frame should merely stop working
     * but stay on record.
     *
     * The scan-anything bundle is keyed on a fingerprint of the photos it
     * contains, so removing one rebuilds it automatically on the next visit.
     */
    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $frame = $this->findFrameOrRedirect($id);

        // Files first: if the row went first and this failed, the files would be
        // orphaned with nothing left pointing at them. Item rows go with the
        // frame row (ON DELETE CASCADE).
        $this->service->deleteFrameFiles($frame);
        $this->service->deleteFile($this->stickerQrPath($frame['slug']));

        $this->frames->delete($id);

        flash('success', 'AR frame ' . $frame['slug'] . ' deleted, along with its photos and targets.');
        redirect('/admin/ar-frames');
    }

    /** Update the frame-wide details: customer reference, notes, and the kill switch. */
    public function updateDetails(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findFrameOrRedirect($id);

        $this->frames->update($id, [
            'customer_name'  => $this->nullIfBlank((string)$this->input('customer_name', '')),
            'customer_phone' => $this->nullIfBlank((string)$this->input('customer_phone', '')),
            'notes'          => $this->nullIfBlank((string)$this->input('notes', '')),
            'is_active'      => $this->input('is_active') ? 1 : 0,
        ]);
        flash('success', 'Frame details updated.');
        redirect('/admin/ar-frames/' . $id);
    }

    // -------------------------------------------------------------- photos

    /**
     * Add another photo and the video it plays. Compiled straight away, like
     * the walk-in flow, so the page comes back ready for the live test.
     */
    public function addItem(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $frame = $this->findFrameOrRedirect($id);

        if ($this->items->countForFrame($id) >= ArFrameItem::MAX_PER_FRAME) {
            flash('error', 'A frame can hold at most ' . ArFrameItem::MAX_PER_FRAME . ' photos.');
            redirect('/admin/ar-frames/' . $id);
        }

        $video = $this->videoFromInput($_POST, $_FILES['video'] ?? null, null);
        if (empty($video['ok'])) {
            flash('error', $video['error']);
            redirect('/admin/ar-frames/' . $id . '#add-photo');
        }

        $photo = $this->service->storePhoto($_FILES['photo'] ?? []);
        if (empty($photo['ok'])) {
            $this->service->deleteFile($video['stored'] ?? null);
            flash('error', $photo['error']);
            redirect('/admin/ar-frames/' . $id . '#add-photo');
        }

        $itemId = $this->service->addItem($id, array_merge($video['data'], ['photo_path' => $photo['path']]));
        $item = $this->items->find($itemId);

        if (!$this->throttleGeneration()) {
            flash('error', 'Photo added, but too many targets were generated in a short time. '
                . 'Wait a minute, then use "Generate missing targets".');
            redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
        }

        $this->compileAndFlash($frame, $item, 'Photo added and its target generated.');
        redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
    }

    /** Replace one photo (e.g. after a poor trackability warning) and recompile it. */
    public function replaceItemPhoto(int $id, int $itemId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $frame = $this->findFrameOrRedirect($id);
        $item = $this->findItemOrRedirect($id, $itemId);

        $stored = $this->service->storePhoto($_FILES['photo'] ?? []);
        if (empty($stored['ok'])) {
            flash('error', $stored['error']);
            redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
        }

        // A new photo invalidates the old target and any earlier live test.
        $this->items->update($itemId, [
            'photo_path'         => $stored['path'],
            'target_path'        => null,
            'trackability_score' => null,
            'trackability_flag'  => null,
            'trackability_json'  => null,
            'verified_at'        => null,
        ]);
        $this->service->deleteFile($item['photo_path']);
        $this->service->deleteFile($item['target_path']);
        $item = $this->items->find($itemId);

        if (!$this->throttleGeneration()) {
            $this->service->refreshFrame($id);
            flash('error', 'Photo replaced, but too many targets were generated in a short time. '
                . 'Wait a minute, then regenerate its target.');
            redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
        }

        $this->compileAndFlash($frame, $item, 'Photo replaced and its target regenerated.');
        redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
    }

    /** Change the video one photo plays, and how it plays. */
    public function updateItemVideo(int $id, int $itemId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findFrameOrRedirect($id);
        $item = $this->findItemOrRedirect($id, $itemId);

        $video = $this->videoFromInput($_POST, $_FILES['video'] ?? null, $item);
        if (empty($video['ok'])) {
            flash('error', $video['error']);
            redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
        }

        $this->items->update($itemId, $video['data']);
        if (!empty($video['stored']) && !empty($item['video_path']) && $item['video_path'] !== $video['stored']) {
            $this->service->deleteFile($item['video_path']);
        }

        flash('success', 'Video updated.');
        redirect('/admin/ar-frames/' . $id . '#item-' . $itemId);
    }

    /**
     * Remove one photo from a frame. The last one cannot be removed — a frame
     * with nothing to scan is just a broken sticker; replace it, or delete the
     * frame.
     */
    public function deleteItem(int $id, int $itemId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findFrameOrRedirect($id);
        $item = $this->findItemOrRedirect($id, $itemId);

        if ($this->items->countForFrame($id) <= 1) {
            flash('error', 'A frame needs at least one photo. Replace this one instead, or delete the whole frame.');
            redirect('/admin/ar-frames/' . $id);
        }

        $this->service->deleteItem($id, $item);
        flash('success', 'Photo removed, along with its video and target.');
        redirect('/admin/ar-frames/' . $id);
    }

    // ------------------------------------------------- walk-in (Quick Create)

    /**
     * One-page counter flow. On submit it stores the photos, creates the frame
     * and compiles every target synchronously — the customer is standing there,
     * so a queue would be useless. The response lands on the detail page with
     * the trackability verdict and the live-test button ready.
     */
    public function quickCreate(): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->viewAdmin('admin/ar_frames_quick_create', [
                'metaTitle'   => 'Quick Create (Walk-in)',
                'compiler'    => $this->compilerStatus(),
                'maxItems'    => ArFrameItem::MAX_PER_FRAME,
                'uploadLimit' => (string)ini_get('post_max_size'),
            ]);
            return;
        }

        $this->requireCsrf();

        $rows = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];

        // Drop rows left completely blank — the form always offers an empty one.
        $keys = [];
        foreach ($rows as $key => $row) {
            $hasPhoto = !empty($_FILES['item_photo']['name'][$key]);
            $hasLink = trim((string)($row['video_url'] ?? '')) !== '';
            $hasFile = !empty($_FILES['item_video']['name'][$key]);
            if ($hasPhoto || $hasLink || $hasFile) {
                $keys[] = $key;
            }
        }

        if (!$keys) {
            $this->quickCreateError('Add at least one photo and the video it should play.');
        }
        if (count($keys) > ArFrameItem::MAX_PER_FRAME) {
            $this->quickCreateError('A frame can hold at most ' . ArFrameItem::MAX_PER_FRAME . ' photos.');
        }

        // Validate every link and photo before storing anything, so one bad row
        // doesn't leave the others' uploads orphaned.
        foreach ($keys as $position => $key) {
            $label = count($keys) > 1 ? 'Photo ' . ($position + 1) . ': ' : '';
            if (empty($_FILES['item_photo']['name'][$key])) {
                $this->quickCreateError($label . 'Please choose the photo to print.');
            }
            $row = $rows[$key];
            if (($row['video_type'] ?? '') !== 'upload' && $this->service->detectVideoSource((string)($row['video_url'] ?? '')) === null) {
                $this->quickCreateError($label . self::VIDEO_URL_HELP);
            }
        }

        $items = [];
        $stored = [];
        foreach ($keys as $position => $key) {
            $label = count($keys) > 1 ? 'Photo ' . ($position + 1) . ': ' : '';

            $photo = $this->service->storePhoto($this->fileAt('item_photo', $key));
            if (empty($photo['ok'])) {
                $this->discardAndFail($stored, $label . $photo['error']);
            }
            $stored[] = $photo['path'];

            $video = $this->videoFromInput($rows[$key], $this->fileAt('item_video', $key), null);
            if (empty($video['ok'])) {
                $this->discardAndFail($stored, $label . $video['error']);
            }
            if (!empty($video['stored'])) {
                $stored[] = $video['stored'];
            }

            $items[] = array_merge($video['data'], ['photo_path' => $photo['path']]);
        }

        if (!$this->throttleGeneration(count($items))) {
            $this->discardAndFail($stored, 'Too many target generations in a short time. Please wait a minute and try again.');
        }

        $id = $this->service->createFrame([
            'channel'        => 'in_store',
            'customer_name'  => $this->nullIfBlank((string)$this->input('customer_name', '')),
            'customer_phone' => $this->nullIfBlank((string)$this->input('customer_phone', '')),
            'notes'          => $this->nullIfBlank((string)$this->input('notes', '')),
            'created_by'     => currentUserId(),
        ], $items);

        @set_time_limit(30 + 20 * count($items));
        $result = $this->service->generateTarget($id);
        $slug = $this->frames->find($id)['slug'];

        if (empty($result['ok'])) {
            flash('error', $result['error'] . ' The frame was saved — replace that photo and generate again.');
            redirect('/admin/ar-frames/' . $id);
        }

        $photos = count($items) > 1 ? count($items) . ' targets' : 'target';
        if ($result['flag'] === 'good') {
            flash('success', sprintf(
                'Frame %s created and %s generated. Trackability %d/100%s. Run the live scan test now, before the customer leaves.',
                $slug,
                $photos,
                $result['score'],
                count($items) > 1 ? ' for the weakest photo' : ''
            ));
        } else {
            flash('error', sprintf(
                'Heads up: %strackability is only %d/100 (%s). %s Swap the photo now while the customer is still here.',
                count($items) > 1 ? 'photo ' . $result['position'] . "'s " : '',
                $result['score'],
                strtoupper($result['flag']),
                $result['advice']
            ));
        }

        redirect('/admin/ar-frames/' . $id);
    }

    private function quickCreateError(string $message): void
    {
        flash('error', $message);
        $this->setOld([
            'customer_name'  => (string)$this->input('customer_name', ''),
            'customer_phone' => (string)$this->input('customer_phone', ''),
            'notes'          => (string)$this->input('notes', ''),
        ]);
        redirect('/admin/ar-frames/quick-create');
    }

    /** Remove files already stored for a Quick Create that is being abandoned. */
    private function discardAndFail(array $stored, string $message): void
    {
        foreach ($stored as $path) {
            $this->service->deleteFile($path);
        }
        $this->quickCreateError($message);
    }

    // ------------------------------------------------------------ live test

    /**
     * The admin-facing live scan test: the same MindAR camera page the customer
     * gets, but wired to report each successful match back to verify().
     */
    public function liveTest(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;
        $frame = $this->findFrameOrRedirect($id);

        $scan = $this->service->frameScan($frame);
        if ($scan === null) {
            flash('error', 'Generate the target before running the live scan test.');
            redirect('/admin/ar-frames/' . $id);
        }

        // Rendered with no admin chrome: this page is held up to a phone camera,
        // and it reuses the public scan view so the test exercises the real thing.
        renderRaw('store/scan_page', [
            'frame'       => $frame,
            'targets'     => $scan['targets'],
            'targetUrl'   => $scan['targetUrl'],
            'photoUrls'   => array_map(fn($item) => ArFrameService::fileUrl($item['photo_path']), $scan['items']),
            'isAdminTest' => true,
            'verifyUrl'   => url('/admin/ar-frames/' . $id . '/verify'),
            'backUrl'     => url('/admin/ar-frames/' . $id),
            'csrf'        => csrfToken(),
            'siteName'    => siteSetting('site_name', SITE_NAME),
        ]);
    }

    /**
     * Full-screen photo, to scan during the live test.
     *
     * Before printing there is no physical photo to point a camera at. Put this
     * on the biggest screen available and scan it with the phone — the photo is
     * the target, so this is a faithful stand-in for the print. ?item= picks
     * which photo; the page links on to the others.
     */
    public function photo(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;
        $frame = $this->findFrameOrRedirect($id);

        $items = $this->service->items($frame);
        $wanted = (int)$this->input('item', 0);
        $position = 0;
        foreach ($items as $index => $item) {
            if ((int)$item['id'] === $wanted) {
                $position = $index;
                break;
            }
        }

        renderRaw('admin/ar_frame_photo', [
            'frame'    => $frame,
            'items'    => $items,
            'position' => $position,
            'backUrl'  => url('/admin/ar-frames/' . $id),
        ]);
    }

    // ------------------------------------------------------ instruction card

    public function card(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;

        $frame = $this->frames->findWithContext($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        require_once APP_PATH . '/services/InstructionCardService.php';
        (new InstructionCardService())->output($frame);
    }

    // -------------------------------------------------------------- QR sticker

    /**
     * Printable sheet of QR stickers for one frame.
     *
     * The sticker goes on the physical frame, so it replaces the site-wide
     * camera button as the way in: scanning it opens this frame's own
     * /scan/{slug} page — the camera, pre-aimed at every photo in this frame and
     * nothing else.
     *
     * The PNG is embedded in the page rather than linked, so what was on screen
     * is exactly what reaches the printer.
     */
    public function sticker(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;
        $frame = $this->findFrameOrRedirect($id);

        require_once APP_PATH . '/services/QrCodeService.php';
        $scanUrl = ArFrameService::scanUrl($frame['slug']);
        $qr = (new QrCodeService())->pngDataUri($scanUrl, self::QR_EC_LEVEL, self::QR_PIXEL_SIZE);

        renderRaw('admin/ar_sticker', [
            'frame'    => $frame,
            'scanUrl'  => $scanUrl,
            'qr'       => $qr,
            'siteName' => siteSetting('site_name', SITE_NAME),
            'backUrl'  => url('/admin/ar-frames/' . $id),
        ]);
    }

    /**
     * Where the sticker's QR image is cached, relative to the uploads dir.
     *
     * Only ever right for the current SITE_URL — which is the point: after a
     * domain change the old file is unreachable by this key and simply stops
     * being written to, rather than serving a code pointing at the old domain.
     */
    private function stickerQrPath(string $slug): string
    {
        require_once APP_PATH . '/services/QrCodeService.php';
        return QrCodeService::cachePath(
            ArFrameService::scanUrl($slug),
            self::QR_EC_LEVEL,
            self::QR_PIXEL_SIZE
        );
    }

    // ----------------------------------------------------------------- helpers

    private function findFrameOrRedirect(int $id): array
    {
        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }
        return $frame;
    }

    private function findItemOrRedirect(int $frameId, int $itemId): array
    {
        $item = $this->items->findForFrame($frameId, $itemId);
        if (!$item) {
            flash('error', 'That photo is not part of this frame.');
            redirect('/admin/ar-frames/' . $frameId);
        }
        return $item;
    }

    /**
     * Read one photo's video fields.
     *
     * One field for every provider when it is a link — the source is detected
     * from the URL. An upload keeps the file already attached unless a new one
     * was chosen.
     *
     * @param array      $input    video_type, video_url, playback_mode
     * @param array|null $file     one $_FILES entry for an uploaded video
     * @param array|null $existing the item being edited, if any
     * @return array{ok: bool, data?: array, stored?: string, error?: string} stored is a newly saved file
     */
    private function videoFromInput(array $input, ?array $file, ?array $existing): array
    {
        $data = [
            'playback_mode' => ($input['playback_mode'] ?? '') === 'overlay' ? 'overlay' : 'fullscreen',
        ];

        if (($input['video_type'] ?? '') !== 'upload') {
            $source = $this->service->detectVideoSource((string)($input['video_url'] ?? ''));
            if ($source === null) {
                return ['ok' => false, 'error' => self::VIDEO_URL_HELP];
            }
            $data['video_type'] = $source['type'];
            $data['video_url'] = $source['url'];
            return ['ok' => true, 'data' => $data];
        }

        $data['video_type'] = 'upload';
        if (!empty($file['name'])) {
            $stored = $this->service->storeVideo($file);
            if (empty($stored['ok'])) {
                return ['ok' => false, 'error' => $stored['error']];
            }
            $data['video_path'] = $stored['path'];
            return ['ok' => true, 'data' => $data, 'stored' => $stored['path']];
        }
        if (empty($existing['video_path'])) {
            return ['ok' => false, 'error' => 'Please choose a video file to upload.'];
        }
        return ['ok' => true, 'data' => $data];
    }

    /** One file out of a PHP multi-file field (name="field[key]"), shaped like a single $_FILES entry. */
    private function fileAt(string $field, $key): array
    {
        $files = $_FILES[$field] ?? null;
        if (!is_array($files) || !isset($files['name'][$key])) {
            return ['error' => UPLOAD_ERR_NO_FILE];
        }
        return [
            'name'     => $files['name'][$key],
            'type'     => $files['type'][$key] ?? '',
            'tmp_name' => $files['tmp_name'][$key] ?? '',
            'error'    => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$key] ?? 0,
        ];
    }

    /** Compile one photo, rebuild the frame's target, and report the verdict. */
    private function compileAndFlash(array $frame, array $item, string $successLead): void
    {
        $result = $this->service->compileItem($frame, $item);
        $refresh = $this->service->refreshFrame((int)$frame['id']);

        if (!empty($result['ok'])) {
            $result['advice'] = ArTargetService::trackabilityAdvice($result['flag']);
            if (empty($refresh['ok'])) {
                $result['ok'] = false;
                $result['error'] = $refresh['error'];
            }
        }
        $this->flashGeneration($result, $successLead);
    }

    private function flashGeneration(array $result, string $successLead): void
    {
        if (empty($result['ok'])) {
            $message = $result['error'] ?? 'Target generation failed.';
            if (!empty($result['detail']) && ENVIRONMENT === 'development') {
                $message .= ' (' . $result['detail'] . ')';
            }
            flash('error', $message);
            return;
        }

        if (!isset($result['score'])) {
            flash('success', 'Every photo already has a target.');
            return;
        }

        $which = !empty($result['position']) && ($result['total'] ?? 1) > 1 ? ' (weakest: photo ' . $result['position'] . ')' : '';
        if ($result['flag'] === 'good') {
            flash('success', sprintf(
                '%s Trackability %d/100%s — %s Now run the live scan test.',
                $successLead,
                $result['score'],
                $which,
                $result['advice']
            ));
        } else {
            // Not an error: the target exists and may well work. But the whole
            // point of checking is to catch a weak photo before printing.
            flash('error', sprintf(
                '%s But trackability is only %d/100 (%s)%s. %s',
                $successLead,
                $result['score'],
                strtoupper($result['flag']),
                $which,
                $result['advice']
            ));
        }
    }

    /**
     * A scan URL a phone on the same network can actually open, for local
     * development only.
     *
     * On a dev machine the admin browses via http://localhost, which a phone
     * cannot resolve — and the camera needs a secure origin, so plain http on a
     * LAN IP is refused by the browser too. This rebuilds the URL as https on
     * the machine's LAN address, which is the only combination that works.
     *
     * Returns null in production, where SITE_URL is already the real public
     * https domain and this would be noise.
     */
    private function phoneTestUrl(string $slug): ?string
    {
        if (ENVIRONMENT !== 'development') {
            return null;
        }

        $ip = gethostbyname(gethostname() ?: '');
        // gethostbyname() returns its input unchanged on failure.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }
        // Loopback is no more use to a phone than localhost is.
        if (strpos($ip, '127.') === 0) {
            return null;
        }

        $basePath = rtrim((string)parse_url(SITE_URL, PHP_URL_PATH), '/');
        return 'https://' . $ip . $basePath . '/scan/' . $slug;
    }

    /**
     * Renders setup instructions when a migration has not been run, and reports
     * whether it did so.
     *
     * Deployment ships code without migrations, so on a fresh deploy this is
     * the expected first state — not an exception. Callers `return` on true.
     * The public scan pages keep working from the old single-photo columns in
     * the meantime; only the admin waits.
     */
    private function schemaMissing(): bool
    {
        $siteRoot = BASE_PATH;

        if (!$this->frames->tableExists()) {
            $migration = 'migrations/2026_07_29_ar_frames.sql';
            $title = 'the AR frames table does not exist yet.';
        } elseif (!$this->frames->itemsReady()) {
            $migration = 'migrations/2026_09_14_ar_frame_items.sql';
            $title = 'the multi-photo migration has not been run yet.';
        } else {
            return false;
        }

        $this->viewAdmin('admin/ar_frames_setup', [
            'metaTitle' => 'AR Frames — Setup Required',
            'setupTitle' => $title,
            'compiler' => $this->compilerStatus(),
            'migrationCommand' =>
                "cd {$siteRoot}\n" .
                "php tools/run-migration.php {$migration}",
            'npmCommand' => "cd {$siteRoot}/tools/mindar-compile\nnpm ci",
        ]);
        return true;
    }

    /**
     * Compiler health, shown in the admin UI so a broken toolchain is obvious
     * before someone tries to serve a customer at the counter.
     */
    private function compilerStatus(): array
    {
        require_once APP_PATH . '/services/ArTargetService.php';
        $service = new ArTargetService();
        $status = $service->preflight();
        $status['mode'] = $service->mode();
        return $status;
    }

    /**
     * Session-scoped rate limit on target generation, counted per photo
     * compiled. Kept in the session rather than a new table because this
     * endpoint is already behind admin auth — the limit is here to stop a stuck
     * finger from pegging the CPU, not to stop an anonymous attacker.
     */
    private function throttleGeneration(int $count = 1): bool
    {
        $now = time();
        $recent = array_values(array_filter(
            $_SESSION['ar_generate_times'] ?? [],
            fn($t) => ($now - (int)$t) < self::GENERATE_WINDOW_SECONDS
        ));

        if (count($recent) + $count > self::GENERATE_LIMIT) {
            $_SESSION['ar_generate_times'] = $recent;
            return false;
        }

        for ($i = 0; $i < $count; $i++) {
            $recent[] = $now;
        }
        $_SESSION['ar_generate_times'] = $recent;
        return true;
    }

    private function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
