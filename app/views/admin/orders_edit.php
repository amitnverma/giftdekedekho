<div class="admin-flex-between" style="margin-bottom:18px;">
    <a href="<?= url('/admin/orders/' . $order['id']) ?>" class="admin-btn admin-btn-sm">&larr; Back to Order #<?= (int)$order['id'] ?></a>
</div>

<form method="post" action="<?= url('/admin/orders/' . $order['id'] . '/edit') ?>" class="admin-form" id="orderEditForm">
    <?= csrfField() ?>

    <div class="admin-grid admin-grid-2" style="align-items:start;">
        <div class="admin-card">
            <h2 class="admin-card-title">Customer &amp; Shipping</h2>
            <?php if ($account): ?>
                <p class="admin-help" style="margin-bottom:14px;">
                    Placed by registered customer <strong><?= e($account['name']) ?></strong> (<?= e($account['email']) ?>).
                    Order emails go to their account email — change that from
                    <a href="<?= url('/admin/customers') ?>">Customers</a>.
                </p>
            <?php endif; ?>
            <div class="admin-form-row">
                <label>Full Name
                    <input type="text" name="full_name" maxlength="120" value="<?= e($address['full_name'] ?? '') ?>">
                </label>
                <label>Phone (shipping)
                    <input type="text" name="phone" maxlength="15" value="<?= e($address['phone'] ?? '') ?>">
                </label>
            </div>
            <div class="admin-form-row">
                <label>Contact Email<?= $account ? ' (guest field)' : '' ?>
                    <input type="email" name="guest_email" maxlength="180" value="<?= e($order['guest_email'] ?? '') ?>">
                </label>
                <label>Contact Phone<?= $account ? ' (guest field)' : '' ?>
                    <input type="text" name="guest_phone" maxlength="15" value="<?= e($order['guest_phone'] ?? '') ?>">
                </label>
            </div>
            <label>Address Line 1
                <input type="text" name="address_line1" maxlength="255" value="<?= e($address['address_line1'] ?? '') ?>">
            </label>
            <label>Address Line 2
                <input type="text" name="address_line2" maxlength="255" value="<?= e($address['address_line2'] ?? '') ?>">
            </label>
            <div class="admin-form-row">
                <label>City
                    <input type="text" name="city" maxlength="100" value="<?= e($address['city'] ?? '') ?>">
                </label>
                <label>State
                    <input type="text" name="state" maxlength="100" value="<?= e($address['state'] ?? '') ?>">
                </label>
                <label>Pincode
                    <input type="text" name="pincode" maxlength="6" pattern="\d{6}" value="<?= e($address['pincode'] ?? '') ?>">
                </label>
            </div>
            <label>Notes
                <textarea name="notes" rows="3"><?= e($order['notes'] ?? '') ?></textarea>
            </label>
        </div>

        <div class="admin-card">
            <h2 class="admin-card-title">Payment</h2>
            <div class="admin-form-row">
                <label>Method
                    <select name="payment_gateway">
                        <?php foreach (['razorpay', 'paypal', 'stripe', 'cod'] as $gw): ?>
                            <option value="<?= $gw ?>" <?= $order['payment_gateway'] === $gw ? 'selected' : '' ?>><?= strtoupper($gw) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Payment Status
                    <select name="payment_status">
                        <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $ps): ?>
                            <option value="<?= $ps ?>" <?= $order['payment_status'] === $ps ? 'selected' : '' ?>><?= ucfirst($ps) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Payment Reference
                <input type="text" name="payment_reference" maxlength="255" value="<?= e($order['payment_reference'] ?? '') ?>">
            </label>
            <p class="admin-help">
                Order status is changed from the order page, since that sends the customer an email.
                Saving here sends nothing.
            </p>
        </div>
    </div>

    <div class="admin-card admin-mt">
        <h2 class="admin-card-title">Items</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th style="width:110px;">Qty</th><th style="width:150px;">Unit Price</th><th style="width:130px;">Line Total</th></tr></thead>
                <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['product_name_snapshot']) ?></td>
                        <td><input type="number" min="1" step="1" name="items[<?= (int)$item['id'] ?>][quantity]" value="<?= (int)$item['quantity'] ?>" data-calc required></td>
                        <td><input type="number" min="0" step="0.01" name="items[<?= (int)$item['id'] ?>][unit_price]" value="<?= e(number_format((float)$item['unit_price'], 2, '.', '')) ?>" data-calc required></td>
                        <td data-line-total></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card admin-mt" style="max-width:380px;">
        <h2 class="admin-card-title">Order Summary</h2>
        <p class="admin-flex-between"><span>Subtotal</span> <strong id="calcSubtotal"></strong></p>
        <label>Discount
            <input type="number" min="0" step="0.01" name="discount" value="<?= e(number_format((float)$order['discount'], 2, '.', '')) ?>" data-calc>
        </label>
        <label>Shipping
            <input type="number" min="0" step="0.01" name="shipping_charge" value="<?= e(number_format((float)$order['shipping_charge'], 2, '.', '')) ?>" data-calc>
        </label>
        <hr>
        <p class="admin-flex-between" style="font-size:18px;"><span>Total</span> <strong id="calcTotal"></strong></p>
        <p class="admin-help">Subtotal and total are recalculated from the items when you save.</p>
    </div>

    <div class="admin-mt">
        <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
        <a href="<?= url('/admin/orders/' . $order['id']) ?>" class="admin-btn">Cancel</a>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('orderEditForm');
    var sym = <?= json_encode(GDD_CURRENCY_SYMBOL) ?>;
    function money(n) { return sym + n.toFixed(2); }
    function num(el) { var v = parseFloat(el.value); return isNaN(v) ? 0 : v; }
    function recalc() {
        var subtotal = 0;
        form.querySelectorAll('tbody tr').forEach(function (tr) {
            var inputs = tr.querySelectorAll('input');
            var line = Math.max(1, Math.floor(num(inputs[0]))) * Math.max(0, num(inputs[1]));
            subtotal += line;
            tr.querySelector('[data-line-total]').textContent = money(line);
        });
        var discount = Math.min(Math.max(0, num(form.discount)), subtotal);
        var shipping = Math.max(0, num(form.shipping_charge));
        document.getElementById('calcSubtotal').textContent = money(subtotal);
        document.getElementById('calcTotal').textContent = money(subtotal - discount + shipping);
    }
    form.addEventListener('input', function (e) { if (e.target.hasAttribute('data-calc')) recalc(); });
    recalc();
})();
</script>
