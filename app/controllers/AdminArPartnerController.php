<?php
/**
 * Admin → AR Partners.
 *
 * Everything that makes one partner's portal theirs is managed here rather than
 * in code: name, slug, logo, colour and contacts; which of singles and albums
 * they may sell; their price list and credit packs; their logins; and their
 * credit balance, including the "buy credits" requests they send.
 */
require_once APP_PATH . '/services/ArPartnerService.php';

class AdminArPartnerController extends BaseController
{
    private ArPartner $partners;
    private ArPartnerService $service;
    private ArPartnerCredit $credits;

    public function __construct()
    {
        $this->partners = new ArPartner();
        $this->service = new ArPartnerService();
        $this->credits = new ArPartnerCredit();
    }

    public function index(): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;

        $this->viewAdmin('admin/ar_partners_index', [
            'metaTitle'       => 'AR Partners',
            'partners'        => $this->partners->listWithStats(),
            'supportWhatsapp' => (string)(new Settings())->get('ar_partner_support_whatsapp', ''),
        ]);
    }

    public function saveSettings(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $digits = preg_replace('/[^\d+]/', '', (string)$this->input('ar_partner_support_whatsapp', ''));
        (new Settings())->set('ar_partner_support_whatsapp', $digits);
        flash('success', 'Partner support WhatsApp number saved.');
        redirect('/admin/ar-partners');
    }

    public function create(): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save(null);
            return;
        }

        $this->viewAdmin('admin/ar_partners_form', [
            'metaTitle' => 'New AR Partner',
            'partner'   => [
                'id' => 0, 'slug' => '', 'name' => '', 'tagline' => '', 'logo_path' => null,
                'brand_color' => ArPartnerService::DEFAULT_BRAND_COLOR,
                'contact_name' => '', 'contact_phone' => '', 'whatsapp' => '', 'contact_email' => '', 'website_url' => '',
                'allow_singles' => 1, 'allow_albums' => 1, 'max_album_pages' => ArFrameItem::MAX_PER_FRAME, 'max_video_mb' => 20,
                'base_credits' => 99, 'duration_prices' => null, 'validity_prices' => null, 'credit_packs' => null,
                'edit_window_days' => 7, 'is_active' => 1, 'notes' => '',
            ],
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;
        $partner = $this->findOrRedirect($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save($partner);
            return;
        }

        $this->viewAdmin('admin/ar_partners_form', [
            'metaTitle' => 'Edit ' . $partner['name'],
            'partner'   => $partner,
        ]);
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;
        $partner = $this->findOrRedirect($id);

        $this->viewAdmin('admin/ar_partners_show', [
            'metaTitle' => $partner['name'],
            'partner'   => $partner,
            'portalUrl' => url('/partner/' . $partner['slug']),
            'users'     => (new ArPartnerUser())->forPartner($id),
            'requests'  => $this->credits->requests($id, 30),
            'ledger'    => $this->credits->ledger($id, 50),
            'content'   => $this->service->contentList($id, [], 100),
            'counts'    => $this->service->contentCounts($id),
            'scans'     => (new ArScanEvent())->totals($id),
            'customers' => (new ArPartnerCustomer())->countForPartner($id),
        ]);
    }

    // ------------------------------------------------------------ saving

    private function save(?array $existing): void
    {
        $id = (int)($existing['id'] ?? 0);
        $back = $id ? '/admin/ar-partners/' . $id . '/edit' : '/admin/ar-partners/create';

        $name = trim((string)$this->input('name', ''));
        $slug = self::cleanSlug((string)$this->input('slug', ''));
        if ($slug === '' && $name !== '') {
            $slug = self::cleanSlug(slugify($name));
        }

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = 'Enter the partner\'s name.';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,59}$/', $slug)) {
            $errors[] = 'The page address needs at least 2 letters or numbers.';
        } elseif ($this->partners->slugTaken($slug, $id)) {
            $errors[] = 'Another partner already uses the page address "' . $slug . '".';
        }
        $email = strtolower(trim((string)$this->input('contact_email', '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The contact email does not look right.';
        }
        $website = trim((string)$this->input('website_url', ''));
        if ($website !== '' && ArPartnerService::safeWebsite($website) === null) {
            $errors[] = 'The website must be a normal http(s) address.';
        }

        // --- pricing: a blank price withholds that option from the partner
        $durationPrices = [];
        foreach (ArPartner::DURATIONS as $seconds) {
            $raw = trim((string)($_POST['duration_prices'][$seconds] ?? ''));
            if ($raw !== '' && !empty($_POST['duration_offered'][$seconds])) {
                $durationPrices[(string)$seconds] = max(0, (int)$raw);
            }
        }
        $validityPrices = [];
        foreach (array_keys(ArPartner::VALIDITIES) as $key) {
            $raw = trim((string)($_POST['validity_prices'][$key] ?? ''));
            if ($raw !== '' && !empty($_POST['validity_offered'][$key])) {
                $validityPrices[$key] = max(0, (int)$raw);
            }
        }
        if (!$durationPrices) {
            $errors[] = 'Offer at least one video duration.';
        }
        if (!$validityPrices) {
            $errors[] = 'Offer at least one validity period.';
        }
        $packs = [];
        foreach ((array)($_POST['packs'] ?? []) as $pack) {
            $price = (int)($pack['price'] ?? 0);
            $credits = (int)($pack['credits'] ?? 0);
            if ($price > 0 && $credits > 0) {
                $packs[] = ['price' => $price, 'credits' => $credits];
            }
        }

        $base = (int)$this->input('base_credits', 0);
        if ($base < 1) {
            $errors[] = 'The per-item rate must be at least 1 credit.';
        }

        // --- a first login, only when creating
        $ownerEmail = strtolower(trim((string)$this->input('owner_email', '')));
        $ownerPassword = (string)$this->input('owner_password', '');
        if (!$id && $ownerEmail !== '') {
            if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'The login email does not look right.';
            } elseif ((new ArPartnerUser())->findByEmail($ownerEmail)) {
                $errors[] = 'That login email is already in use.';
            }
            if (strlen($ownerPassword) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'The login password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect($back);
        }

        $data = [
            'slug'             => $slug,
            'name'             => mb_substr($name, 0, 120),
            'tagline'          => $this->nullIfBlank(mb_substr((string)$this->input('tagline', ''), 0, 200)),
            'brand_color'      => ArPartnerService::safeColor((string)$this->input('brand_color', '')),
            'contact_name'     => $this->nullIfBlank(mb_substr((string)$this->input('contact_name', ''), 0, 120)),
            'contact_phone'    => $this->nullIfBlank(substr(preg_replace('/[^\d+ ]/', '', (string)$this->input('contact_phone', '')), 0, 20)),
            'whatsapp'         => $this->nullIfBlank(substr(preg_replace('/[^\d+]/', '', (string)$this->input('whatsapp', '')), 0, 20)),
            'contact_email'    => $email === '' ? null : $email,
            'website_url'      => $website === '' ? null : ArPartnerService::safeWebsite($website),
            'allow_singles'    => $this->input('allow_singles') ? 1 : 0,
            'allow_albums'     => $this->input('allow_albums') ? 1 : 0,
            'max_album_pages'  => max(1, min(ArFrameItem::MAX_PER_FRAME, (int)$this->input('max_album_pages', ArFrameItem::MAX_PER_FRAME))),
            'max_video_mb'     => max(1, min(500, (int)$this->input('max_video_mb', 20))),
            'base_credits'     => $base,
            'duration_prices'  => json_encode($durationPrices),
            'validity_prices'  => json_encode($validityPrices),
            'credit_packs'     => json_encode($packs),
            'edit_window_days' => max(0, min(365, (int)$this->input('edit_window_days', 7))),
            'is_active'        => $this->input('is_active') ? 1 : 0,
            'notes'            => $this->nullIfBlank((string)$this->input('notes', '')),
        ];

        // --- logo
        $oldLogo = $existing['logo_path'] ?? null;
        if (!empty($_FILES['logo']['name'])) {
            $stored = $this->service->storeLogo($_FILES['logo']);
            if (empty($stored['ok'])) {
                flash('error', $stored['error']);
                redirect($back);
            }
            $data['logo_path'] = $stored['path'];
        } elseif ($this->input('remove_logo')) {
            $data['logo_path'] = null;
        }

        if ($id) {
            $this->partners->update($id, $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $this->partners->create($data);

            if ($ownerEmail !== '') {
                (new ArPartnerUser())->create(
                    $id,
                    trim((string)$this->input('owner_name', '')) ?: ($data['contact_name'] ?? $data['name']),
                    $ownerEmail,
                    $ownerPassword,
                    'owner'
                );
            }
            $bonus = (int)$this->input('opening_credits', 0);
            if ($bonus > 0) {
                $this->credits->applyNow($id, $bonus, 'added', 'Joining bonus', ['created_by' => currentUserId()]);
            }
        }

        // Old logo files go only once the row no longer points at them.
        if (array_key_exists('logo_path', $data) && $oldLogo && $oldLogo !== $data['logo_path']) {
            (new ArFrameService())->deleteFile($oldLogo);
        }

        flash('success', 'Partner saved. Their page is at /partner/' . $slug);
        redirect('/admin/ar-partners/' . $id);
    }

    // ------------------------------------------------------------- logins

    public function addUser(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findOrRedirect($id);

        $users = new ArPartnerUser();
        $name = trim((string)$this->input('name', ''));
        $email = strtolower(trim((string)$this->input('email', '')));
        $password = (string)$this->input('password', '');

        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a name and a valid email for the login.');
        } elseif ($users->findByEmail($email)) {
            flash('error', 'That email already has a partner login.');
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            flash('error', 'The password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
        } else {
            $users->create($id, mb_substr($name, 0, 120), $email, $password, (string)$this->input('role', 'editor'));
            flash('success', 'Login created for ' . $email . '. Share the password with them securely.');
        }
        redirect('/admin/ar-partners/' . $id . '#logins');
    }

    public function updateUser(int $id, int $userId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findOrRedirect($id);

        $users = new ArPartnerUser();
        $user = $users->findForPartner($id, $userId);
        if (!$user) {
            flash('error', 'That login was not found.');
            redirect('/admin/ar-partners/' . $id . '#logins');
        }

        $role = (string)$this->input('role', $user['role']);
        $users->update($userId, [
            'is_active' => $this->input('is_active') ? 1 : 0,
            'role'      => isset(ArPartnerUser::ROLES[$role]) ? $role : $user['role'],
        ]);

        $password = (string)$this->input('password', '');
        if ($password !== '') {
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                flash('error', 'Saved, but the new password was too short (minimum ' . PASSWORD_MIN_LENGTH . ') and was not changed.');
                redirect('/admin/ar-partners/' . $id . '#logins');
            }
            $users->setPassword($userId, $password);
        }
        flash('success', 'Login for ' . $user['email'] . ' updated' . ($password !== '' ? ', with a new password.' : '.'));
        redirect('/admin/ar-partners/' . $id . '#logins');
    }

    // ------------------------------------------------------------ credits

    /** Add or remove credits by hand, with a reason the partner will see. */
    public function adjustCredits(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findOrRedirect($id);

        $amount = (int)$this->input('amount', 0);
        $reason = trim((string)$this->input('description', ''));
        if ($amount === 0) {
            flash('error', 'Enter a number of credits — negative to take credits away.');
            redirect('/admin/ar-partners/' . $id . '#credits');
        }
        if ($reason === '') {
            $reason = $amount > 0 ? 'Credits added' : 'Credits removed';
        }

        $result = $this->credits->applyNow(
            $id,
            $amount,
            $amount > 0 ? 'added' : 'adjusted',
            $reason,
            ['created_by' => currentUserId()],
            (bool)$this->input('allow_negative')
        );
        if (empty($result['ok'])) {
            flash('error', $result['error'] . ' Tick "allow a negative balance" to go ahead anyway.');
        } else {
            flash('success', sprintf('%s%s credits. New balance: %s.', $amount > 0 ? '+' : '', number_format($amount), number_format($result['balance'])));
        }
        redirect('/admin/ar-partners/' . $id . '#credits');
    }

    /** Payment received for a request: add its credits, once. */
    public function fulfilRequest(int $id, int $requestId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findOrRedirect($id);

        $db = $this->credits->db();
        $db->beginTransaction();
        try {
            // Locked, so a double-click cannot add the credits twice.
            $request = $this->credits->lockRequest($id, $requestId);
            if (!$request || $request['status'] !== 'pending') {
                $db->rollBack();
                flash('error', 'That request has already been handled.');
                redirect('/admin/ar-partners/' . $id . '#credits');
            }
            $this->credits->apply($id, (int)$request['credits'], 'added', sprintf(
                'Credit pack %s%s (request #%d)',
                GDD_CURRENCY_SYMBOL,
                number_format((int)$request['price']),
                $requestId
            ), ['request_id' => $requestId, 'created_by' => currentUserId()]);
            $this->credits->updateRequest($requestId, [
                'status'     => 'fulfilled',
                'handled_by' => currentUserId(),
                'handled_at' => date('Y-m-d H:i:s'),
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        flash('success', number_format((int)$request['credits']) . ' credits added.');
        redirect('/admin/ar-partners/' . $id . '#credits');
    }

    public function cancelRequest(int $id, int $requestId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->findOrRedirect($id);

        $db = $this->credits->db();
        $db->beginTransaction();
        $request = $this->credits->lockRequest($id, $requestId);
        if ($request && $request['status'] === 'pending') {
            $this->credits->updateRequest($requestId, [
                'status'     => 'cancelled',
                'handled_by' => currentUserId(),
                'handled_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $db->commit();
        flash('success', 'Request cancelled.');
        redirect('/admin/ar-partners/' . $id . '#credits');
    }

    // ------------------------------------------------------------ helpers

    private function findOrRedirect(int $id): array
    {
        $partner = $this->partners->find($id);
        if (!$partner) {
            flash('error', 'That partner was not found.');
            redirect('/admin/ar-partners');
        }
        return $partner;
    }

    /** Setup instructions until the migrations have run. Callers return on true. */
    private function schemaMissing(): bool
    {
        if ($this->partners->tableExists()) {
            return false;
        }
        $frames = new ArFrame();
        $migration = !$frames->itemsReady()
            ? 'migrations/2026_09_14_ar_frame_items.sql' . "\nphp tools/run-migration.php migrations/2026_09_16_ar_partners.sql"
            : 'migrations/2026_09_16_ar_partners.sql';

        require_once APP_PATH . '/services/ArTargetService.php';
        $compiler = (new ArTargetService())->preflight();
        $compiler['mode'] = (new ArTargetService())->mode();

        $this->viewAdmin('admin/ar_frames_setup', [
            'metaTitle'        => 'AR Partners — Setup Required',
            'setupTitle'       => 'the AR partners migration has not been run yet.',
            'compiler'         => $compiler,
            'migrationCommand' => 'cd ' . BASE_PATH . "\nphp tools/run-migration.php " . $migration,
            'npmCommand'       => 'cd ' . BASE_PATH . "/tools/mindar-compile\nnpm ci",
        ]);
        return true;
    }

    /**
     * Reduce whatever was typed or pasted to a page address: "/partner1/",
     * "partner/Narain Jewellers" and a full copied URL all become just the last
     * part, lowercase, with anything else turned into single hyphens. The form
     * applies the same rules as you type.
     */
    public static function cleanSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^[a-z]+://[^/]+#', '', $value);
        $value = preg_replace('#^/*(partner/)?#', '', $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return substr(trim($value, '-'), 0, 60);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
