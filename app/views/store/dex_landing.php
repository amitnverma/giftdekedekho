<?php
/**
 * DEx — Digital Experience landing page (/dex).
 *
 * The web version of the "Interactive Memories Catalogue" PDF, laid out as one
 * scrolling page. All copy, images and colours come from $dex — Admin → Design
 * Editor → DEx Landing, over dexLandingDefaults() (the PDF's originals, whose
 * images live in /images/dex). The header carries the same DEx Studio partner
 * sign-in / registration as the shop's account menu; those links are fixed.
 */
$img = fn(string $file): string => asset('/images/dex/' . $file);
$src = fn(string $path): string => $path !== '' ? asset($path) : '';
$year = date('Y');

$hero  = $dex['hero'];
$steps = $dex['steps'];
$exp   = $dex['experiences'];
$coll  = $dex['collection'];
$part  = $dex['partners'];
// The free partner trial, advertised only while registration really gives one.
$trial = dexTrialOffer();
$trialBadge = $trial ? dexTrialText((string)($part['trial_badge'] ?? ''), $trial) : '';
$trialText  = $trial ? dexTrialText((string)($part['trial_text'] ?? ''), $trial) : '';
$trialCta   = $trial ? dexTrialText((string)($part['trial_cta'] ?? ''), $trial) : '';
$buyCta     = (string)($part['buy_cta'] ?? '') ?: 'Register & buy credits';
$demoUrl = $exp['demo_code'] !== '' ? url('/scan/' . $exp['demo_code']) : '';

// "My Baby 1st Album" → 1<sup>st</sup>, after escaping.
$label = fn(string $text): string => preg_replace('/(\d)(st|nd|rd|th)\b/', '$1<sup>$2</sup>', e($text));

// Colours from the Design Editor; the lighter tints are mixed from them here.
$mix = function (string $hex, string $with, float $amount): string {
    [$a, $b] = [sscanf($hex, '#%02x%02x%02x'), sscanf($with, '#%02x%02x%02x')];
    $out = '#';
    for ($i = 0; $i < 3; $i++) {
        $out .= sprintf('%02x', (int)round($a[$i] + ($b[$i] - $a[$i]) * $amount));
    }
    return $out;
};
$c = $dex['colors'];
$cssVars = [
    '--dex-navy'       => $c['navy'],
    '--dex-navy-2'     => $mix($c['navy'], '#ffffff', .07),
    '--dex-gold'       => $c['gold'],
    '--dex-gold-dark'  => $c['gold_dark'],
    '--dex-gold-light' => $c['gold_light'],
    '--dex-cream'      => $c['cream'],
    '--dex-cream-2'    => $mix($c['cream'], '#ffffff', .5),
    '--dex-line'       => $mix($c['cream'], $c['gold'], .28),
    '--dex-ink'        => $c['ink'],
];

// Tagline sentences each get a span so they can stack on phones.
$taglineParts = array_values(array_filter(array_map('trim', preg_split('/(?<=\.)\s+/', $hero['tagline']))));

