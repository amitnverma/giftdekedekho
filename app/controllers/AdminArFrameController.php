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
 */
class AdminArFrameController extends BaseController
{
    private const PER_PAGE = 20;

    /** Target generation is CPU-heavy, so cap it per admin session. */
    private const GENERATE_LIMIT = 20;
    private const GENERATE_WINDOW_SECONDS = 300;

    private ArFrame $frames;
    private ArFrameService $service;

    public function __construct()
    {
        require_once APP_PATH . '/services/ArFrameService.php';
        $this->frames = new ArFrame();
        $this->service = new ArFrameService();
    }

    // ------------------------------------------------------------------ queue

    public function index(): void
    {
        $this->requireAdmin();

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
        ]);
    }

    public function show(int $id): void
    {
        $this->requireAdmin();

        $frame = $this->frames->findWithContext($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        $this->viewAdmin('admin/ar_frames_show', [
            'metaTitle'    => 'AR Frame ' . $frame['slug'],
            'frame'        => $frame,
            'playback'     => $this->service->playback($frame),
            'transitions'  => ArFrame::allowedTransitions($frame),
            'scanUrl'      => ArFrameService::scanUrl($frame['slug']),
            'phoneTestUrl' => $this->phoneTestUrl($frame['slug']),
            'compiler'     => $this->compilerStatus(),
        ]);
    }

    // ------------------------------------------------- shared pipeline actions

    /**
     * Generate (or regenerate) the .mind target for a frame. Used by both the
     * online queue and the walk-in flow.
     */
    public function generateTarget(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        if (!$this->throttleGeneration()) {
            flash('error', 'Too many target generations in a short time. Please wait a minute and try again.');
            redirect('/admin/ar-frames/' . $id);
        }

        $result = $this->service->generateTarget($id);

        if (empty($result['ok'])) {
            $message = $result['error'];
            if (!empty($result['detail']) && ENVIRONMENT === 'development') {
                $message .= ' (' . $result['detail'] . ')';
            }
            flash('error', $message);
            redirect('/admin/ar-frames/' . $id);
        }

        if ($result['flag'] === 'good') {
            flash('success', sprintf(
                'Target generated. Trackability %d/100 — %s Now run the live scan test.',
                $result['score'],
                $result['advice']
            ));
        } else {
            // Not an error: the target exists and may well work. But the whole
            // point of checking is to catch a weak photo before printing.
            flash('error', sprintf(
                'Target generated, but trackability is only %d/100 (%s). %s',
                $result['score'],
                strtoupper($result['flag']),
                $result['advice']
            ));
        }

        redirect('/admin/ar-frames/' . $id);
    }

    /**
     * Recorded by the live-test page (JSON) once MindAR reports a real match
     * against the frame's own target. This is the gate that unlocks printing.
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

        if (!$this->service->markVerified($id)) {
            jsonResponse(['ok' => false, 'message' => 'Could not record the test result.'], 500);
        }

        jsonResponse([
            'ok' => true,
            'message' => 'Live scan test passed. This frame is ready to print.',
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
     * so it must not be possible to clear by accident.
     */
    public function confirmTest(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }
        if (empty($frame['target_path'])) {
            flash('error', 'Generate the target before recording a live test.');
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

        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

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

    /** Replace the customer photo (e.g. after a poor trackability warning). */
    public function replacePhoto(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        $stored = $this->service->storePhoto($_FILES['photo'] ?? []);
        if (empty($stored['ok'])) {
            flash('error', $stored['error']);
            redirect('/admin/ar-frames/' . $id);
        }

        $oldPhoto = $frame['photo_path'];
        $oldTarget = $frame['target_path'];

        // A new photo invalidates the old target and any earlier live test.
        $this->frames->update($id, [
            'photo_path'         => $stored['path'],
            'target_path'        => null,
            'trackability_score' => null,
            'trackability_flag'  => null,
            'trackability_json'  => null,
            'verified_at'        => null,
            'status'             => 'pending_setup',
        ]);
        $this->service->deleteFile($oldPhoto);
        $this->service->deleteFile($oldTarget);

        flash('success', 'Photo replaced. Generate the target again to continue.');
        redirect('/admin/ar-frames/' . $id);
    }

    /** Update the video, playback mode, notes and customer reference. */
    public function updateDetails(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        $data = [
            'customer_name'  => $this->nullIfBlank((string)$this->input('customer_name', '')),
            'customer_phone' => $this->nullIfBlank((string)$this->input('customer_phone', '')),
            'notes'          => $this->nullIfBlank((string)$this->input('notes', '')),
            'playback_mode'  => $this->input('playback_mode') === 'overlay' ? 'overlay' : 'fullscreen',
            'is_active'      => $this->input('is_active') ? 1 : 0,
        ];

        $videoType = $this->input('video_type') === 'upload' ? 'upload' : 'youtube';

        if ($videoType === 'youtube') {
            $url = $this->service->normaliseYoutubeUrl((string)$this->input('video_url', ''));
            if ($url === null) {
                flash('error', 'Please enter a valid YouTube link.');
                redirect('/admin/ar-frames/' . $id);
            }
            $data['video_type'] = 'youtube';
            $data['video_url'] = $url;
        } else {
            $data['video_type'] = 'upload';
            // Only replace the file when a new one was actually chosen.
            if (!empty($_FILES['video']['name'])) {
                $stored = $this->service->storeVideo($_FILES['video']);
                if (empty($stored['ok'])) {
                    flash('error', $stored['error']);
                    redirect('/admin/ar-frames/' . $id);
                }
                $old = $frame['video_path'];
                $data['video_path'] = $stored['path'];
                $this->service->deleteFile($old);
            } elseif (empty($frame['video_path'])) {
                flash('error', 'Please choose a video file to upload.');
                redirect('/admin/ar-frames/' . $id);
            }
        }

        $this->frames->update($id, $data);
        flash('success', 'Frame details updated.');
        redirect('/admin/ar-frames/' . $id);
    }

    // ------------------------------------------------- walk-in (Quick Create)

    /**
     * One-page counter flow. On submit it stores the photo, creates the frame
     * and compiles the target synchronously — the customer is standing there, so
     * a queue would be useless. The response lands on the detail page with the
     * trackability verdict and the live-test button ready.
     */
    public function quickCreate(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->viewAdmin('admin/ar_frames_quick_create', [
                'metaTitle' => 'Quick Create (Walk-in)',
                'compiler'  => $this->compilerStatus(),
            ]);
            return;
        }

        $this->requireCsrf();

        $videoType = $this->input('video_type') === 'upload' ? 'upload' : 'youtube';
        $videoUrl = null;
        $videoPath = null;

        // Validate the video before touching the photo, so a bad link doesn't
        // leave an orphaned upload behind.
        if ($videoType === 'youtube') {
            $videoUrl = $this->service->normaliseYoutubeUrl((string)$this->input('video_url', ''));
            if ($videoUrl === null) {
                $this->quickCreateError('Please paste a valid YouTube link (youtube.com or youtu.be).');
            }
        }

        $photo = $this->service->storePhoto($_FILES['photo'] ?? []);
        if (empty($photo['ok'])) {
            $this->quickCreateError($photo['error']);
        }

        if ($videoType === 'upload') {
            $video = $this->service->storeVideo($_FILES['video'] ?? []);
            if (empty($video['ok'])) {
                $this->service->deleteFile($photo['path']);
                $this->quickCreateError($video['error']);
            }
            $videoPath = $video['path'];
        }

        if (!$this->throttleGeneration()) {
            $this->service->deleteFile($photo['path']);
            if ($videoPath !== null) {
                $this->service->deleteFile($videoPath);
            }
            $this->quickCreateError('Too many target generations in a short time. Please wait a minute and try again.');
        }

        $id = $this->service->createFrame([
            'channel'        => 'in_store',
            'photo_path'     => $photo['path'],
            'video_type'     => $videoType,
            'video_url'      => $videoUrl,
            'video_path'     => $videoPath,
            'playback_mode'  => $this->input('playback_mode') === 'overlay' ? 'overlay' : 'fullscreen',
            'customer_name'  => $this->nullIfBlank((string)$this->input('customer_name', '')),
            'customer_phone' => $this->nullIfBlank((string)$this->input('customer_phone', '')),
            'notes'          => $this->nullIfBlank((string)$this->input('notes', '')),
            'created_by'     => currentUserId(),
        ]);

        $result = $this->service->generateTarget($id);

        if (empty($result['ok'])) {
            flash('error', $result['error'] . ' The frame was saved — replace the photo and generate again.');
            redirect('/admin/ar-frames/' . $id);
        }

        if ($result['flag'] === 'good') {
            flash('success', sprintf(
                'Frame %s created and target generated in seconds. Trackability %d/100. Run the live scan test now, before the customer leaves.',
                $this->frames->find($id)['slug'],
                $result['score']
            ));
        } else {
            flash('error', sprintf(
                'Heads up: trackability is only %d/100 (%s). %s Swap the photo now while the customer is still here.',
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
            'video_url'      => (string)$this->input('video_url', ''),
            'notes'          => (string)$this->input('notes', ''),
        ]);
        redirect('/admin/ar-frames/quick-create');
    }

    // ------------------------------------------------------------ live test

    /**
     * The admin-facing live scan test: the same MindAR camera page the customer
     * gets, but wired to report a successful match back to verify().
     */
    public function liveTest(int $id): void
    {
        $this->requireAdmin();

        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }
        if (empty($frame['target_path'])) {
            flash('error', 'Generate the target before running the live scan test.');
            redirect('/admin/ar-frames/' . $id);
        }

        // Rendered with no admin chrome: this page is held up to a phone camera,
        // and it reuses the public scan view so the test exercises the real thing.
        renderRaw('store/scan_page', [
            'frame'      => $frame,
            'playback'   => $this->service->playback($frame),
            'targetUrl'  => ArFrameService::fileUrl($frame['target_path']),
            'photoUrl'   => ArFrameService::fileUrl($frame['photo_path']),
            'isAdminTest' => true,
            'verifyUrl'  => url('/admin/ar-frames/' . $id . '/verify'),
            'backUrl'    => url('/admin/ar-frames/' . $id),
            'csrf'       => csrfToken(),
            'siteName'   => siteSetting('site_name', SITE_NAME),
        ]);
    }

    /**
     * Full-screen photo, to scan during the live test.
     *
     * Before printing there is no physical photo to point a camera at. Put this
     * on the biggest screen available and scan it with the phone — the frame's
     * own photo is the target, so this is a faithful stand-in for the print.
     */
    public function photo(int $id): void
    {
        $this->requireAdmin();

        $frame = $this->frames->find($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        renderRaw('admin/ar_frame_photo', [
            'frame' => $frame,
            'backUrl' => url('/admin/ar-frames/' . $id),
        ]);
    }

    // ------------------------------------------------------ instruction card

    public function card(int $id): void
    {
        $this->requireAdmin();

        $frame = $this->frames->findWithContext($id);
        if (!$frame) {
            flash('error', 'That AR frame was not found.');
            redirect('/admin/ar-frames');
        }

        require_once APP_PATH . '/services/InstructionCardService.php';
        (new InstructionCardService())->output($frame);
    }

    // ----------------------------------------------------------------- helpers

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
     * Session-scoped rate limit on target generation. Kept in the session rather
     * than a new table because this endpoint is already behind admin auth — the
     * limit is here to stop a stuck finger from pegging the CPU, not to stop an
     * anonymous attacker.
     */
    private function throttleGeneration(): bool
    {
        $now = time();
        $recent = array_values(array_filter(
            $_SESSION['ar_generate_times'] ?? [],
            fn($t) => ($now - (int)$t) < self::GENERATE_WINDOW_SECONDS
        ));

        if (count($recent) >= self::GENERATE_LIMIT) {
            $_SESSION['ar_generate_times'] = $recent;
            return false;
        }

        $recent[] = $now;
        $_SESSION['ar_generate_times'] = $recent;
        return true;
    }

    private function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
