<?php
/**
 * Sign-in page for one partner's portal, in that partner's branding — or, with
 * no $partner, the shared DEx Studio sign-in linked from the storefront header.
 */
$hub = empty($partner);
$base = $hub ? '/partner' : '/partner/' . $partner['slug'];
$heading = $hub ? 'Partner sign in' : $partner['name'];
$subheading = $hub
    ? 'Sign in to your DEx Studio. We will take you straight to your own portal.'
    : ($partner['tagline'] ?: 'DEx Studio — sign in to continue');
$initials = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', (string)($partner['name'] ?? $brand['name'])), 0, 2)) ?: 'DX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · <?= e($hub ? 'DEx Studio' : $partner['name']) ?></title>
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
            <?php if ($hub): ?><span class="dex-badge"><b>DEx</b> Studio · Digital Experience</span><?php endif; ?>
            <h1><?= e($heading) ?></h1>
            <p><?= e($subheading) ?></p>
        </div>

        <?php if ($msg = flash('error')): ?>
            <div class="banner banner-danger" role="alert"><p><?= e($msg) ?></p></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="banner banner-success" role="status"><p><?= e($msg) ?></p></div>
        <?php endif; ?>

        <?php if (!$hub && empty($partner['is_active'])): ?>
            <div class="banner banner-amber"><p><?= ArPartner::awaitingActivation($partner)
                ? 'This account is waiting to be activated. You can sign in once your payment has been received.'
                : 'This account is currently paused.' ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?= url($base . '/login') ?>">
            <?= csrfField() ?>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <?= CaptchaService::field('partner-login') ?>
            <button class="btn" type="submit" style="width:100%">Sign in</button>
        </form>
        <p class="hint" style="text-align:center;margin-top:16px">
            <a href="<?= url($base . '/forgot-password') ?>">Forgot your password?</a>
        </p>
        <?php if ($hub): ?>
            <div class="login-switch">
                <p>New to DEx? <a href="<?= url('/partner/register') ?>">Become a DEx partner</a></p>
                <p>Shopping for a gift? <a href="<?= url('/account/login') ?>">Customer sign in</a></p>
                <p><a href="<?= url('/') ?>">← Back to <?= e($brand['name']) ?></a></p>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php if (!empty($brand['poweredBy'])): ?>
    <footer class="p-footer" style="margin-top:-60px">Powered by <?= e($brand['poweredBy']) ?></footer>
<?php endif; ?>
</body>
</html>