$icons = [
    'camera'  => '<path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h1.8l1.4-2h4.6l1.4 2h1.8A2.5 2.5 0 0 1 20 8.5v8a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5z"/><circle cx="12" cy="12.5" r="3.5"/>',
    'link'    => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1.2 1.2"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1.2-1.2"/>',
    'play'    => '<path d="M8 5.5v13l10.5-6.5z" fill="currentColor" stroke="none"/>',
    'heart'   => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.2a4.3 4.3 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>',
    'gift'    => '<rect x="4" y="9" width="16" height="11" rx="1"/><path d="M3 9h18M12 9v11M12 9c-1.5-3.5-6-4-6-1.5S10 9 12 9zm0 0c1.5-3.5 6-4 6-1.5S14 9 12 9z"/>',
    'diamond' => '<path d="M6.5 4h11L21 9l-9 11L3 9z"/><path d="M3 9h18M9.5 4 8 9l4 11 4-11-1.5-5"/>',
    'infinity'=> '<path d="M12 12c-2-2.8-3.7-4-5.5-4a4 4 0 0 0 0 8c1.8 0 3.5-1.2 5.5-4zm0 0c2 2.8 3.7 4 5.5 4a4 4 0 0 0 0-8c-1.8 0-3.5 1.2-5.5 4z"/>',
    'people'  => '<circle cx="12" cy="7.5" r="2.5"/><circle cx="5.5" cy="9" r="2"/><circle cx="18.5" cy="9" r="2"/><path d="M7.5 19c0-3 2-5 4.5-5s4.5 2 4.5 5M2.5 18.5c0-2.3 1.3-3.8 3-3.8 1 0 1.8.4 2.3 1M21.5 18.5c0-2.3-1.3-3.8-3-3.8-1 0-1.8.4-2.3 1"/>',
    'qr'      => '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><rect x="7" y="7" width="4" height="4"/><rect x="13" y="7" width="4" height="4"/><rect x="7" y="13" width="4" height="4"/><path d="M13 13h2v2h-2zM15 15h2v2h-2z"/>',
    'tap'     => '<path d="M10 11V5.5a1.5 1.5 0 0 1 3 0V11l3.6.8a2 2 0 0 1 1.5 2.3l-.8 4.4a2 2 0 0 1-2 1.5H11a2 2 0 0 1-1.6-.8L6.3 15a1.4 1.4 0 0 1 2-2L10 14.5"/><path d="M7.5 5.5a4 4 0 0 1 8 0"/>',
    'play-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="M10.2 8.8v6.4l5-3.2z"/>',
    'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'caret'   => '<path d="M6 9l6 6 6-6"/>',
    'studio'  => '<rect x="3.5" y="4.5" width="17" height="12" rx="1.5"/><path d="M8 20h8M12 16.5V20"/><path d="M10.5 8.5v5l4-2.5z"/>',
    'coins'   => '<ellipse cx="12" cy="6.5" rx="7" ry="2.5"/><path d="M5 6.5v5c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-5M5 11.5v5c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-5"/>',
    'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"/>',
    'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
];
$icon = fn(string $name, string $cls = 'dex-ico'): string =>
    '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';

// Icons stay with their position; the words beside them are editable.
$verbIcons    = ['camera', 'link', 'play', 'heart'];
$promiseIcons = ['gift', 'diamond', 'infinity', 'people'];
$stepIcons    = ['qr', 'link', 'play'];
$benefitIcons = ['qr', 'tap', 'play-circle', 'heart'];
$pointIcons   = ['studio', 'qr', 'coins'];

$studioLabel = $partnerOn ? 'Open DEx Studio' : 'Partner sign in';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($dex['meta_title']) ?> | <?= e($siteName) ?></title>
<meta name="description" content="<?= e($dex['meta_description']) ?>">
<link rel="canonical" href="<?= e(url('/dex')) ?>">
<meta property="og:title" content="<?= e($dex['meta_title']) ?>">
<meta property="og:description" content="<?= e($dex['meta_description']) ?>">
<?php if ($hero['image'] !== ''): ?><meta property="og:image" content="<?= e($src($hero['image'])) ?>"><?php endif; ?>
<link rel="icon" href="<?= e($img('dex-logo.webp')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Allura&family=Cinzel:wght@500;600;700;800;900&family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('public/css/dex.css') ?>?v=<?= filemtime(BASE_PATH . '/public/css/dex.css') ?>">
<style>:root{<?php foreach ($cssVars as $k => $v): ?><?= $k ?>:<?= e($v) ?>;<?php endforeach; ?>}</style>
</head>
<body class="dex-page">

