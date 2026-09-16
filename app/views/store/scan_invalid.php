<?php
// $brand is set when the frame belongs to a partner: their customer should see
// the shop they bought from, not GiftDekeDekho.
$brand = $brand ?? null;
$accent = $brand['color'] ?? '#e63946';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($siteName) ?> — Living Photo Unavailable</title>
<style>
  * { margin:0; padding:0; box-sizing: border-box; }
  body { min-height:100vh; display:flex; align-items:center; justify-content:center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background:#f8f9fb; text-align:center; padding:24px; }
  .card { padding: 40px 24px; max-width: 30em; }
  img { height: 48px; max-width: 200px; object-fit: contain; margin-bottom: 18px; }
  h1 { font-size: 22px; margin-bottom: 10px; }
  p { color: #6b7280; line-height: 1.6; }
  a { display:inline-block; margin-top:22px; color:<?= e($accent) ?>; font-weight:600; text-decoration:none; }
  .powered { margin-top: 28px; font-size: 12px; color: #9ca3af; }
</style>
</head>
<body>
  <div class="card">
    <?php if ($brand === null): ?>
      <img src="<?= e(productImage($logo)) ?>" alt="">
    <?php elseif (!empty($brand['logo'])): ?>
      <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>">
    <?php endif; ?>
    <h1>Living Photo unavailable</h1>
    <p><?= e($message) ?></p>
    <?php if ($brand === null): ?>
      <p style="margin-top:14px;font-size:14px">
        The link is printed on the small card that came with your frame — it looks like
        <code>/scan/gdd-xxxxxx</code>.
      </p>
      <a href="<?= url('/') ?>">Visit <?= e($siteName) ?></a>
    <?php else: ?>
      <?php if (!empty($brand['website'])): ?>
        <a href="<?= e($brand['website']) ?>" rel="noopener">Visit <?= e($brand['name']) ?></a>
      <?php endif; ?>
      <?php if (!empty($brand['poweredBy'])): ?>
        <p class="powered">Powered by <?= e($brand['poweredBy']) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
