<?php
$__settings = $__settings ?? new Settings();
$__siteName = $__siteName ?? $__settings->get('site_name', SITE_NAME);
$__whatsapp = $__settings->get('whatsapp_number', '');
?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-col">
      <h4><?= e($__siteName) ?></h4>
      <p><?= e($__settings->get('site_tagline', '')) ?></p>
      <p><?= e($__settings->get('site_address', '')) ?></p>
      <p>📞 <?= e($__settings->get('site_phone', '')) ?></p>
      <p>✉️ <?= e($__settings->get('site_email', '')) ?></p>
    </div>
    <div class="footer-col">
      <h4>Shop</h4>
      <ul>
        <?php foreach ((new Category())->activeTopLevel() as $__cat): ?>
          <li><a href="<?= url('/category/' . $__cat['slug']) ?>"><?= e($__cat['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Account</h4>
      <ul>
        <li><a href="<?= url('/account') ?>">My Account</a></li>
        <li><a href="<?= url('/account/orders') ?>">Order History</a></li>
        <li><a href="<?= url('/account/wishlist') ?>">Wishlist</a></li>
        <li><a href="<?= url('/contact') ?>">Contact Us</a></li>
        <li><a href="<?= url('/about') ?>">About Us</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Connect</h4>
      <div class="social-links">
        <?php if ($u = $__settings->get('social_facebook')): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
        <?php if ($u = $__settings->get('social_instagram')): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        <?php if ($u = $__settings->get('social_twitter')): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener">Twitter</a><?php endif; ?>
        <?php if ($u = $__settings->get('social_youtube')): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener">YouTube</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="footer-bottom container">
    <p><?= e($__settings->get('footer_copyright', '© ' . date('Y') . ' ' . $__siteName)) ?></p>
  </div>
</footer>

<?php if ($__whatsapp): ?>
<a class="whatsapp-float" href="https://wa.me/<?= e($__whatsapp) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.36 5.07L2 22l5.05-1.32A9.94 9.94 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm.02 18a7.9 7.9 0 0 1-4.04-1.1l-.29-.17-3 .78.8-2.92-.19-.3A7.93 7.93 0 1 1 20 12a7.94 7.94 0 0 1-7.98 8zm4.4-5.92c-.24-.12-1.4-.7-1.62-.77-.22-.08-.38-.12-.54.12-.16.24-.62.77-.76.93-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.21-1.43-1.35-1.67-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.47-.4-.4-.54-.41-.14-.01-.3-.01-.46-.01-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.7 2.6 4.12 3.64.58.25 1.03.4 1.38.51.58.18 1.1.16 1.52.1.46-.07 1.4-.57 1.6-1.12.2-.55.2-1.02.14-1.12-.06-.1-.22-.16-.46-.28z"/></svg>
</a>
<?php endif; ?>

<script src="<?= asset('public/js/main.js') ?>?v=<?= filemtime(BASE_PATH . '/public/js/main.js') ?>"></script>
</body>
</html>