<!-- ═══════════════ Header ═══════════════ -->
<header class="dex-nav" id="dexNav">
  <div class="dex-wrap dex-nav-inner">
    <a href="<?= url('/dex') ?>" class="dex-brand" aria-label="DEx — Digital Experience home">
      <img src="<?= e($img('dex-logo.webp')) ?>" alt="DEx — Digital Emotions. Scan, Connect, Relive" width="438" height="354">
    </a>

    <nav class="dex-links" aria-label="Page sections">
      <a href="#how-it-works">How it works</a>
      <a href="#experiences">Experiences</a>
      <?php if ($coll["products"]): ?><a href="#collection">Collection</a><?php endif; ?>
      <a href="#partners">For partners</a>
    </nav>

    <div class="dex-nav-actions">
      <a href="<?= url('/') ?>" class="dex-shop-link">Shop <?= e($siteName) ?></a>

      <div class="dex-acct" data-dex-menu>
        <button type="button" class="dex-acct-trigger" aria-expanded="false" aria-controls="dexAcctPanel">
          <span class="dex-acct-av" aria-hidden="true"><?= $icon('user') ?></span>
          <span class="dex-acct-text">
            <small><?= $partnerOn ? 'Signed in' : 'Hello, partner' ?></small>
            <strong>DEx Studio</strong>
          </span>
          <?= $icon('caret', 'dex-acct-caret') ?>
        </button>
        <div class="dex-acct-panel" id="dexAcctPanel">
          <div class="dex-acct-brand">
            <span class="dex-mark" aria-hidden="true">DEx</span>
            <div>
              <strong>DEx Studio</strong>
              <span>Digital Experience</span>
            </div>
          </div>
          <a href="<?= url('/partner/login') ?>" class="dex-btn dex-btn-navy dex-btn-block">
            <?= e($studioLabel) ?> <?= $icon('arrow') ?>
          </a>
          <small class="dex-acct-note">Separate login from your shopping account.</small>
          <?php if (!$partnerOn): ?>
            <p class="dex-acct-new">New to DEx? <a href="<?= url('/partner/register') ?>">Become a DEx partner</a></p>
          <?php endif; ?>
          <div class="dex-acct-customer">
            <?php if ($customerOn): ?>
              Shopping with us? <a href="<?= url('/account') ?>">Your account</a>
            <?php else: ?>
              Shopping with us? <a href="<?= url('/account/login') ?>">Customer sign in</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!$partnerOn): ?>
        <a href="<?= url('/partner/register') ?>" class="dex-btn dex-btn-gold dex-nav-cta">Become a Partner</a>
      <?php endif; ?>

      <button type="button" class="dex-burger" aria-expanded="false" aria-controls="dexMobile" aria-label="Open menu">
        <?= $icon('menu') ?>
      </button>
    </div>
  </div>

  <div class="dex-mobile" id="dexMobile" hidden>
    <div class="dex-wrap">
      <a href="#how-it-works">How it works</a>
      <a href="#experiences">Experiences</a>
      <?php if ($coll["products"]): ?><a href="#collection">Collection</a><?php endif; ?>
      <a href="#partners">For partners</a>
      <div class="dex-mobile-cta">
        <a href="<?= url('/partner/login') ?>" class="dex-btn dex-btn-navy"><?= e($studioLabel) ?></a>
        <?php if (!$partnerOn): ?>
          <a href="<?= url('/partner/register') ?>" class="dex-btn dex-btn-gold">Become a DEx partner</a>
        <?php endif; ?>
      </div>
      <a href="<?= url('/') ?>" class="dex-mobile-shop">← Shop <?= e($siteName) ?></a>
    </div>
  </div>
</header>

<main>

