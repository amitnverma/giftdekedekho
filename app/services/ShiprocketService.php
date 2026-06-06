<?php
/**
 * Minimal Shiprocket API integration: authenticates and creates a shipment
 * for a confirmed order, storing the shipment id + tracking URL back on the order.
 * Credentials are read from admin settings (shiprocket_email / shiprocket_password).
 */
class ShiprocketService
{
    private Settings $settings;
    private const BASE_URL = 'https://apiv2.shiprocket.in/v1/external';

    public function __construct()
    {
        $this->settings = new Settings();
    }

    private function token(): ?string
    {
        $email = $this->settings->get('shiprocket_email');
        $password = $this->settings->get('shiprocket_password');
        if (!$email || !$password) return null;

        $response = $this->request('/auth/login', 'POST', [
            'email' => $email,
            'password' => $password,
        ]);

        return $response['token'] ?? null;
    }

    /**
     * Create a shipment for an order and persist shiprocket_order_id + tracking info.
     */
    public function createShipmentForOrder(int $orderId): bool
    {
        $token = $this->token();
        if (!$token) return false;

        $orderModel = new Order();
        $order = $orderModel->findWithItems($orderId);
        if (!$order) return false;

        $addr = json_decode($order['address_snapshot_json'], true) ?: [];
        $items = [];
        foreach ($order['items'] as $item) {
            $items[] = [
                'name' => $item['product_name_snapshot'],
                'sku' => 'ITEM-' . $item['id'],
                'units' => (int)$item['quantity'],
                'selling_price' => (float)$item['unit_price'],
            ];
        }

        $payload = [
            'order_id' => 'GDD-' . $orderId,
            'order_date' => date('Y-m-d H:i', strtotime($order['created_at'])),
            'pickup_location' => 'Primary',
            'billing_customer_name' => $addr['full_name'] ?? 'Customer',
            'billing_address' => $addr['address_line1'] ?? '',
            'billing_city' => $addr['city'] ?? '',
            'billing_pincode' => $addr['pincode'] ?? '',
            'billing_state' => $addr['state'] ?? '',
            'billing_country' => 'India',
            'billing_email' => $order['guest_email'] ?? 'guest@giftdekedekho.com',
            'billing_phone' => $addr['phone'] ?? $order['guest_phone'] ?? '',
            'shipping_is_billing' => true,
            'order_items' => $items,
            'payment_method' => $order['payment_gateway'] === 'cod' ? 'COD' : 'Prepaid',
            'sub_total' => (float)$order['subtotal'],
            'length' => 10, 'breadth' => 10, 'height' => 10, 'weight' => 0.5,
        ];

        $response = $this->request('/orders/create/adhoc', 'POST', $payload, $token);
        if (empty($response['order_id'])) return false;

        $shipmentId = (string)$response['order_id'];
        $trackingUrl = 'https://shiprocket.co/tracking/' . $shipmentId;

        $orderModel->setShiprocketInfo($orderId, $shipmentId, $trackingUrl);

        return true;
    }

    private function request(string $path, string $method, array $body, ?string $token = null): ?array
    {
        $headers = ['Content-Type: application/json'];
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;

        $ch = curl_init(self::BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $raw === false) {
            error_log('Shiprocket error: ' . $err);
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
