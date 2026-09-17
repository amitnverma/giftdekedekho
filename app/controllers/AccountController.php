<?php

require_once APP_PATH . '/services/NotificationService.php';

class AccountController extends BaseController
{
    /** How long an emailed password-reset link works. */
    private const RESET_LINK_SECONDS = 3600;

    public function login(): void
    {
        // ?redirect=/product/foo sends the customer back where they came from.
        // Only same-site paths are accepted, never another host.
        $back = (string)($_GET['redirect'] ?? '');
        if ($back !== '' && $back[0] === '/' && !str_starts_with($back, '//') && !str_contains($back, '\\')) {
            $_SESSION['redirect_after_login'] = $back;
        }

        if (isLoggedIn()) redirect($_SESSION['redirect_after_login'] ?? '/account');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->input('action') === 'login') {
            $this->requireCsrf();
            $email = strtolower(trim($this->input('email')));
            $password = (string)$this->input('password');

            if ($this->isRateLimited($email)) {
                flash('error', 'Too many login attempts. Please try again later.');
                redirect('/account/login');
            }

            $userModel = new User();
            $user = $userModel->findByEmail($email);

            if (!$user || !$userModel->verifyPassword($user, $password) || $user['role'] !== 'customer') {
                $this->recordAttempt($email);
                flash('error', 'Invalid email or password.');
                $this->setOld(['email' => $email]);
                redirect('/account/login');
            }

            $this->loginUser($user);
            (new Cart())->mergeGuestCartIntoUser((int)$user['id']);

            $redirectTo = $_SESSION['redirect_after_login'] ?? '/account';
            unset($_SESSION['redirect_after_login']);
            redirect($redirectTo);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->input('action') === 'register') {
            $this->handleRegister();
            return;
        }

