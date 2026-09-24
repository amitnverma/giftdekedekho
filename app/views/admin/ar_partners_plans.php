<?php
/**
 * Admin → AR Partners → Sign-up plans & pricing.
 *
 * Everything /partner/register offers — the packs, the highlighted pack, the
 * free trial card and all its wording — and the pricing every new partner
 * starts with. Saved by AdminArPartnerController::savePlans().
 *
 * Expects: $offer (ArPartner::signupPlan(), or a failed save's input),
 * $trial (enabled, credits, days), $trialsReady.
 */
$packs = $offer['packs'];
$rows = max(6, count($packs) + 2);   // room to add a couple of packs
while (count($packs) < $rows) {
    $packs[] = ['price' => '', 'credits' => '', 'badge' => ''];
}
$durationPrices = ArPartner::durationPrices(['duration_prices' => $offer['duration_prices']]);
$validityPrices = ArPartner::validityPrices(['validity_prices' => $offer['validity_prices']]);
$hint = fn(string $text): string => '<span class="admin-label-hint">' . $text . '</span>';
?>
<div class="admin-flex-between">
    <p class="admin-muted" style="margin:0;font-size:13px;max-width:62em">
        What <a href="<?= url('/partner/register') ?>" target="_blank">/partner/register ↗</a> offers, and the pricing every new partner starts with.
        A new partner gets a <strong>copy</strong> of this pricing, so changing it here never reprices an existing partner — edit those on each partner's page.
    </p>
    <a class="admin-btn" href="<?= url('/admin/ar-partners') ?>">← All partners</a>
</div>

