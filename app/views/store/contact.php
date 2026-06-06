<div class="container contact-grid">
  <div>
    <h1>Get in Touch</h1>
    <p>Have a question about an order, customization, or just want to say hi? We'd love to hear from you.</p>
    <p>📍 <?= e($siteAddress) ?></p>
    <p>📞 <?= e($sitePhone) ?></p>
    <p>✉️ <?= e($siteEmail) ?></p>
  </div>
  <div>
    <form method="post" action="<?= url('/contact') ?>">
      <?= csrfField() ?>
      <div class="form-group"><label>Your Name</label><input type="text" name="name" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Message</label><textarea name="message" rows="5" required></textarea></div>
      <button type="submit" class="btn btn-primary">Send Message</button>
    </form>
  </div>
</div>