<!-- ═══════════════ Hero — Interactive Memories Catalogue ═══════════════ -->
<section class="dex-hero" id="top">
  <img class="dex-leaf dex-leaf-tl" src="<?= e($img('leaf-corner.webp')) ?>" alt="" aria-hidden="true">
  <div class="dex-wrap dex-hero-grid">
    <div class="dex-hero-copy">
      <h1 class="dex-hero-title">
        <?php if ($hero['kicker'] !== ''): ?><span class="dex-hero-kicker"><?= e($hero['kicker']) ?></span><?php endif; ?>
        <span class="dex-gold-text"><?= e($hero['title_gold']) ?></span>
        <?php if ($hero['title'] !== ''): ?><span class="dex-hero-cat"><?= e($hero['title']) ?></span><?php endif; ?>
      </h1>
      <span class="dex-rule" aria-hidden="true"></span>
      <?php if ($taglineParts): ?>
        <p class="dex-hero-tag"><?php foreach ($taglineParts as $i => $sentence): ?><?= $i ? ' ' : '' ?><span><?= e($sentence) ?></span><?php endforeach; ?></p>
      <?php endif; ?>

      <ul class="dex-verbs">
        <?php foreach ($hero['verbs'] as $i => $verb): if ($verb === '') continue; ?>
          <li><span class="dex-ring"><?= $icon($verbIcons[$i] ?? 'heart') ?></span><?= e($verb) ?></li>
        <?php endforeach; ?>
      </ul>

      <div class="dex-hero-ctas">
        <a href="#how-it-works" class="dex-btn dex-btn-navy"><?= e($hero['cta_primary']) ?></a>
        <a href="#try-it" class="dex-btn dex-btn-line"><?= e($hero['cta_demo']) ?></a>
      </div>
      <?php if ($trial && !$partnerOn && $trialBadge !== ''): ?>
        <a href="#partners" class="dex-hero-trial">For businesses · <strong><?= e($trialBadge) ?></strong> <?= $icon('arrow') ?></a>
      <?php endif; ?>
    </div>

    <div class="dex-hero-visual">
      <?php if ($hero['script_1'] !== '' || $hero['script_2'] !== ''): ?>
        <p class="dex-script dex-hero-script"><?= e($hero['script_1']) ?><?php if ($hero['script_2'] !== ''): ?><br><span><?= e($hero['script_2']) ?></span><?php endif; ?> <i aria-hidden="true">♡</i></p>
      <?php endif; ?>
      <?php if ($hero['image'] !== ''): ?>
        <figure class="dex-hero-photo">
          <img src="<?= e($src($hero['image'])) ?>"
               alt="A framed family photo with a play button, and a phone playing the same moment as a video">
        </figure>
      <?php endif; ?>
    </div>
  </div>

  <div class="dex-wrap">
    <ul class="dex-promise dex-reveal">
      <?php foreach ($hero['promises'] as $i => $pr): if (($pr['title'] ?? '') === '' && ($pr['sub'] ?? '') === '') continue; ?>
        <li><?= $icon($promiseIcons[$i] ?? 'gift') ?><strong><?= e($pr['title']) ?></strong><span><?= e($pr['sub']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($hero['bottom_line'] !== ''): ?>
      <p class="dex-tagline"><span><?= e($hero['bottom_line']) ?></span></p>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ 3 Easy Steps ═══════════════ -->
<section class="dex-steps" id="how-it-works">
  <img class="dex-leaf dex-leaf-tr" src="<?= e($img('leaf-corner.webp')) ?>" alt="" aria-hidden="true">
  <div class="dex-wrap">
    <header class="dex-head">
      <?php if ($steps['script'] !== ''): ?><p class="dex-script dex-head-script"><?= e($steps['script']) ?></p><?php endif; ?>
      <h2 class="dex-steps-title">
        <?php if ($steps['title'] !== ''): ?><span><?= e($steps['title']) ?></span><?php endif; ?>
        <strong><?= e($steps['title_big']) ?></strong>
      </h2>
      <?php if ($steps['banner'] !== ''): ?><p class="dex-banner"><span><?= e($steps['banner']) ?></span></p><?php endif; ?>
      <?php if ($steps['subline'] !== ''): ?><p class="dex-head-sub dex-dotrule"><?= e($steps['subline']) ?></p><?php endif; ?>
    </header>

    <ol class="dex-step-list">
      <?php foreach ($steps['items'] as $i => $st): ?>
        <li class="dex-step dex-reveal">
          <span class="dex-step-no"><?= sprintf('%02d', $i + 1) ?></span>
          <span class="dex-step-ico"><?= $icon($stepIcons[$i] ?? 'play') ?></span>
          <h3><?= e($st['title']) ?></h3>
          <?php if (($st['desc'] ?? '') !== ''): ?><p><?= e($st['desc']) ?></p><?php endif; ?>
          <?php if (($st['image'] ?? '') !== ''): ?>
            <img src="<?= e($src($st['image'])) ?>" loading="lazy" alt="<?= e($st['title'] . ' — ' . ($st['desc'] ?? '')) ?>">
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>

    <?php $benefits = array_filter($steps['benefits'], fn($b) => $b !== ''); ?>
    <?php if ($benefits): ?>
      <ul class="dex-benefits dex-reveal">
        <?php foreach ($benefits as $i => $b): ?>
          <li><span class="dex-ring dex-ring-light"><?= $icon($benefitIcons[$i] ?? 'heart') ?></span><?= e($b) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ Experiences + live demo ═══════════════ -->
<section class="dex-exp" id="experiences">
  <img class="dex-leaf dex-leaf-tl" src="<?= e($img('leaf-corner.webp')) ?>" alt="" aria-hidden="true">
  <div class="dex-wrap">
    <header class="dex-head">
      <?php if ($exp['eyebrow'] !== ''): ?><p class="dex-eyebrow"><?= e($exp['eyebrow']) ?></p><?php endif; ?>
      <h2 class="dex-h2"><?= e($exp['heading']) ?></h2>
      <span class="dex-rule dex-rule-c" aria-hidden="true"></span>
    </header>

    <div class="dex-exp-grid">
      <?php if ($exp['main_image'] !== ''): ?>
        <figure class="dex-exp-hero dex-reveal">
          <img src="<?= e($src($exp['main_image'])) ?>" loading="lazy" alt="The framed photo used by the live demo">
        </figure>
      <?php endif; ?>

      <aside class="dex-try dex-reveal" id="try-it" aria-label="<?= e($exp['try_heading'] ?: 'Live demo') ?>">
        <?php if ($exp['try_eyebrow'] !== ''): ?><p class="dex-eyebrow"><?= e($exp['try_eyebrow']) ?></p><?php endif; ?>
        <?php if ($exp['try_heading'] !== ''): ?><h3><?= e($exp['try_heading']) ?></h3><?php endif; ?>
        <?php if ($exp['qr_image'] !== ''): ?>
          <?php if ($demoUrl !== ''): ?>
            <a href="<?= e($demoUrl) ?>" class="dex-qr" aria-label="Open the live demo scanner">
          <?php else: ?>
            <span class="dex-qr">
          <?php endif; ?>
              <img src="<?= e($src($exp['qr_image'])) ?>" width="240" height="240" alt="QR code for the live demo">
          <?= $demoUrl !== '' ? '</a>' : '</span>' ?>
        <?php endif; ?>
        <?php if ($exp['scan_label'] !== ''): ?><p class="dex-qr-scan"><?= e($exp['scan_label']) ?></p><?php endif; ?>
        <?php if ($exp['scan_then'] !== ''): ?><p class="dex-qr-then"><?= e($exp['scan_then']) ?></p><?php endif; ?>
        <?php if ($exp['demo_code'] !== ''): ?><p class="dex-qr-code"><?= e($exp['demo_code']) ?></p><?php endif; ?>
        <?php if ($exp['try_hint'] !== ''): ?><p class="dex-try-hint"><?= e($exp['try_hint']) ?></p><?php endif; ?>
      </aside>
    </div>

    <?php $gallery = array_values(array_filter($exp['gallery'], fn($g) => $g !== '')); ?>
    <?php if ($gallery): ?>
      <div class="dex-exp-row">
        <?php foreach ($gallery as $i => $g): ?>
          <figure class="dex-reveal<?= $i === 2 ? ' dex-exp-album' : '' ?>"><img src="<?= e($src($g)) ?>" loading="lazy" alt=""></figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ Collection ═══════════════ -->
<?php if ($coll['products']): ?>
<section class="dex-collection" id="collection">
  <img class="dex-leaf dex-leaf-tr" src="<?= e($img('leaf-corner.webp')) ?>" alt="" aria-hidden="true">
  <div class="dex-wrap">
    <header class="dex-head">
      <?php if ($coll['eyebrow'] !== ''): ?><p class="dex-eyebrow"><?= e($coll['eyebrow']) ?></p><?php endif; ?>
      <h2 class="dex-h2"><?= e($coll['heading']) ?></h2>
      <span class="dex-rule dex-rule-c" aria-hidden="true"></span>
    </header>

    <ul class="dex-products">
      <?php foreach ($coll['products'] as $pr): ?>
        <li class="dex-product dex-reveal">
          <div class="dex-product-img">
            <img src="<?= e($src($pr['image'])) ?>" loading="lazy" alt="<?= e($pr['label']) ?>">
          </div>
          <?php if ($pr['label'] !== ''): ?><p><?= $label($pr['label']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════ Partner CTA ═══════════════ -->
<section class="dex-partner" id="partners">
  <div class="dex-wrap dex-partner-grid">
    <div class="dex-partner-copy">
      <span class="dex-studio-badge"><b>DEx</b> Studio · Digital Experience</span>
      <h2><?= e($part['heading']) ?></h2>
      <?php if ($part['text'] !== ''): ?><p><?= e($part['text']) ?></p><?php endif; ?>
      <ul class="dex-partner-points">
        <?php foreach ($part['points'] as $i => $pt): if (($pt['title'] ?? '') === '' && ($pt['desc'] ?? '') === '') continue; ?>
          <li><?= $icon($pointIcons[$i] ?? 'qr') ?><span><strong><?= e($pt['title']) ?></strong><?= e($pt['desc']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="dex-partner-card dex-reveal">
      <?php if ($partnerOn): ?>
        <h3>Welcome back</h3>
        <p>You're signed in to DEx Studio.</p>
        <a href="<?= url('/partner/login') ?>" class="dex-btn dex-btn-gold dex-btn-block">Open DEx Studio <?= $icon('arrow') ?></a>
      <?php else: ?>
        <?php if ($part['card_title'] !== ''): ?><h3><?= e($part['card_title']) ?></h3><?php endif; ?>
        <?php if ($part['card_text'] !== ''): ?><p><?= e($part['card_text']) ?></p><?php endif; ?>
        <?php /* Buying leads; the trial is the secondary way in for those not ready to pay. */ ?>
        <a href="<?= url('/partner/register') ?>" class="dex-btn dex-btn-gold dex-btn-block"><?= e($buyCta) ?> <?= $icon('arrow') ?></a>
        <?php if ($trial && $trialCta !== ''): ?>
          <a href="<?= url('/partner/register?plan=trial') ?>" class="dex-trial-link"><?= e($trialCta) ?></a>
          <?php if ($trialText !== ''): ?><small class="dex-trial-note"><?= e($trialText) ?></small><?php endif; ?>
        <?php endif; ?>
        <div class="dex-or"><span>Already a partner?</span></div>
        <a href="<?= url('/partner/login') ?>" class="dex-btn dex-btn-ghost dex-btn-block">Partner sign in</a>
        <small>Separate login from your shopping account.</small>
      <?php endif; ?>
    </div>
  </div>
</section>

</main>

<!-- ═══════════════ Footer ═══════════════ -->
<footer class="dex-foot">
  <svg class="dex-foot-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0 90V38C240 6 520 0 760 22s520 48 680 10v58z" style="fill:var(--dex-navy)"/>
    <path d="M0 38C240 6 520 0 760 22s520 48 680 10" fill="none" style="stroke:var(--dex-gold)" stroke-width="2"/>
  </svg>
  <div class="dex-foot-body">
    <div class="dex-wrap dex-foot-grid">
      <div class="dex-foot-brand">
        <img src="<?= e($img('dex-logo.webp')) ?>" alt="DEx — Digital Emotions" width="438" height="354" loading="lazy">
        <?php if ($dex["footer_line"] !== ""): ?><p><?= e($dex["footer_line"]) ?></p><?php endif; ?>
      </div>
      <nav class="dex-foot-links" aria-label="DEx">
        <h4>DEx</h4>
        <a href="#how-it-works">How it works</a>
        <a href="#experiences">Experiences</a>
        <?php if ($coll["products"]): ?><a href="#collection">Collection</a><?php endif; ?>
      </nav>
      <nav class="dex-foot-links" aria-label="Partners">
        <h4>Partners</h4>
        <a href="<?= url('/partner/login') ?>"><?= e($studioLabel) ?></a>
        <?php if (!$partnerOn): ?><a href="<?= url('/partner/register') ?>">Become a DEx partner</a><?php endif; ?>
        <a href="<?= url('/contact') ?>">Contact us</a>
      </nav>
      <nav class="dex-foot-links" aria-label="<?= e($siteName) ?>">
        <h4><?= e($siteName) ?></h4>
        <a href="<?= url('/') ?>">Shop gifts</a>
        <a href="<?= url('/about') ?>">About us</a>
        <a href="<?= url('/account/login') ?>">Customer sign in</a>
      </nav>
    </div>
    <div class="dex-wrap dex-foot-base">
      <span class="dex-diamond-rule" aria-hidden="true">◆</span>
      <p>© <?= e($year) ?> <?= e($siteName) ?> · DEx — Digital Experience</p>
    </div>
  </div>
</footer>

<script>
(function () {
  var nav = document.getElementById('dexNav');
  var menu = nav.querySelector('[data-dex-menu]');
  var trigger = menu.querySelector('.dex-acct-trigger');
  var burger = nav.querySelector('.dex-burger');
  var mobile = document.getElementById('dexMobile');

  function setMenu(open) {
    menu.classList.toggle('is-open', open);
    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function setMobile(open) {
    mobile.hidden = !open;
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    nav.classList.toggle('is-mobile-open', open);
  }

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    setMobile(false);
    setMenu(!menu.classList.contains('is-open'));
  });
  burger.addEventListener('click', function (e) {
    e.stopPropagation();
    setMenu(false);
    setMobile(mobile.hidden);
  });
  document.addEventListener('click', function (e) {
    if (!menu.contains(e.target)) setMenu(false);
  });
  mobile.addEventListener('click', function (e) {
    if (e.target.closest('a')) setMobile(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { setMenu(false); setMobile(false); }
  });

  function onScroll() { nav.classList.toggle('is-scrolled', window.scrollY > 8); }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // Gentle fade-up as sections enter the viewport.
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var items = document.querySelectorAll('.dex-reveal');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    document.documentElement.classList.add('dex-js');
    items.forEach(function (el) { io.observe(el); });
  }
})();
</script>
</body>
</html>
