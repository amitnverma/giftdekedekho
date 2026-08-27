<?php
/**
 * The Living Photo camera page — public /scan/{slug} and the admin live test.
 *
 * Standalone (no site layout) on purpose: it has to load fast on a phone and
 * fill the screen with the camera. Both callers render this same view so the
 * admin test proves the real customer path.
 *
 * MindAR's dist is an ES module that imports a bare "three" specifier, so an
 * import map is required — this is MindAR's own documented setup. Versions are
 * pinned: three's sRGBEncoding export (which mind-ar 1.2.5 imports) was removed
 * in later releases, so upgrading blindly would break the page.
 */
$isAdminTest = !empty($isAdminTest);
$frame = $frame ?? null;
// $frame is null on the scan-anything page, which matches every active frame.
$isMulti = $frame === null;
$metrics = !empty($frame['trackability_json']) ? json_decode($frame['trackability_json'], true) : null;
$aspect = 1.0;
if ($metrics && !empty($metrics['compiled_width']) && !empty($metrics['compiled_height'])) {
    $aspect = (float)$metrics['compiled_width'] / (float)$metrics['compiled_height'];
}

// ?debug=1 shows a live diagnostic overlay. Deliberately available on the public
// page too: when a recipient reports "nothing happens", this is the only way to
// find out which of the many possible causes it actually was.
$showDebug = isset($_GET['debug']) && $_GET['debug'] === '1';

