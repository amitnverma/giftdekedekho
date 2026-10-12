<?php
/**
 * Sends transactional emails (via PHPMailer/SMTP) and SMS (via MSG91).
 * SMTP & SMS credentials are stored in the `settings` table (Admin → Notifications).
 *
 * PHPMailer is committed under libs/PHPMailer/src. When no SMTP host is set,
 * email falls back to PHP's mail() — which on the production VPS accepts the
 * message and delivers nothing, so SMTP must be configured there.
 */
class NotificationService
{
    /** Kept short: order emails are sent while the customer waits on checkout. */
    private const SMTP_TIMEOUT_SECONDS = 15;

    private Settings $settings;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    // ===================== EMAIL =====================

    /** Why the last sendEmail() returned false, for the admin's test button and the log. */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /** "SMTP smtp.hostinger.com:465 (SSL)", or a note that PHP mail() is in use. */
    public function transportLabel(): string
    {
        $host = trim((string)$this->settings->get('smtp_host', ''));
        if ($host === '') {
            return 'PHP mail() — no SMTP host is set';
        }
        $port = $this->smtpPort();
        return 'SMTP ' . $host . ':' . $port . ($port === 465 ? ' (SSL)' : ($port === 587 ? ' (STARTTLS)' : ''));
    }

    public function sendEmail(string $toEmail, string $toName, string $subject, string $bodyHtml): bool
    {
        $this->lastError = null;
        $host = trim((string)$this->settings->get('smtp_host', ''));

        if ($host === '') {
            $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
            $headers .= 'From: ' . $this->settings->get('smtp_from_name', SITE_NAME) . ' <' . $this->settings->get('smtp_from_email', 'noreply@example.com') . ">\r\n";
            if (@mail($toEmail, $subject, $bodyHtml, $headers)) {
                return true;
            }
            return $this->fail($toEmail, 'No SMTP host is set in Admin → Notifications, and PHP mail() refused the message.');
        }

        $lib = BASE_PATH . '/libs/PHPMailer/src/';
        if (!is_file($lib . 'PHPMailer.php') || !is_file($lib . 'SMTP.php') || !is_file($lib . 'Exception.php')) {
            return $this->fail($toEmail, 'PHPMailer is missing from libs/PHPMailer/src.');
        }
        require_once $lib . 'Exception.php';
        require_once $lib . 'PHPMailer.php';
        require_once $lib . 'SMTP.php';

        $user = trim((string)$this->settings->get('smtp_user', ''));
        // Mail hosts such as Hostinger reject a From address other than the
        // mailbox that signed in, so that mailbox is the default.
        $from = trim((string)$this->settings->get('smtp_from_email', '')) ?: $user;
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $this->fail($toEmail, 'Set a valid From Email (or an email-address SMTP username) in Admin → Notifications.');
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $port = $this->smtpPort();
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            // 465 is SSL from the first byte; 587 upgrades with STARTTLS. Any
            // other port is left to PHPMailer, which upgrades when offered.
            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($port === 587) {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->SMTPAuth = $user !== '';
            $mail->Username = $user;
            $mail->Password = (string)$this->settings->get('smtp_pass', '');
            $mail->Timeout = self::SMTP_TIMEOUT_SECONDS;
            $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;

            $mail->setFrom($from, (string)($this->settings->get('smtp_from_name', '') ?: SITE_NAME));
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", $bodyHtml)), ENT_QUOTES, 'UTF-8'));

            $mail->send();
            return true;
        } catch (Throwable $e) {
            return $this->fail($toEmail, $mail->ErrorInfo ?: $e->getMessage());
        }
    }

    private function smtpPort(): int
    {
        return (int)$this->settings->get('smtp_port', 587) ?: 587;
    }

    private function fail(string $toEmail, string $error): bool
    {
        $this->lastError = $error;
        error_log('Email to ' . $toEmail . ' failed: ' . $error);
        return false;
    }

    // ===================== SMS (MSG91) =====================