        $this->view('account_auth', ['metaTitle' => 'Login / Register | ' . SITE_NAME]);
    }

    public function register(): void
    {
        $this->login(); // shares the same combined auth view with tabs
    }

    private function handleRegister(): void
    {
        $this->requireCsrf();
        $name = trim((string)$this->input('name'));
        $email = strtolower(trim((string)$this->input('email')));
        $phone = trim((string)$this->input('phone'));
        $password = (string)$this->input('password');
        $confirm = (string)$this->input('password_confirm');

        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (!preg_match('/^\d{10}$/', $phone)) $errors[] = 'Please enter a valid 10-digit phone number.';
        if (strlen($password) < PASSWORD_MIN_LENGTH) $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        $userModel = new User();
        if (empty($errors) && $userModel->findByEmail($email)) {
            $errors[] = 'An account with this email already exists.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            $this->setOld(['name' => $name, 'email' => $email, 'phone' => $phone]);
            redirect('/account/register');
        }

        $userId = $userModel->create($name, $email, $password, $phone, 'customer');
        $user = $userModel->find($userId);
        $this->loginUser($user);
        (new Cart())->mergeGuestCartIntoUser($userId);
        flash('success', 'Welcome to ' . SITE_NAME . '!');
        $redirectTo = $_SESSION['redirect_after_login'] ?? '/account';
        unset($_SESSION['redirect_after_login']);
        redirect($redirectTo);
    }

    private function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_pw'] = self::passwordStamp($user);
        $this->clearOld();
    }

    /**
     * A short fingerprint of a customer's password hash, kept in their session:
     * once the password changes, sessions signed in with the old one end
     * (see endStaleCustomerSession() in helpers.php).
     */
    public static function passwordStamp(array $user): string
    {
        return substr(hash('sha256', (string)$user['password_hash']), 0, 20);
    }

    // --------------------------------------------------------- password reset

    /**
     * "Forgot password": email a reset link. The reply is the same whether or
     * not the address has an account, so the form cannot be used to find out.
     */
    public function forgotPassword(): void
    {
        if (isLoggedIn()) redirect('/account/profile');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $email = strtolower(trim((string)$this->input('email', '')));
            $identifier = 'customer-reset:' . $email;
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->setOld(['email' => $email]);
                flash('error', 'Enter the email address you sign in with.');
                redirect('/account/forgot-password');
            }
            if ($this->resetRateLimited($identifier)) {
                flash('error', 'A reset link was requested several times already. Please check your inbox, or wait 15 minutes and try again.');
                redirect('/account/forgot-password');
            }
            $this->recordAttempt(substr($identifier, 0, 180));

            $this->clearOld();
            flash('success', 'If ' . $email . ' has an account here, a reset link is on its way. It works for '
                . intdiv(self::RESET_LINK_SECONDS, 60) . ' minutes. No email? Check your spam folder, or contact us.');

            $user = (new User())->findByEmail($email);
            if (!$user || $user['role'] !== 'customer' || empty($user['is_active'])) {
                redirect('/account/login');
            }

            // Answer first, then send: an SMTP round trip takes seconds, and a
            // reply that was only slow for real accounts would give them away.
            header('Location: ' . url('/account/login'));
            session_write_close();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
            $link = url('/account/reset-password?token=' . rawurlencode($this->resetToken($user)));
            $siteName = (string)siteSetting('site_name', SITE_NAME);
            $sent = (new NotificationService())->sendEmail($user['email'], (string)$user['name'],
                'Reset your ' . $siteName . ' password', $this->resetEmailHtml($user, $link, $siteName));
            if (!$sent) {
                error_log('Customer password reset email failed for user ' . (int)$user['id']);
            }
            exit;
        }

        $this->view('account_password_forgot', ['metaTitle' => 'Forgot password | ' . SITE_NAME]);
    }

    public function resetPassword(): void
    {
        $token = (string)$this->input('token', '');
        $user = $this->userForResetToken($token);
        if (!$user) {
            flash('error', 'That reset link has expired or has already been used. Request a new one below.');
            redirect('/account/forgot-password');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $password = (string)$this->input('password', '');
            $error = null;
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                $error = 'The new password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            } elseif (!hash_equals($password, (string)$this->input('password_confirm', ''))) {
                $error = 'The two new passwords do not match.';
            }
            if ($error !== null) {
                flash('error', $error);
                redirect('/account/reset-password?token=' . rawurlencode($token));
            }
            (new User())->updatePassword((int)$user['id'], $password);
            // Old sessions end through the password stamp; clear this browser's
            // customer login too.
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['user_pw']);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed. Log in with the new one.');
            redirect('/account/login');
        }

        // The token is in this page's address: keep it out of Referer headers
        // sent to anything the page loads.
        header('Referrer-Policy: no-referrer');
        $this->view('account_password_reset', [
            'metaTitle' => 'Choose a new password | ' . SITE_NAME,
            'token'     => $token,
            'email'     => $user['email'],
        ]);
    }

    /**
     * A password-reset token for one customer.
     *
     * Nothing is stored: the token is the user id and an expiry, signed with a
     * key that includes the current password hash. Setting any new password
     * changes that hash, so every link issued before it stops working,
     * including this one once it is used.
     */
    private function resetToken(array $user): string
    {
        $payload = (int)$user['id'] . '.' . (time() + self::RESET_LINK_SECONDS);
        return $payload . '.' . $this->resetSignature($user, $payload);
    }

    /** The customer a reset token belongs to, if it is genuine, unexpired and unused. */
    private function userForResetToken(string $token): ?array
    {
        if (!preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{64})$/', $token, $m) || (int)$m[2] < time()) {
            return null;
        }
        $user = (new User())->find((int)$m[1]);
        if (!$user || $user['role'] !== 'customer' || empty($user['is_active'])) {
            return null;
        }
        return hash_equals($this->resetSignature($user, $m[1] . '.' . $m[2]), $m[3]) ? $user : null;
    }

    private function resetSignature(array $user, string $payload): string
    {
        $settings = new Settings();
        $key = (string)$settings->get('customer_reset_key', '');
        if ($key === '') {
            $key = bin2hex(random_bytes(32));
            $settings->set('customer_reset_key', $key);
        }
        return hash_hmac('sha256', $payload . '|' . $user['password_hash'], $key);
    }

    private function resetRateLimited(string $identifier): bool
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([substr($identifier, 0, 180)]);
        return (int)$stmt->fetchColumn() >= 3;
    }

    private function resetEmailHtml(array $user, string $link, string $siteName): string
    {
        return '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:520px">'
            . '<p>Hi ' . e((string)$user['name']) . ',</p>'
            . '<p>Someone asked to reset the password for your <strong>' . e($siteName) . '</strong> account ('
            . e((string)$user['email']) . ').</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;background:#e63946;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:bold">Choose a new password</a></p>'
            . '<p style="font-size:13px;color:#6b7280">The link works for ' . intdiv(self::RESET_LINK_SECONDS, 60)
            . ' minutes and only once. If you did not ask for this, ignore this email — your password stays the same.</p>'
            . '<p style="font-size:12px;color:#9ca3af;word-break:break-all">' . e($link) . '</p>'
            . '</div>';
    }

    private function isRateLimited(string $identifier): bool
    {
        $settings = new Settings();
        $maxAttempts = (int)$settings->get('max_login_attempts', 5);
        $lockoutMinutes = (int)$settings->get('login_lockout_minutes', 15);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT COUNT(*) c FROM login_attempts WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$identifier, $lockoutMinutes]);
        return (int)$stmt->fetch()['c'] >= $maxAttempts;
    }

    private function recordAttempt(string $identifier): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)');
        $stmt->execute([$identifier, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        redirect('/');
    }

    public function dashboard(): void
    {
        $this->requireLogin();
        $userId = currentUserId();
        $orderModel = new Order();
        $recentOrders = array_slice($orderModel->userOrders($userId), 0, 5);
        $wishlistCount = count((new Wishlist())->forUser($userId));
        $addressCount = count((new Address())->forUser($userId));

        $this->view('account_dashboard', [
            'metaTitle' => 'My Account | ' . SITE_NAME,
            'recentOrders' => $recentOrders,
            'wishlistCount' => $wishlistCount,
            'addressCount' => $addressCount,
            'active' => 'dashboard',
        ]);
    }

    public function orders(): void
    {
        $this->requireLogin();

        // Handle review submission posted from product page
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->input('action') === 'submit_review') {
            $this->requireCsrf();
            $productId = (int)$this->input('product_id');
            $rating = max(1, min(5, (int)$this->input('rating', 5)));
            $title = trim((string)$this->input('title'));
            $body = strip_tags(trim((string)$this->input('body')));
            if ($body !== '') {
                (new Review())->create($productId, currentUserId(), $rating, $title ?: null, $body);
                flash('success', 'Thank you! Your review has been submitted for approval.');
            }
            redirect($_SERVER['HTTP_REFERER'] ?? '/account/orders');
        }

        $orders = (new Order())->userOrders(currentUserId());
        $this->view('account_orders', [
            'metaTitle' => 'My Orders | ' . SITE_NAME,
            'orders' => $orders,
            'active' => 'orders',
        ]);
    }

    public function orderDetail(int $orderId): void
    {
        $this->requireLogin();
        $order = (new Order())->findWithItems($orderId);
        if (!$order || (int)$order['user_id'] !== currentUserId()) {
            http_response_code(404);
            (new PageController())->notFound();
            return;
        }
        $this->view('account_order_detail', [
            'metaTitle' => 'Order #' . $orderId . ' | ' . SITE_NAME,
            'order' => $order,
            'active' => 'orders',
        ]);
    }

    public function wishlist(): void
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->input('action') === 'remove') {
            $this->requireCsrf();
            $productId = (int)$this->input('product_id');
            (new Wishlist())->toggle(currentUserId(), $productId);
            redirect('/account/wishlist');
        }

        $items = (new Wishlist())->forUser(currentUserId());
        $this->view('account_wishlist', [
            'metaTitle' => 'My Wishlist | ' . SITE_NAME,
            'items' => $items,
            'active' => 'wishlist',
        ]);
    }

    public function addresses(): void
    {
        $this->requireLogin();
        $addressModel = new Address();
        $userId = currentUserId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $action = $this->input('action');
            if ($action === 'add') {
                $addressModel->create($userId, [
                    'label' => $this->input('label', 'Home'),
                    'address_line1' => $this->input('address_line1'),
                    'address_line2' => $this->input('address_line2'),
                    'city' => $this->input('city'),
                    'state' => $this->input('state'),
                    'pincode' => $this->input('pincode'),
                    'is_default' => $this->input('is_default') ? 1 : 0,
                ]);
                flash('success', 'Address added successfully.');
            } elseif ($action === 'delete') {
                $addressModel->deleteForUser((int)$this->input('address_id'), $userId);
                flash('success', 'Address removed.');
            }
            redirect('/account/addresses');
        }

        $addresses = $addressModel->forUser($userId);
        $this->view('account_addresses', [
            'metaTitle' => 'My Addresses | ' . SITE_NAME,
            'addresses' => $addresses,
            'active' => 'addresses',
        ]);
    }

    public function profile(): void
    {
        $this->requireLogin();
        $userModel = new User();
        $userId = currentUserId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $action = $this->input('action');
            if ($action === 'update_profile') {
                $name = trim((string)$this->input('name'));
                $phone = trim((string)$this->input('phone'));
                $userModel->updateProfile($userId, $name, $phone);
                $_SESSION['user_name'] = $name;
                flash('success', 'Profile updated successfully.');
            } elseif ($action === 'change_password') {
                $current = (string)$this->input('current_password');
                $new = (string)$this->input('new_password');
                $user = $userModel->find($userId);
                if (!$userModel->verifyPassword($user, $current)) {
                    flash('error', 'Current password is incorrect.');
                } elseif (strlen($new) < PASSWORD_MIN_LENGTH) {
                    flash('error', 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
                } else {
                    $userModel->updatePassword($userId, $new);
                    // Keep this browser signed in; every other session ends at its next click.
                    session_regenerate_id(true);
                    $_SESSION['user_pw'] = self::passwordStamp($userModel->find($userId));
                    flash('success', 'Password changed. Anywhere else you were logged in has been logged out.');
                }
            }
            redirect('/account/profile');
        }

        $user = $userModel->find($userId);
        $this->view('account_profile', [
            'metaTitle' => 'Profile Settings | ' . SITE_NAME,
            'user' => $user,
            'active' => 'profile',
        ]);
    }
}
