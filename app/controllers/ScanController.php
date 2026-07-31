<?php
/**
 * Public Living Photo scanner.
 *
 * /scan/{slug}  — scans against one frame's target. Preferred: matching against
 *                 a single target is faster and far more reliable than searching
 *                 every active frame, and it stays that way as the catalogue grows.
 * /scan         — the evergreen URL. There is no reliable way to match against
 *                 every target at once on a phone, so this explains where to find
 *                 the personal link rather than pretending to scan everything.
 */
class ScanController extends BaseController
{
    /**
     * The evergreen /scan URL: opens the camera and matches against every active
     * frame at once, so a recipient can scan without knowing their code.
     *
     * The per-frame /scan/{slug} link on the printed card stays the reliable
     * path — it loads a single target, so it is smaller and faster regardless of
     * how many frames exist.
     */
    public function index(): void
    {
        require_once APP_PATH . '/services/ArFrameService.php';
        $service = new ArFrameService();
        $frames = new ArFrame();

        if (!$frames->tableExists()) {
            $this->unavailable();
            return;
        }

        $bundle = $service->scanBundle();
        if (empty($bundle['ok'])) {
            $this->unavailable();
            return;
        }

        // One entry per target, indexed the same way the browser reports a match.
        $targets = [];
        foreach ($bundle['frames'] as $frame) {
            $targets[] = $service->browserTarget($frame);
        }

        renderRaw('store/scan_page', [
            'frame' => null,
            'targets' => $targets,
            'targetUrl' => ArFrameService::fileUrl($bundle['path']),
            'photoUrl' => '',
            'isAdminTest' => false,
            'verifyUrl' => null,
            'backUrl' => null,
            'csrf' => null,
            'siteName' => siteSetting('site_name', SITE_NAME),
            'bundleBytes' => $bundle['bytes'],
        ]);
    }

    private function unavailable(): void
    {
        renderRaw('store/scan_landing', [
            'siteName' => siteSetting('site_name', SITE_NAME),
            'logo' => siteSetting('logo_path', '/images/GDKD logo.png'),
        ]);
    }

    public function show(string $slug): void
    {
        // Validate the shape before touching the database — the slug comes
        // straight off a printed card, so typos are the common case.
        if (!preg_match('/^gdd-[a-z2-9]{6}$/', $slug)) {
            $this->invalid('That link doesn\'t look right. Please check the code on your card and try again.');
            return;
        }

        require_once APP_PATH . '/services/ArFrameService.php';
        $service = new ArFrameService();
        $frames = new ArFrame();

        // Code deploys ahead of migrations, so the table may not exist yet.
        // A recipient holding a printed card must never see a raw SQL error.
        if (!$frames->tableExists()) {
            $this->invalid('This Living Photo is still being prepared. Please try again a little later.');
            return;
        }

        $frame = $frames->findBySlug($slug);

        if (!$frame || empty($frame['is_active'])) {
            $this->invalid('This Living Photo link is no longer available.');
            return;
        }
        if (empty($frame['target_path'])) {
            $this->invalid('This Living Photo is still being prepared. Please try again a little later.');
            return;
        }

        $playback = $service->playback($frame);
        if ($playback === null) {
            $this->invalid('This Living Photo is still being prepared. Please try again a little later.');
            return;
        }

        // Single frame expressed in the same shape as the scan-all page, so the
        // view and the browser module have exactly one code path.
        renderRaw('store/scan_page', [
            'frame' => $frame,
            'targets' => [$service->browserTarget($frame)],
            'targetUrl' => ArFrameService::fileUrl($frame['target_path']),
            'photoUrl' => ArFrameService::fileUrl($frame['photo_path']),
            'isAdminTest' => false,
            'verifyUrl' => null,
            'backUrl' => null,
            'csrf' => null,
            'siteName' => siteSetting('site_name', SITE_NAME),
        ]);
    }

    private function invalid(string $message): void
    {
        http_response_code(404);
        renderRaw('store/scan_invalid', [
            'message' => $message,
            'siteName' => siteSetting('site_name', SITE_NAME),
            'logo' => siteSetting('logo_path', '/images/GDKD logo.png'),
        ]);
    }
}
