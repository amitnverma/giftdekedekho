<?php
/**
 * "Become a DEx partner" — /partner/register, in the shop's own branding.
 *
 * Three states: the form; $done, the "what happens next" page shown once after
 * registering; and $closed, until the registration migration has been run.
 * With $trial set (the free trial is on), registering opens DEx Studio at
 * once and $done is never shown; buying a pack is optional. Without it there
 * is no online payment: the applicant pays outside the site and the admin
 * activates the account, which adds the chosen pack's credits.
 */
$siteName = $brand['name'];
$initials = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$siteName), 0, 2)) ?: 'DX';
$selectedPack = old('pack', $trial ? '-1' : '');
$trialDays = $trial ? $trial['days'] . ' day' . ($trial['days'] === 1 ? '' : 's') : '';
$siteEmail = trim((string)siteSetting('site_email', ''));
$sitePhone = trim((string)siteSetting('site_phone', ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Become a DEx partner · <?= e($siteName) ?></title>
<?php if (!empty($brand['logo'])): ?><link rel="icon" href="<?= e($brand['logo']) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= asset('public/css/partner.css') ?>">
<style>:root { --brand: <?= e($brand['color']) ?>; }</style>
</head>
<body>
<main class="login-page">
    <div class="login-card login-card-wide">
        <div class="login-brand">
            <?php if (!empty($brand['logo'])): ?>
                <img src="<?= e($brand['logo']) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
                <span class="p-logo-mark"><?= e($initials) ?></span>
            <?php endif; ?>
            <span class="dex-badge"><b>DEx</b> Studio · Digital Experience</span>
            <h1><?= $done ? 'Registration received' : 'Become a DEx partner' ?></h1>
            <p><?= $done
                ? 'Thank you, ' . e($done['business']) . '. One step left.'
                : ($trial
                    ? 'Start a free trial of DEx Studio — no payment needed.'
                    : 'Sell Living Photo DEx to your own customers, from your own branded DEx Studio.') ?></p>
        </div>

        <?php if ($closed): ?>
            <div class="banner banner-amber"><p>Online registration is not open yet. Please contact <?= e($siteName) ?> to become a DEx partner.</p></div>
            <?php if ($support): ?>
                <a class="btn" style="width:100%" target="_blank" rel="noopener"
                   href="https://wa.me/<?= e($support) ?>?text=<?= rawurlencode('Hi, I would like to become a DEx partner on ' . $siteName . '.') ?>">Message us on WhatsApp</a>
            <?php endif; ?>

        <?php elseif ($done): ?>
            <ol class="signup-steps">
                <li class="is-done"><strong>Account created</strong><span>You will sign in with <?= e($done['email']) ?>.</span></li>
                <li class="is-now">
                    <strong>Pay <?= e(GDD_CURRENCY_SYMBOL . number_format((int)$done['price'])) ?> for <?= number_format((int)$done['credits']) ?> credits</strong>
                    <span>Online payment is not available yet — contact us and we will tell you how to pay.</span>
                </li>
                <li><strong>We activate your account</strong><span>Once the payment is received, your credits are added and you can sign in. We will email you.</span></li>
            </ol>
            <?php if ($support): ?>
                <a class="btn" style="width:100%" target="_blank" rel="noopener"
                   href="https://wa.me/<?= e($support) ?>?text=<?= rawurlencode('Hi, I have registered ' . $done['business'] . ' (' . $done['email'] . ') as a DEx partner and chose the ' . GDD_CURRENCY_SYMBOL . number_format((int)$done['price']) . ' pack for ' . number_format((int)$done['credits']) . ' credits. How should I pay?') ?>">
                    Message us on WhatsApp to pay
                </a>
            <?php endif; ?>
            <?php if ($sitePhone !== '' || $siteEmail !== ''): ?>
                <p class="hint" style="text-align:center;margin-top:12px">
                    <?= $support ? 'Or reach us' : 'Reach us' ?>
                    <?php if ($sitePhone !== ''): ?> on <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $sitePhone)) ?>"><?= e($sitePhone) ?></a><?php endif; ?>
                    <?php if ($sitePhone !== '' && $siteEmail !== ''): ?> or<?php endif; ?>
                    <?php if ($siteEmail !== ''): ?> at <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a><?php endif; ?>.
                </p>
            <?php endif; ?>
            <div class="login-switch">
                <p><a href="<?= url('/partner/login') ?>">Sign in to DEx Studio</a></p>
                <p><a href="<?= url('/') ?>">← Back to <?= e($siteName) ?></a></p>
            </div>

        <?php else: ?>
            <?php if ($msg = flash('error')): ?>
                <div class="banner banner-danger" role="alert"><p><?= e($msg) ?></p></div>
            <?php endif; ?>

            <?php if (!empty($trialUsed)): ?>
                <div class="banner banner-amber"><p>A free trial has already been used in this browser. You can register by choosing a credit
                    pack below, or <a href="<?= url('/partner/login') ?>">sign in to your existing DEx account</a>.</p></div>
            <?php endif; ?>
            <?php if ($trial): ?>
                <div class="trial-box">
                    <p class="trial-box-title">Free trial · <?= number_format($trial['credits']) ?> credits</p>
                    <ul>
                        <li>Your DEx Studio opens as soon as you register — create DEx content and test it on your phone straight away.</li>
                        <li>A basic DEx item (one photo with a short video) uses <?= number_format((int)$rate['base_credits']) ?> credits.</li>
                        <li>One free trial per business — mobile number, email and device.</li>
                        <li><strong>Trial content is temporary:</strong> every photo, video and QR link you create during the trial is
                            <strong>deleted automatically <?= e($trialDays) ?> after it is created</strong>.
                            Buy a credit pack to create content that stays live.</li>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('/partner/register') ?>">
                <?= csrfField() ?>
                <div class="field">
                    <label for="business_name">Shop or business name</label>
                    <input type="text" id="business_name" name="business_name" value="<?= old('business_name') ?>" maxlength="120" autocomplete="organization" required autofocus>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="name">Your name</label>
                        <input type="text" id="name" name="name" value="<?= old('name') ?>" maxlength="120" autocomplete="name" required>
                    </div>
                    <div class="field">
                        <label for="phone">Mobile / WhatsApp</label>
                        <input type="tel" id="phone" name="phone" value="<?= old('phone') ?>" maxlength="20" autocomplete="tel" inputmode="tel" required>
                    </div>
                </div>
                <div class="field">
                    <label for="city">City <span class="muted" style="font-weight:400">(optional)</span></label>
                    <input type="text" id="city" name="city" value="<?= old('city') ?>" maxlength="80" autocomplete="address-level2">
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>" maxlength="180" autocomplete="email" required>
                    <p class="hint">You sign in with this, and password reset links are sent to it.</p>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required>
                    </div>
                    <div class="field">
                        <label for="password_confirm">Confirm password</label>
                        <input type="password" id="password_confirm" name="password_confirm" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required>
                    </div>
                </div>

                <fieldset class="field pack-pick">
                    <legend class="field-label"><?= $trial ? 'Buy credits now as well? <span class="muted" style="font-weight:400">(optional)</span>' : 'Starting credits' ?></legend>
                    <div class="packs">
                        <?php if ($trial): ?>
                            <label class="pack pack-option pack-option-trial">
                                <input type="radio" name="pack" value="-1" <?= $selectedPack === '-1' ? 'checked' : '' ?>>
                                <span class="price">Free</span>
                                <span class="get">Trial only</span>
                                <span class="per"><?= number_format($trial['credits']) ?> credits</span>
                            </label>
                        <?php endif; ?>
                        <?php foreach ($packs as $i => $pack): ?>
                            <label class="pack pack-option">
                                <input type="radio" name="pack" value="<?= (int)$i ?>" <?= $trial ? '' : 'required' ?> <?= $selectedPack === (string)$i ? 'checked' : '' ?>>
                                <span class="price"><?= e(GDD_CURRENCY_SYMBOL . number_format($pack['price'])) ?></span>
                                <span class="get"><?= number_format($pack['credits']) ?> credits</span>
                                <span class="per"><?= e(GDD_CURRENCY_SYMBOL . number_format(ArPartnerService::packItemPrice($pack, $rate), 2)) ?>/item</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="hint">
                        <?php if ($trial): ?>
                            <strong>No payment is taken now.</strong> If you pick a pack, we will contact you to arrange payment and add the
                            credits once it is received — that also ends your trial, so what you create afterwards stays live.
                        <?php else: ?>
                            A basic DEx item (one photo with a short video) uses <?= number_format((int)$rate['base_credits']) ?> credits.
                            <strong>No payment is taken now:</strong> we will contact you to arrange payment, then activate your account and add the credits.
                        <?php endif; ?>
                    </p>
                </fieldset>

                <?= CaptchaService::field('partner-register') ?>
                <button class="btn" type="submit" style="width:100%"><?= $trial ? 'Start my free trial' : 'Register as a DEx partner' ?></button>
            </form>
            <div class="login-switch">
                <p>Already a DEx partner? <a href="<?= url('/partner/login') ?>">Sign in</a></p>
                <p><a href="<?= url('/') ?>">← Back to <?= e($siteName) ?></a></p>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
