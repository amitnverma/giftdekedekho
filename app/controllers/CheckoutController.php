<?php

class CheckoutController extends BaseController
{
    public function index(): void
    {
        $cartModel = new Cart();
        $items = $cartModel->items();
        if (empty($items)) {
            redirect('/cart');
        }

        $subtotal = $cartModel->subtotal($items);
        $discount = 0.0;
        $couponCode = $_SESSION['coupon_code'] ?? null;
        if ($couponCode) {
            $result = (new Coupon())->validate($couponCode, $subtotal);
            $discount = $result['ok'] ? $result['discount'] : 0;
        }
        $shippingModel = new Shipping();
        $shipping = $shippingModel->calculateCharge($subtotal - $discount);
        $total = max(0, $subtotal - $discount) + $shipping;

        $addresses = [];
        if (isLoggedIn()) {
            $addresses = (new Address())->forUser(currentUserId());
        }

        $settings = new Settings();

        $this->view('checkout', [
            'metaTitle' => 'Checkout | ' . SITE_NAME,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $total,
            'couponCode' => $couponCode,
            'addresses' => $addresses,
            'razorpayKeyId' => $settings->get('razorpay_key_id'),
            'paypalClientId' => $settings->get('paypal_client_id'),
            'stripePublishableKey' => $settings->get('stripe_publishable_key'),
        ]);
    }

    public function placeOrder(): void
    {
        $this->requireCsrf();
        $cartModel = new Cart();
        $items = $cartModel->items();
        if (empty($items)) {
            flash('error', 'Your cart is empty.');
            redirect('/cart');
        }

        // ---- Resolve address ----
        $addressSnapshot = $this->resolveAddressSnapshot();
        if (!$addressSnapshot) {
            flash('error', 'Please provide a valid delivery address.');
            redirect('/checkout');
        }

        $guestEmail = $this->input('guest_email');
        $guestPhone = $this->input('guest_phone');
        if (!isLoggedIn() && (empty($guestEmail) || empty($guestPhone))) {
            flash('error', 'Please provide your email and phone number for guest checkout.');
            redirect('/checkout');
        }

        $subtotal = $cartModel->subtotal($items);
        $discount = 0.0;
        $couponCode = $_SESSION['coupon_code'] ?? null;
        $coupon = null;
        if ($couponCode) {
            $result = (new Coupon())->validate($couponCode, $subtotal);
            if ($result['ok']) {
                $discount = $result['discount'];
                $coupon = $result['coupon'];
            }
        }
        $shippingModel = new Shipping();
        $shipping = $shippingModel->calculateCharge($subtotal - $discount);
        $total = max(0, $subtotal - $discount) + $shipping;

        $gateway = $this->input('payment_gateway', 'cod');
        if (!in_array($gateway, ['razorpay', 'paypal', 'stripe', 'cod'], true)) {
            $gateway = 'cod';
        }

        $orderModel = new Order();
        $orderId = $orderModel->create([
            'user_id' => currentUserId(),
            'guest_email' => isLoggedIn() ? null : $guestEmail,
            'guest_phone' => isLoggedIn() ? null : $guestPhone,
            'address_snapshot_json' => json_encode($addressSnapshot),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_charge' => $shipping,
            'total' => $total,
            'payment_gateway' => $gateway,
            'payment_status' => $gateway === 'cod' ? 'pending' : 'pending',
            'order_status' => 'pending',
            'coupon_id' => $coupon['id'] ?? null,
        ]);

        $productModel = new Product();
        foreach ($items as $item) {
            $price = $item['sale_price'] !== null ? (float)$item['sale_price'] : (float)$item['base_price'];
            $orderModel->addItem([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['name'],
                'product_image_snapshot' => $item['thumbnail'],
                'unit_price' => $price,
                'quantity' => $item['quantity'],
                'customization_json' => $item['customization_json'],
            ]);
            $productModel->decrementStock((int)$item['product_id'], (int)$item['quantity']);
        }

        if ($coupon) {
            (new Coupon())->incrementUsage((int)$coupon['id']);
        }

        // ---- Payment gateway dispatch ----
        require_once APP_PATH . '/services/PaymentService.php';
        $paymentService = new PaymentService();

        switch ($gateway) {
            case 'razorpay':
                $rzpOrder = $paymentService->createRazorpayOrder($orderId, $total);
                $_SESSION['pending_order_id'] = $orderId;
                $cartModel->clear();
                unset($_SESSION['coupon_code']);
                $this->view('checkout_razorpay', [
                    'metaTitle' => 'Complete Payment | ' . SITE_NAME,
                    'orderId' => $orderId,
                    'rzpOrder' => $rzpOrder,
                    'total' => $total,
                    'keyId' => (new Settings())->get('razorpay_key_id'),
                ]);
                return;

            case 'paypal':
                $approveUrl = $paymentService->createPaypalOrder($orderId, $total);
                $_SESSION['pending_order_id'] = $orderId;
                $cartModel->clear();
                unset($_SESSION['coupon_code']);
                if ($approveUrl) {
                    redirect($approveUrl);
                }
                redirect('/order/confirmation/' . $orderId);
                return;

            case 'stripe':
                $clientSecret = $paymentService->createStripePaymentIntent($orderId, $total);
                $_SESSION['pending_order_id'] = $orderId;
                $cartModel->clear();
                unset($_SESSION['coupon_code']);
                $this->view('checkout_stripe', [
                    'metaTitle' => 'Complete Payment | ' . SITE_NAME,
                    'orderId' => $orderId,
                    'clientSecret' => $clientSecret,
                    'total' => $total,
                    'publishableKey' => (new Settings())->get('stripe_publishable_key'),
                ]);
                return;

            default: // COD
                $orderModel->updateStatus($orderId, 'confirmed');
                $cartModel->clear();
                unset($_SESSION['coupon_code']);
                $this->sendOrderNotifications($orderId);
                redirect('/order/confirmation/' . $orderId);
        }
    }

