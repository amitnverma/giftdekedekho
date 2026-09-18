<?php
/** "Forgot password" for one partner's portal, or (no $partner) the shared seller sign-in: emails a reset link. */
$hub = empty($partner);
$base = $hub ? '/partner' : '/partner/' . $partner['slug'];
$heading = $hub ? 'Seller sign in' : $partner['name'];
$subheading = $hub ? 'Reset the password for your partner studio' : ($partner['tagline'] ?: 'DEx studio — sign in to continue');
$initials = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', (string)($partner['name'] ?? $brand['name'])), 0, 2)) ?: 'DX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Forgot password · <?= e($hub ? $brand['name'] . ' seller' : $partner['name']) ?></title>
<?php if (!empty($brand['logo'])): ?><link rel="icon" href="<?= e($brand['logo']) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= asset('public/css/partner.css') ?>">
<style>:root { --brand: <?= e($brand['color']) ?>; }</style>
</head>
<body>
<main class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <?php if (!empty($brand['logo'])): ?>
                <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>">
            <?php else: ?>
                <span class="p-logo-mark"><?= e($initials) ?></span>
            <?php endif; ?>
            <h1><?= e($heading) ?></h1>
            <p><?= e($subheading) ?></p>
        </div>

        <?php if ($msg = flash('error')): ?>
            <div class="banner banner-danger" role="alert"><p><?= e($msg) ?></p></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="banner banner-success" role="status"><p><?= e($msg) ?></p></div>
        <?php endif; ?>

        <p class="hint" style="margin-top:0">Enter the email you sign in with, and we will send you a link to choose a new password.</p>
        <form method="post" action="<?= url($base . '/forgot-password') ?>">
            <?= csrfField() ?>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="username" required autofocus>
            </div>
            <?= CaptchaService::field('partner-forgot-password') ?>
            <button class="btn" type="submit" style="width:100%">Email me a reset link</button>
        </form>
        <p class="hint" style="text-align:center;margin-top:16px">
            <a href="<?= url($base . '/login') ?>">← Back to sign in</a>
        </p>
    </div>
</main>
<?php if (!empty($brand['poweredBy'])): ?>
    <footer class="p-footer" style="margin-top:-60px">Powered by <?= e($brand['poweredBy']) ?></footer>
<?php endif; ?>
</body>
</html>
