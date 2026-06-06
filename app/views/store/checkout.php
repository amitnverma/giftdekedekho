<?php $cartModel = new Cart(); ?>
<div class="container">
  <h1 class="page-title">Checkout</h1>
  <form action="<?= url('/checkout/place-order') ?>" method="post" id="checkoutForm">
    <?= csrfField() ?>
    <div class="checkout-layout">
      <div>
        <h3>Delivery Address</h3>

        <?php if (!empty($addresses)): ?>
          <div id="savedAddresses">
            <?php foreach ($addresses as $i => $addr): ?>
              <label class="address-card <?= $i === 0 ? 'selected' : '' ?>">
                <input type="radio" name="address_option" value="saved" data-address-id="<?= (int)$addr['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> style="margin-right:8px">
                <strong><?= e($addr['label']) ?></strong> — <?= e($addr['address_line1']) ?>, <?= e($addr['city']) ?>, <?= e($addr['state']) ?> - <?= e($addr['pincode']) ?>
              </label>
            <?php endforeach; ?>
            <input type="hidden" name="address_id" id="selectedAddressId" value="<?= (int)$addresses[0]['id'] ?>">
          </div>
          <label class="address-card" style="font-weight:600">
            <input type="radio" name="address_option" value="new" style="margin-right:8px"> Use a new address
          </label>
        <?php endif; ?>

        <div id="newAddressForm" style="<?= !empty($addresses) ? 'display:none' : '' ?>">
          <?php if (!isLoggedIn()): ?>
            <div class="form-row">
              <div class="form-group"><label>Email *</label><input type="email" name="guest_email" required></div>
              <div class="form-group"><label>Phone *</label><input type="tel" name="guest_phone" pattern="[0-9]{10}" required></div>
            </div>
          <?php endif; ?>
          <div class="form-row">
            <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
            <div class="form-group"><label>Phone *</label><input type="tel" name="phone" pattern="[0-9]{10}" required></div>
          </div>
          <div class="form-group"><label>Address Line 1 *</label><input type="text" name="address_line1" required></div>
          <div class="form-group"><label>Address Line 2</label><input type="text" name="address_line2"></div>
          <div class="form-row">
            <div class="form-group"><label>City *</label><input type="text" name="city" required></div>
            <div class="form-group"><label>State *</label><input type="text" name="state" required></div>
          </div>
          <div class="form-group"><label>Pincode *</label><input type="text" name="pincode" pattern="[0-9]{6}" maxlength="6" required></div>
          <?php if (isLoggedIn()): ?>
            <label style="font-weight:400"><input type="checkbox" name="save_address" value="1"> Save this address for future orders</label>
          <?php endif; ?>
        </div>

        <h3 style="margin-top:30px">Payment Method</h3>
        <div class="payment-options">
          <label class="payment-option selected">
            <input type="radio" name="payment_gateway" value="cod" checked> Cash on Delivery
          </label>
          <?php if (!empty($razorpayKeyId)): ?>
          <label class="payment-option">
            <input type="radio" name="payment_gateway" value="razorpay"> Razorpay (Cards / UPI / Netbanking)
          </label>
          <?php endif; ?>
          <?php if (!empty($paypalClientId)): ?>
          <label class="payment-option">
            <input type="radio" name="payment_gateway" value="paypal"> PayPal
          </label>
          <?php endif; ?>
          <?php if (!empty($stripePublishableKey)): ?>
          <label class="payment-option">
            <input type="radio" name="payment_gateway" value="stripe"> Credit / Debit Card (Stripe)
          </label>
          <?php endif; ?>
        </div>
      </div>

      <div class="order-summary">
        <h3>Order Summary</h3>
        <?php foreach ($items as $item): ?>
          <div class="row"><span><?= e($item['name']) ?> × <?= (int)$item['quantity'] ?></span><span><?= formatPrice($cartModel->lineTotal($item) ?? 0) ?></span></div>
        <?php endforeach; ?>
        <div class="row"><span>Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
        <?php if ($discount > 0): ?><div class="row" style="color:#1b7a43"><span>Discount</span><span>−<?= formatPrice($discount) ?></span></div><?php endif; ?>
        <div class="row"><span>Shipping</span><span><?= $shipping > 0 ? formatPrice($shipping) : 'FREE' ?></span></div>
        <div class="row total-row"><span>Total</span><span><?= formatPrice($total) ?></span></div>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top:14px">Place Order</button>
      </div>
    </div>
  </form>
</div>

<script>
(function(){
  var radios = document.querySelectorAll('input[name="address_option"]');
  var newForm = document.getElementById('newAddressForm');
  var savedWrap = document.getElementById('savedAddresses');
  var hiddenAddrId = document.getElementById('selectedAddressId');
  radios.forEach(function(r){
    r.addEventListener('change', function(){
      document.querySelectorAll('.address-card').forEach(function(c){ c.classList.remove('selected'); });
      r.closest('.address-card').classList.add('selected');
      if (r.value === 'new') { newForm.style.display = ''; }
      else {
        newForm.style.display = 'none';
        if (hiddenAddrId) hiddenAddrId.value = r.getAttribute('data-address-id');
      }
    });
  });
  document.querySelectorAll('.payment-option').forEach(function(opt){
    opt.addEventListener('click', function(){
      document.querySelectorAll('.payment-option').forEach(function(o){ o.classList.remove('selected'); });
      opt.classList.add('selected');
      opt.querySelector('input').checked = true;
    });
  });
})();
</script>
