<?php

class AdminSettingsController extends BaseController
{
    public function general(): void
    {
        $this->requireAdmin();
        $settings = new Settings();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $settings->setMany([
                'site_name' => trim((string)$this->input('site_name')),
                'site_tagline' => trim((string)$this->input('site_tagline')),
                'site_email' => trim((string)$this->input('site_email')),
                'site_phone' => trim((string)$this->input('site_phone')),
                'site_address' => trim((string)$this->input('site_address')),
                'currency_symbol' => trim((string)$this->input('currency_symbol', '₹')),
                'currency_code' => trim((string)$this->input('currency_code', 'INR')),
                'low_stock_threshold' => (string)(int)$this->input('low_stock_threshold', 5),
                'meta_title_suffix' => trim((string)$this->input('meta_title_suffix')),
                'google_analytics_id' => trim((string)$this->input('google_analytics_id')),
                'admin_ip_whitelist' => trim((string)$this->input('admin_ip_whitelist')),
                'max_login_attempts' => (string)(int)$this->input('max_login_attempts', 5),
                'login_lockout_minutes' => (string)(int)$this->input('login_lockout_minutes', 15),
                'shiprocket_email' => trim((string)$this->input('shiprocket_email')),
                'shiprocket_password' => trim((string)$this->input('shiprocket_password')),
            ]);
            flash('success', 'General settings updated.');
            redirect('/admin/settings');
        }

        $this->viewAdmin('admin/settings_general', [
            'metaTitle' => 'General Settings',
            'settings' => $settings->getAll(),
        ]);
    }

    public function payments(): void
    {
        $this->requireAdmin();
        $settings = new Settings();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $settings->setMany([
                'razorpay_key_id' => trim((string)$this->input('razorpay_key_id')),
                'razorpay_key_secret' => trim((string)$this->input('razorpay_key_secret')),
                'razorpay_mode' => $this->input('razorpay_mode') === 'live' ? 'live' : 'test',
                'paypal_client_id' => trim((string)$this->input('paypal_client_id')),
                'paypal_client_secret' => trim((string)$this->input('paypal_client_secret')),
                'paypal_mode' => $this->input('paypal_mode') === 'live' ? 'live' : 'sandbox',
                'stripe_publishable_key' => trim((string)$this->input('stripe_publishable_key')),
                'stripe_secret_key' => trim((string)$this->input('stripe_secret_key')),
                'stripe_mode' => $this->input('stripe_mode') === 'live' ? 'live' : 'test',
            ]);
            flash('success', 'Payment gateway settings updated.');
            redirect('/admin/settings/payments');
        }

        $this->viewAdmin('admin/settings_payments', [
            'metaTitle' => 'Payment Settings',
            'settings' => $settings->getAll(),
        ]);
    }

    public function notifications(): void
    {
        $this->requireAdmin();
        $settings = new Settings();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $settings->setMany([
                'smtp_host' => trim((string)$this->input('smtp_host')),
                'smtp_port' => (string)(int)$this->input('smtp_port', 587),
                'smtp_user' => trim((string)$this->input('smtp_user')),
                'smtp_pass' => trim((string)$this->input('smtp_pass')),
                'smtp_from_name' => trim((string)$this->input('smtp_from_name')),
                'smtp_from_email' => trim((string)$this->input('smtp_from_email')),
                'msg91_api_key' => trim((string)$this->input('msg91_api_key')),
                'msg91_sender_id' => trim((string)$this->input('msg91_sender_id')),
                'order_confirmed_template' => (string)$this->input('order_confirmed_template'),
                'order_shipped_template' => (string)$this->input('order_shipped_template'),
                'order_delivered_template' => (string)$this->input('order_delivered_template'),
            ]);
            flash('success', 'Notification settings updated.');
            redirect('/admin/notifications');
        }

        $this->viewAdmin('admin/settings_notifications', [
            'metaTitle' => 'Notification Settings',
            'settings' => $settings->getAll(),
        ]);
    }
}
