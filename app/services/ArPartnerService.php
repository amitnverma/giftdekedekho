<?php
/**
 * Business rules for B2B AR partners.
 *
 * A partner's content is an ordinary Living Photo frame — same items, same
 * compiler, same QR sticker and /scan/{slug} page — created through
 * ArFrameService. What this adds is the partner layer around it: pricing in
 * credits, charging for content in the same transaction that creates it,
 * validity and the edit window, branding for the pages a partner's customers
 * see, and scan analytics.
 */
require_once APP_PATH . '/services/ArFrameService.php';

class ArPartnerService
{
    public const LOGO_DIR = 'partner-logos';
    private const LOGO_MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const MAX_LOGO_BYTES = 2 * 1024 * 1024;

    /** GiftDekeDekho's own scan pages, which have always used this accent. */
    public const DEFAULT_BRAND_COLOR = '#e63946';

    private ArPartner $partners;
    private ArPartnerCredit $credits;
    private ArFrameService $frames;
    private PDO $db;

    public function __construct()
    {
        $this->partners = new ArPartner();
        $this->credits = new ArPartnerCredit();
        $this->frames = new ArFrameService();
        $this->db = $this->credits->db();
    }

    // ---------------------------------------------------------------- pricing

    /**
     * What a single or album costs this partner.
     *
     * Each page is charged the base rate plus its video-length surcharge plus the
     * validity surcharge, so an album of two 30-second pages for five years costs
     * exactly two such singles.
     *
     * @param int[] $durations one entry per page, in seconds
     * @return array{ok: bool, total?: int, pages?: int[], error?: string}
     */
    public function quote(array $partner, array $durations, string $validity): array
    {
        $durationPrices = ArPartner::durationPrices($partner);
        $validityPrices = ArPartner::validityPrices($partner);

        if (!isset($validityPrices[$validity])) {
            return ['ok' => false, 'error' => 'Choose how long the AR should stay active.'];
        }
        if (!$durations) {
            return ['ok' => false, 'error' => 'Add at least one photo and video.'];
        }

        $base = (int)$partner['base_credits'];
        $pages = [];
        foreach ($durations as $seconds) {
            $seconds = (int)$seconds;
            if (!isset($durationPrices[$seconds])) {
                return ['ok' => false, 'error' => 'Choose a video duration.'];
            }
            $pages[] = $base + $durationPrices[$seconds] + $validityPrices[$validity];
        }

        return ['ok' => true, 'total' => array_sum($pages), 'pages' => $pages];
    }

    /** How many basic items the balance covers — the dashboard's "can create". */
    public static function itemsAffordable(array $partner): int
    {
        $base = max(1, (int)$partner['base_credits']);
        return intdiv(max(0, (int)$partner['credit_balance']), $base);
    }

    /** Rupees per basic item when buying a pack, as the reference shows it. */
    public static function packItemPrice(array $pack, array $partner): float
    {
        return $pack['credits'] > 0 ? $pack['price'] * (int)$partner['base_credits'] / $pack['credits'] : 0.0;
    }

    // ---------------------------------------------------------------- content

    /**
     * Create a single or album and pay for it, all or nothing.
     *
     * The frame, its items and the debit are one transaction: content is never
     * created without being paid for, and credits are never taken for content
     * that failed to save. Targets are compiled afterwards by the caller — that
     * takes seconds per photo and must not hold the partner's row locked.
     *
     * @param array{kind: string, title: string, customer_id: int, validity: string} $attrs
     * @param array[] $pages each: photo_path, video_path, max_seconds, title, playback_mode
     * @return array{ok: bool, frame_id?: int, cost?: int, error?: string}
     */
    public function createContent(array $partner, ?int $partnerUserId, array $attrs, array $pages): array
    {
        $quote = $this->quote($partner, array_column($pages, 'max_seconds'), $attrs['validity']);
        if (empty($quote['ok'])) {
            return $quote;
        }

        $years = ArPartner::VALIDITIES[$attrs['validity']][1];
        $now = time();

        $items = [];
        foreach ($pages as $page) {
            $items[] = [
                'photo_path'    => $page['photo_path'],
                'video_type'    => 'upload',
                'video_path'    => $page['video_path'],
                'playback_mode' => ArFrameItem::playbackMode($page['playback_mode'] ?? null),
                'max_seconds'   => (int)$page['max_seconds'],
                'title'         => $page['title'] !== '' ? $page['title'] : null,
            ];
        }

        $this->db->beginTransaction();
        try {
            $frameId = $this->frames->createFrame([
                'channel'             => 'partner',
                'partner_id'          => (int)$partner['id'],
                'partner_customer_id' => $attrs['customer_id'],
                'title'               => $attrs['title'],
                'content_kind'        => $attrs['kind'],
                'validity'            => $attrs['validity'],
                'active_until'        => $years === null ? null : date('Y-m-d H:i:s', strtotime('+' . $years . ' years', $now)),
                'editable_until'      => date('Y-m-d H:i:s', $now + 86400 * (int)$partner['edit_window_days']),
                'credits_charged'     => $quote['total'],
                // From PHP, like the validity and edit-window dates beside it —
                // MySQL's own clock may be in a different timezone.
                'created_at'          => date('Y-m-d H:i:s', $now),
            ], $items);

            $label = ($attrs['kind'] === 'album' ? 'Album: ' : 'Single: ') . $attrs['title'];
            $charge = $this->credits->apply(
                (int)$partner['id'],
                -$quote['total'],
                'used',
                sprintf('%s (%d item%s, %s)', $label, count($items), count($items) === 1 ? '' : 's',
                    ArPartner::validityLabel($attrs['validity'])),
                ['frame_id' => $frameId]
            );
            if (empty($charge['ok'])) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => $charge['error']];
            }

