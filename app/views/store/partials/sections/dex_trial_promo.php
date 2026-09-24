<?php
/**
 * Home page invitation to businesses: a free DEx Studio trial.
 *
 * Edited in Design Editor → DEx Free Trial, ordered in Page Layout. On by
 * default, and hidden whenever registration would not give a trial (switched
 * off in AR Partners, or its migration not run), so it never promises one.
 * {credits} and {days} in the copy are filled in from the live trial settings.
 */
$promo = $sections['dex_trial_promo'] ?? [];
if (($promo['is_active'] ?? true) === false) return;

$trial = dexTrialOffer();
if ($trial === null) return;

// A dark DEx-navy panel by default, so its own colours are the fallbacks.
$_dtStyle = $promo['style'] ?? [];
if (empty($_dtStyle['bg_color'])) $_dtStyle['bg_color'] = '#0b2234';
$_dtAlign = in_array($_dtStyle['align'] ?? '', ['left', 'center', 'right'], true) ? $_dtStyle['align'] : 'center';
$_dtKickerCol = !empty($_dtStyle['kicker_color']) ? $_dtStyle['kicker_color'] : '#e2bf6c';
$_dtHeadStyle = 'color:' . e(!empty($_dtStyle['heading_color']) ? $_dtStyle['heading_color'] : '#ffffff') . ';margin:10px 0 14px;';
if (!empty($_dtStyle['heading_size'])) $_dtHeadStyle .= 'font-size:' . (int)$_dtStyle['heading_size'] . 'px;';
$_dtTextStyle = 'color:' . e(!empty($_dtStyle['subtext_color']) ? $_dtStyle['subtext_color'] : '#d9cfb8') . ';line-height:1.7;margin-bottom:26px;';
if (!empty($_dtStyle['subtext_size'])) $_dtTextStyle .= 'font-size:' . (int)$_dtStyle['subtext_size'] . 'px;';

$_dtKicker  = dexTrialText((string)($promo['kicker'] ?? 'For businesses · DEx Studio'), $trial);
$_dtHeading = dexTrialText((string)($promo['heading'] ?? 'Sell Living Photo DEx to your own customers'), $trial);
$_dtText    = dexTrialText((string)($promo['text'] ?? 'Your own branded DEx Studio — pay only for what you create, no subscription. Not sure yet? Try it free with {credits} credits; trial content is deleted automatically after {days} days.'), $trial);
$_dtCta     = dexTrialText((string)($promo['cta_text'] ?? 'or try it free first'), $trial);
$_dtBuy     = dexTrialText((string)($promo['buy_text'] ?? 'Become a DEx partner'), $trial);
$_dtLink    = dexTrialText((string)($promo['link_text'] ?? 'Learn about DEx'), $trial);
?>
<section class="section" style="<?= sectionBgStyle($_dtStyle) ?>">
  <div class="container">
    <div class="reveal" style="max-width:760px;margin:0 auto;text-align:<?= e($_dtAlign) ?>;color:#fff">
      <?php if ($_dtKicker !== ''): ?><span class="gdd-kicker" style="color:<?= e($_dtKickerCol) ?>"><?= e($_dtKicker) ?></span><?php endif; ?>
      <h2 style="<?= $_dtHeadStyle ?>"><?= e($_dtHeading) ?></h2>
      <?php if ($_dtText !== ''): ?><p style="<?= $_dtTextStyle ?>"><?= e($_dtText) ?></p><?php endif; ?>
      <div style="display:flex;gap:14px;flex-wrap:wrap;justify-content:<?= $_dtAlign === 'left' ? 'flex-start' : ($_dtAlign === 'right' ? 'flex-end' : 'center') ?>;align-items:center">
        <?php /* Buying leads; the trial is the secondary way in for those not ready to pay. */ ?>
        <a href="<?= url('/partner/register') ?>" class="btn btn-primary" style="font-size:16px;padding:14px 32px"><?= e($_dtBuy ?: 'Become a DEx partner') ?></a>
        <?php if ($_dtCta !== ''): ?>
          <a href="<?= url('/partner/register?plan=trial') ?>" style="color:#fff;font-weight:600;text-decoration:underline"><?= e($_dtCta) ?></a>
        <?php endif; ?>
        <?php if ($_dtLink !== ''): ?>
          <a href="<?= url('/dex') ?>" style="color:<?= e($_dtKickerCol) ?>;font-weight:600;text-decoration:underline"><?= e($_dtLink) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
