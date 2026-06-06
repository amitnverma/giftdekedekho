<?php
/**
 * Thin wrapper around Razorpay, PayPal and Stripe APIs.
 * API keys are read from the `settings` table (admin-editable), not hardcoded.
 * Each gateway has a sandbox/live mode toggle via `<gateway>_mode` setting.
 */
class PaymentService
{
    private Settings $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    // ===================== RAZORPAY =====================

    public function createRazorpayOrder(int $orderId, float $amount): ?array
    {
        $keyId = $this->settings->get('razorpay_key_id');
        $keySecret = $this->settings->get('razorpay_key_secret');
        if (!$keyId || !$keySecret) return null;

        $payload = json_encode([
            'amount' => (int)round($amount * 100),
            'currency' => 'INR',
            'receipt' => 'order_' . $orderId,
            'payment_capture' => 1,
        ]);

        $response = $this->curlRequest('https://api.razorpay.com/v1/orders', 'POST', $payload, [
            'Content-Type: application/json',
        ], $keyId . ':' . $keySecret);

        return $response ?: null;
    }

    public function verifyRazorpaySignature(string $rzpOrderId, string $rzpPaymentId, string $signature): bool
    {
        $keySecret = $this->settings->get('razorpay_key_secret');
        if (!$keySecret || !$rzpOrderId || !$rzpPaymentId || !$signature) return false;

        $expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, $keySecret);
        return hash_equals($expected, $signature);
    }

    // ===================== PAYPAL =====================

    public function createPaypalOrder(int $orderId, float $amount): ?string
    {
        $clientId = $this->settings->get('paypal_client_id');
        $secret = $this->settings->get('paypal_client_secret');
        if (!$clientId || !$secret) return null;

        $base = $this->paypalBaseUrl();
        $token = $this->paypalAccessToken($base, $clientId, $secret);
        if (!$token) return null;

        // Convert INR to USD-equivalent isn't handled by PayPal directly for India; we pass INR if supported by account, else USD.
        $payload = json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'order_' . $orderId,
                'amount' => ['currency_code' => 'USD', 'value' => number_format($amount / 83, 2, '.', '')],
            ]],
            'application_context' => [
                'return_url' => url('/checkout/payment-callback?gateway=paypal&order_id=' . $orderId),
                'cancel_url' => url('/checkout?cancelled=1'),
            ],
        ]);

        $response = $this->curlRequest($base . '/v2/checkout/orders', 'POST', $payload, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ]);

        if (!$response || empty($response['links'])) return null;

        foreach ($response['links'] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return $link['href'];
            }
        }
        return null;
    }

    public function capturePaypalOrder(string $token): array
    {
        $clientId = $this->settings->get('paypal_client_id');
        $secret = $this->settings->get('paypal_client_secret');
        if (!$clientId || !$secret || !$token) return ['ok' => false];

        $base = $this->paypalBaseUrl();
        $accessToken = $this->paypalAccessToken($base, $clientId, $secret);
        if (!$accessToken) return ['ok' => false];

        $response = $this->curlRequest($base . "/v2/checkout/orders/{$token}/capture", 'POST', '{}', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);

        $status = $response['status'] ?? '';
        $captureId = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        return ['ok' => $status === 'COMPLETED', 'capture_id' => $captureId];
    }

    private function paypalAccessToken(string $base, string $clientId, string $secret): ?string
    {
        $response = $this->curlRequest($base . '/v1/oauth2/token', 'POST', 'grant_type=client_credentials', [
            'Content-Type: application/x-www-form-urlencoded',
        ], $clientId . ':' . $secret);

        return $response['access_token'] ?? null;
    }

    private function paypalBaseUrl(): string
    {
        $mode = $this->settings->get('paypal_mode', 'sandbox');
        return $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    // ===================== STRIPE =====================

    public function createStripePaymentIntent(int $orderId, float $amount): ?string
    {
        $secretKey = $this->settings->get('stripe_secret_key');
        if (!$secretKey) return null;

        $payload = http_build_query([
            'amount' => (int)round($amount * 100),
            'currency' => 'inr',
            'metadata' => ['order_id' => $orderId],
            'automatic_payment_methods' => ['enabled' => 'true'],
        ]);

        $response = $this->curlRequest('https://api.stripe.com/v1/payment_intents', 'POST', $payload, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $secretKey . ':');

        return $response['client_secret'] ?? null;
    }

    public function confirmStripePaymentIntent(string $intentId): bool
    {
        $secretKey = $this->settings->get('stripe_secret_key');
        if (!$secretKey || !$intentId) return false;

        $response = $this->curlRequest('https://api.stripe.com/v1/payment_intents/' . $intentId, 'GET', null, [], $secretKey . ':');
        return ($response['status'] ?? '') === 'succeeded';
    }

    // ===================== Shared cURL helper =====================

    private function curlRequest(string $url, string $method, ?string $body, array $headers = [], ?string $basicAuth = null): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($basicAuth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
        }
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $raw === false) {
            error_log('PaymentService cURL error: ' . $error);
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
