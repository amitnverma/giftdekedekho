<?php
/** Set a new password from an emailed reset link. */
$initials = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$partner['name']), 0, 2)) ?: 'DX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Choose a new password · <?= e($partner['name']) ?></title>
<?php if (!empty($brand['logo'])): ?><link rel="icon" href="<?= e($brand['logo']) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= asset('public/css/partner.css') ?>">
<style>:root { --brand: <?= e($brand['color']) ?>; }</style>
</head>
<body>
<main class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <?php if (!empty($brand['logo'])): ?>
                <img src="<?= e($brand['logo']) ?>" alt="<?= e($partner['name']) ?>">
            <?php else: ?>
                <span class="p-logo-mark"><?= e($initials) ?></span>
            <?php endif; ?>
            <h1><?= e($partner['name']) ?></h1>
            <p><?= e($partner['tagline'] ?: 'DEx studio — sign in to continue') ?></p>
        </div>

        <?php if ($msg = flash('error')): ?>
            <div class="banner banner-danger" role="alert"><p><?= e($msg) ?></p></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="banner banner-success" role="status"><p><?= e($msg) ?></p></div>
        <?php endif; ?>

        <p class="hint" style="margin-top:0">Choose a new password for <strong><?= e($email) ?></strong>.</p>
        <form method="post" action="<?= url('/partner/' . $partner['slug'] . '/reset-password') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" hidden>
            <div class="field">
                <label for="password">New password <span class="muted">(at least <?= PASSWORD_MIN_LENGTH ?> characters)</span></label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autofocus>
            </div>
            <div class="field">
                <label for="password_confirm">Repeat new password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
            </div>
            <button class="btn" type="submit" style="width:100%">Save new password</button>
        </form>
    </div>
</main>
<?php if (!empty($brand['poweredBy'])): ?>
    <footer class="p-footer" style="margin-top:-60px">Powered by <?= e($brand['poweredBy']) ?></footer>
<?php endif; ?>
</body>
</html>
