<div class="container" style="max-width:500px;padding:60px 20px">
  <h1>Complete Your Payment</h1>
  <p>Order #<?= (int)$orderId ?> — Amount: <?= formatPrice($total) ?></p>
  <form id="stripePaymentForm">
    <div id="payment-element" style="margin:20px 0;padding:14px;border:1px solid var(--color-border);border-radius:8px"></div>
    <button id="stripeSubmitBtn" class="btn btn-primary btn-block">Pay Now</button>
    <div id="stripeError" style="color:#b3261e;margin-top:10px"></div>
  </form>
  <p style="margin-top:20px;text-align:center"><a href="<?= url('/order/confirmation/' . $orderId) ?>">Skip for now</a></p>
</div>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  var clientSecret = <?= json_encode($clientSecret) ?>;
  if (!clientSecret) return;
  var stripe = Stripe(<?= json_encode($publishableKey) ?>);
  var elements = stripe.elements({ clientSecret: clientSecret });
  var paymentElement = elements.create('payment');
  paymentElement.mount('#payment-element');

  document.getElementById('stripePaymentForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('stripeSubmitBtn');
    btn.disabled = true;
    stripe.confirmPayment({
      elements: elements,
      confirmParams: { return_url: window.GDD_BASE_URL + '/checkout/payment-callback?gateway=stripe&order_id=<?= (int)$orderId ?>' },
      redirect: 'if_required'
    }).then(function (result) {
      btn.disabled = false;
      if (result.error) {
        document.getElementById('stripeError').textContent = result.error.message;
      } else if (result.paymentIntent) {
        window.location.href = window.GDD_BASE_URL + '/checkout/payment-callback?gateway=stripe&order_id=<?= (int)$orderId ?>&payment_intent=' + result.paymentIntent.id;
      }
    });
  });
})();
</script>
