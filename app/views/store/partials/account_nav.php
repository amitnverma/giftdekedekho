<?php $active = $active ?? ''; ?>
<nav class="account-nav">
  <a href="<?= url('/account') ?>" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
  <a href="<?= url('/account/orders') ?>" class="<?= $active === 'orders' ? 'active' : '' ?>">My Orders</a>
  <a href="<?= url('/account/wishlist') ?>" class="<?= $active === 'wishlist' ? 'active' : '' ?>">Wishlist</a>
  <a href="<?= url('/account/addresses') ?>" class="<?= $active === 'addresses' ? 'active' : '' ?>">Addresses</a>
  <a href="<?= url('/account/profile') ?>" class="<?= $active === 'profile' ? 'active' : '' ?>">Profile Settings</a>
  <a href="<?= url('/account/logout') ?>">Logout</a>
</nav>