    public function sendSms(string $phone, string $message): bool
    {
        $apiKey = $this->settings->get('msg91_api_key');
        if (!$apiKey || !$phone) return false;

        $senderId = $this->settings->get('msg91_sender_id', 'GFTDKD');
        $url = 'https://api.msg91.com/api/v5/flow/';

        $payload = json_encode([
            'sender' => $senderId,
            'route' => '4',
            'mobiles' => '91' . preg_replace('/\D/', '', $phone),
            'message' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'authkey: ' . $apiKey],
            CURLOPT_TIMEOUT => 20,
        ]);
        $result = curl_exec($ch);
        $err = curl_error($ch);

        if ($err) {
            error_log('MSG91 SMS error: ' . $err);
            return false;
        }
        return true;
    }

    // ===================== Order lifecycle notifications =====================

    public function sendOrderConfirmed(int $orderId): void
    {
        $order = (new Order())->findWithItems($orderId);
        if (!$order) return;
        $recipient = $this->resolveRecipient($order);
        $template = $this->settings->get('order_confirmed_template', 'Hi {{name}}, your order #{{order_id}} has been confirmed.');
        $message = $this->fillTemplate($template, $order, $recipient);

        if ($recipient['email']) {
            $this->sendEmail($recipient['email'], $recipient['name'], 'Order Confirmed — #' . $orderId . ' | ' . SITE_NAME, nl2br(e($message)));
        }
        if ($recipient['phone']) {
            $this->sendSms($recipient['phone'], $message);
        }
    }

    /** Customer skipped payment: tell them the order is on hold and give them a link to pay. */
    public function sendPaymentPending(int $orderId): void
    {
        $order = (new Order())->findWithItems($orderId);
        if (!$order || $order['payment_status'] === 'paid') return;
        $recipient = $this->resolveRecipient($order);
        require_once APP_PATH . '/services/PaymentService.php';
        $link = (new PaymentService())->payLink($orderId);

        if ($recipient['email']) {
            $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:520px">'
                . '<p>Hi ' . e((string)$recipient['name']) . ',</p>'
                . '<p>We have saved your order <strong>#' . $orderId . '</strong> for ' . e(formatPrice($order['total']))
                . ', but its payment is still <strong>pending</strong>. We will start preparing it as soon as payment is received.</p>'
                . $this->itemsTableHtml($order)
                . '<p><a href="' . e($link) . '" style="display:inline-block;background:#e63946;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:bold">Complete payment</a></p>'
                . '<p style="font-size:12px;color:#9ca3af;word-break:break-all">' . e($link) . '</p>'
                . '</div>';
            $this->sendEmail($recipient['email'], (string)$recipient['name'], 'Payment pending — Order #' . $orderId . ' | ' . SITE_NAME, $html);
        }
        if ($recipient['phone']) {
            $this->sendSms($recipient['phone'], 'Hi ' . $recipient['name'] . ', payment for your ' . SITE_NAME . ' order #' . $orderId . ' is pending. Pay here: ' . $link);
        }
    }

    /** Tells the shop (Contact Email in Admin → Settings) that an order is confirmed. */
    public function sendAdminNewOrder(int $orderId): void
    {
        $to = trim((string)$this->settings->get('site_email', ''));
        $order = (new Order())->findWithItems($orderId);
        if ($to === '' || !$order) return;
        $recipient = $this->resolveRecipient($order);
        $addr = json_decode($order['address_snapshot_json'] ?? '{}', true) ?: [];
        $payment = $order['payment_gateway'] === 'cod'
            ? 'Cash on Delivery'
            : ucfirst((string)$order['payment_gateway']) . ' — ' . $order['payment_status']
                . (!empty($order['payment_reference']) ? ' (' . $order['payment_reference'] . ')' : '');

        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:560px">'
            . '<p>New order <strong>#' . $orderId . '</strong> for <strong>' . e(formatPrice($order['total'])) . '</strong>.</p>'
            . '<p><strong>Customer:</strong> ' . e((string)$recipient['name'])
            . ($recipient['email'] ? ' · ' . e((string)$recipient['email']) : '')
            . ($recipient['phone'] ? ' · ' . e((string)$recipient['phone']) : '') . '<br>'
            . '<strong>Payment:</strong> ' . e($payment) . '<br>'
            . '<strong>Ship to:</strong> ' . e(trim(($addr['address_line1'] ?? '') . ' ' . ($addr['address_line2'] ?? '')))
            . ', ' . e((string)($addr['city'] ?? '')) . ', ' . e((string)($addr['state'] ?? '')) . ' - ' . e((string)($addr['pincode'] ?? '')) . '</p>'
            . $this->itemsTableHtml($order)
            . '<p><a href="' . e(url('/admin/orders/' . $orderId)) . '">Open in admin</a></p>'
            . '</div>';
        $this->sendEmail($to, SITE_NAME . ' Admin', 'New order #' . $orderId . ' — ' . formatPrice($order['total']), $html);
    }

    private function itemsTableHtml(array $order): string
    {
        $rows = '';
        foreach ($order['items'] as $item) {
            $rows .= '<tr><td style="padding:4px 8px">' . e((string)$item['product_name_snapshot']) . '</td>'
                . '<td style="padding:4px 8px;text-align:center">' . (int)$item['quantity'] . '</td>'
                . '<td style="padding:4px 8px;text-align:right">' . e(formatPrice($item['unit_price'] * $item['quantity'])) . '</td></tr>';
        }
        return '<table style="border-collapse:collapse;font-size:14px;margin:8px 0 16px">' . $rows
            . '<tr><td style="padding:4px 8px;font-weight:bold" colspan="2">Total</td>'
            . '<td style="padding:4px 8px;text-align:right;font-weight:bold">' . e(formatPrice($order['total'])) . '</td></tr></table>';
    }

    public function sendOrderShipped(int $orderId): void
    {
        $order = (new Order())->findWithItems($orderId);
        if (!$order) return;
        $recipient = $this->resolveRecipient($order);
        $template = $this->settings->get('order_shipped_template', 'Hi {{name}}, your order #{{order_id}} has shipped! Track: {{tracking_url}}');
        $message = $this->fillTemplate($template, $order, $recipient);

        if ($recipient['email']) {
            $this->sendEmail($recipient['email'], $recipient['name'], 'Your Order Has Shipped — #' . $orderId . ' | ' . SITE_NAME, nl2br(e($message)));
        }
        if ($recipient['phone']) {
            $this->sendSms($recipient['phone'], $message);
        }
    }

    public function sendOrderDelivered(int $orderId): void
    {
        $order = (new Order())->findWithItems($orderId);
        if (!$order) return;
        $recipient = $this->resolveRecipient($order);
        $template = $this->settings->get('order_delivered_template', 'Hi {{name}}, your order #{{order_id}} has been delivered!');
        $message = $this->fillTemplate($template, $order, $recipient);

        if ($recipient['email']) {
            $this->sendEmail($recipient['email'], $recipient['name'], 'Order Delivered — #' . $orderId . ' | ' . SITE_NAME, nl2br(e($message)));
        }
        if ($recipient['phone']) {
            $this->sendSms($recipient['phone'], $message);
        }
    }

    public function sendContactMessage(string $name, string $email, string $message): void
    {
        $to = $this->settings->get('site_email');
        if (!$to) return;
        $body = "New contact form submission:<br><br><strong>Name:</strong> " . e($name) .
            "<br><strong>Email:</strong> " . e($email) . "<br><strong>Message:</strong><br>" . nl2br(e($message));
        $this->sendEmail($to, SITE_NAME . ' Admin', 'New Contact Message from ' . $name, $body);
    }

    private function resolveRecipient(array $order): array
    {
        if (!empty($order['user_id'])) {
            $user = (new User())->find((int)$order['user_id']);
            if ($user) {
                return ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']];
            }
        }
        $addr = json_decode($order['address_snapshot_json'] ?? '{}', true) ?: [];
        return [
            'name' => $addr['full_name'] ?? 'Customer',
            'email' => $order['guest_email'] ?? null,
            'phone' => $order['guest_phone'] ?? ($addr['phone'] ?? null),
        ];
    }

    private function fillTemplate(string $template, array $order, array $recipient): string
    {
        $replacements = [
            '{{name}}' => $recipient['name'] ?? 'Customer',
            '{{order_id}}' => $order['id'],
            '{{total}}' => number_format((float)$order['total'], 2),
            '{{tracking_url}}' => $order['tracking_url'] ?? '',
            '{{tracking_number}}' => $order['tracking_number'] ?? '',
        ];
        return strtr($template, $replacements);
    }
}
