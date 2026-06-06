<?php
/**
 * POST /api/wishlist-toggle.php  (product_id, csrf_token)
 * Returns: {"added": bool} or {"requires_login": true}
 */
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    jsonResponse(['requires_login' => true]);
}

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token.'], 419);
}

$productId = (int)($_POST['product_id'] ?? 0);
if ($productId <= 0) {
    jsonResponse(['error' => 'Invalid product.'], 422);
}

$added = (new Wishlist())->toggle(currentUserId(), $productId);
jsonResponse(['added' => $added]);
