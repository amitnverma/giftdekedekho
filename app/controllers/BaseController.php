<?php

abstract class BaseController
{
    protected function view(string $name, array $data = []): void
    {
        render($name, $data);
    }

    protected function viewAdmin(string $name, array $data = []): void
    {
        $data['_adminView'] = $name;
        renderRaw('admin/layout', $data);
    }

    protected function input(string $key, $default = null)
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    protected function requireLogin(): void
    {
        if (!isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/account';
            redirect('/account/login');
        }
    }

    protected function requireAdmin(): void
    {
        if (!isAdmin()) {
            redirect('/admin/login');
        }
        // Session timeout for admin area
        if (!empty($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > SESSION_TIMEOUT_SECONDS) {
            session_unset();
            session_destroy();
            redirect('/admin/login');
        }
        $_SESSION['admin_last_activity'] = time();
    }

    protected function requireCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
            http_response_code(419);
            die('Invalid or expired security token. Please go back and try again.');
        }
    }

    protected function setOld(array $data): void
    {
        $_SESSION['_old'] = $data;
    }

    protected function clearOld(): void
    {
        unset($_SESSION['_old']);
    }
}