    public function paymentCallback(): void
    {
        $orderId = (int)($_SESSION['pending_order_id'] ?? $this->input('order_id'));
        if (!$orderId) {
            redirect('/');
        }
        $gateway = $this->input('gateway', '');
        require_once APP_PATH . '/services/PaymentService.php';
        $paymentService = new PaymentService();
        $orderModel = new Order();

        $verified = false;
        $reference = null;

        if ($gateway === 'razorpay') {
            $verified = $paymentService->verifyRazorpaySignature(
                $this->input('razorpay_order_id'),
                $this->input('razorpay_payment_id'),
                $this->input('razorpay_signature')
            );
            $reference = $this->input('razorpay_payment_id');
        } elseif ($gateway === 'paypal') {
            $token = $this->input('token');
            $result = $paymentService->capturePaypalOrder($token);
            $verified = $result['ok'] ?? false;
            $reference = $result['capture_id'] ?? $token;
        } elseif ($gateway === 'stripe') {
            $intentId = $this->input('payment_intent');
            $verified = $paymentService->confirmStripePaymentIntent($intentId);
            $reference = $intentId;
        }

        if ($verified) {
            $orderModel->updatePaymentStatus($orderId, 'paid', $reference);
            $orderModel->updateStatus($orderId, 'confirmed');
            $this->sendOrderNotifications($orderId);
            unset($_SESSION['pending_order_id']);
            redirect('/order/confirmation/' . $orderId);
        }

        flash('error', 'Payment verification failed. If money was deducted, it will be refunded within 5-7 business days.');
        redirect('/order/confirmation/' . $orderId);
    }

    public function confirmation(int $orderId): void
    {
        $orderModel = new Order();
        $order = $orderModel->findWithItems($orderId);
        if (!$order) {
            http_response_code(404);
            (new PageController())->notFound();
            return;
        }
        // Basic ownership guard for logged in users
        if (isLoggedIn() && $order['user_id'] && (int)$order['user_id'] !== currentUserId()) {
            http_response_code(403);
            die('Forbidden');
        }

        $this->view('order_confirmation', [
            'metaTitle' => 'Order Confirmed | ' . SITE_NAME,
            'order' => $order,
        ]);
    }

    private function resolveAddressSnapshot(): ?array
    {
        $addressOption = $this->input('address_option', 'new');

        if ($addressOption === 'saved' && isLoggedIn()) {
            $addressId = (int)$this->input('address_id');
            $addr = (new Address())->find_($addressId, currentUserId());
            if (!$addr) return null;
            return [
                'label' => $addr['label'],
                'address_line1' => $addr['address_line1'],
                'address_line2' => $addr['address_line2'],
                'city' => $addr['city'],
                'state' => $addr['state'],
                'pincode' => $addr['pincode'],
            ];
        }

        $line1 = $this->input('address_line1');
        $line2 = $this->input('address_line2');
        $city = $this->input('city');
        $state = $this->input('state');
        $pincode = $this->input('pincode');
        $name = $this->input('full_name');
        $phone = $this->input('phone');

        if (!$line1 || !$city || !$state || !preg_match('/^\d{6}$/', (string)$pincode) || !$name || !$phone) {
            return null;
        }

        // Save address for logged-in users who opt in
        if (isLoggedIn() && $this->input('save_address')) {
            (new Address())->create(currentUserId(), [
                'label' => 'Home',
                'address_line1' => $line1,
                'address_line2' => $line2,
                'city' => $city,
                'state' => $state,
                'pincode' => $pincode,
                'is_default' => 0,
            ]);
        }

        return [
            'full_name' => $name,
            'phone' => $phone,
            'address_line1' => $line1,
            'address_line2' => $line2,
            'city' => $city,
            'state' => $state,
            'pincode' => $pincode,
        ];
    }

    private function sendOrderNotifications(int $orderId): void
    {
        try {
            require_once APP_PATH . '/services/NotificationService.php';
            (new NotificationService())->sendOrderConfirmed($orderId);
        } catch (Throwable $e) {
            // Notification failures must not block order placement
            error_log('Notification error: ' . $e->getMessage());
        }
    }
}
