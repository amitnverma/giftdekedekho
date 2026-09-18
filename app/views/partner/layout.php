<?php
/**
 * Shell for every signed-in partner page. The partner's name, logo and colour
 * come from their record, which is edited in Admin → AR Partners.
 *
 * Expects: $partner, $partnerUser, $brand, $base, $pageTitle, $activeNav, $_partnerView
 */
if (!function_exists('partnerIcon')) {
    /** Small line icons used by the stat tiles and action cards. */
    function partnerIcon(string $name): string
    {
        $paths = [
            'coin'     => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.6-1.5-3-1.5-1.7 0-3 .8-3 2s1.3 1.7 3 2 3 .8 3 2-1.3 2-3 2c-1.4 0-2.5-.5-3-1.5M12 6v2m0 8v2"/>',
            'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3 2.8-4.8 5.5-4.8s4.9 1.8 5.5 4.8M16 11.2a2.8 2.8 0 1 0 0-5.6M18 14.6c1.5.7 2.4 2.2 2.7 4.4"/>',
            'user-add' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3 2.8-4.8 5.5-4.8s4.9 1.8 5.5 4.8M18 8v6m-3-3h6"/>',
            'image'    => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>',
            'album'    => '<rect x="3.5" y="7" width="17" height="13" rx="2"/><path d="M6 4h12M8 7V5.5M16 7V5.5M3.5 12h17"/>',
            'eye'      => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        ];
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . ($paths[$name] ?? '') . '</svg>';
    }
}

$nav = [
    'home'      => ['Home', ''],
    'customers' => ['Customers', '/customers'],
];
if (!empty($partner['allow_singles'])) {
    $nav['singles'] = ['Singles', '/singles'];
}
if (!empty($partner['allow_albums'])) {
    $nav['albums'] = ['Albums', '/albums'];
}
$nav['credits'] = ['My Credits', '/credits'];
$nav['analytics'] = ['Analytics', '/analytics'];

$balance = (int)$partner['credit_balance'];

// The page is rendered first so it can put a button in the heading ($headAction).
$headAction = null;
ob_start();
require viewPath('partner/' . $_partnerView . '.php');
$pageHtml = ob_get_clean();
$initials = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$partner['name']), 0, 2)) ?: 'DX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title><?= e($pageTitle) ?> · <?= e($partner['name']) ?></title>
<?php if (!empty($brand['logo'])): ?><link rel="icon" href="<?= e($brand['logo']) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= asset('public/css/partner.css') ?>">
<style>:root { --brand: <?= e($brand['color']) ?>; }</style>
</head>
<body>
<header class="p-topbar">
    <div class="p-topbar-in">
        <a class="p-logo" href="<?= url($base) ?>" aria-label="<?= e($partner['name']) ?> home">
            <?php if (!empty($brand['logo'])): ?>
                <img src="<?= e($brand['logo']) ?>" alt="<?= e($partner['name']) ?>">
            <?php else: ?>
                <span class="p-logo-mark"><?= e($initials) ?></span>
            <?php endif; ?>
        </a>
        <nav class="p-nav" aria-label="Main">
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <a href="<?= url($base . $href) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="p-topbar-right">
            <a class="pill pill-credits<?= $balance < (int)$partner['base_credits'] ? ' is-low' : '' ?>" href="<?= url($base . '/credits') ?>">
                <?= number_format($balance) ?> credits
            </a>
            <span class="pill pill-role"><?= e(ArPartnerUser::ROLES[$partnerUser['role']] ?? 'Editor') ?></span>
            <details class="p-user">
                <summary><?= e($partnerUser['name']) ?>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="p-user-menu">
                    <div class="who">Signed in as <?= e($partnerUser['email']) ?></div>
                    <a href="<?= url($base . '/credits') ?>">Credits &amp; billing</a>
                    <a href="<?= url($base . '/password') ?>">Change password</a>
                    <a href="<?= url($base . '/logout') ?>">Sign out</a>
                </div>
            </details>
        </div>
    </div>
</header>

<div class="p-head">
    <div class="p-head-in">
        <h1><?= e($pageTitle) ?></h1>
        <?php if (!empty($headAction)): ?><?= $headAction ?><?php endif; ?>
    </div>
</div>

<main class="p-canvas">
    <div class="p-wrap">
        <?php if ($msg = flash('success')): ?>
            <div class="banner banner-success" role="status"><p><?= e($msg) ?></p></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="banner banner-danger" role="alert"><p><?= e($msg) ?></p></div>
        <?php endif; ?>

        <?= $pageHtml ?>
    </div>
</main>

<?php if (!empty($brand['poweredBy'])): ?>
    <footer class="p-footer">Powered by <?= e($brand['poweredBy']) ?></footer>
<?php endif; ?>

<script>window.PARTNER_BASE = <?= json_encode(url($base)) ?>;</script>
<script src="<?= asset('public/js/partner.js') ?>" defer></script>
</body>
</html>
