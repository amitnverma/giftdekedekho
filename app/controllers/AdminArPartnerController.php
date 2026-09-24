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
require_once APP_PATH . '/services/NotificationService.php';

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

        $this->purgeTrialContent();
        // Registrations waiting for activation first — they are the ones needing action.
        $partners = $this->partners->listWithStats();
        usort($partners, fn($a, $b) => (int)ArPartner::awaitingActivation($b) <=> (int)ArPartner::awaitingActivation($a));

        $this->viewAdmin('admin/ar_partners_index', [
            'metaTitle'       => 'AR Partners',
            'partners'        => $partners,
            'supportWhatsapp' => (string)(new Settings())->get('ar_partner_support_whatsapp', ''),
            'trialsReady'     => $this->partners->trialsReady(),
            'trial'           => ArPartner::trialSettings(),
        ]);
    }

    /** The free trial new registrations get: on or off, its credits, and how long its content lives. */
    public function saveTrialSettings(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $credits = max(0, min(1000000, (int)$this->input('dex_trial_credits', ArPartner::DEFAULT_TRIAL_CREDITS)));
        $days = max(1, min(365, (int)$this->input('dex_trial_days', ArPartner::DEFAULT_TRIAL_DAYS)));
        (new Settings())->setMany([
            'dex_trial_enabled' => $this->input('dex_trial_enabled') ? '1' : '0',
            'dex_trial_credits' => (string)$credits,
            'dex_trial_days'    => (string)$days,
        ]);
        flash('success', sprintf('Free trial saved: %s credits, content deleted after %d day%s. Applies to new trial accounts and new trial content.',
            number_format($credits), $days, $days === 1 ? '' : 's'));
        redirect('/admin/ar-partners#trial');
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

        $stash = $this->takeFormStash(0);
        $this->viewAdmin('admin/ar_partners_form', [
            'metaTitle' => 'New AR Partner',
            'owner'     => $stash['owner'] ?? [],
            'trialsReady' => $this->partners->trialsReady(),
            'trial'       => ArPartner::trialSettings(),
            'partner'   => array_merge([
                'id' => 0, 'slug' => '', 'name' => '', 'tagline' => '', 'logo_path' => null,
                'brand_color' => ArPartnerService::DEFAULT_BRAND_COLOR,
                'contact_name' => '', 'contact_phone' => '', 'whatsapp' => '', 'contact_email' => '', 'website_url' => '',
                'allow_singles' => 1, 'allow_albums' => 1, 'max_album_pages' => ArFrameItem::MAX_PER_FRAME, 'max_video_mb' => 20,
                'base_credits' => ArPartner::DEFAULT_BASE_CREDITS, 'duration_prices' => null, 'validity_prices' => null, 'credit_packs' => null,
                'edit_window_days' => 7, 'is_active' => 1, 'notes' => '',
            ], $stash['data'] ?? []),
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

        $stash = $this->takeFormStash($id);
        $this->viewAdmin('admin/ar_partners_form', [
            'metaTitle' => 'Edit ' . $partner['name'],
            'partner'   => array_merge($partner, $stash['data'] ?? []),
            'logins'    => (new ArPartnerUser())->forPartner($id),
        ]);
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        if ($this->schemaMissing()) return;
        $this->purgeTrialContent();
        $partner = $this->findOrRedirect($id);

        $this->viewAdmin('admin/ar_partners_show', [
            'metaTitle' => $partner['name'],
            'partner'   => $partner,
            'trial'     => ArPartner::isTrial($partner) && $this->partners->trialsReady() ? ArPartner::trialSettings() : null,
            'trialsReady' => $this->partners->trialsReady(),
            'trialDefaults' => ArPartner::trialSettings(),
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
        } elseif (in_array($slug, ArPartner::RESERVED_SLUGS, true)) {
            $errors[] = 'The page address "' . $slug . '" is reserved. Please choose another.';
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

        // --- the first login, required when creating: without it nobody can
        // sign in, and its email is where "Forgot password" sends reset links.
        $ownerName = trim((string)$this->input('owner_name', ''));
        $ownerEmail = strtolower(trim((string)$this->input('owner_email', '')));
        $ownerPassword = (string)$this->input('owner_password', '');
        if (!$id) {
            if ($ownerEmail === '') {
                $errors[] = 'Enter a login email for the partner — they sign in with it, and password reset links are sent to it.';
            } elseif (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'The login email does not look right.';
            } elseif ((new ArPartnerUser())->findByEmail($ownerEmail)) {
                $errors[] = 'That login email is already used by a partner login.';
            }
            if (strlen($ownerPassword) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'Set a login password of at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            }
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
            // 0 = no limit; the column is a TINYINT UNSIGNED.
            'max_album_pages'  => max(0, min(255, (int)$this->input('max_album_pages', ArFrameItem::MAX_PER_FRAME))),
            'max_video_mb'     => max(1, min(500, (int)$this->input('max_video_mb', 20))),
            'base_credits'     => $base,
            'duration_prices'  => json_encode($durationPrices),
            'validity_prices'  => json_encode($validityPrices),
            'credit_packs'     => json_encode($packs),
            'edit_window_days' => max(0, min(365, (int)$this->input('edit_window_days', 7))),
            'is_active'        => $this->input('is_active') ? 1 : 0,
            'notes'            => $this->nullIfBlank((string)$this->input('notes', '')),
        ];

        if ($errors) {
            // Keep what was typed — the form is long, and a typo in one field
            // must not cost the whole price list. Passwords are never kept.
            $_SESSION['ar_partner_form'] = [
                'id'    => $id,
                'data'  => array_merge($data, [
                    'slug'          => (string)$this->input('slug', ''),
                    'contact_email' => $email,
                    'website_url'   => $website,
                ]),
                'owner' => ['name' => $ownerName, 'email' => $ownerEmail, 'opening_credits' => (string)$this->input('opening_credits', ''),
                    'is_trial' => (bool)$this->input('is_trial')],
            ];
            flash('error', implode(' ', $errors));
            redirect($back);
        }

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

        // Switching a pending registration on here counts as activating it.
        // Its credit request stays open, to be marked paid separately.
        if ($existing && $data['is_active'] && ($existing['signup_status'] ?? null) === 'pending') {
            $data['signup_status'] = 'approved';
        }

        if ($id) {
            $this->partners->update($id, $data);
        } else {
            // The partner, its first login and any opening credits exist
            // together or not at all — never a partner nobody can sign in to.
            // A trial account: its content is deleted after the trial days, and a
            // blank opening balance means the usual trial credits.
            $trial = $this->input('is_trial') && $this->partners->trialsReady();
            if ($trial) {
                $data['is_trial'] = 1;
            }
            $db = $this->credits->db();
            $db->beginTransaction();
            try {
                $data['created_at'] = date('Y-m-d H:i:s');
                $id = $this->partners->create($data);
                (new ArPartnerUser())->create(
                    $id,
                    mb_substr($ownerName ?: ($data['contact_name'] ?? $data['name']), 0, 120),
                    $ownerEmail,
                    $ownerPassword,
                    'owner'
                );
                $opening = trim((string)$this->input('opening_credits', ''));
                $bonus = $trial && $opening === '' ? ArPartner::trialSettings()['credits'] : (int)$opening;
                if ($bonus > 0) {
                    $this->credits->apply($id, $bonus, 'added', $trial ? 'Free trial credits' : 'Joining bonus', ['created_by' => currentUserId()]);
                }
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if (!empty($data['logo_path'])) {
                    (new ArFrameService())->deleteFile($data['logo_path']);
                }
                throw $e;
            }
        }

        // Old logo files go only once the row no longer points at them.
        if (array_key_exists('logo_path', $data) && $oldLogo && $oldLogo !== $data['logo_path']) {
            (new ArFrameService())->deleteFile($oldLogo);
        }

        flash('success', $existing
            ? 'Partner saved. Their page is at /partner/' . $slug
            : ($trial ? 'Trial partner' : 'Partner') . ' created. They sign in at /partner/' . $slug . ' with ' . $ownerEmail . ' — share the password with them securely.');
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

        // The login email is also where reset links go, so a typo must be fixable.
        $email = strtolower(trim((string)$this->input('email', $user['email'])));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Not saved: "' . $email . '" is not a valid email address.');
            redirect('/admin/ar-partners/' . $id . '#logins');
        }
        $owner = $users->findByEmail($email);
        if ($owner && (int)$owner['id'] !== $userId) {
            flash('error', 'Not saved: ' . $email . ' is already used by another partner login.');
            redirect('/admin/ar-partners/' . $id . '#logins');
        }

        $role = (string)$this->input('role', $user['role']);
        $users->update($userId, [
            'email'     => mb_substr($email, 0, 180),
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
        flash('success', 'Login for ' . $email . ' updated' . ($password !== '' ? ', with a new password.' : '.'));
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
        $partner = $this->findOrRedirect($id);

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
            // Paying for a registration's pack is what activates it.
            $activating = ArPartner::awaitingActivation($partner);
            if ($activating) {
                $this->partners->update($id, ['is_active' => 1, 'signup_status' => 'approved']);
            }
            // A paid pack ends a trial. Content made on the trial keeps its deletion date.
            $endingTrial = ArPartner::isTrial($partner) && $this->partners->trialsReady();
            if ($endingTrial) {
                $this->service->endTrial($id, false);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($activating) {
            $this->finishActivation($partner, (int)$request['credits']);
        }
        flash('success', number_format((int)$request['credits']) . ' credits added.'
            . ($endingTrial ? ' Their free trial has ended; content made during the trial is still deleted on schedule — use “Keep trial content” to keep it.' : ''));
        redirect('/admin/ar-partners/' . $id . '#credits');
    }

    // ------------------------------------------------------- registrations

    /**
     * Activate a seller who registered online, optionally without adding
     * credits (when they paid something other than the pack they chose, the
     * admin activates and then adjusts the balance by hand).
     */
    public function activate(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $partner = $this->findOrRedirect($id);
        if (!ArPartner::awaitingActivation($partner)) {
            flash('error', 'This partner is not waiting for activation.');
            redirect('/admin/ar-partners/' . $id);
        }

        $added = 0;
        $db = $this->credits->db();
        $db->beginTransaction();
        try {
            $requestId = (int)$this->input('request_id', 0);
            $request = $requestId ? $this->credits->lockRequest($id, $requestId) : null;
            if ($request && $request['status'] === 'pending') {
                $this->credits->apply($id, (int)$request['credits'], 'added', sprintf(
                    'Credit pack %s%s (registration, request #%d)',
                    GDD_CURRENCY_SYMBOL,
                    number_format((int)$request['price']),
                    $requestId
                ), ['request_id' => $requestId, 'created_by' => currentUserId()]);
                $this->credits->updateRequest($requestId, [
                    'status'     => 'fulfilled',
                    'handled_by' => currentUserId(),
                    'handled_at' => date('Y-m-d H:i:s'),
                ]);
                $added = (int)$request['credits'];
            }
            $this->partners->update($id, ['is_active' => 1, 'signup_status' => 'approved']);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $mailed = $this->finishActivation($partner, $added);
        flash('success', $partner['name'] . ' is active' . ($added ? ' with ' . number_format($added) . ' credits' : '') . '. '
            . ($mailed ? 'They have been emailed that they can sign in.' : 'The "you are active" email could not be sent — let them know they can sign in at /partner/login.'));
        redirect('/admin/ar-partners/' . $id);
    }

    /** Turn a registration down: the account stays switched off and its open requests are cancelled. */
    public function reject(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $partner = $this->findOrRedirect($id);
        if (!ArPartner::awaitingActivation($partner)) {
            flash('error', 'This partner is not waiting for activation.');
            redirect('/admin/ar-partners/' . $id);
        }

        $db = $this->credits->db();
        $db->beginTransaction();
        $this->partners->update($id, ['signup_status' => 'rejected']);
        $db->prepare("UPDATE ar_partner_credit_requests SET status = 'cancelled', handled_by = ?, handled_at = ?
                      WHERE partner_id = ? AND status = 'pending'")
           ->execute([currentUserId(), date('Y-m-d H:i:s'), $id]);
        $db->commit();

        flash('success', 'Registration from ' . $partner['name'] . ' declined. They cannot sign in; nothing was deleted.');
        redirect('/admin/ar-partners/' . $id);
    }

    /** Tell the registered owner they can sign in. Returns whether the email went. */
    private function finishActivation(array $partner, int $credits): bool
    {
        $owner = null;
        foreach ((new ArPartnerUser())->forPartner((int)$partner['id']) as $user) {
            if (!empty($user['is_active'])) {
                $owner = $user;
                break;
            }
        }
        if (!$owner) {
            return false;
        }
        $siteName = (string)siteSetting('site_name', SITE_NAME);
        $link = url('/partner/' . $partner['slug']);
        $color = ArPartnerService::safeColor($partner['brand_color'] ?? null);
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:520px">'
            . '<p>Hi ' . e((string)$owner['name']) . ',</p>'
            . '<p>Your DEx partner account for <strong>' . e((string)$partner['name']) . '</strong> is now active'
            . ($credits > 0 ? ', and <strong>' . number_format($credits) . ' credits</strong> have been added' : '') . '.</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;background:' . $color . ';color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:bold">Sign in to your DEx Studio</a></p>'
            . '<p style="font-size:13px;color:#6b7280">Sign in with ' . e((string)$owner['email']) . ' and the password you chose when you registered.</p>'
            . '<p style="font-size:12px;color:#9ca3af;word-break:break-all">' . e($link) . '</p>'
            . '</div>';
        return (new NotificationService())->sendEmail((string)$owner['email'], (string)$owner['name'],
            'Your ' . $siteName . ' DEx partner account is active', $html);
    }

    /**
     * End a trial by hand, optionally keeping what was made during it. Also
     * used after a trial has ended, to rescue its remaining content.
     */
    public function endTrial(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $partner = $this->findOrRedirect($id);
        if (!$this->partners->trialsReady()) {
            redirect('/admin/ar-partners/' . $id);
        }
        $wasTrial = ArPartner::isTrial($partner);
        $kept = $this->service->endTrial($id, (bool)$this->input('keep_content'));
        flash('success', ($wasTrial ? 'Free trial ended — new content is kept as normal.' : 'Done.')
            . ($this->input('keep_content') ? ' ' . $kept . ' piece' . ($kept === 1 ? '' : 's') . ' of trial content will no longer be deleted.' : ''));
        redirect('/admin/ar-partners/' . $id);
    }

    /** Put an existing partner on a trial: from now on, what they create is deleted after the trial days. */
    public function startTrial(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $partner = $this->findOrRedirect($id);
        if (!$this->partners->trialsReady() || ArPartner::isTrial($partner)) {
            redirect('/admin/ar-partners/' . $id);
        }
        $this->partners->update($id, ['is_trial' => 1]);
        $credits = max(0, (int)$this->input('credits', 0));
        if ($credits > 0) {
            $this->credits->applyNow($id, $credits, 'added', 'Free trial credits', ['created_by' => currentUserId()]);
        }
        flash('success', $partner['name'] . ' is now on a free trial' . ($credits > 0 ? ' with ' . number_format($credits) . ' extra credits' : '') . '.');
        redirect('/admin/ar-partners/' . $id);
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

    /** Remove trial content whose time is up. A failed clean-up must never break the admin. */
    private function purgeTrialContent(): void
    {
        try {
            $this->service->purgeDueTrialContent();
        } catch (Throwable $e) {
            error_log('Trial content clean-up failed: ' . $e->getMessage());
        }
    }

    /** Input kept from a failed save of this form (0 = the create form), used once. */
    private function takeFormStash(int $id): array
    {
        $stash = $_SESSION['ar_partner_form'] ?? null;
        unset($_SESSION['ar_partner_form']);
        return is_array($stash) && (int)($stash['id'] ?? -1) === $id ? $stash : [];
    }

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
