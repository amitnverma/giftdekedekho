<?php
/**
 * The B2B partner portal: /partner/{slug}/...
 *
 * Each partner gets its own branded page, where its staff sign in, keep a list
 * of their own customers, create singles (one photo + video) and albums (several)
 * paid for in credits, print the QR sticker, and see how often their customers
 * open them.
 *
 * Isolation is the point of the design:
 *  - sign-in is a separate session key, checked against this URL's partner, so a
 *    login for one partner never opens another's portal, the storefront account
 *    area or the admin;
 *  - every read goes through a partner-scoped query (findForPartner, findContent)
 *    rather than trusting an id from the URL;
 *  - content is tagged channel 'partner', which keeps it out of GiftDekeDekho's
 *    AR queue and scan-anything page.
 */
require_once APP_PATH . '/services/ArPartnerService.php';
require_once APP_PATH . '/services/NotificationService.php';
require_once APP_PATH . '/services/CaptchaService.php';

class PartnerController extends BaseController
{
    /** Partners work a shop counter all day, so their session outlasts the admin's. */
    private const SESSION_IDLE_SECONDS = 12 * 3600;

    private const GENERATE_LIMIT = 24;
    private const GENERATE_WINDOW_SECONDS = 300;
    /** Photos compiled in one request, so a large album never outlasts the server's time limit. */
    private const GENERATE_BATCH = 8;
    /** Enough for an image and a video on every page of a large album. */
    private const MAX_PENDING_UPLOADS = 400;

    private ArPartner $partners;
    private ArPartnerService $service;
    private ArFrameService $frames;
    private array $partner = [];
    private array $user = [];

    public function __construct()
    {
        $this->partners = new ArPartner();
        $this->service = new ArPartnerService();
        $this->frames = new ArFrameService();
    }

    /**
     * The one seller sign-in for every partner: /partner, /partner/login and
     * /partner/forgot-password, linked from the storefront header ("Your seller
     * account"). A login belongs to exactly one partner (emails are unique across
     * them), so the email alone says whose portal to open once the password
     * checks out. $this->partner stays empty until then, which makes go() and
     * portalUrl() point back here. The branded /partner/{slug}/login still works.
     */
    public function hub(string $page): void
    {
        if (!$this->partners->tableExists()) {
            $this->notFound();
            return;
        }
        if ($page === 'forgot-password') {
            $this->forgotPassword();
            return;
        }
        if ($page === 'register') {
            $this->register();
            return;
        }
        // Already signed in to a portal: straight there, no second sign-in.
        $auth = $_SESSION['partner_auth'] ?? null;
        if (is_array($auth) && time() - (int)($auth['seen'] ?? 0) < self::SESSION_IDLE_SECONDS
            && ($partner = $this->partners->find((int)($auth['partner_id'] ?? 0)))) {
            redirect('/partner/' . $partner['slug']);
        }
        $this->login();
    }