            $this->db->commit();
            return ['ok' => true, 'frame_id' => $frameId, 'cost' => $quote['total']];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * A partner's content with the summary each list row needs.
     *
     * @param array{kind?: string|null, customer_id?: int|null, search?: string} $filters
     */
    public function contentList(int $partnerId, array $filters = [], int $limit = 500): array
    {
        $sql = "SELECT f.*,
                       c.name AS customer_name,
                       (SELECT i.photo_path FROM ar_frame_items i WHERE i.frame_id = f.id
                         ORDER BY i.sort_order, i.id LIMIT 1) AS first_photo,
                       (SELECT COUNT(*) FROM ar_frame_items i WHERE i.frame_id = f.id) AS item_count,
                       (SELECT COUNT(*) FROM ar_frame_items i WHERE i.frame_id = f.id
                         AND i.target_path IS NOT NULL) AS target_count,
                       (SELECT COUNT(*) FROM ar_scan_events e WHERE e.frame_id = f.id) AS opens
                FROM ar_frames f
                LEFT JOIN ar_partner_customers c ON c.id = f.partner_customer_id
                WHERE f.partner_id = ?";
        $params = [$partnerId];

        if (!empty($filters['kind'])) {
            $sql .= ' AND f.content_kind = ?';
            $params[] = $filters['kind'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND f.partner_customer_id = ?';
            $params[] = (int)$filters['customer_id'];
        }
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (f.title LIKE ? OR f.slug LIKE ? OR c.name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY f.created_at DESC, f.id DESC LIMIT ' . max(1, $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** One of this partner's frames, or null — ids from URLs are never trusted alone. */
    public function findContent(int $partnerId, int $frameId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*, c.name AS customer_name, c.phone AS customer_phone_number
             FROM ar_frames f
             LEFT JOIN ar_partner_customers c ON c.id = f.partner_customer_id
             WHERE f.id = ? AND f.partner_id = ? LIMIT 1'
        );
        $stmt->execute([$frameId, $partnerId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{singles: int, albums: int, pages: int} */
    public function contentCounts(int $partnerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT SUM(f.content_kind = 'single') AS singles,
                    SUM(f.content_kind = 'album') AS albums,
                    (SELECT COUNT(*) FROM ar_frame_items i JOIN ar_frames g ON g.id = i.frame_id
                      WHERE g.partner_id = ?) AS pages
             FROM ar_frames f WHERE f.partner_id = ?"
        );
        $stmt->execute([$partnerId, $partnerId]);
        $row = $stmt->fetch() ?: [];
        return [
            'singles' => (int)($row['singles'] ?? 0),
            'albums'  => (int)($row['albums'] ?? 0),
            'pages'   => (int)($row['pages'] ?? 0),
        ];
    }

    public static function isExpired(array $frame): bool
    {
        return !empty($frame['active_until']) && strtotime((string)$frame['active_until']) < time();
    }

    public static function isEditable(array $frame): bool
    {
        return !empty($frame['editable_until']) && strtotime((string)$frame['editable_until']) >= time();
    }

    /**
     * One word for where a piece of content stands, plus the tag style to show.
     *
     * @return array{0: string, 1: string} [label, css modifier]
     */
    public static function contentState(array $frame): array
    {
        if (self::isExpired($frame)) {
            return ['Expired', 'off'];
        }
        if (empty($frame['is_active'])) {
            return ['Switched off', 'off'];
        }
        $items = (int)($frame['item_count'] ?? 0);
        $targets = (int)($frame['target_count'] ?? 0);
        if (empty($frame['target_path']) || ($items > 0 && $targets < $items)) {
            return ['Needs attention', 'warn'];
        }
        return ['Live', 'live'];
    }

    // --------------------------------------------------------------- branding

    /**
     * Branding for a page one of the partner's customers sees — the scan page,
     * its error page, the sticker. GiftDekeDekho's own frames get the site's own
     * name and accent, and no "powered by" line.
     *
     * @return array{name: string, logo: string|null, color: string, website: string|null, poweredBy: string|null}
     */
    public function brandFor(?array $frame): array
    {
        $siteName = (string)siteSetting('site_name', SITE_NAME);
        $partner = null;
        if ($frame !== null && !empty($frame['partner_id']) && $this->partners->tableExists()) {
            $partner = $this->partners->find((int)$frame['partner_id']);
        }
        return self::brand($partner, $siteName);
    }

    public static function brand(?array $partner, string $siteName): array
    {
        if ($partner === null) {
            return [
                'name'      => $siteName,
                'logo'      => null,
                'color'     => self::DEFAULT_BRAND_COLOR,
                'website'   => null,
                'poweredBy' => null,
            ];
        }
        return [
            'name'      => (string)$partner['name'],
            'logo'      => empty($partner['logo_path']) ? null : ArFrameService::fileUrl($partner['logo_path']),
            'color'     => self::safeColor($partner['brand_color'] ?? null),
            'website'   => self::safeWebsite($partner['website_url'] ?? null),
            'poweredBy' => $siteName,
        ];
    }

    public static function safeColor(?string $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : self::DEFAULT_BRAND_COLOR;
    }

    /** An http(s) URL safe to put in an href, or null. */
    public static function safeWebsite(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url) ? $url : null;
    }

    /** Validate and store a partner logo. SVG is refused: it can carry script. */
    public function storeLogo(array $file): array
    {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Please choose a logo file.'];
        }
        if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['ok' => false, 'error' => 'The logo upload failed. Please try again.'];
        }
        if (($file['size'] ?? 0) > self::MAX_LOGO_BYTES) {
            return ['ok' => false, 'error' => 'That logo is too large. Maximum is 2MB.'];
        }

        $mime = (string)finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
        if (!isset(self::LOGO_MIMES[$mime]) || @getimagesize($file['tmp_name']) === false) {
            return ['ok' => false, 'error' => 'Please upload the logo as a PNG, JPG or WebP image.'];
        }

        $dir = UPLOAD_PATH . '/' . self::LOGO_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create the upload directory.'];
        }
        $name = 'logo_' . bin2hex(random_bytes(8)) . '.' . self::LOGO_MIMES[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Could not save the logo.'];
        }
        return ['ok' => true, 'path' => self::LOGO_DIR . '/' . $name];
    }

