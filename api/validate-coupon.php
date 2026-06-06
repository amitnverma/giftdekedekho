<?php
/**
 * POST /api/validate-coupon.php (code, csrf_token)
 * Returns: {"ok": bool, "message": string, "discount": float}
 */
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token.'], 419);
}

$code = strtoupper(trim((string)($_POST['code'] ?? '')));
$cartModel = new Cart();
$subtotal = $cartModel->subtotal($cartModel->items());

$result = (new Coupon())->validate($code, $subtotal);
jsonResponse(['ok' => $result['ok'], 'message' => $result['message'], 'discount' => $result['discount']]);
