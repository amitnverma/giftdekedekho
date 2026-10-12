<div class="container" style="text-align:center;padding:60px 20px">
  <h1>Complete Your Payment</h1>
  <p>Order #<?= (int)$orderId ?> — Amount: <?= formatPrice($total) ?></p>
  <button id="rzpPayBtn" class="btn btn-primary">Pay Now</button>
  <form method="post" action="<?= url('/order/skip-payment/' . $orderId) ?>" style="margin-top:20px"><?= csrfField() ?><button type="submit" style="background:none;border:0;padding:0;font:inherit;color:inherit;cursor:pointer;text-decoration:underline">Skip for now — pay later</button></form>
  <p style="font-size:13px;color:var(--color-muted)">Your order stays on hold until it is paid. We will email you a link to complete payment.</p>
</div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('rzpPayBtn').addEventListener('click', function () {
  var options = {
    key: <?= json_encode($keyId) ?>,
    amount: <?= (int)round($total * 100) ?>,
    currency: 'INR',
    name: <?= json_encode(SITE_NAME) ?>,
    description: 'Order #<?= (int)$orderId ?>',
    order_id: <?= json_encode($rzpOrder['id'] ?? '') ?>,
    handler: function (response) {
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = window.GDD_BASE_URL + '/checkout/payment-callback';
      var fields = {
        gateway: 'razorpay',
        order_id: <?= json_encode($orderId) ?>,
        razorpay_order_id: response.razorpay_order_id,
        razorpay_payment_id: response.razorpay_payment_id,
        razorpay_signature: response.razorpay_signature,
        csrf_token: gddCsrf()
      };
      Object.keys(fields).forEach(function (k) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = k; input.value = fields[k];
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    },
    theme: { color: '#e63946' }
  };
  var rzp = new Razorpay(options);
  rzp.open();
});
</script>
