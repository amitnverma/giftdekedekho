<?php /** "Forgot password" for storefront customers: emails a reset link. */ ?>
<div class="container" style="max-width:480px;padding:50px 20px">
  <h1 style="font-size:24px;margin-bottom:8px">Forgot your password?</h1>
  <p style="color:#6b7280;margin-bottom:24px">Enter the email you log in with, and we will send you a link to choose a new password.</p>

  <form method="post" action="<?= url('/account/forgot-password') ?>">
    <?= csrfField() ?>
    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="username" required autofocus></div>
    <button type="submit" class="btn btn-primary btn-block">Email me a reset link</button>
  </form>
  <p style="text-align:center;margin-top:16px"><a href="<?= url('/account/login') ?>">← Back to login</a></p>
</div>
