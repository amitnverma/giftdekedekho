<?php /** Set a new password from an emailed reset link. */ ?>
<div class="container" style="max-width:480px;padding:50px 20px">
  <h1 style="font-size:24px;margin-bottom:8px">Choose a new password</h1>
  <p style="color:#6b7280;margin-bottom:24px">For <strong><?= e($email) ?></strong>.</p>

  <form method="post" action="<?= url('/account/reset-password') ?>">
    <?= csrfField() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" hidden>
    <div class="form-group"><label for="password">New password <small style="color:#6b7280">(at least <?= PASSWORD_MIN_LENGTH ?> characters)</small></label><input type="password" id="password" name="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autofocus></div>
    <div class="form-group"><label for="password_confirm">Repeat new password</label><input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
    <button type="submit" class="btn btn-primary btn-block">Save new password</button>
  </form>
</div>
