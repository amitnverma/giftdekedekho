<?php
/**
 * Public Living Photo scanner.
 *
 * /scan/{slug}  — scans against one frame's photos. Preferred: matching against
 *                 a handful of targets is faster and far more reliable than
 *                 searching every active frame, and it stays that way as the
 *                 catalogue grows.
 * /scan         — the evergreen URL, matching every active photo at once.
 */
class ScanController extends BaseController
{
    /**
     * The evergreen /scan URL: opens the camera and matches against every active
     * photo at once, so a recipient can scan without knowing their code.
     *
     * The per-frame /scan/{slug} link on the sticker stays the reliable path —
     * it loads only that frame's targets, so it is smaller and faster regardless
     * of how many frames exist.
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
        foreach ($bundle['targets'] as $row) {
            $targets[] = $service->browserTarget($row, (string)$row['slug']);
        }

        renderRaw('store/scan_page', [
            'frame' => null,
            'targets' => $targets,
            'targetUrl' => ArFrameService::fileUrl($bundle['path']),
            'photoUrls' => [],
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
            $this->invalid('This Living Photo link is no longer available.', $frame);
            return;
        }

        // Partner content is sold for a fixed period.
        require_once APP_PATH . '/services/ArPartnerService.php';
        $partners = new ArPartnerService();
        if (ArPartnerService::isExpired($frame)) {
            $this->invalid('This AR experience has expired. Please contact the shop you bought it from to renew it.', $frame);
            return;
        }

        // Every photo of the frame that is ready, in the order of the one target
        // file that holds them all.
        $scan = $service->frameScan($frame);
        $playable = $scan === null ? [] : array_filter($scan['targets'], fn($t) => $t['videoType'] !== null);
        if (!$playable) {
            $this->invalid('This Living Photo is still being prepared. Please try again a little later.', $frame);
            return;
        }

        $partners->recordOpen($frame);
        $brand = $partners->brandFor($frame);

        // Expressed in the same shape as the scan-all page, so the view and the
        // browser module have exactly one code path. No photo URLs: the photos
        // are the surprise, and the public page never shows them.
        renderRaw('store/scan_page', [
            'frame' => $frame,
            'targets' => $scan['targets'],
            'targetUrl' => $scan['targetUrl'],
            'photoUrls' => [],
            'isAdminTest' => false,
            'verifyUrl' => null,
            'backUrl' => null,
            'csrf' => null,
            'siteName' => $brand['name'],
            'brand' => $brand,
        ]);
    }

    /** @param array|null $frame when known, so a partner's customer sees the partner's branding */
    private function invalid(string $message, ?array $frame = null): void
    {
        http_response_code(404);
        $brand = null;
        if ($frame !== null && !empty($frame['partner_id'])) {
            require_once APP_PATH . '/services/ArPartnerService.php';
            $brand = (new ArPartnerService())->brandFor($frame);
        }
        renderRaw('store/scan_invalid', [
            'message' => $message,
            'siteName' => $brand['name'] ?? siteSetting('site_name', SITE_NAME),
            'logo' => siteSetting('logo_path', '/images/GDKD logo.png'),
            'brand' => $brand,
        ]);
    }
}