    // ----------------------------------------------------------- sign-in

    /** How long an emailed reset link works. */
    public const RESET_LINK_SECONDS = 3600;

    /**
     * A password-reset token for one partner login.
     *
     * Nothing is stored: the token is the login id and an expiry, signed with a
     * key that includes the current password hash. Setting any new password —
     * through this link, the portal or the admin — changes that hash, so every
     * link issued before it stops working, including this one once it is used.
     */
    public function resetToken(array $user, ?int $expires = null): string
    {
        $expires = $expires ?? time() + self::RESET_LINK_SECONDS;
        $payload = (int)$user['id'] . '.' . $expires;
        return $payload . '.' . $this->resetSignature($user, $payload);
    }

    /** The login a reset token belongs to, if it is genuine, unexpired and unused. */
    public function userForResetToken(int $partnerId, string $token): ?array
    {
        if (!preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{64})$/', $token, $m) || (int)$m[2] < time()) {
            return null;
        }
        $user = (new ArPartnerUser())->findForPartner($partnerId, (int)$m[1]);
        if (!$user || empty($user['is_active'])) {
            return null;
        }
        return hash_equals($this->resetSignature($user, $m[1] . '.' . $m[2]), $m[3]) ? $user : null;
    }

    /**
     * A short fingerprint of a login's password hash, kept in its session: once
     * the password changes, sessions signed in with the old one end.
     */
    public static function passwordStamp(array $user): string
    {
        return substr(hash('sha256', (string)$user['password_hash']), 0, 20);
    }

    private function resetSignature(array $user, string $payload): string
    {
        return hash_hmac('sha256', $payload . '|' . (int)$user['partner_id'] . '|' . $user['password_hash'], $this->secret('ar_partner_reset_key'));
    }

    // -------------------------------------------------------------- analytics

    /**
     * Count an opening of a public scan page.
     *
     * Link previews (WhatsApp, Telegram, Facebook…) fetch the page the moment a
     * link is shared, which would otherwise count as an open nobody made.
     * Failures are swallowed: a recipient's scan must never break over a stat.
     */
    public function recordOpen(array $frame): void
    {
        $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($agent === '' || preg_match('/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|slack|discord|curl|wget|python|headless/i', $agent)) {
            return;
        }
        try {
            if (!$this->partners->tableExists()) {
                return;
            }
            $visitor = substr(hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $agent, $this->visitorKey()), 0, 32);
            (new ArScanEvent())->record((int)$frame['id'], empty($frame['partner_id']) ? null : (int)$frame['partner_id'], $visitor);
        } catch (Throwable $e) {
            // Analytics is never worth a failed scan page.
        }
    }

    /** Secret for the visitor hash, created on first use and kept in settings. */
    private function visitorKey(): string
    {
        return $this->secret('ar_visitor_hash_key');
    }

    private function secret(string $name): string
    {
        $settings = new Settings();
        $key = (string)$settings->get($name, '');
        if ($key === '') {
            $key = bin2hex(random_bytes(32));
            $settings->set($name, $key);
        }
        return $key;
    }
}
