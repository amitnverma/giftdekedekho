<?php

class AdminAuthController extends BaseController
{
    public function login(): void
    {
        if (isAdmin()) redirect('/admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $email = strtolower(trim((string)$this->input('email')));
            $password = (string)$this->input('password');

            $settings = new Settings();
            $whitelist = array_filter(array_map('trim', explode(',', $settings->get('admin_ip_whitelist', ''))));
            if (!empty($whitelist) && !in_array($_SERVER['REMOTE_ADDR'] ?? '', $whitelist, true)) {
                flash('error', 'Access denied from this IP address.');
                redirect('/admin/login');
            }

            if ($this->isRateLimited($email)) {
                flash('error', 'Too many failed attempts. Please try again later.');
                redirect('/admin/login');
            }

            $userModel = new User();
            $user = $userModel->findByEmail($email);

            if (!$user || $user['role'] !== 'admin' || !$userModel->verifyPassword($user, $password)) {
                $this->recordAttempt($email);
                flash('error', 'Invalid credentials.');
                redirect('/admin/login');
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['admin_last_activity'] = time();

            redirect('/admin');
        }

        renderRaw('admin/login', ['metaTitle' => 'Admin Login']);
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        redirect('/admin/login');
    }

    private function isRateLimited(string $identifier): bool
    {
        $settings = new Settings();
        $maxAttempts = (int)$settings->get('max_login_attempts', 5);
        $lockoutMinutes = (int)$settings->get('login_lockout_minutes', 15);
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) c FROM login_attempts WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)');
        $stmt->execute(['admin:' . $identifier, $lockoutMinutes]);
        return (int)$stmt->fetch()['c'] >= $maxAttempts;
    }

    private function recordAttempt(string $identifier): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)');
        $stmt->execute(['admin:' . $identifier, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    }
}