    public function dispatch(string $slug, array $rest, string $method): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,59}$/', $slug) || !$this->partners->tableExists()) {
            $this->notFound();
            return;
        }
        $partner = $this->partners->findBySlug($slug);
        if (!$partner) {
            $this->notFound();
            return;
        }
        $this->partner = $partner;

        $path = '/' . implode('/', array_map('strval', $rest));
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $post = $method === 'POST';

        if ($path === '/login') {
            $this->login();
            return;
        }
        if ($path === '/logout') {
            $this->logout();
            return;
        }
        if ($path === '/forgot-password') {
            $this->forgotPassword();
            return;
        }
        if ($path === '/reset-password') {
            $this->resetPassword();
            return;
        }

        $this->requirePartnerLogin();

        switch (true) {
            case $path === '/':
                $this->dashboard();
                break;

            case $path === '/customers':
                $post ? $this->createCustomer() : $this->customers();
                break;
            case preg_match('#^/customers/(\d+)$#', $path, $m) === 1:
                $post ? $this->updateCustomer((int)$m[1]) : $this->customer((int)$m[1]);
                break;

            case $path === '/singles':
                $this->contentIndex('single');
                break;
            case $path === '/albums':
                $this->contentIndex('album');
                break;
            case $path === '/singles/create':
                $post ? $this->storeContent('single') : $this->createForm('single');
                break;
            case $path === '/albums/create':
                $post ? $this->storeContent('album') : $this->createForm('album');
                break;

            case $path === '/upload' && $post:
                $this->upload();
                break;

            case preg_match('#^/content/(\d+)$#', $path, $m) === 1:
                $this->content((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/update$#', $path, $m) === 1 && $post:
                $this->updateContent((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/edit$#', $path, $m) === 1:
                $post ? $this->saveEdit((int)$m[1]) : $this->editForm((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/generate$#', $path, $m) === 1 && $post:
                $this->generate((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/sticker$#', $path, $m) === 1:
                $this->sticker((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/photo$#', $path, $m) === 1:
                $this->photo((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/confirm-test$#', $path, $m) === 1 && $post:
                $this->confirmTest((int)$m[1]);
                break;

            case $path === '/credits':
                $this->credits();
                break;
            case $path === '/credits/request' && $post:
                $this->requestCredits();
                break;

            case $path === '/analytics':
                $this->analytics();
                break;

            case $path === '/password':
                $post ? $this->changePassword() : $this->passwordForm();
                break;

            default:
                $this->notFound();
        }
    }

    // ------------------------------------------------------------------ auth

    private function login(): void
    {
        if ($this->signedIn()) {
            $this->go('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $email = strtolower(trim((string)$this->input('email', '')));
            $password = (string)$this->input('password', '');
            $identifier = 'partner:' . $email;

            if ($captchaError = CaptchaService::verify('partner-login')) {
                $this->setOld(['email' => $email]);
                flash('error', $captchaError);
                $this->go('/login');
            }

            if ($this->loginRateLimited($identifier)) {
                flash('error', 'Too many sign-in attempts. Please wait 15 minutes and try again.');
                $this->go('/login');
            }

            $users = new ArPartnerUser();
            $user = $email === '' ? null : $users->findByEmail($email);
            // On the shared seller sign-in, the login itself says which partner.
            $partner = $this->partner ?: ($user ? $this->partners->find((int)$user['partner_id']) : null);
            // One message for every failure, so the form never confirms which
            // emails have accounts, or which partner they belong to.
            if (!$user || !$partner || (int)$user['partner_id'] !== (int)$partner['id']
                || empty($user['is_active']) || !password_verify($password, $user['password_hash'])) {
                $this->recordLoginAttempt($identifier);
                $this->setOld(['email' => $email]);
                flash('error', 'Incorrect email or password.');
                $this->go('/login');
            }
            if (empty($partner['is_active'])) {
                $siteName = (string)siteSetting('site_name', SITE_NAME);
                flash('error', match ($partner['signup_status'] ?? null) {
                    'pending'  => 'Your seller account is waiting to be activated. We activate it and add your credits once your payment is received — please contact ' . $siteName . ' if you have already paid.',
                    'rejected' => 'Your seller registration was not approved. Please contact ' . $siteName . '.',
                    default    => 'This account is paused. Please contact ' . $siteName . '.',
                });
                $this->go('/login');
            }
            $this->partner = $partner;

            session_regenerate_id(true);
            $_SESSION['partner_auth'] = [
                'user_id'    => (int)$user['id'],
                'partner_id' => (int)$this->partner['id'],
                'pw'         => ArPartnerService::passwordStamp($user),
                'seen'       => time(),
            ];
            $users->update((int)$user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
            $this->clearOld();
            $this->go('/');
        }

        renderRaw('partner/login', [
            'partner' => $this->partner,
            'brand'   => $this->brand(),
        ]);
    }

    private function logout(): void
    {
        // Only the partner session: the same browser may also be signed in to
        // the storefront, and that is not this page's to end.
        unset($_SESSION['partner_auth'], $_SESSION['partner_uploads']);
        session_regenerate_id(true);
        $this->go('/login');
    }

    private function signedIn(): bool
    {
        $auth = $_SESSION['partner_auth'] ?? null;
        return is_array($auth) && $this->partner
            && (int)($auth['partner_id'] ?? 0) === (int)$this->partner['id']
            && time() - (int)($auth['seen'] ?? 0) < self::SESSION_IDLE_SECONDS;
    }

    private function requirePartnerLogin(): void
    {
        if (!$this->signedIn()) {
            $this->respondUnauthenticated();
        }

        // Re-read on every request, so switching a login or the partner off in
        // the admin takes effect at once rather than at the next sign-in.
        $user = (new ArPartnerUser())->findForPartner((int)$this->partner['id'], (int)$_SESSION['partner_auth']['user_id']);
        // A password changed anywhere — here, by reset link or in the admin —
        // ends every session that signed in with the old one.
        if (!$user || empty($user['is_active']) || empty($this->partner['is_active'])
            || !hash_equals(ArPartnerService::passwordStamp($user), (string)($_SESSION['partner_auth']['pw'] ?? ''))) {
            unset($_SESSION['partner_auth']);
            $this->respondUnauthenticated();
        }

        $this->user = $user;
        $_SESSION['partner_auth']['seen'] = time();
    }

    private function respondUnauthenticated(): void
    {
        if ($this->wantsJson()) {
            $this->json(['ok' => false, 'error' => 'You have been signed out. Please sign in again.'], 401);
        }
        $this->go('/login');
    }

    private function loginRateLimited(string $identifier, int $max = 5): bool
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([substr($identifier, 0, 180)]);
        return (int)$stmt->fetchColumn() >= $max;
    }

    private function recordLoginAttempt(string $identifier): void
    {
        Database::getInstance()
            ->prepare('INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)')
            ->execute([substr($identifier, 0, 180), $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    }

    /**
     * "Forgot password": email a reset link. The reply is the same whether or
     * not the address has a login here, so the form cannot be used to find out.
     */
    private function forgotPassword(): void
    {
        if ($this->signedIn()) {
            $this->go('/password');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $email = strtolower(trim((string)$this->input('email', '')));
            $identifier = 'partner-reset:' . $email;
            if ($captchaError = CaptchaService::verify('partner-forgot-password')) {
                $this->setOld(['email' => $email]);
                flash('error', $captchaError);
                $this->go('/forgot-password');
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->setOld(['email' => $email]);
                flash('error', 'Enter the email address you sign in with.');
                $this->go('/forgot-password');
            }
            if ($this->loginRateLimited($identifier, 3)) {
                flash('error', 'A reset link was requested several times already. Please check your inbox, or wait 15 minutes and try again.');
                $this->go('/forgot-password');
            }
            $this->recordLoginAttempt($identifier);

            $this->clearOld();
            flash('success', 'If ' . $email . ' has a login here, a reset link is on its way. It works for '
                . intdiv(ArPartnerService::RESET_LINK_SECONDS, 60) . ' minutes. No email? Ask '
                . (string)siteSetting('site_name', SITE_NAME) . ' to reset your password.');

            // Where the reply goes is fixed before the lookup: on the shared
            // seller page, bouncing to the partner's own page would give it away.
            $back = $this->portalPath('/login');
            $user = (new ArPartnerUser())->findByEmail($email);
            if ($user && !$this->partner) {
                $this->partner = $this->partners->find((int)$user['partner_id']) ?: [];
            }
            if (!$user || !$this->partner || (int)$user['partner_id'] !== (int)$this->partner['id'] || empty($user['is_active'])) {
                redirect($back);
            }

            // Answer first, then send: an SMTP round trip takes seconds, and a
            // reply that was only slow for real accounts would give them away.
            header('Location: ' . url($back));
            session_write_close();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
            $link = $this->portalUrl('/reset-password?token=' . rawurlencode($this->service->resetToken($user)));
            $sent = (new NotificationService())->sendEmail($user['email'], (string)$user['name'],
                'Reset your ' . $this->partner['name'] . ' password', $this->resetEmailHtml($user, $link));
            if (!$sent) {
                error_log('Partner password reset email failed for partner ' . (int)$this->partner['id'] . ', login ' . (int)$user['id']);
            }
            exit;
        }

        renderRaw('partner/password_forgot', [
            'partner' => $this->partner,
            'brand'   => $this->brand(),
        ]);
    }

    /**
     * "Become a seller": /partner/register. There is no online payment yet, so
     * this creates the shop paused (signup_status 'pending') with a pending
     * credit request for the pack chosen. The applicant pays outside the site,
     * and the admin activates the account from Admin → AR Partners, which adds
     * the pack's credits. Until then the login is refused with an explanation.
     */
    private function register(): void
    {
        $packs = ArPartner::creditPacks([]);
        $rate = ['base_credits' => ArPartner::DEFAULT_BASE_CREDITS];
        $view = [
            'brand'   => $this->brand(),
            'packs'   => $packs,
            'rate'    => $rate,
            'support' => $this->supportWhatsapp(),
            'closed'  => !$this->partners->signupsReady(),
            'done'    => null,
        ];

        if ($view['closed']) {
            renderRaw('partner/register', $view);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $business = trim((string)$this->input('business_name', ''));
            $name = trim((string)$this->input('name', ''));
            $phone = substr(preg_replace('/[^\d+]/', '', (string)$this->input('phone', '')), 0, 20);
            $city = trim((string)$this->input('city', ''));
            $email = strtolower(trim((string)$this->input('email', '')));
            $password = (string)$this->input('password', '');
            $packIndex = (int)$this->input('pack', -1);
            $this->setOld(['business_name' => $business, 'name' => $name, 'phone' => $phone,
                'city' => $city, 'email' => $email, 'pack' => (string)$packIndex]);

            if ($captchaError = CaptchaService::verify('partner-register')) {
                flash('error', $captchaError);
                $this->go('/register');
            }
            // Per address, not per email: one person trying many emails is the case to stop.
            $identifier = 'partner-register:' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if ($this->loginRateLimited($identifier, 3)) {
                flash('error', 'Several registrations were sent from here already. Please wait 15 minutes, or contact us directly.');
                $this->go('/register');
            }

            $errors = [];
            if (mb_strlen($business) < 2) {
                $errors[] = 'Enter your shop or business name.';
            }
            if (mb_strlen($name) < 2) {
                $errors[] = 'Enter your name.';
            }
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) < 10 || strlen($digits) > 15) {
                $errors[] = 'Enter a mobile number we can reach you on, with at least 10 digits.';
            }
            $users = new ArPartnerUser();
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address — you will sign in with it.';
            } elseif ($users->findByEmail($email)) {
                $errors[] = 'That email already has a seller account. Sign in, or use "Forgot your password?".';
            }
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'Choose a password of at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            } elseif ($password !== (string)$this->input('password_confirm', '')) {
                $errors[] = 'The two passwords do not match.';
            }
            $pack = $packs[$packIndex] ?? null;
            if ($pack === null) {
                $errors[] = 'Choose a credit pack to start with.';
            }
            if ($errors) {
                flash('error', implode(' ', $errors));
                $this->go('/register');
            }
            $this->recordLoginAttempt($identifier);

            $credits = new ArPartnerCredit();
            $db = $credits->db();
            $db->beginTransaction();
            try {
                $partnerId = $this->partners->create([
                    'slug'          => $this->partners->uniqueSlug($business),
                    'name'          => mb_substr($business, 0, 120),
                    'contact_name'  => mb_substr($name, 0, 120),
                    'contact_phone' => $phone,
                    'whatsapp'      => substr($digits, 0, 20),
                    'contact_email' => mb_substr($email, 0, 180),
                    'is_active'     => 0,
                    'signup_status' => 'pending',
                    'notes'         => 'Registered online on ' . date('d M Y H:i') . ($city !== '' ? '. City: ' . mb_substr($city, 0, 80) : '') . '.',
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
                $userId = $users->create($partnerId, mb_substr($name, 0, 120), $email, $password, 'owner');
                $credits->createRequest($partnerId, $pack['price'], $pack['credits'], $userId);
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            $this->clearOld();
            $_SESSION['partner_signup_done'] = [
                'business' => $business,
                'email'    => $email,
                'price'    => $pack['price'],
                'credits'  => $pack['credits'],
            ];

            // Reply first, then tell the admin: an SMTP round trip takes seconds.
            header('Location: ' . url('/partner/register'));
            session_write_close();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
            $adminEmail = trim((string)siteSetting('site_email', ''));
            if ($adminEmail !== '') {
                $rows = [
                    'Business' => $business,
                    'Name'     => $name,
                    'Mobile'   => $phone,
                    'Email'    => $email,
                    'City'     => $city !== '' ? $city : '—',
                    'Pack'     => GDD_CURRENCY_SYMBOL . number_format($pack['price']) . ' for ' . number_format($pack['credits']) . ' credits',
                ];
                $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:520px">'
                    . '<p>A new seller has registered and is waiting for activation.</p><table style="border-collapse:collapse">';
                foreach ($rows as $label => $value) {
                    $html .= '<tr><td style="padding:3px 14px 3px 0;color:#6b7280">' . e($label) . '</td><td style="padding:3px 0"><strong>' . e($value) . '</strong></td></tr>';
                }
                $html .= '</table><p>Once their payment is received, open the partner in the admin and press “Activate”. That adds the credits and lets them sign in.</p>'
                    . '<p><a href="' . e(url('/admin/ar-partners/' . $partnerId)) . '">Open in Admin → AR Partners</a></p></div>';
                if (!(new NotificationService())->sendEmail($adminEmail, SITE_NAME . ' Admin', 'New seller registration: ' . $business, $html)) {
                    error_log('Partner registration email to admin failed for partner ' . $partnerId);
                }
            }
            exit;
        }

        $view['done'] = $_SESSION['partner_signup_done'] ?? null;
        unset($_SESSION['partner_signup_done']);
        renderRaw('partner/register', $view);
    }

    private function resetPassword(): void
    {
        $token = (string)$this->input('token', '');
        $user = $this->service->userForResetToken((int)$this->partner['id'], $token);
        if (!$user) {
            flash('error', 'That reset link has expired or has already been used. Request a new one below.');
            $this->go('/forgot-password');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $error = $this->newPasswordError();
            if ($error !== null) {
                flash('error', $error);
                $this->go('/reset-password?token=' . rawurlencode($token));
            }
            (new ArPartnerUser())->setPassword((int)$user['id'], (string)$this->input('password', ''));
            // Old sessions end through the password stamp; clear this browser too.
            unset($_SESSION['partner_auth']);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed. Sign in with the new one.');
            $this->go('/login');
        }

        renderRaw('partner/password_reset', [
            'partner' => $this->partner,
            'brand'   => $this->brand(),
            'token'   => $token,
            'email'   => $user['email'],
        ]);
    }

    private function passwordForm(): void
    {
        $this->page('password', 'Change password', '', []);
    }

    private function changePassword(): void
    {
        $this->requireCsrf();
        $identifier = 'partner-password:' . (int)$this->user['id'];
        if ($this->loginRateLimited($identifier)) {
            flash('error', 'Too many attempts. Please wait 15 minutes and try again.');
            $this->go('/password');
        }
        if (!password_verify((string)$this->input('current_password', ''), $this->user['password_hash'])) {
            $this->recordLoginAttempt($identifier);
            flash('error', 'Your current password is not correct.');
            $this->go('/password');
        }
        $error = $this->newPasswordError();
        if ($error !== null) {
            flash('error', $error);
            $this->go('/password');
        }

        $users = new ArPartnerUser();
        $users->setPassword((int)$this->user['id'], (string)$this->input('password', ''));
        // Keep this browser signed in; every other session ends at its next click.
        session_regenerate_id(true);
        $_SESSION['partner_auth']['pw'] = ArPartnerService::passwordStamp($users->findForPartner((int)$this->partner['id'], (int)$this->user['id']));
        flash('success', 'Password changed. Anywhere else you were signed in has been signed out.');
        $this->go('/password');
    }

    private function newPasswordError(): ?string
    {
        $password = (string)$this->input('password', '');
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return 'The new password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        }
        if (!hash_equals($password, (string)$this->input('password_confirm', ''))) {
            return 'The two new passwords do not match.';
        }
        return null;
    }

    private function resetEmailHtml(array $user, string $link): string
    {
        $color = ArPartnerService::safeColor($this->partner['brand_color'] ?? null);
        return '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:520px">'
            . '<p>Hi ' . e((string)$user['name']) . ',</p>'
            . '<p>Someone asked to reset the password for your <strong>' . e((string)$this->partner['name']) . '</strong> AR studio login ('
            . e((string)$user['email']) . ').</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;background:' . $color . ';color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:bold">Choose a new password</a></p>'
            . '<p style="font-size:13px;color:#6b7280">The link works for ' . intdiv(ArPartnerService::RESET_LINK_SECONDS, 60)
            . ' minutes and only once. If you did not ask for this, ignore this email — your password stays the same.</p>'
            . '<p style="font-size:12px;color:#9ca3af;word-break:break-all">' . e($link) . '</p>'
            . '</div>';
    }

    // ------------------------------------------------------------- dashboard

    private function dashboard(): void
    {
        $pid = (int)$this->partner['id'];
        $this->page('dashboard', 'Dashboard', 'home', [
            'counts'    => $this->service->contentCounts($pid),
            'customers' => (new ArPartnerCustomer())->countForPartner($pid),
            'scans'     => (new ArScanEvent())->totals($pid),
            'recent'    => $this->service->contentList($pid, [], 6),
        ]);
    }

    // ------------------------------------------------------------- customers

    private function customers(): void
    {
        $search = (string)$this->input('search', '');
        $this->page('customers', 'Customers', 'customers', [
            'customers' => (new ArPartnerCustomer())->forPartner((int)$this->partner['id'], $search),
            'search'    => $search,
        ]);
    }

    private function createCustomer(): void
    {
        $this->requireCsrf();
        $data = $this->customerInput();
        if (isset($data['error'])) {
            flash('error', $data['error']);
            $this->setOld($_POST);
            $this->go('/customers#add');
        }
        $data['partner_id'] = (int)$this->partner['id'];
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = (new ArPartnerCustomer())->create($data);
        $this->clearOld();
        flash('success', $data['name'] . ' added.');
        $this->go('/customers/' . $id);
    }

    private function customer(int $id): void
    {
        $customer = $this->findCustomerOr404($id);
        $this->page('customer_show', $customer['name'], 'customers', [
            'customer' => $customer,
            'content'  => $this->service->contentList((int)$this->partner['id'], ['customer_id' => $id]),
        ]);
    }

    private function updateCustomer(int $id): void
    {
        $this->requireCsrf();
        $this->findCustomerOr404($id);
        $data = $this->customerInput();
        if (isset($data['error'])) {
            flash('error', $data['error']);
            $this->go('/customers/' . $id);
        }
        (new ArPartnerCustomer())->update($id, $data);
        flash('success', 'Customer details saved.');
        $this->go('/customers/' . $id);
    }

    private function customerInput(): array
    {
        $name = trim((string)$this->input('name', ''));
        $phone = preg_replace('/[^\d+]/', '', (string)$this->input('phone', ''));
        $email = strtolower(trim((string)$this->input('email', '')));

        if (mb_strlen($name) < 2) {
            return ['error' => 'Please enter the customer\'s name.'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'That email address does not look right.'];
        }
        return [
            'name'      => mb_substr($name, 0, 120),
            'phone'     => $phone === '' ? null : substr($phone, 0, 20),
            'email'     => $email === '' ? null : $email,
            'reference' => $this->blankToNull(mb_substr((string)$this->input('reference', ''), 0, 80)),
            'notes'     => $this->blankToNull((string)$this->input('notes', '')),
        ];
    }

    private function findCustomerOr404(int $id): array
    {
        $customer = (new ArPartnerCustomer())->findForPartner((int)$this->partner['id'], $id);
        if (!$customer) {
            flash('error', 'That customer was not found.');
            $this->go('/customers');
        }
        return $customer;
    }

    // --------------------------------------------------------------- content

    private function contentIndex(string $kind): void
    {
        $search = (string)$this->input('search', '');
        $this->page('content_index', $kind === 'album' ? 'Albums' : 'Singles', $kind === 'album' ? 'albums' : 'singles', [
            'kind'    => $kind,
            'content' => $this->service->contentList((int)$this->partner['id'], ['kind' => $kind, 'search' => $search]),
            'search'  => $search,
        ]);
    }

    private function createForm(string $kind): void
    {
        if (!$this->kindAllowed($kind)) {
            flash('error', ($kind === 'album' ? 'Albums' : 'Singles') . ' are not enabled for your account.');
            $this->go('/');
        }

        $this->page('content_create', $kind === 'album' ? 'New Album' : 'New AR Content', $kind === 'album' ? 'albums' : 'singles', [
            'kind'           => $kind,
            'customers'      => (new ArPartnerCustomer())->optionsForPartner((int)$this->partner['id']),
            'preselect'      => (int)$this->input('customer', 0),
            'durationPrices' => ArPartner::durationPrices($this->partner),
            'validityPrices' => ArPartner::validityPrices($this->partner),
            'packs'          => ArPartner::creditPacks($this->partner),
            'maxPages'       => $this->maxAlbumPages(),
            'playbackModes'  => ArFrameItem::PLAYBACK_MODES,
            'maxVideoMb'     => $this->maxVideoMb(),
            'uploadLimit'    => (string)ini_get('upload_max_filesize'),
            'compiler'       => $this->compilerOk(),
        ]);
    }

    /**
     * Create a single or album from files already uploaded one by one.
     *
     * Files arrive beforehand through upload(), each in its own request, so an
     * album of several videos never has to fit into one POST. This request only
     * carries the tokens those uploads returned, which can only ever resolve to
     * this partner's own files.
     */
    private function storeContent(string $kind): void
    {
        $this->requireCsrf();
        $back = $kind === 'album' ? '/albums/create' : '/singles/create';
        if (!$this->kindAllowed($kind)) {
            flash('error', 'That is not enabled for your account.');
            $this->go('/');
        }

        $validity = (string)$this->input('validity', '');
        $title = trim((string)$this->input('title', ''));
        if ($title === '') {
            $title = ($kind === 'album' ? 'Album' : 'Single Frame') . ' - ' . date('d M Y, h:i A');
        }

        // --- the pages, resolved from upload tokens
        $rows = is_array($_POST['pages'] ?? null) ? array_values($_POST['pages']) : [];
        if ($kind === 'single') {
            $rows = array_slice($rows, 0, 1);
        }
        if (!$rows) {
            $this->failCreate($back, 'Add a photo and the video it should play.');
        }
        $maxPages = $this->maxAlbumPages();
        if ($maxPages !== null && count($rows) > $maxPages) {
            $this->failCreate($back, 'An album can hold at most ' . $maxPages . ' photos.');
        }

        $pages = [];
        $tokens = [];
        foreach ($rows as $n => $row) {
            $label = $kind === 'album' ? 'AR content ' . ($n + 1) . ': ' : '';
            $photo = $this->uploadFromToken((string)($row['photo_token'] ?? ''), 'photo');
            $video = $this->uploadFromToken((string)($row['video_token'] ?? ''), 'video');
            if ($photo === null) {
                $this->failCreate($back, $label . 'Upload the target image.');
            }
            if ($video === null) {
                $this->failCreate($back, $label . 'Upload the video.');
            }
            $tokens[] = (string)$row['photo_token'];
            $tokens[] = (string)$row['video_token'];
            $pages[] = [
                'photo_path'  => $photo['path'],
                'video_path'  => $video['path'],
                'max_seconds' => (int)($kind === 'album' ? ($row['duration'] ?? 0) : $this->input('duration', 0)),
                'title'       => mb_substr(trim((string)($row['title'] ?? '')), 0, 120),
                'playback_mode' => ArFrameItem::playbackMode((string)($kind === 'album' ? ($row['playback_mode'] ?? '') : $this->input('playback_mode', ''))),
            ];
        }

        $quote = $this->service->quote($this->partner, array_column($pages, 'max_seconds'), $validity);
        if (empty($quote['ok'])) {
            $this->failCreate($back, $quote['error']);
        }

        // --- the customer: an existing one of this partner's, or a new one
        $customers = new ArPartnerCustomer();
        $newCustomerId = null;
        $customerRef = (string)$this->input('customer_id', '');
        if ($customerRef === 'new') {
            $name = trim((string)$this->input('new_customer_name', ''));
            if (mb_strlen($name) < 2) {
                $this->failCreate($back, 'Enter the new customer\'s name.');
            }
            $phone = preg_replace('/[^\d+]/', '', (string)$this->input('new_customer_phone', ''));
            $newCustomerId = $customers->create([
                'partner_id' => (int)$this->partner['id'],
                'name'       => mb_substr($name, 0, 120),
                'phone'      => $phone === '' ? null : substr($phone, 0, 20),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $customer = $customers->find($newCustomerId);
        } else {
            $customer = $customers->findForPartner((int)$this->partner['id'], (int)$customerRef);
            if (!$customer) {
                $this->failCreate($back, 'Select a customer.');
            }
        }

        $result = $this->service->createContent($this->partner, (int)$this->user['id'], [
            'kind'        => $kind,
            'title'       => mb_substr($title, 0, 160),
            'customer_id' => (int)$customer['id'],
            'validity'    => $validity,
        ], $pages);

        if (empty($result['ok'])) {
            if ($newCustomerId !== null) {
                $customers->delete($newCustomerId);
            }
            // The form checks the balance before it can be submitted, so this is
            // a race (another tab spent the credits) or a real failure.
            foreach ($pages as $page) {
                $this->frames->deleteFile($page['photo_path']);
                $this->frames->deleteFile($page['video_path']);
            }
            foreach ($tokens as $token) {
                unset($_SESSION['partner_uploads'][$token]);
            }
            $this->failCreate($back, $result['error']);
        }

        // Paid for and saved: the uploads now belong to the frame.
        foreach ($tokens as $token) {
            unset($_SESSION['partner_uploads'][$token]);
        }
        $frameId = (int)$result['frame_id'];
        // Shown to GiftDekeDekho staff if they open the frame from the admin.
        (new ArFrame())->update($frameId, [
            'customer_name'  => mb_substr((string)$customer['name'], 0, 120),
            'customer_phone' => $customer['phone'] === null ? null : substr((string)$customer['phone'], 0, 15),
        ]);

        $this->prepareMissing($frameId, count($pages), sprintf('Created for %s credits.', number_format($result['cost'])));
        $this->go('/content/' . $frameId);
    }

    private function failCreate(string $back, string $message): void
    {
        flash('error', $message);
        $this->setOld([
            'title'    => (string)$this->input('title', ''),
            'validity' => (string)$this->input('validity', ''),
        ]);
        $this->go($back);
    }

    /**
     * Store one photo or video for a form that has not been submitted yet.
     *
     * Answers in JSON. Stray output — a deprecation notice on the server's
     * newer PHP, say — is discarded so it can never corrupt the reply.
     */
    private function upload(): void
    {
        ob_start();

        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->json(['ok' => false, 'error' => 'That file is larger than the server allows.'], 413);
        }
        if (!verifyCsrf()) {
            $this->json(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 419);
        }

        $kind = (string)$this->input('kind', '');
        if ($kind !== 'photo' && $kind !== 'video') {
            $this->json(['ok' => false, 'error' => 'Unknown upload.'], 400);
        }
        if (empty($_FILES['file'])) {
            $this->json(['ok' => false, 'error' => 'Please choose a file.'], 400);
        }

        $uploads = $_SESSION['partner_uploads'] ?? [];
        if (count($uploads) >= self::MAX_PENDING_UPLOADS) {
            $this->json(['ok' => false, 'error' => 'Too many files waiting. Create or cancel what you have started, then refresh.'], 429);
        }

        $stored = $kind === 'photo'
            ? $this->frames->storePhoto($_FILES['file'])
            : $this->frames->storeVideo($_FILES['file'], $this->maxVideoMb() * 1024 * 1024);
        if (empty($stored['ok'])) {
            $this->json(['ok' => false, 'error' => $stored['error']], 422);
        }

        $token = bin2hex(random_bytes(16));
        $uploads[$token] = [
            'partner_id' => (int)$this->partner['id'],
            'kind'       => $kind,
            'path'       => $stored['path'],
            'at'         => time(),
        ];
        $_SESSION['partner_uploads'] = $uploads;

        $this->json(['ok' => true, 'token' => $token]);
    }

    private function uploadFromToken(string $token, string $kind): ?array
    {
        $upload = $_SESSION['partner_uploads'][$token] ?? null;
        if (!is_array($upload) || $upload['kind'] !== $kind || (int)$upload['partner_id'] !== (int)$this->partner['id']) {
            return null;
        }
        return $upload;
    }

    private function content(int $id): void
    {
        $frame = $this->findContentOr404($id);
        $items = $this->frames->items($frame);
        $scanUrl = ArFrameService::scanUrl($frame['slug']);

        require_once APP_PATH . '/services/QrCodeService.php';
        require_once APP_PATH . '/services/ArTargetService.php';
        $stat = (new ArScanEvent())->frameTotals($id);

        $this->page('content_show', (string)($frame['title'] ?: $frame['slug']), $frame['content_kind'] === 'album' ? 'albums' : 'singles', [
            'frame'      => $frame,
            'items'      => $items,
            'scanUrl'    => $scanUrl,
            'qr'         => (new QrCodeService())->pngDataUri($scanUrl, 'Q', 8),
            'editable'   => ArPartnerService::isEditable($frame),
            'expired'    => ArPartnerService::isExpired($frame),
            'opens'      => $stat['opens'],
            'visitors'   => $stat['visitors'],
        ]);
    }

    /** The on/off switch, which stays available after the edit window closes. */
    private function updateContent(int $id): void
    {
        $this->requireCsrf();
        $this->findContentOr404($id);
        (new ArFrame())->update($id, ['is_active' => $this->input('is_active') ? 1 : 0]);
        flash('success', 'Saved.');
        $this->go('/content/' . $id);
    }

    private function editForm(int $id): void
    {
        $frame = $this->findEditableOr404($id);
        $this->page('content_edit', 'Edit ' . (string)($frame['title'] ?: $frame['slug']), $frame['content_kind'] === 'album' ? 'albums' : 'singles', [
            'frame'         => $frame,
            'items'         => $this->frames->items($frame),
            'customers'     => (new ArPartnerCustomer())->optionsForPartner((int)$this->partner['id']),
            'playbackModes' => ArFrameItem::PLAYBACK_MODES,
            'maxVideoMb'    => $this->maxVideoMb(),
        ]);
    }

    /**
     * Save the edit page: title and customer, and for each photo its title,
     * playback mode, and optionally a new image and/or video.
     *
     * What was paid for — video length, validity, the number of pages — is not
     * editable here. Every upload token is checked before anything is changed,
     * so an expired upload never leaves the content half-edited.
     */
    private function saveEdit(int $id): void
    {
        $this->requireCsrf();
        $frame = $this->findEditableOr404($id);
        $back = '/content/' . $id . '/edit';

        $data = [];
        $title = trim((string)$this->input('title', ''));
        if ($title !== '') {
            $data['title'] = mb_substr($title, 0, 160);
        }
        $customer = (new ArPartnerCustomer())->findForPartner((int)$this->partner['id'], (int)$this->input('customer_id', 0));
        if ($customer) {
            $data['partner_customer_id'] = (int)$customer['id'];
            $data['customer_name'] = mb_substr((string)$customer['name'], 0, 120);
            $data['customer_phone'] = $customer['phone'] === null ? null : substr((string)$customer['phone'], 0, 15);
        }

        $input = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        $changes = [];
        foreach ($this->frames->items($frame) as $item) {
            $row = is_array($input[$item['id']] ?? null) ? $input[$item['id']] : [];
            $photoToken = (string)($row['photo_token'] ?? '');
            $videoToken = (string)($row['video_token'] ?? '');
            $photo = $photoToken === '' ? null : $this->uploadFromToken($photoToken, 'photo');
            $video = $videoToken === '' ? null : $this->uploadFromToken($videoToken, 'video');
            if (($photoToken !== '' && $photo === null) || ($videoToken !== '' && $video === null)) {
                flash('error', 'An upload has expired. Please choose the new image or video again.');
                $this->go($back);
            }
            $changes[] = [$item, $row, $photo, $video, $photoToken, $videoToken];
        }

        $itemModel = new ArFrameItem();
        $newPhotos = 0;
        foreach ($changes as [$item, $row, $photo, $video, $photoToken, $videoToken]) {
            $update = [
                'title'         => $this->blankToNull(mb_substr((string)($row['title'] ?? ''), 0, 120)),
                'playback_mode' => ArFrameItem::playbackMode((string)($row['playback_mode'] ?? $item['playback_mode'])),
            ];
            if ($video !== null) {
                $update['video_type'] = 'upload';
                $update['video_path'] = $video['path'];
            }
            if ($photo !== null) {
                // A new photo invalidates the old target and any earlier test.
                $update += [
                    'photo_path'         => $photo['path'],
                    'target_path'        => null,
                    'trackability_score' => null,
                    'trackability_flag'  => null,
                    'trackability_json'  => null,
                    'verified_at'        => null,
                ];
                $newPhotos++;
            }
            $itemModel->update((int)$item['id'], $update);

            if ($video !== null) {
                unset($_SESSION['partner_uploads'][$videoToken]);
                if (!empty($item['video_path']) && $item['video_path'] !== $video['path']) {
                    $this->frames->deleteFile($item['video_path']);
                }
            }
            if ($photo !== null) {
                unset($_SESSION['partner_uploads'][$photoToken]);
                $this->frames->deleteFile($item['photo_path']);
                $this->frames->deleteFile($item['target_path']);
            }
        }

        if ($data) {
            (new ArFrame())->update($id, $data);
        }

        if ($newPhotos === 0) {
            flash('success', 'Changes saved.');
            $this->go('/content/' . $id);
        }
        // Drop the replaced photos from the scan target straight away, so the old
        // image stops playing even if preparing the new one has to wait.
        $this->frames->refreshFrame($id);
        $this->prepareMissing($id, $newPhotos, 'Changes saved.');
        $this->go('/content/' . $id);
    }

    private function findEditableOr404(int $id): array
    {
        $frame = $this->findContentOr404($id);
        if (!ArPartnerService::isEditable($frame)) {
            flash('error', 'The edit window for this content closed on '
                . date('d M Y, h:i A', strtotime((string)($frame['editable_until'] ?: $frame['created_at']))) . '.');
            $this->go('/content/' . $id);
        }
        return $frame;
    }

    /** Compile any photo still missing a target — after a failure or a throttle. */
    private function generate(int $id): void
    {
        $this->requireCsrf();
        $frame = $this->findContentOr404($id);
        $missing = count(array_filter($this->frames->items($frame), fn($i) => empty($i['target_path'])));
        if ($missing === 0) {
            flash('success', 'Every photo is already prepared.');
            $this->go('/content/' . $id);
        }
        $this->prepareMissing($id, $missing, 'Photos prepared.');
        $this->go('/content/' . $id);
    }

    private function sticker(int $id): void
    {
        $frame = $this->findContentOr404($id);
        require_once APP_PATH . '/services/QrCodeService.php';
        $scanUrl = ArFrameService::scanUrl($frame['slug']);

        renderRaw('admin/ar_sticker', [
            'frame'    => $frame,
            'scanUrl'  => $scanUrl,
            // Same settings as GiftDekeDekho's own stickers: printed small and handled.
            'qr'       => (new QrCodeService())->pngDataUri($scanUrl, 'Q', 12),
            'siteName' => $this->partner['name'],
            'backUrl'  => $this->portalUrl('/content/' . $id),
        ]);
    }

    private function photo(int $id): void
    {
        $frame = $this->findContentOr404($id);
        $items = $this->frames->items($frame);
        $wanted = (int)$this->input('item', 0);
        $position = 0;
        foreach ($items as $index => $item) {
            if ((int)$item['id'] === $wanted) {
                $position = $index;
                break;
            }
        }

        renderRaw('admin/ar_frame_photo', [
            'frame'     => $frame,
            'items'     => $items,
            'position'  => $position,
            'backUrl'   => $this->portalUrl('/content/' . $id),
            'photoBase' => '/partner/' . $this->partner['slug'] . '/content/' . $id . '/photo',
        ]);
    }

    /**
     * "I scanned it on my phone and it played". The test runs on the public
     * scan link, which needs no sign-in on the phone, so the result is recorded
     * here on the computer the partner is already signed in on.
     */
    private function confirmTest(int $id): void
    {
        $this->requireCsrf();
        $frame = $this->findContentOr404($id);
        $items = $this->frames->items($frame);
        if (!$items || empty($frame['target_path']) || array_filter($items, fn($i) => empty($i['target_path']))) {
            flash('error', 'Prepare every photo before recording the test.');
            $this->go('/content/' . $id);
        }
        if (!$this->input('confirmed')) {
            flash('error', 'Tick the box to confirm the video played.');
            $this->go('/content/' . $id);
        }
        $this->frames->markVerified($id);
        flash('success', 'Test recorded.');
        $this->go('/content/' . $id);
    }

    private function findContentOr404(int $id): array
    {
        $frame = $this->service->findContent((int)$this->partner['id'], $id);
        if (!$frame) {
            flash('error', 'That content was not found.');
            $this->go('/');
        }
        return $frame;
    }

    // --------------------------------------------------------------- credits

    private function credits(): void
    {
        $credits = new ArPartnerCredit();
        $this->page('credits', 'My Credits', 'credits', [
            'packs'    => ArPartner::creditPacks($this->partner),
            'ledger'   => $credits->ledger((int)$this->partner['id']),
            'requests' => $credits->requests((int)$this->partner['id'], 10),
            'support'  => $this->supportWhatsapp(),
        ]);
    }

    /**
     * Ask for a credit pack. Nothing is charged here: payment is arranged with
     * GiftDekeDekho directly, and the admin adds the credits once it is received.
     */
    private function requestCredits(): void
    {
        $this->requireCsrf();
        $packs = ArPartner::creditPacks($this->partner);
        $pack = $packs[(int)$this->input('pack', -1)] ?? null;
        if ($pack === null) {
            flash('error', 'Choose a credit pack.');
            $this->go('/credits');
        }

        $credits = new ArPartnerCredit();
        if ($credits->hasRecentPending((int)$this->partner['id'], $pack['price'])) {
            flash('success', 'Your request for this pack is already with us — we will add the credits once payment is received.');
            $this->go('/credits');
        }

        $credits->createRequest((int)$this->partner['id'], $pack['price'], $pack['credits'], (int)$this->user['id']);
        flash('success', sprintf(
            'Request sent for %s credits (%s). We will add them once payment is received.',
            number_format($pack['credits']),
            formatPrice($pack['price'])
        ));
        $this->go('/credits');
    }

    private function supportWhatsapp(): ?string
    {
        $digits = preg_replace('/\D/', '', (string)siteSetting('ar_partner_support_whatsapp', ''));
        return $digits === '' ? null : $digits;
    }

    // ------------------------------------------------------------- analytics

    private function analytics(): void
    {
        $pid = (int)$this->partner['id'];
        $events = new ArScanEvent();
        $this->page('analytics', 'Analytics', 'analytics', [
            'totals'  => $events->totals($pid),
            'counts'  => $this->service->contentCounts($pid),
            'daily'   => $events->daily($pid, 30),
            'content' => $events->perFrame($pid),
        ]);
    }

    // --------------------------------------------------------------- helpers

    private function page(string $view, string $title, string $nav, array $data = []): void
    {
        renderRaw('partner/layout', array_merge($data, [
            '_partnerView' => $view,
            'pageTitle'    => $title,
            'activeNav'    => $nav,
            'partner'      => $this->partner,
            'partnerUser'  => $this->user,
            'brand'        => $this->brand(),
            'base'         => '/partner/' . $this->partner['slug'],
        ]));
    }

    private function brand(): array
    {
        $siteName = (string)siteSetting('site_name', SITE_NAME);
        if (!$this->partner) {
            // The shared seller sign-in wears the shop's own colours and logo.
            return [
                'name'      => $siteName,
                'logo'      => asset((string)siteSetting('logo_path', '/images/GDKD logo.png')),
                'color'     => ArPartnerService::safeColor((string)siteSetting('primary_color', '')),
                'website'   => null,
                'poweredBy' => null,
            ];
        }
        return ArPartnerService::brand($this->partner, $siteName);
    }

    private function kindAllowed(string $kind): bool
    {
        return $kind === 'album' ? !empty($this->partner['allow_albums']) : !empty($this->partner['allow_singles']);
    }

    /** Pages allowed in one album, or null when the admin set 0 (no limit). */
    private function maxAlbumPages(): ?int
    {
        $max = (int)$this->partner['max_album_pages'];
        return $max <= 0 ? null : $max;
    }

    private function maxVideoMb(): int
    {
        return max(1, (int)$this->partner['max_video_mb']);
    }

    private function compilerOk(): bool
    {
        require_once APP_PATH . '/services/ArTargetService.php';
        return !empty((new ArTargetService())->preflight()['ok']);
    }

    private function flashGeneration(array $result, string $lead): void
    {
        if (empty($result['ok'])) {
            flash('error', $lead . ' But a photo could not be prepared: ' . ($result['error'] ?? 'unknown error')
                . ' Replace that image, or press "Prepare photos" to try again.');
            return;
        }
        if (isset($result['flag']) && $result['flag'] !== 'good') {
            flash('error', sprintf(
                '%s Heads up: %sthis photo may be hard to scan (%d/100). %s',
                $lead,
                ($result['total'] ?? 1) > 1 ? 'photo ' . $result['position'] . ' — ' : '',
                $result['score'],
                $result['advice'] ?? ''
            ));
            return;
        }
        flash('success', $lead . ' Your AR is live — print the QR sticker, or send the link to your customer.');
    }

    /**
     * Compile the photos still missing a target, as many as this request may.
     *
     * Each request compiles at most GENERATE_BATCH photos, within the rolling
     * GENERATE_LIMIT, so an album of any size is prepared by pressing "Prepare
     * photos" again rather than by one request that runs past the time limit.
     */
    private function prepareMissing(int $frameId, int $missing, string $lead): void
    {
        $batch = $this->claimGeneration(min($missing, self::GENERATE_BATCH));
        if ($batch === 0) {
            flash('error', $lead . ' But many photos were processed in the last few minutes. Wait a minute, then press "Prepare photos".');
            return;
        }

        @set_time_limit(30 + 25 * $batch);
        $result = $this->frames->generateTarget($frameId, true, $batch);
        if ($batch < $missing && !empty($result['ok'])) {
            flash('error', sprintf('%s %d of %d photos prepared — press "Prepare photos" to continue with the rest.', $lead, $batch, $missing));
            return;
        }
        $this->flashGeneration($result, $lead);
    }

    /** Reserve up to $wanted compilations in the rolling window; returns how many were granted. */
    private function claimGeneration(int $wanted): int
    {
        $now = time();
        $recent = array_values(array_filter(
            $_SESSION['partner_generate_times'] ?? [],
            fn($t) => ($now - (int)$t) < self::GENERATE_WINDOW_SECONDS
        ));
        $granted = max(0, min($wanted, self::GENERATE_LIMIT - count($recent)));
        for ($i = 0; $i < $granted; $i++) {
            $recent[] = $now;
        }
        $_SESSION['partner_generate_times'] = $recent;
        return $granted;
    }

    /** A path in this partner's portal, or on the shared seller sign-in when no partner is known yet. */
    private function portalPath(string $path): string
    {
        return '/partner' . ($this->partner ? '/' . $this->partner['slug'] : '') . ($path === '/' ? '' : $path);
    }

    private function portalUrl(string $path): string
    {
        return url($this->portalPath($path));
    }

    private function go(string $path): void
    {
        redirect($this->portalPath($path));
    }

    private function wantsJson(): bool
    {
        return str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /** JSON reply with any stray buffered output thrown away first. */
    private function json(array $data, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        jsonResponse($data, $status);
    }

    private function notFound(): void
    {
        http_response_code(404);
        (new PageController())->notFound();
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
