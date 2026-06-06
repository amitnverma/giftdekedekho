<?php
/**
 * Sends transactional emails (via PHPMailer/SMTP) and SMS (via MSG91).
 * SMTP & SMS credentials are stored in the `settings` table (admin-editable).
 *
 * Note: PHPMailer must be present in /libs/PHPMailer (composer or manual vendor copy).
 * If it's missing, email sending degrades gracefully to PHP's mail().
 */
class NotificationService
{
    private Settings $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    // ===================== EMAIL =====================

    public function sendEmail(string $toEmail, string $toName, string $subject, string $bodyHtml): bool
    {
        $phpMailerAutoload = BASE_PATH . '/libs/PHPMailer/src/PHPMailer.php';

        if (is_file($phpMailerAutoload) && is_file(BASE_PATH . '/libs/PHPMailer/src/SMTP.php') && is_file(BASE_PATH . '/libs/PHPMailer/src/Exception.php')) {
            require_once BASE_PATH . '/libs/PHPMailer/src/Exception.php';
            require_once BASE_PATH . '/libs/PHPMailer/src/PHPMailer.php';
            require_once BASE_PATH . '/libs/PHPMailer/src/SMTP.php';

            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $this->settings->get('smtp_host');
                $mail->SMTPAuth = true;
                $mail->Username = $this->settings->get('smtp_user');
                $mail->Password = $this->settings->get('smtp_pass');
                $mail->SMTPSecure = 'tls';
                $mail->Port = (int)$this->settings->get('smtp_port', 587);

                $mail->setFrom($this->settings->get('smtp_from_email'), $this->settings->get('smtp_from_name', SITE_NAME));
                $mail->addAddress($toEmail, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $bodyHtml;
                $mail->AltBody = strip_tags($bodyHtml);

                return $mail->send();
            } catch (Throwable $e) {
                error_log('PHPMailer error: ' . $e->getMessage());
                return false;
            }
        }

        // Fallback: native mail()
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: ' . $this->settings->get('smtp_from_name', SITE_NAME) . ' <' . $this->settings->get('smtp_from_email', 'noreply@example.com') . ">\r\n";
        return @mail($toEmail, $subject, $bodyHtml, $headers);
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
        curl_close($ch);

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
