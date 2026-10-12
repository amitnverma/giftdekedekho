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
            'gateways' => $this->enabledGateways($settings),
        ]);
    }

    /** Gateways the storefront may offer: those with keys configured, plus COD when the admin has switched it on. */
    private function enabledGateways(Settings $settings): array
    {
        $gateways = [];
        if ($settings->get('cod_enabled', '0') === '1') $gateways[] = 'cod';
        if ($settings->get('razorpay_key_id') !== '') $gateways[] = 'razorpay';
        if ($settings->get('paypal_client_id') !== '') $gateways[] = 'paypal';
        if ($settings->get('stripe_publishable_key') !== '') $gateways[] = 'stripe';
        return $gateways;
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

        $gateway = (string)$this->input('payment_gateway', '');
        if (!in_array($gateway, $this->enabledGateways(new Settings()), true)) {
            flash('error', 'Please choose an available payment method.');
            redirect('/checkout');
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
            $orderItemId = $orderModel->addItem([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['name'],
                'product_image_snapshot' => $item['thumbnail'],
                'unit_price' => $price,
                'quantity' => $item['quantity'],
                'customization_json' => $item['customization_json'],
            ]);
            $productModel->decrementStock((int)$item['product_id'], (int)$item['quantity']);
            $this->createArFrames($orderItemId, $item['customization_json'] ?? null);
        }

        if ($coupon) {
            (new Coupon())->incrementUsage((int)$coupon['id']);
        }

        if ($gateway === 'cod') {
            $orderModel->updateStatus($orderId, 'confirmed');
            $cartModel->clear();
            unset($_SESSION['coupon_code']);
            $this->sendOrderNotifications($orderId);
            redirect('/order/confirmation/' . $orderId);
        }

        $cartModel->clear();
        unset($_SESSION['coupon_code']);
        $this->startPayment($orderId, $gateway, $total);
    }

    /** Opens the gateway's payment step for an unpaid order: renders its page or redirects. */
    private function startPayment(int $orderId, string $gateway, float $total): void
    {
        require_once APP_PATH . '/services/PaymentService.php';
        $paymentService = new PaymentService();
        $_SESSION['pending_order_id'] = $orderId;

        switch ($gateway) {
            case 'razorpay':
                $this->view('checkout_razorpay', [
                    'metaTitle' => 'Complete Payment | ' . SITE_NAME,
                    'orderId' => $orderId,
                    'rzpOrder' => $paymentService->createRazorpayOrder($orderId, $total),
                    'total' => $total,
                    'keyId' => (new Settings())->get('razorpay_key_id'),
                ]);
                return;

            case 'paypal':
                $approveUrl = $paymentService->createPaypalOrder($orderId, $total);
                if ($approveUrl) {
                    // Absolute PayPal URL: redirect() would prefix our own site URL.
                    header('Location: ' . $approveUrl);
                    exit;
                }
                flash('error', 'We could not open PayPal right now. Your order is saved — please try paying again shortly.');
                redirect('/order/confirmation/' . $orderId);
                return;

            case 'stripe':
                $this->view('checkout_stripe', [
                    'metaTitle' => 'Complete Payment | ' . SITE_NAME,
                    'orderId' => $orderId,
                    'clientSecret' => $paymentService->createStripePaymentIntent($orderId, $total),
                    'total' => $total,
                    'publishableKey' => (new Settings())->get('stripe_publishable_key'),
                ]);
                return;
        }
        redirect('/order/confirmation/' . $orderId);
    }

    /** Pay-later link from the "payment pending" email, the confirmation page or My Orders. */
    public function pay(int $orderId): void
    {
        require_once APP_PATH . '/services/PaymentService.php';
        $order = (new Order())->find($orderId);
        $allowed = $order && (
            (new PaymentService())->verifyPayLink($orderId, (string)($_GET['t'] ?? ''))
            || (int)($_SESSION['pending_order_id'] ?? 0) === $orderId
            || (isLoggedIn() && (int)$order['user_id'] === currentUserId())
        );
        if (!$allowed) {
            http_response_code(404);
            (new PageController())->notFound();
            return;
        }
        if (!$this->awaitingPayment($order)) {
            redirect('/order/confirmation/' . $orderId);
        }
        $this->startPayment($orderId, (string)$order['payment_gateway'], (float)$order['total']);
    }

    /** "Skip for now" on the payment page: keep the order unpaid and email the customer a link to pay. */
    public function skipPayment(int $orderId): void
    {
        $this->requireCsrf();
        $order = (new Order())->find($orderId);
        if (!$order || (int)($_SESSION['pending_order_id'] ?? 0) !== $orderId) {
            redirect('/');
        }
        if ($this->awaitingPayment($order) && empty($_SESSION['payment_link_sent'][$orderId])) {
            $_SESSION['payment_link_sent'][$orderId] = true;
            try {
                require_once APP_PATH . '/services/NotificationService.php';
                (new NotificationService())->sendPaymentPending($orderId);
            } catch (Throwable $e) {
                error_log('Payment pending notification error: ' . $e->getMessage());
            }
        }
        redirect('/order/confirmation/' . $orderId);
    }

    /** An online-payment order that has not been paid (or cancelled) yet. */
    private function awaitingPayment(array $order): bool
    {
        return $order['payment_gateway'] !== 'cod'
            && in_array($order['payment_status'], ['pending', 'failed'], true)
            && !in_array($order['order_status'], ['cancelled', 'refunded'], true);
    }

    public function paymentCallback(): void
    {
        // The posted order wins over the session: a pay-later link may be used in a
        // browser that still remembers a different pending order.
        $orderId = (int)($this->input('order_id') ?: ($_SESSION['pending_order_id'] ?? 0));
        $orderModel = new Order();
        $order = $orderId ? $orderModel->find($orderId) : null;
        if (!$order) {
            redirect('/');
        }
        if ($order['payment_status'] === 'paid') {
            redirect('/order/confirmation/' . $orderId);
        }
        $gateway = $this->input('gateway', '');
        require_once APP_PATH . '/services/PaymentService.php';
        $paymentService = new PaymentService();

        $verified = false;
        $reference = null;

        if ($gateway === 'razorpay') {
            $rzpOrderId = (string)$this->input('razorpay_order_id');
            $verified = $paymentService->verifyRazorpaySignature(
                $rzpOrderId,
                (string)$this->input('razorpay_payment_id'),
                (string)$this->input('razorpay_signature')
            ) && $paymentService->razorpayOrderBelongsTo($rzpOrderId, $orderId);
            $reference = $this->input('razorpay_payment_id');
        } elseif ($gateway === 'paypal') {
            $token = $this->input('token');
            $result = $paymentService->capturePaypalOrder($token);
            $verified = $result['ok'] ?? false;
            $reference = $result['capture_id'] ?? $token;
        } elseif ($gateway === 'stripe') {
            $intentId = (string)$this->input('payment_intent');
            $verified = $paymentService->confirmStripePaymentIntent($intentId, $orderId);
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

        $paymentPending = $this->awaitingPayment($order);
        $payLink = null;
        if ($paymentPending) {
            require_once APP_PATH . '/services/PaymentService.php';
            $payLink = (new PaymentService())->payLink($orderId);
        }
        $this->view('order_confirmation', [
            'metaTitle' => ($paymentPending ? 'Payment Pending' : 'Order Confirmed') . ' | ' . SITE_NAME,
            'order' => $order,
            'paymentPending' => $paymentPending,
            'payLink' => $payLink,
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

    /**
     * Queue a Living Photo AR frame for any ar_frame customization on this line
     * item. The row starts at 'pending_setup'; an admin generates the target and
     * runs the live scan test from the AR Frames queue. Target generation is
     * deliberately not done here — the customer isn't waiting, and a ~5s compile
     * has no business sitting inside a checkout request.
     */
    /** Frames created by this request, waiting for their targets to be compiled. */
    private array $newArFrameIds = [];

    private function createArFrames(int $orderItemId, ?string $customizationJson): void
    {
        if (empty($customizationJson)) {
            return;
        }

        try {
            $customization = json_decode($customizationJson, true);
            if (!is_array($customization)) {
                return;
            }

            require_once APP_PATH . '/services/ArFrameService.php';
            $arService = new ArFrameService();

            foreach ($customization as $entry) {
                if (($entry['option_type'] ?? '') !== 'ar_frame') {
                    continue;
                }

                // Photos and videos the customer uploaded from the product page.
                if (!empty($entry['items']) && is_array($entry['items'])) {
                    $items = [];
                    foreach ($entry['items'] as $pair) {
                        if (!empty($pair['photo']) && !empty($pair['video'])) {
                            $items[] = ['photo_path' => $pair['photo'], 'video_type' => 'upload', 'video_path' => $pair['video']];
                        }
                    }
                    if (!empty($items)) {
                        $this->queueTargetGeneration(
                            $arService->createFrame(['channel' => 'online', 'order_item_id' => $orderItemId], $items)
                        );
                    }
                    continue;
                }

                // Carts filled before uploads existed: one photo and a YouTube link.
                if (empty($entry['photo'])) {
                    continue;
                }
                $this->queueTargetGeneration($arService->createFrame([
                    'channel' => 'online',
                    'order_item_id' => $orderItemId,
                    'photo_path' => $entry['photo'],
                    'video_type' => 'youtube',
                    'video_url' => $entry['value'] ?? null,
                ]));
            }
        } catch (Throwable $e) {
            // A frame can always be created by hand from the admin queue, so
            // this must never take an otherwise-valid order down with it.
            error_log('AR frame creation error: ' . $e->getMessage());
        }
    }

    /**
     * Compile the targets for the frames this order created, once the customer's
     * page has been sent.
     *
     * A photo is only scannable after its target is compiled — until then the
     * sticker says the Living Photo is "still being prepared". Doing it here is
     * what makes an online order behave like one built at the counter, where
     * Quick Create compiles on the spot.
     *
     * It costs seconds per photo, so it runs from the shutdown handler, after
     * the response has been handed back on servers that allow it (nginx/PHP-FPM
     * does). Checkout itself never waits. If the host cannot detach, or a
     * compile fails, the frame simply stays in the admin queue with its
     * "Generate targets" button, and tools/ar-generate-pending.php catches it.
     */
    private function queueTargetGeneration(int $frameId): void
    {
        if ($frameId <= 0) {
            return;
        }

        $this->newArFrameIds[] = $frameId;
        if (count($this->newArFrameIds) > 1) {
            return; // the handler registered for the first frame takes them all
        }

        register_shutdown_function(function (): void {
            $ids = $this->newArFrameIds;
            $this->newArFrameIds = [];
            if (!$ids) {
                return;
            }

            @ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                @litespeed_finish_request();
            }
            @set_time_limit(0);

            try {
                require_once APP_PATH . '/services/ArFrameService.php';
                $service = new ArFrameService();
                foreach ($ids as $id) {
                    $result = $service->generateTarget((int)$id, true);
                    if (empty($result['ok'])) {
                        error_log('AR target generation for frame ' . $id . ' failed: ' . ($result['error'] ?? 'unknown error'));
                    }
                }
            } catch (Throwable $e) {
                // The order is already placed; a frame without a target is a
                // queue item for the admin, not a failed purchase.
                error_log('AR target generation error: ' . $e->getMessage());
            }
        });
    }

    private function sendOrderNotifications(int $orderId): void
    {
        try {
            require_once APP_PATH . '/services/NotificationService.php';
            $notifications = new NotificationService();
            $notifications->sendOrderConfirmed($orderId);
            $notifications->sendAdminNewOrder($orderId);
        } catch (Throwable $e) {
            // Notification failures must not block order placement
            error_log('Notification error: ' . $e->getMessage());
        }
    }
}
