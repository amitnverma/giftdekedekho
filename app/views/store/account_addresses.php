<div class="container account-layout">
  <?php renderRaw('store/partials/account_nav', ['active' => $active]); ?>
  <div>
    <h1>My Addresses</h1>

    <?php if (empty($addresses)): ?>
      <p style="color:var(--color-muted)">You have no saved addresses yet.</p>
    <?php else: ?>
      <?php foreach ($addresses as $addr): ?>
        <div class="address-card">
          <strong><?= e($addr['label']) ?></strong> <?= $addr['is_default'] ? '<span class="badge badge-confirmed">Default</span>' : '' ?>
          <p><?= e($addr['address_line1']) ?> <?= e($addr['address_line2']) ?><br>
          <?= e($addr['city']) ?>, <?= e($addr['state']) ?> - <?= e($addr['pincode']) ?></p>
          <form method="post" action="<?= url('/account/addresses') ?>" onsubmit="return confirm('Delete this address?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <h3 style="margin-top:30px">Add New Address</h3>
    <form method="post" action="<?= url('/account/addresses') ?>" style="max-width:520px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label>Label</label><input type="text" name="label" placeholder="Home / Office" value="Home"></div>
      <div class="form-group"><label>Address Line 1 *</label><input type="text" name="address_line1" required></div>
      <div class="form-group"><label>Address Line 2</label><input type="text" name="address_line2"></div>
      <div class="form-row">
        <div class="form-group"><label>City *</label><input type="text" name="city" required></div>
        <div class="form-group"><label>State *</label><input type="text" name="state" required></div>
      </div>
      <div class="form-group"><label>Pincode *</label><input type="text" name="pincode" pattern="[0-9]{6}" maxlength="6" required></div>
      <label style="font-weight:400"><input type="checkbox" name="is_default" value="1"> Set as default address</label>
      <button type="submit" class="btn btn-primary" style="margin-top:14px">Save Address</button>
    </form>
  </div>
</div>
