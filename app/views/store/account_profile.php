<div class="container account-layout">
  <?php renderRaw('store/partials/account_nav', ['active' => $active]); ?>
  <div>
    <h1>Profile Settings</h1>

    <h3>Personal Information</h3>
    <form method="post" action="<?= url('/account/profile') ?>" style="max-width:480px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update_profile">
      <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
      <div class="form-group"><label>Email</label><input type="email" value="<?= e($user['email']) ?>" disabled></div>
      <div class="form-group"><label>Phone</label><input type="tel" name="phone" pattern="[0-9]{10}" value="<?= e($user['phone'] ?? '') ?>"></div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>

    <h3 style="margin-top:34px">Change Password</h3>
    <form method="post" action="<?= url('/account/profile') ?>" style="max-width:480px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
      <div class="form-group"><label>New Password</label><input type="password" name="new_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
      <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
  </div>
</div>