$scanConfig = [
    'targetUrl' => $targetUrl,
    // Index in this array is the anchor index MindAR reports on a match, so a
    // single frame and the scan-anything bundle share one code path.
    'targets'   => $targets,
    'aspect'    => $aspect,
    'verifyUrl' => $isAdminTest ? $verifyUrl : null,
    'csrf'      => $isAdminTest ? $csrf : null,
    // Arriving here means a QR sticker was deliberately scanned, so go straight
    // to the camera. Not on the scan-anything page, which would start a
    // multi-megabyte download unasked, and not on the admin test, where the
    // intro carries the instructions for running the test.
    'autoStart' => !$isAdminTest && !$isMulti,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= $isAdminTest ? 'Live Scan Test' : 'Your Living Photo' ?> · <?= e($siteName) ?></title>
<style>
    * { box-sizing: border-box; }
    html, body {
        margin: 0; padding: 0; height: 100%; overflow: hidden;
        background: #000; color: #fff;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        -webkit-tap-highlight-color: transparent;
    }
    #arContainer { position: fixed; inset: 0; width: 100%; height: 100%; }
    #arContainer video, #arContainer canvas { object-fit: cover; }

    .ar-panel {
        position: fixed; inset: 0; z-index: 20;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        text-align: center; padding: 28px;
        background: radial-gradient(circle at 50% 40%, #1c1c22 0%, #000 100%);
    }
    .ar-panel h1 { font-size: 24px; margin: 0 0 12px; font-weight: 700; }
    .ar-panel p { font-size: 15px; line-height: 1.6; margin: 0 0 22px; color: #c9c9d1; max-width: 30em; }
    .ar-btn {
        display: inline-block; border: 0; border-radius: 999px; cursor: pointer;
        padding: 15px 34px; font-size: 16px; font-weight: 700;
        background: #e63946; color: #fff; font-family: inherit;
    }
    .ar-btn-ghost { background: rgba(255,255,255,.14); }
    .ar-thumb {
        width: 140px; height: 140px; object-fit: cover; border-radius: 14px;
        margin-bottom: 22px; border: 2px solid rgba(255,255,255,.22);
    }

    /* Scanning guidance over the live camera */
    #arStatus {
        position: fixed; inset: 0; z-index: 10; display: none;
        flex-direction: column; align-items: center; justify-content: space-between;
        padding: calc(26px + env(safe-area-inset-top)) 24px calc(34px + env(safe-area-inset-bottom));
        pointer-events: none;
    }
    /* The admin test has a topbar sitting where the title would go. */
    #arStatus.has-topbar { padding-top: calc(70px + env(safe-area-inset-top)); }

    /* Title above the viewfinder. Recipients were opening the camera and not
       knowing what they were meant to do with it, so the instruction lives on
       the camera screen rather than only on the intro they already tapped past. */
    /* Above the reticle's vignette, which is painted by a later sibling and
       would otherwise wash the title out. */
    .ar-guide-head, .ar-guide-foot { position: relative; z-index: 1; }
    .ar-guide-head { max-width: 24em; }
    .ar-guide-head h2 {
        margin: 0 0 6px; font-size: 22px; font-weight: 700; line-height: 1.25;
        text-shadow: 0 2px 12px rgba(0,0,0,.85);
    }
    .ar-guide-head p {
        margin: 0; font-size: 14.5px; line-height: 1.45; color: rgba(255,255,255,.88);
        text-shadow: 0 2px 10px rgba(0,0,0,.9);
    }

    /* Viewfinder: corner brackets sized to the photo's own shape, with a faint
       ghost of that photo inside them. A plain empty box gave no clue how far
       away or how square to hold the phone — lining the real photo up with the
       ghost is something anyone can do without being told. */
    .ar-reticle {
        position: relative; margin: auto;
        width: min(78vw, 360px, calc(48vh * var(--ar-target-aspect, 1)));
        aspect-ratio: var(--ar-target-aspect, 1);
        box-shadow: 0 0 0 100vmax rgba(0,0,0,.45);
    }
    .ar-corner {
        position: absolute; width: 34px; height: 34px;
        border: 3px solid #fff;
    }
    .ar-corner-tl { top: -3px; left: -3px;  border-right: 0; border-bottom: 0; border-top-left-radius: 16px; }
    .ar-corner-tr { top: -3px; right: -3px; border-left: 0;  border-bottom: 0; border-top-right-radius: 16px; }
    .ar-corner-bl { bottom: -3px; left: -3px;  border-right: 0; border-top: 0; border-bottom-left-radius: 16px; }
    .ar-corner-br { bottom: -3px; right: -3px; border-left: 0;  border-top: 0; border-bottom-right-radius: 16px; }

    .ar-ghost {
        position: absolute; inset: 0; width: 100%; height: 100%;
        object-fit: contain; opacity: .38;
        animation: arGhostPulse 2.8s ease-in-out infinite;
    }
    @keyframes arGhostPulse { 0%, 100% { opacity: .3; } 50% { opacity: .5; } }

    .ar-guide-foot { display: flex; flex-direction: column; align-items: center; gap: 9px; }
    /* Points up at the viewfinder, so the hint underneath is read as being
       about the box rather than about the whole screen. */
    .ar-guide-arrow {
        font-size: 20px; line-height: 1; color: #fff;
        text-shadow: 0 2px 10px rgba(0,0,0,.85);
        animation: arArrowNudge 1.8s ease-in-out infinite;
    }
    @keyframes arArrowNudge { 0%, 100% { transform: translateY(3px); opacity: .65; } 50% { transform: translateY(-3px); opacity: 1; } }

    @media (prefers-reduced-motion: reduce) {
        .ar-ghost, .ar-guide-arrow { animation: none; }
    }

    #arHint {
        background: rgba(0,0,0,.62); backdrop-filter: blur(8px);
        border-radius: 14px; padding: 13px 20px; font-size: 15px; font-weight: 500;
        line-height: 1.45; max-width: 22em; margin: 0;
    }
    .ar-topbar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 12;
        padding: calc(12px + env(safe-area-inset-top)) 16px 12px;
        display: flex; gap: 10px; align-items: center; justify-content: space-between;
        font-size: 13px; color: rgba(255,255,255,.85);
        background: linear-gradient(rgba(0,0,0,.55), transparent);
        pointer-events: none;
    }
    .ar-topbar a { pointer-events: auto; color: #fff; text-decoration: none; background: rgba(255,255,255,.18); padding: 7px 14px; border-radius: 999px; }

    /* Full-screen player */
    #arPlayer {
        position: fixed; inset: 0; z-index: 30; display: none;
        flex-direction: column; align-items: center; justify-content: center;
        /* Warm vignette rather than flat black — the video should feel like it
           is hanging on a wall, not like a media player took over the phone. */
        background: radial-gradient(circle at 50% 45%, #23201d 0%, #0b0a09 100%);
        padding: 2.4vmin;

        /* The video's shape. 16:9 is only the assumption used until the file's
           metadata arrives; the module replaces it with the real ratio, because
           a phone-shot portrait video letterboxed into a 16:9 box was playing
           at a fraction of the screen it could have filled. */
        --ar-video-aspect: 1.7778;
        /* Player padding + moulding + mat, both sides: 2 x (2.4 + 1.2 + 2). */
        --ar-chrome: 11.2vmin;
        --ar-avail-w: calc(100vw - var(--ar-chrome));
        /* The 9vmin keeps the unmute pill, which hangs below the moulding, on
           the screen. dvh (where supported) excludes the browser's own chrome,
           which vh does not. */
        --ar-avail-h: calc(100vh - var(--ar-chrome) - 9vmin - env(safe-area-inset-top) - env(safe-area-inset-bottom));
        --ar-avail-h: calc(100dvh - var(--ar-chrome) - 9vmin - env(safe-area-inset-top) - env(safe-area-inset-bottom));
    }

    /* The picture frame the video plays inside. Moulding, mat and a cast
       shadow, sized so the video still fills as much of a phone as possible. */
    .ar-frame {
        position: relative; display: none;
        max-width: 100%; max-height: 100%;
        padding: 2vmin;                                /* the mat */
        background: linear-gradient(145deg, #f6f1e7 0%, #e8e0d2 100%);
        border: 1.2vmin solid;
        border-image: linear-gradient(145deg, #a9793f 0%, #6d4620 45%, #c99a5b 70%, #7a5127 100%) 1;
        box-shadow:
            0 0 0 0.35vmin rgba(0,0,0,.35),            /* rebate shadow */
            0 3vmin 6vmin rgba(0,0,0,.65);             /* cast shadow */
    }
    .ar-frame::after {
        /* Faint glass sheen across the picture. */
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background: linear-gradient(118deg, rgba(255,255,255,.16) 0%, rgba(255,255,255,0) 42%);
    }
    .ar-frame.is-visible { display: block; }

    /* Both players are hidden by default — the module shows exactly one, so an
       empty <video> never stacks above an iframe. */
    /* As large as the screen allows for the video's own shape: capped by the
       width available, and by the height available once turned back into a
       width. --ar-video-aspect is per-element, so a portrait upload resizing
       itself never reshapes a 16:9 provider embed. */
    #arVideo, #arYoutube {
        display: none; background: #000;
        width: min(var(--ar-avail-w), calc(var(--ar-avail-h) * var(--ar-video-aspect)));
        max-width: 100%;
        aspect-ratio: var(--ar-video-aspect); height: auto;
    }
    #arYoutube iframe { width: 100%; height: 100%; border: 0; display: block; }

    /* Unmute prompt — only for embedded providers, which cannot be primed.
       Sits below the moulding so it never covers the player's own controls. */
    #arUnmute {
        position: absolute; left: 50%; transform: translateX(-50%);
        bottom: -7vmin; z-index: 35; display: none;
        border: 0; cursor: pointer; font-family: inherit; font-weight: 700;
        background: #e63946; color: #fff; border-radius: 999px;
        padding: 11px 22px; font-size: 14px; white-space: nowrap;
        box-shadow: 0 6px 18px rgba(0,0,0,.5);
    }
    #arClose {
        position: absolute; top: calc(14px + env(safe-area-inset-top)); right: 14px; z-index: 33;
        width: 40px; height: 40px; border-radius: 50%; border: 0; cursor: pointer;
        background: rgba(255,255,255,.22); color: #fff; font-size: 20px; line-height: 1; font-family: inherit;
    }
    #arTapToPlay {
        position: absolute; inset: 0; z-index: 32; display: none;
        flex-direction: column; align-items: center; justify-content: center;
        background: rgba(0,0,0,.66); cursor: pointer; gap: 14px;
    }
    #arTapToPlay span { font-size: 17px; font-weight: 600; }
    #arFallback {
        position: absolute; left: 0; right: 0; z-index: 34; display: none;
        bottom: calc(14px + env(safe-area-inset-bottom)); text-align: center; padding: 0 16px;
    }
    #arFallback a {
        display: inline-block; color: #fff; text-decoration: none; font-size: 13.5px;
        background: rgba(0,0,0,.62); backdrop-filter: blur(8px);
        padding: 10px 18px; border-radius: 999px;
    }
    .ar-play-ring {
        width: 76px; height: 76px; border-radius: 50%; background: #e63946;
        display: flex; align-items: center; justify-content: center; font-size: 26px;
    }

    /* Admin live-test confirmation */
    #arVerified {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 40; display: none;
        flex-direction: column; gap: 12px; align-items: center;
        padding: 22px 20px calc(26px + env(safe-area-inset-bottom));
        background: #0f7b3f; text-align: center;
    }
    #arVerified p { margin: 0; font-size: 15px; font-weight: 600; }
    .ar-test-badge {
        background: #f5b400; color: #241c00; font-weight: 800; font-size: 11px;
        letter-spacing: .06em; text-transform: uppercase; padding: 5px 11px; border-radius: 999px;
    }
