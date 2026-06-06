<?php
/**
 * GET /api/check-pincode.php?pincode=XXXXXX
 * Returns: {"serviceable": bool, "estimated_days": int}
 */
require __DIR__ . '/bootstrap.php';

$pincode = trim((string)($_GET['pincode'] ?? ''));

if (!preg_match('/^\d{6}$/', $pincode)) {
    jsonResponse(['error' => 'Invalid pincode format.'], 422);
}

$result = (new Shipping())->checkPincode($pincode);
jsonResponse($result);
