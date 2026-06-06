<?php

class CartController extends BaseController
{
    public function index(): void
    {
        $cartModel = new Cart();
        $items = $cartModel->items();
        $subtotal = $cartModel->subtotal($items);
        $shippingModel = new Shipping();

        $discount = 0.0;
        $couponCode = $_SESSION['coupon_code'] ?? null;
        $couponMessage = null;
        if ($couponCode) {
            $result = (new Coupon())->validate($couponCode, $subtotal);
            if ($result['ok']) {
                $discount = $result['discount'];
            } else {
                unset($_SESSION['coupon_code']);
                $couponCode = null;
            }
        }

        $shipping = $shippingModel->calculateCharge($subtotal - $discount);
        $total = max(0, $subtotal - $discount) + $shipping;

        $this->view('cart', [
            'metaTitle' => 'Your Cart | ' . SITE_NAME,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $total,
            'couponCode' => $couponCode,
            'cartModel' => $cartModel,
        ]);
    }

    public function add(): void
    {
        $this->requireCsrf();
        $productModel = new Product();
        $productId = (int)$this->input('product_id');
        $quantity = max(1, (int)$this->input('quantity', 1));
        $product = $productModel->find($productId);

        if (!$product || !$product['is_active']) {
            flash('error', 'This product is not available.');
            redirect('/cart');
        }
        if ((int)$product['stock_qty'] < $quantity) {
            flash('error', 'Insufficient stock for this product.');
            redirect('/product/' . $product['slug']);
        }

        $options = $productModel->customizationOptions($productId);
        $customization = [];
        $rawCustom = $_POST['customization'] ?? [];

        foreach ($options as $opt) {
            $entry = $rawCustom[$opt['id']] ?? null;
            $type = $opt['option_type'];

            if ($type === 'photo_upload') {
                if (!empty($_FILES['customization']['name'][$opt['id']]['file'])) {
                    $uploaded = $this->handlePhotoUpload($opt['id']);
                    if ($uploaded) {
                        $customization[] = [
                            'option_id' => (int)$opt['id'],
                            'option_type' => $type,
                            'label' => $opt['label'],
                            'value' => $uploaded,
                            'extra_charge' => (float)$opt['extra_charge'],
                        ];
                    } elseif ($opt['is_required']) {
                        flash('error', 'Please upload a valid photo (JPG/PNG, max 5MB).');
                        redirect('/product/' . $product['slug']);
                    }
                } elseif ($opt['is_required']) {
                    flash('error', 'Please upload a photo for "' . $opt['label'] . '".');
                    redirect('/product/' . $product['slug']);
                }
                continue;
            }

            $value = trim((string)($entry['value'] ?? ''));
            $checked = !empty($entry['checked']);

            if ($type === 'gift_wrap' || $type === 'video_photo') {
                if ($checked) {
                    $customization[] = [
                        'option_id' => (int)$opt['id'],
                        'option_type' => $type,
                        'label' => $opt['label'],
                        'value' => true,
                        'extra_charge' => (float)$opt['extra_charge'],
                    ];
                }
                continue;
            }

            if ($value === '' && $opt['is_required']) {
                flash('error', 'Please fill in "' . $opt['label'] . '".');
                redirect('/product/' . $product['slug']);
            }
            if ($value !== '') {
                if (!empty($opt['char_limit'])) {
                    $value = mb_substr($value, 0, (int)$opt['char_limit']);
                }
                $customization[] = [
                    'option_id' => (int)$opt['id'],
                    'option_type' => $type,
                    'label' => $opt['label'],
                    'value' => strip_tags($value),
                    'extra_charge' => (float)$opt['extra_charge'],
                ];
            }
        }

        (new Cart())->add($productId, $quantity, $customization);
        flash('success', 'Added to cart successfully!');
        redirect('/cart');
    }

    private function handlePhotoUpload(int $optionId): ?string
    {
        $err = $_FILES['customization']['error'][$optionId]['file'] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) return null;

        $tmpName = $_FILES['customization']['tmp_name'][$optionId]['file'];
        $size = $_FILES['customization']['size'][$optionId]['file'];
        if ($size > 5 * 1024 * 1024) return null;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/jpg' => 'jpg'];
        if (!isset($allowed[$mime])) return null;

        $dir = UPLOAD_PATH . '/customizations';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $dest)) return null;

        return UPLOAD_URL . '/customizations/' . $filename;
    }

    public function update(): void
    {
        $this->requireCsrf();
        $cartId = (int)$this->input('cart_id');
        $quantity = max(1, (int)$this->input('quantity', 1));
        (new Cart())->updateQuantity($cartId, $quantity);
        jsonResponse(['ok' => true]);
    }

    public function remove(): void
    {
        $this->requireCsrf();
        $cartId = (int)$this->input('cart_id');
        (new Cart())->removeItem($cartId);
        flash('success', 'Item removed from cart.');
        redirect('/cart');
    }

    public function applyCoupon(): void
    {
        $this->requireCsrf();
        $code = strtoupper(trim($this->input('code', '')));
        $cartModel = new Cart();
        $subtotal = $cartModel->subtotal($cartModel->items());
        $result = (new Coupon())->validate($code, $subtotal);

        if ($result['ok']) {
            $_SESSION['coupon_code'] = $code;
        } else {
            unset($_SESSION['coupon_code']);
        }
        jsonResponse(['ok' => $result['ok'], 'message' => $result['message'], 'discount' => $result['discount']]);
    }
}