</style>
</head>
<body>

<div id="arContainer"></div>

<?php if ($isAdminTest): ?>
    <div class="ar-topbar">
        <span class="ar-test-badge">Live scan test · <?= e($frame['slug']) ?></span>
        <a href="<?= e($backUrl) ?>">Back to admin</a>
    </div>
<?php endif; ?>

<!-- Intro / permission gate.
     On a frame's own /scan/{slug} this is only the loading state: the scanner
     starts the camera by itself and hides this. It comes back if the browser
     refuses the camera without a tap, and it stays the real gate on the
     scan-anything page and the admin test. A tap here is worth having when it
     happens — it is the gesture that lets the video play with sound later. -->
<div class="ar-panel" id="arIntro">
    <?php if ($photoUrl !== ''): ?>
        <img class="ar-thumb" src="<?= e($photoUrl) ?>" alt="">
        <!-- Labelled explicitly. Without this, someone holding a phone reads the
             thumbnail as the thing to scan — which would mean pointing the phone
             at its own screen. -->
        <p style="font-size:12px;color:#8b8b95;margin:0 0 18px;letter-spacing:.02em">
            ↑ This is the photo to look for — not the thing to scan
        </p>
    <?php endif; ?>
    <h1><?= $isAdminTest ? 'Live scan test' : 'Your Living Photo' ?></h1>
    <p>
        <?php if ($isAdminTest): ?>
            Open this page <strong>on a phone</strong>, then point it at the photo shown on your computer
            screen (use "Show photo full-screen" on the frame page) or at a printed proof.
            The frame is only marked verified when a real match fires — it cannot be printed or handed over until then.
        <?php elseif ($isMulti): ?>
            Point your camera at the <strong>printed photo in your frame</strong> and the video will start playing.
            Nothing to install — just allow camera access.
        <?php else: ?>
            Point your camera at the <strong>printed photo in your frame</strong> and the video will start playing.
            Nothing to install — just allow camera access.
        <?php endif; ?>
    </p>
    <?php if ($isMulti && !empty($bundleBytes)): ?>
        <p style="font-size:12px;color:#8b8b95;margin:-8px 0 18px">
            Recognises any <?= e($siteName) ?> Living Photo
            (<?= number_format($bundleBytes / 1048576, 1) ?>MB to load once)
        </p>
    <?php endif; ?>
    <button class="ar-btn" id="arStart" type="button">Start camera</button>
    <?php // $frame is null on the scan-anything page, which has no single frame. ?>
    <?php if (!$isAdminTest && ($frame['trackability_flag'] ?? null) === 'poor'): ?>
        <p style="margin-top:18px;font-size:13px;color:#f5b400">
            Tip: this photo is quite plain, so it may need good light and a steady hand.
        </p>
    <?php endif; ?>
