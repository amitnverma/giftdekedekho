<?php

class PageController extends BaseController
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->view('404', ['metaTitle' => 'Page Not Found | ' . SITE_NAME]);
    }

    public function contact(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $name = trim((string)$this->input('name'));
            $email = trim((string)$this->input('email'));
            $message = trim((string)$this->input('message'));

            if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && $message) {
                try {
                    require_once APP_PATH . '/services/NotificationService.php';
                    (new NotificationService())->sendContactMessage($name, $email, $message);
                } catch (Throwable $e) {
                    error_log('Contact mail error: ' . $e->getMessage());
                }
                flash('success', 'Thanks for reaching out! We will get back to you shortly.');
            } else {
                flash('error', 'Please fill in all fields with a valid email address.');
            }
            redirect('/contact');
        }

        $settings = new Settings();
        $this->view('contact', [
            'metaTitle' => 'Contact Us | ' . SITE_NAME,
            'siteEmail' => $settings->get('site_email'),
            'sitePhone' => $settings->get('site_phone'),
            'siteAddress' => $settings->get('site_address'),
        ]);
    }

    public function about(): void
    {
        $settings = new Settings();
        $this->view('about', [
            'metaTitle' => 'About Us | ' . SITE_NAME,
            'aboutHtml' => $settings->get('about_us_text', ''),
        ]);
    }
}