<form method="post" action="<?= url('/admin/ar-partners/plans') ?>" class="admin-form admin-mt" style="max-width:900px">
    <?= csrfField() ?>

    <div class="admin-card">
        <h3 class="admin-card-title">Credit packs on the sign-up page</h3>
        <p class="admin-muted" style="font-size:13px;margin-top:0">
            Shown cheapest first. The <strong>highlighted</strong> pack is outlined and preselected. A badge is the small label above a pack
            (e.g. “Most popular”, “Best for studios”) — leave it blank for none. Leave a whole row blank to drop it.
            These are also the packs new partners see on their My Credits page.
        </p>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Price (<?= e(GDD_CURRENCY_SYMBOL) ?>)</th><th>Credits given</th><th>Badge</th><th style="text-align:center">Highlighted</th></tr></thead>
                <tbody>
                    <?php foreach ($packs as $i => $pack): ?>
                        <tr>
                            <td><input type="number" name="packs[<?= $i ?>][price]" min="0" value="<?= e((string)$pack['price']) ?>" style="width:120px"></td>
                            <td><input type="number" name="packs[<?= $i ?>][credits]" min="0" value="<?= e((string)$pack['credits']) ?>" style="width:120px"></td>
                            <td><input type="text" name="packs[<?= $i ?>][badge]" maxlength="24" value="<?= e((string)$pack['badge']) ?>" placeholder="e.g. Most popular" style="width:170px"></td>
                            <td style="text-align:center"><input type="radio" name="preselected" value="<?= $i ?>" <?= $i === (int)$offer['preselected'] && $pack['price'] !== '' ? 'checked' : '' ?> aria-label="Highlight this pack"></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" class="admin-muted" style="font-size:12.5px">No pack highlighted</td>
                        <td style="text-align:center"><input type="radio" name="preselected" value="" <?= (int)$offer['preselected'] < 0 ? 'checked' : '' ?> aria-label="Highlight no pack"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="admin-form-row admin-mt">
            <label>Bonus label <?= $hint('{bonus} = credits above the price. Blank hides it') ?>
                <input type="text" name="bonus_label" maxlength="30" value="<?= e($offer['bonus_label']) ?>">
            </label>
            <label class="admin-checkbox" style="align-self:end">
                <input type="checkbox" name="show_per_item" value="1" <?= $offer['show_per_item'] ? 'checked' : '' ?>>
                Show the price per basic item on each pack
            </label>
        </div>
    </div>

    <div class="admin-card admin-mt" id="trial">
        <h3 class="admin-card-title">Free trial</h3>
        <?php if (!$trialsReady): ?>
            <p class="admin-muted" style="font-size:13px;margin:0 0 10px">
                Run <code>php tools/run-migration.php migrations/2026_09_23_partner_trials.sql</code> to switch on free trials.
                Until then the settings below are saved but no trial is offered.
            </p>
        <?php endif; ?>
        <p class="admin-muted" style="font-size:13px;margin-top:0">
            Shown as the last card beside the packs. A trial account starts at once with these credits, and everything it creates —
            photos, videos and QR links — is <strong>deleted automatically</strong> after the days below. The trial ends when you mark
            one of their credit packs paid. Turning it off also hides the trial on <code>/dex</code> and the home page.
        </p>
        <label class="admin-checkbox">
            <input type="checkbox" name="dex_trial_enabled" value="1" <?= $trial['enabled'] ? 'checked' : '' ?>>
            Offer a free trial on the sign-up page
        </label>
        <div class="admin-form-row">
            <label>Trial credits <?= $hint('Default ' . ArPartner::DEFAULT_TRIAL_CREDITS) ?>
                <input type="number" name="dex_trial_credits" min="0" max="1000000" required value="<?= (int)$trial['credits'] ?>">
            </label>
            <label>Delete trial content after (days) <?= $hint('Default ' . ArPartner::DEFAULT_TRIAL_DAYS) ?>
                <input type="number" name="dex_trial_days" min="1" max="365" required value="<?= (int)$trial['days'] ?>">
            </label>
        </div>
        <div class="admin-form-row">
            <label>Card badge <?= $hint('Blank hides it') ?>
                <input type="text" name="trial_ribbon" maxlength="24" value="<?= e($offer['trial_ribbon']) ?>">
            </label>
            <label>Card title
                <input type="text" name="trial_title" maxlength="20" value="<?= e($offer['trial_title']) ?>">
            </label>
            <label>Card sub-line <?= $hint('Blank hides it') ?>
                <input type="text" name="trial_sub" maxlength="30" value="<?= e($offer['trial_sub']) ?>">
            </label>
            <label>Deletion warning <?= $hint('Must include {days}') ?>
                <input type="text" name="trial_warning" maxlength="40" value="<?= e($offer['trial_warning']) ?>">
            </label>
        </div>
        <p class="admin-muted" style="font-size:12.5px;margin:0">
            {credits} and {days} are filled in from the numbers above. Changes apply to trial accounts and trial content created from now on.
        </p>
    </div>

    <div class="admin-card admin-mt">
        <h3 class="admin-card-title">Sign-up page wording</h3>
        <div class="admin-form-row">
            <label>Heading, with a trial on offer
                <input type="text" name="heading" maxlength="80" value="<?= e($offer['heading']) ?>">
            </label>
            <label>Heading, without a trial
                <input type="text" name="heading_no_trial" maxlength="80" value="<?= e($offer['heading_no_trial']) ?>">
            </label>
        </div>
        <label>Reasons to buy <?= $hint('One per line, shown with a tick under the packs when a pack is chosen. Up to 6; blank hides the list') ?>
            <textarea name="perks" rows="4"><?= e(implode("\n", $offer['perks'])) ?></textarea>
        </label>
        <label>Payment note under the packs <?= $hint('Blank hides it') ?>
            <textarea name="buy_note" rows="2"><?= e($offer['buy_note']) ?></textarea>
        </label>
        <div class="admin-form-row">
            <label>Buy button <?= $hint('Must include {price}; {credits} also works') ?>
                <input type="text" name="buy_button" maxlength="40" value="<?= e($offer['buy_button']) ?>">
            </label>
            <label>Trial button
                <input type="text" name="trial_button" maxlength="40" value="<?= e($offer['trial_button']) ?>">
            </label>
        </div>
        <label>Line under the buy button <?= $hint('Blank hides it') ?>
            <input type="text" name="buy_footnote" maxlength="120" value="<?= e($offer['buy_footnote']) ?>">
        </label>
        <label>Trial terms <?= $hint('One per line, shown when the trial card is chosen. The deletion term is always shown first and cannot be removed') ?>
            <textarea name="trial_terms" rows="3"><?= e(implode("\n", $offer['trial_terms'])) ?></textarea>
        </label>
    </div>

    <div class="admin-card admin-mt">
        <h3 class="admin-card-title">Default pricing for new partners</h3>
        <p class="admin-muted" style="font-size:13px;margin-top:0">
            Each item — a single, or one page of an album — costs the base rate + its video-length surcharge + the validity surcharge.
            Untick an option to leave it out. The base rate also sets the “per item” figure on the packs.
        </p>
        <label style="max-width:240px">Base rate per item (credits) *
            <input type="number" name="base_credits" min="1" required value="<?= (int)$offer['base_credits'] ?>">
        </label>
        <?php require viewPath('admin/partials/_ar_pricing_tables.php'); ?>
    </div>

    <div class="admin-form-actions admin-mt">
        <button type="submit" class="admin-btn admin-btn-primary">Save sign-up plans &amp; pricing</button>
        <a href="<?= url('/admin/ar-partners') ?>" class="admin-btn">Cancel</a>
    </div>
</form>
