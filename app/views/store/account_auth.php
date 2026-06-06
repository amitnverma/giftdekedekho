<div class="container" style="max-width:480px;padding:50px 20px">
  <div class="auth-tabs">
    <button class="active" data-auth="login">Login</button>
    <button data-auth="register">Register</button>
  </div>

  <form class="auth-form active" data-auth="login" method="post" action="<?= url('/account/login') ?>">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="login">
    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= old('email') ?>" required></div>
    <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
    <button type="submit" class="btn btn-primary btn-block">Login</button>
  </form>

  <form class="auth-form" data-auth="register" method="post" action="<?= url('/account/register') ?>">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="register">
    <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= old('name') ?>" required></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= old('email') ?>" required></div>
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" pattern="[0-9]{10}" value="<?= old('phone') ?>" required></div>
    <div class="form-group"><label>Password</label><input type="password" name="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
    <div class="form-group"><label>Confirm Password</label><input type="password" name="password_confirm" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
  </form>
</div>
