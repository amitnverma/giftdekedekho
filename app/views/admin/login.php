<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login · <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= asset('public/css/admin.css') ?>">
</head>
<body class="admin-login-body">
    <div class="admin-login-card">
        <img src="<?= asset('public/images/logo.png') ?>" alt="<?= e(SITE_NAME) ?>" class="admin-login-logo" onerror="this.style.display='none'">
        <h1>Admin Login</h1>
        <p class="admin-login-sub"><?= e(SITE_NAME) ?> Control Panel</p>

        <?php if ($msg = flash('error')): ?>
            <div class="admin-alert admin-alert-error"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="admin-alert admin-alert-success"><?= e($msg) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('/admin/login') ?>" class="admin-login-form">
            <?= csrfField() ?>
            <label>Email Address
                <input type="email" name="email" required autofocus value="<?= e(old('email')) ?>">
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-block">Sign In</button>
        </form>
        <p class="admin-login-footer">&larr; <a href="<?= url('/') ?>">Back to store</a></p>
    </div>
</body>
</html>
