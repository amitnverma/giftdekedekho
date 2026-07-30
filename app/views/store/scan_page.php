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
    'targetUrl'    => $targetUrl,
    'videoType'    => $playback['type'],
    'youtubeId'    => $playback['youtube_id'] ?? null,
    'playbackMode' => $frame['playback_mode'],
    'aspect'       => $aspect,
    'verifyUrl'    => $isAdminTest ? $verifyUrl : null,
    'csrf'         => $isAdminTest ? $csrf : null,
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
        padding: 32px 24px calc(40px + env(safe-area-inset-bottom)); pointer-events: none;
    }
    .ar-reticle {
        width: min(74vw, 340px); aspect-ratio: 1; margin: auto;
        border-radius: 18px; border: 2px dashed rgba(255,255,255,.5);
        box-shadow: 0 0 0 100vmax rgba(0,0,0,.32);
    }
    #arHint {
        background: rgba(0,0,0,.62); backdrop-filter: blur(8px);
        border-radius: 14px; padding: 13px 20px; font-size: 15px; font-weight: 500;
        line-height: 1.45; max-width: 22em;
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
        flex-direction: column; align-items: center; justify-content: center; background: #000;
    }
    #arVideo { width: 100%; height: 100%; object-fit: contain; display: none; background: #000; }
    #arYoutube { display: none; width: 100%; height: 100%; }
    #arYoutube iframe { width: 100%; height: 100%; border: 0; }
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

<!-- Intro / permission gate. The camera only starts on a real tap, which also
     gives us the user gesture that lets the video play with sound. -->
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
        <?php else: ?>
            Point your camera at the <strong>printed photo in your frame</strong> and the video will start playing.
            Nothing to install — just allow camera access.
        <?php endif; ?>
    </p>
    <button class="ar-btn" id="arStart" type="button">Start camera</button>
    <?php if (!$isAdminTest && $frame['trackability_flag'] === 'poor'): ?>
        <p style="margin-top:18px;font-size:13px;color:#f5b400">
            Tip: this photo is quite plain, so it may need good light and a steady hand.
        </p>
    <?php endif; ?>
</div>

<!-- Scanning guidance -->
<div id="arStatus">
    <div class="ar-reticle"></div>
    <p id="arHint">Point your camera at the photo</p>
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
    <video id="arVideo" playsinline controls preload="none"
           <?= $playback['type'] === 'upload' ? 'src="' . e($playback['url']) . '"' : '' ?>></video>
    <div id="arYoutube"></div>
    <div id="arTapToPlay">
        <div class="ar-play-ring">▶</div>
        <span>Tap to play your video</span>
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
