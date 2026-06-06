<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice #<?= (int)$order['id'] ?> · <?= e(SITE_NAME) ?></title>
<style>
    body { font-family: Arial, sans-serif; color: #2c2f38; padding: 30px; max-width: 800px; margin: 0 auto; }
    h1 { margin: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border: 1px solid #ccc; padding: 8px 10px; font-size: 14px; text-align: left; }
    th { background: #f4f5f8; }
    .right { text-align: right; }
    .totals td { border: none; }
    .print-btn { margin-top: 20px; padding: 10px 18px; }
    @media print { .no-print { display: none; } }
</style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>
    <h1><?= e(siteSetting('site_name', SITE_NAME)) ?></h1>
    <p>Tax Invoice &middot; Order #<?= (int)$order['id'] ?> &middot; <?= date('d M Y', strtotime($order['created_at'])) ?></p>

    <p><strong>Shipping Address:</strong><br>
        <?= e($address['full_name'] ?? '') ?><br>
        <?= e($address['address_line1'] ?? '') ?> <?= e($address['address_line2'] ?? '') ?><br>
        <?= e($address['city'] ?? '') ?>, <?= e($address['state'] ?? '') ?> - <?= e($address['pincode'] ?? '') ?><br>
        Phone: <?= e($address['phone'] ?? '') ?>
    </p>

    <table>
        <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Unit Price</th><th class="right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td><?= e($item['product_name_snapshot']) ?></td>
                <td class="right"><?= (int)$item['quantity'] ?></td>
                <td class="right"><?= formatPrice($item['unit_price']) ?></td>
                <td class="right"><?= formatPrice((float)$item['unit_price'] * (int)$item['quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="right" colspan="3">Subtotal</td><td class="right"><?= formatPrice($order['subtotal']) ?></td></tr>
        <tr><td class="right" colspan="3">Discount</td><td class="right">- <?= formatPrice($order['discount']) ?></td></tr>
        <tr><td class="right" colspan="3">Shipping</td><td class="right"><?= formatPrice($order['shipping_charge']) ?></td></tr>
        <tr><td class="right" colspan="3"><strong>Grand Total</strong></td><td class="right"><strong><?= formatPrice($order['total']) ?></strong></td></tr>
    </table>

    <p>Payment Method: <?= strtoupper($order['payment_gateway']) ?> &middot; Payment Status: <?= ucfirst($order['payment_status']) ?></p>
    <p>Thank you for shopping with <?= e(siteSetting('site_name', SITE_NAME)) ?>!</p>
</body>
</html>
