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
$selectedPack = old('pack', $trial && ($plan ?? '') === 'trial' ? 'trial' : ($recommended >= 0 ? (string)$recommended : ''));
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
                    <h2 class="reg-step-title"><span>1</span> <?= e($trial ? $offer['heading'] : $offer['heading_no_trial']) ?></h2>
                    <div class="packs packs-sale" role="radiogroup" aria-label="Credit pack">
                        <?php foreach ($packs as $i => $pack):
                            $per = ArPartnerService::packItemPrice($pack, $rate);
                            // Credits are worth a rupee each, so anything above the price is a bonus.
                            $bonus = $pack['credits'] - $pack['price'];
                            $isPopular = $i === $recommended; ?>
                            <label class="pack pack-option<?= $isPopular ? ' is-popular' : '' ?>">
                                <?php if ($pack['badge'] !== ''): ?><span class="pack-ribbon"><?= e($pack['badge']) ?></span><?php endif; ?>
                                <input type="radio" name="pack" value="<?= (int)$i ?>" required
                                       <?= $selectedPack === (string)$i ? 'checked' : '' ?>
                                       data-label="<?= e(ArPartner::planText($offer['buy_button'], ['price' => GDD_CURRENCY_SYMBOL . number_format($pack['price']), 'credits' => $pack['credits']])) ?>">
                                <span class="price"><?= e(GDD_CURRENCY_SYMBOL . number_format($pack['price'])) ?></span>
                                <span class="get"><?= number_format($pack['credits']) ?> credits</span>
                                <?php if ($offer['show_per_item']): ?><span class="per"><?= e(GDD_CURRENCY_SYMBOL . number_format($per, 2)) ?>/item</span><?php endif; ?>
                                <?php if ($bonus > 0 && $offer['bonus_label'] !== ''): ?><span class="save"><?= e(ArPartner::planText($offer['bonus_label'], ['bonus' => $bonus])) ?></span><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($trial): ?>
                            <label class="pack pack-option pack-trial">
                                <?php $tv = ['days' => (int)$trial['days'], 'credits' => (int)$trial['credits']]; ?>
                                <?php if ($offer['trial_ribbon'] !== ''): ?><span class="pack-ribbon pack-ribbon-trial"><?= e(ArPartner::planText($offer['trial_ribbon'], $tv)) ?></span><?php endif; ?>
                                <input type="radio" name="pack" value="trial" required <?= $selectedPack === 'trial' ? 'checked' : '' ?>
                                       data-label="<?= e($offer['trial_button']) ?>">
                                <span class="price"><?= e(ArPartner::planText($offer['trial_title'] ?: 'Free', $tv)) ?></span>
                                <span class="get"><?= number_format($trial['credits']) ?> credits</span>
                                <?php if ($offer['trial_sub'] !== ''): ?><span class="per"><?= e(ArPartner::planText($offer['trial_sub'], $tv)) ?></span><?php endif; ?>
                                <?php /* The deletion warning is not optional: it is the trial's key term. */ ?>
                                <span class="save save-warn" title="Everything created on the trial is deleted automatically <?= e($trialDays) ?> after it is made"><?= e(ArPartner::planText($offer['trial_warning'] ?: 'Deletes in {days} days', $tv)) ?></span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div data-show-for="buy"<?= $selectedPlan === 'trial' ? ' hidden' : '' ?>>
                        <?php if ($offer['perks']): ?>
                            <ul class="reg-perks">
                                <?php foreach ($offer['perks'] as $perk): ?><li><?= e($perk) ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p class="hint">
                            A basic DEx item (one photo with a short video) uses <?= number_format((int)$rate['base_credits']) ?> credits.
                            <?= e($offer['buy_note']) ?>
                        </p>
                    </div>
                    <?php if ($trial): ?>
                        <div class="trial-box" data-show-for="trial"<?= $selectedPlan === 'trial' ? '' : ' hidden' ?>>
                            <p class="trial-box-title">Free trial · <?= number_format($trial['credits']) ?> credits</p>
                            <ul>
                                <?php /* The deletion term is fixed: it is what the trial is. */ ?>
                                <li><strong>Trial content is temporary:</strong> every photo, video and QR link is
                                    <strong>deleted automatically <?= e($trialDays) ?> after it is created</strong>.</li>
                                <?php foreach ($offer['trial_terms'] as $term): ?>
                                    <li><?= e(ArPartner::planText($term, ['days' => (int)$trial['days'], 'credits' => (int)$trial['credits']])) ?></li>
                                <?php endforeach; ?>
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
                        echo e($offer['trial_button']);
                    } else {
                        $chosen = $packs[(int)$selectedPack] ?? null;
                        echo $chosen ? e(ArPartner::planText($offer['buy_button'], ['price' => GDD_CURRENCY_SYMBOL . number_format($chosen['price']), 'credits' => $chosen['credits']])) : 'Register as a DEx partner';
                    } ?></button>
                <?php if ($offer['buy_footnote'] !== ''): ?>
                    <p class="hint" style="text-align:center;margin-top:8px" data-show-for="buy"<?= $selectedPlan === 'trial' ? ' hidden' : '' ?>><?= e($offer['buy_footnote']) ?></p>
                <?php endif; ?>
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