</div>

<!-- Scanning guidance.
     --ar-target-aspect is the compiled target's own width/height, so the
     brackets are the shape of the photo being looked for, not an arbitrary
     square: filling them is the same action as framing the photo properly. -->
<div id="arStatus"<?= $isAdminTest ? ' class="has-topbar"' : '' ?> style="--ar-target-aspect:<?= $aspect > 0 ? round($aspect, 4) : 1 ?>">
    <div class="ar-guide-head">
        <h2><?= $isMulti ? 'Find your Living Photo' : 'Point at your photo' ?></h2>
        <p>
            <?php if ($photoUrl !== ''): ?>
                Line the real photo up with the faded one, until it fills the corners
            <?php else: ?>
                Hold your phone straight on, so the printed photo fills the corners
            <?php endif; ?>
        </p>
    </div>
    <div class="ar-reticle">
        <span class="ar-corner ar-corner-tl"></span>
        <span class="ar-corner ar-corner-tr"></span>
        <span class="ar-corner ar-corner-bl"></span>
        <span class="ar-corner ar-corner-br"></span>
        <?php if ($photoUrl !== ''): ?>
            <img class="ar-ghost" src="<?= e($photoUrl) ?>" alt="" aria-hidden="true">
        <?php endif; ?>
    </div>
    <div class="ar-guide-foot">
        <span class="ar-guide-arrow" aria-hidden="true">&#9650;</span>
        <p id="arHint">Point your camera at the photo</p>
    </div>
