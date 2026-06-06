<?php

class AccountController extends BaseController
{
    public function login(): void
    {
        if (isLoggedIn()) redirect('/account');

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
        redirect('/account');
    }

    private function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $this->clearOld();
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
                    flash('success', 'Password changed successfully.');
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
