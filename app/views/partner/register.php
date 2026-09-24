<?php
/**
 * "Become a DEx partner" — /partner/register, in the shop's own branding.
 *
 * Three states: the form; $done, the "what happens next" page shown once after
 * registering; and $closed, until the registration migration has been run.
 * One choice, made in the pack grid: the paid packs lead, with the admin's
 * recommended pack marked "Most popular" and preselected, and — with $trial
 * set — the free trial is the last card, marked with its deletion terms.
 * The chosen card decides the path (pack=trial or a pack index) and the
 * button names it. A "try free" button elsewhere sends ?plan=trial, which
 * preselects the trial card. Buying has no
 * online payment: the applicant pays outside the site and the admin activates
 * the account, which adds the pack's credits ($done). The trial opens DEx
 * Studio at once.
 */
$siteName = $brand['name'];
$initials = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$siteName), 0, 2)) ?: 'DX';
// Buying is the default path; only an explicit trial choice (a "try free"
// button, or the switch on this page) opens the trial.
$selectedPack = old('pack', $trial && ($plan ?? '') === 'trial' ? 'trial' : (string)$recommended);
$selectedPlan = $trial && $selectedPack === 'trial' ? 'trial' : 'buy';
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
    <div class="login-card login-card-wide<?= $closed || $done ? '' : ' login-card-xl' ?>">
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
                    ? 'Your own branded DEx Studio. Pay only for what you create — no subscription.'
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
                <div class="banner banner-amber"><p>A free trial has already been used in this browser. You can register by buying credits
                    below, or <a href="<?= url('/partner/login') ?>">sign in to your existing DEx account</a>.</p></div>
            <?php endif; ?>

            <form method="post" action="<?= url('/partner/register') ?>" data-register-form
                  data-mode="<?= $selectedPlan === 'trial' ? 'trial' : 'buy' ?>">
                <?= csrfField() ?>

                <?php /* One choice for everyone: the paid packs lead, the free trial is the last card. */ ?>
                <section class="reg-step">
                    <h2 class="reg-step-title"><span>1</span> <?= $trial ? 'Choose a credit pack — or try it free' : 'Choose your credit pack' ?></h2>
                    <div class="packs packs-sale" role="radiogroup" aria-label="Credit pack">
                        <?php foreach ($packs as $i => $pack):
                            $per = ArPartnerService::packItemPrice($pack, $rate);
                            // Credits are worth a rupee each, so anything above the price is a bonus.
                            $bonus = $pack['credits'] - $pack['price'];
                            $isPopular = $i === $recommended; ?>
                            <label class="pack pack-option<?= $isPopular ? ' is-popular' : '' ?>">
                                <?php if ($isPopular): ?><span class="pack-ribbon">Most popular</span><?php endif; ?>
                                <input type="radio" name="pack" value="<?= (int)$i ?>" required
                                       <?= $selectedPack === (string)$i ? 'checked' : '' ?>
                                       data-label="Register — <?= e(GDD_CURRENCY_SYMBOL . number_format($pack['price'])) ?> pack">
                                <span class="price"><?= e(GDD_CURRENCY_SYMBOL . number_format($pack['price'])) ?></span>
                                <span class="get"><?= number_format($pack['credits']) ?> credits</span>
                                <span class="per"><?= e(GDD_CURRENCY_SYMBOL . number_format($per, 2)) ?>/item</span>
                                <?php if ($bonus > 0): ?><span class="save">+<?= number_format($bonus) ?> bonus</span><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($trial): ?>
                            <label class="pack pack-option pack-trial">
                                <span class="pack-ribbon pack-ribbon-trial">Try first</span>
                                <input type="radio" name="pack" value="trial" required <?= $selectedPack === 'trial' ? 'checked' : '' ?>
                                       data-label="Start my free trial">
                                <span class="price">Free</span>
                                <span class="get"><?= number_format($trial['credits']) ?> credits</span>
                                <span class="per">No payment</span>
                                <span class="save save-warn" title="Everything created on the trial is deleted automatically <?= e($trialDays) ?> after it is made">Deletes in <?= e($trialDays) ?></span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div data-show-for="buy"<?= $selectedPlan === 'trial' ? ' hidden' : '' ?>>
                        <ul class="reg-perks">
                            <li>Your DEx content stays live for its full validity</li>
                            <li>Your own branded DEx Studio</li>
                            <li>No subscription — top up whenever you need</li>
                        </ul>
                        <p class="hint">
                            A basic DEx item (one photo with a short video) uses <?= number_format((int)$rate['base_credits']) ?> credits.
                            <strong>No payment is taken now:</strong> we contact you to arrange payment, then activate your account and add the credits.
                        </p>
                    </div>
                    <?php if ($trial): ?>
                        <div class="trial-box" data-show-for="trial"<?= $selectedPlan === 'trial' ? '' : ' hidden' ?>>
                            <p class="trial-box-title">Free trial · <?= number_format($trial['credits']) ?> credits</p>
                            <ul>
                                <li>No payment — your DEx Studio opens as soon as you register.</li>
                                <li><strong>Trial content is temporary:</strong> every photo, video and QR link is
                                    <strong>deleted automatically <?= e($trialDays) ?> after it is created</strong>.</li>
                                <li>One free trial per business. Buy a credit pack at any time to keep what you create from then on.</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </section>

                <h2 class="reg-step-title"><span>2</span> Your business details</h2>
                <div class="field">
                    <label for="business_name">Shop or business name</label>
                    <input type="text" id="business_name" name="business_name" value="<?= old('business_name') ?>" maxlength="120" autocomplete="organization" required>
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

                <?= CaptchaService::field('partner-register') ?>
                <button class="btn" type="submit" style="width:100%" data-submit-label><?php
                    if ($selectedPlan === 'trial') {
                        echo 'Start my free trial';
                    } else {
                        $chosen = $packs[(int)$selectedPack] ?? null;
                        echo $chosen ? 'Register — ' . e(GDD_CURRENCY_SYMBOL . number_format($chosen['price'])) . ' pack' : 'Register and choose payment';
                    } ?></button>
                <p class="hint" style="text-align:center;margin-top:8px" data-show-for="buy"<?= $selectedPlan === 'trial' ? ' hidden' : '' ?>>No payment now · we activate your account once payment is received</p>
            </form>
            <script>
            // The chosen card decides: show its terms, and name what the button will do.
            (function () {
                var form = document.querySelector('[data-register-form]');
                var button = form.querySelector('[data-submit-label]');
                form.addEventListener('change', function (e) {
                    if (e.target.name !== 'pack') return;
                    var plan = e.target.value === 'trial' ? 'trial' : 'buy';
                    form.setAttribute('data-mode', plan);
                    form.querySelectorAll('[data-show-for]').forEach(function (el) { el.hidden = el.getAttribute('data-show-for') !== plan; });
                    button.textContent = e.target.getAttribute('data-label');
                });
            })();
            </script>
            <div class="login-switch">
                <p>Already a DEx partner? <a href="<?= url('/partner/login') ?>">Sign in</a></p>
                <p><a href="<?= url('/') ?>">← Back to <?= e($siteName) ?></a></p>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