</div>

<!-- Errors -->
<div class="ar-panel" id="arError" style="display:none">
    <h1>Camera unavailable</h1>
    <p id="arErrorText"></p>
    <button class="ar-btn" type="button" data-retry>Try again</button>
</div>

<!-- Player -->
<div id="arPlayer">
    <button id="arClose" type="button" aria-label="Close video">×</button>
    <!-- The video plays inside a picture frame, so the moment reads as the
         gift coming alive rather than a media player taking over the screen.
         src is set by the module once a match identifies which frame it is. -->
    <div class="ar-frame" id="arFrame">
        <video id="arVideo" playsinline controls preload="auto"></video>
        <div id="arYoutube"></div>
        <button id="arUnmute" type="button">🔊 Tap for sound</button>
    </div>
    <div id="arTapToPlay">
        <div class="ar-play-ring">▶</div>
        <span>Tap to play your video</span>
    </div>
    <!-- Always offered once an embed is built. A provider can refuse to play
         inside an iframe (YouTube reports "player configuration error 153"),
         and an iframe reports success even while showing its own error, so this
         is the only reliable way to guarantee the recipient reaches the video. -->
    <div id="arFallback">
        <a href="#" target="_blank" rel="noopener">Video not playing? Open it directly ↗</a>
    </div>
</div>

<?php if ($isAdminTest): ?>
    <div id="arVerified">
        <p data-message></p>
        <a class="ar-btn ar-btn-ghost" href="<?= e($backUrl) ?>">Back to frame</a>
    </div>
<?php endif; ?>

<?php if ($showDebug): ?>
    <pre id="arDebug" style="position:fixed;left:0;right:0;bottom:0;z-index:50;margin:0;
        padding:10px 12px;background:rgba(0,0,0,.82);color:#7dff9b;font-size:11px;
        line-height:1.5;font-family:ui-monospace,Menlo,monospace;max-height:45vh;overflow:auto;
        white-space:pre-wrap">waiting for scanner…</pre>
<?php endif; ?>

<script id="arScanConfig" type="application/json"><?= json_encode($scanConfig, JSON_UNESCAPED_SLASHES) ?></script>
<script type="module">
    /*
     * The scanner is a few hundred KB of tracking library, so the button stays
     * disabled until it has actually loaded — and a load failure is reported
     * rather than swallowed. Previously an import error left the button with no
     * handler at all, so tapping it did nothing and said nothing.
     */
    // An async IIFE rather than top-level await: a browser without top-level
    // await would fail to parse this whole script, which is the very silent
    // failure being fixed.
    (async function () {
        const startBtn = document.getElementById('arStart');
        const originalLabel = startBtn.textContent;
        startBtn.disabled = true;
        startBtn.textContent = 'Loading scanner…';

        try {
            const module = await import('<?= asset('public/js/ar-scan.js') ?>');
            module.initScanner(JSON.parse(document.getElementById('arScanConfig').textContent));
            startBtn.disabled = false;
            startBtn.textContent = originalLabel;
        } catch (err) {
            document.getElementById('arIntro').style.display = 'none';
            document.getElementById('arErrorText').textContent =
                'The scanner could not load. Please reload the page, or try a different browser.';
            const errorPanel = document.getElementById('arError');
            errorPanel.style.display = 'flex';
            // initScanner never ran, so the retry button has no handler of its own.
            errorPanel.querySelector('[data-retry]').addEventListener('click', function () {
                window.location.reload();
            });
            // Kept for diagnosis — this is the failure that used to be invisible.
            console.error('Living Photo scanner failed to load:', err);
        }
    })();
</script>
<noscript>
    <div class="ar-panel">
        <h1>JavaScript required</h1>
        <p>Please enable JavaScript to view your Living Photo.</p>
    </div>
</noscript>
</body>
</html>
