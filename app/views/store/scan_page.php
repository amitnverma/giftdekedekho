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

// The photo is the surprise. Rendering it on the scan page — as a thumbnail on
// the intro, or as a guide inside the viewfinder — hands the recipient the
// picture before the frame does, which is the one thing this gift is for. The
// public page therefore never shows it; the viewfinder guides by shape alone.
//
// The admin live test still shows it: there is no surprise to protect there,
// and the tester has to know which photo to point the phone at.
$revealPhoto = $isAdminTest && $photoUrl !== '';

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

    /* Viewfinder: corner brackets sized to the photo's own shape.
       Deliberately generous. The box is an aiming aid, not a cut-out the photo
       has to be posted through — the tracker matches the photo wherever it is
       in the camera's view, so a tight box only makes people fuss over
       alignment that never mattered. Filling roughly this much of the screen is
       the part that genuinely helps recognition. */
    .ar-reticle {
        position: relative; margin: auto;
        width: min(88vw, 460px, calc(58vh * var(--ar-target-aspect, 1)));
        aspect-ratio: var(--ar-target-aspect, 1);
        box-shadow: 0 0 0 100vmax rgba(0,0,0,.42);
    }
    .ar-corner {
        position: absolute; width: 34px; height: 34px;
        border: 3px solid #fff;
    }
    .ar-corner-tl { top: -3px; left: -3px;  border-right: 0; border-bottom: 0; border-top-left-radius: 16px; }
    .ar-corner-tr { top: -3px; right: -3px; border-left: 0;  border-bottom: 0; border-top-right-radius: 16px; }
    .ar-corner-bl { bottom: -3px; left: -3px;  border-right: 0; border-top: 0; border-bottom-left-radius: 16px; }
    .ar-corner-br { bottom: -3px; right: -3px; border-left: 0;  border-top: 0; border-bottom-right-radius: 16px; }

    /* Admin live test only: the real photo, faded, to line the phone up with.
       Never on the public page — see $revealPhoto. */
    .ar-ghost {
        position: absolute; inset: 0; width: 100%; height: 100%;
        object-fit: contain; opacity: .38;
        animation: arGhostPulse 2.8s ease-in-out infinite;
    }
    @keyframes arGhostPulse { 0%, 100% { opacity: .3; } 50% { opacity: .5; } }

    /* What the public page shows instead of the photo: the outline of a framed
       picture, at the shape the tracker is looking for. It says how to hold the
       phone without showing a single pixel of the surprise. */
    .ar-silhouette {
        position: absolute; inset: 0; display: flex;
        align-items: center; justify-content: center;
        opacity: .3;
    }
    .ar-silhouette svg { width: 34%; max-width: 116px; height: auto; }

    /* A scan line travelling down the box. It is the one cue everyone already
       reads as "the camera is looking", and it replaces the reassurance the
       faded photo used to give: something is happening, keep holding. */
    /* The sweep has to be clipped to the box, but the corner brackets sit 3px
       outside it — clipping on the reticle itself cut their outer edges off. */
    .ar-sweep-track { position: absolute; inset: 0; overflow: hidden; }
    .ar-sweep {
        position: absolute; left: 0; right: 0; height: 26%;
        background: linear-gradient(to bottom,
            rgba(255,255,255,0) 0%, rgba(255,255,255,.13) 55%, rgba(255,255,255,.34) 96%,
            rgba(255,255,255,.72) 100%);
        animation: arSweep 2.6s cubic-bezier(.45,0,.55,1) infinite;
    }
    @keyframes arSweep {
        0%       { transform: translateY(-26%); opacity: 0; }
        12%, 82% { opacity: 1; }
        100%     { transform: translateY(100%); opacity: 0; }
    }

    .ar-guide-foot { display: flex; flex-direction: column; align-items: center; gap: 9px; }
    /* Points up at the viewfinder, so the hint underneath is read as being
       about the box rather than about the whole screen. */
    .ar-guide-arrow {
        font-size: 20px; line-height: 1; color: #fff;
        text-shadow: 0 2px 10px rgba(0,0,0,.85);
        animation: arArrowNudge 1.8s ease-in-out infinite;
    }
    @keyframes arArrowNudge { 0%, 100% { transform: translateY(3px); opacity: .65; } 50% { transform: translateY(-3px); opacity: 1; } }

    /* Torch. Poor light is the most common reason a real print will not match,
       and it is the one cause the recipient can actually fix from here. Shown
       only when the camera track reports the capability, so it never appears as
       a dead button on iOS, where the web has no access to the torch. */
    #arTorch {
        display: none; pointer-events: auto; align-items: center; gap: 8px;
        border: 0; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 600;
        background: rgba(0,0,0,.62); backdrop-filter: blur(8px); color: #fff;
        border-radius: 999px; padding: 10px 18px;
    }
    #arTorch.is-on { background: #f5b400; color: #241c00; }

    @media (prefers-reduced-motion: reduce) {
        .ar-ghost, .ar-guide-arrow, .ar-sweep { animation: none; }
        .ar-sweep { display: none; }
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
        /* A dim gallery wall rather than flat black — the video should feel like
           it is hanging on a wall, not like a media player took over the phone.
           The second layer is the pool of light the frame appears to sit in. */
        background:
            radial-gradient(ellipse 70% 46% at 50% 46%, rgba(255,226,178,.13) 0%, rgba(255,226,178,0) 70%),
            radial-gradient(circle at 50% 42%, #262220 0%, #121010 55%, #060505 100%);
        padding: var(--ar-pad);

        /* The video's shape. 16:9 is only the assumption used until the file's
           metadata arrives; the module replaces it with the real ratio, because
           a phone-shot portrait video letterboxed into a 16:9 box was playing
           at a fraction of the screen it could have filled. */
        --ar-video-aspect: 1.7778;

        /* The frame's profile, in one place. --ar-chrome is derived from these
           rather than hand-totalled, so changing the moulding or the mat can no
           longer silently mis-size the video inside it. */
        --ar-pad: 2.4vmin;        /* breathing room between frame and screen */
        --ar-lip: 0.5vmin;        /* the outer lip of the moulding */
        --ar-moulding: 1.9vmin;   /* the wood itself */
        --ar-mat: 3.2vmin;        /* the mount board */
        --ar-bevel: 0.5vmin;      /* the 45° cut at the mat's window */

        /* The unmute pill hangs below the frame, so the height it occupies has
           to come off the video's budget or it ends up off the screen. Its own
           height is in px because the padding and font-size that set it are. */
        --ar-pill: calc(1.6vmin + 44px);

        --ar-chrome: calc(2 * (var(--ar-pad) + var(--ar-lip) + var(--ar-moulding) + var(--ar-mat) + var(--ar-bevel)));
        --ar-avail-w: calc(100vw - var(--ar-chrome));
        /* dvh (where supported) excludes the browser's own chrome, which vh
           does not — on a phone that difference is most of a toolbar. */
        --ar-avail-h: calc(100vh - var(--ar-chrome) - var(--ar-pill) - env(safe-area-inset-top) - env(safe-area-inset-bottom));
        --ar-avail-h: calc(100dvh - var(--ar-chrome) - var(--ar-pill) - env(safe-area-inset-top) - env(safe-area-inset-bottom));
    }

    /* The picture frame the video plays inside, built in the layers a real one
       has: lip, moulding, mount board, bevel, glass. Each is its own element so
       the widths above stay honest — a single bordered box cannot show a mat
       bevel or a rebate shadow, and that flatness is what read as a CSS border
       rather than as a frame. */
    .ar-frame {
        position: relative; display: none;
        max-width: 100%; max-height: 100%;
    }
    .ar-frame.is-visible { display: block; }

    /* The wall the frame hangs on. Without it the cast shadow falls on black
       and disappears, which leaves the frame looking pasted onto the screen
       rather than standing off a surface. */
    .ar-frame::before {
        content: ''; position: absolute; z-index: -1; pointer-events: none;
        inset: -14vmin -10vmin -10vmin;
        background: radial-gradient(ellipse at 50% 42%,
            rgba(255,226,180,.16) 0%, rgba(255,206,150,.06) 42%, rgba(255,200,140,0) 72%);
    }

    /* Outer lip: the thin dark edge that runs round the outside of a moulding
       and separates it from the wall. Also carries the cast shadow, which is
       offset downward because the light in this scene comes from above. */
    .ar-frame-lip {
        padding: var(--ar-lip);
        border-radius: 0.45vmin;
        background: linear-gradient(152deg, #4a3116 0%, #23160a 48%, #503722 100%);
        box-shadow:
            0 0 0 0.12vmin rgba(0,0,0,.9),                /* crisp outline */
            0 0.5vmin 1.4vmin rgba(0,0,0,.6),             /* contact shadow */
            0 3.6vmin 7.5vmin -1.2vmin rgba(0,0,0,.9);    /* cast shadow */
    }

    /* The moulding.
       The gradient sets the light direction across the whole frame; the stacked
       inset rings are what make a flat band read as a carved profile, because
       they run parallel to each edge the way a real moulding's steps do. From
       the outside in: burnished outer edge, the shade falling away from it, the
       gilt crown, then the shadow dropping into the rebate. A single gradient
       cannot do this — it was the missing depth that made the old frame read as
       a gold border rather than as a frame. */
    .ar-frame-moulding {
        padding: var(--ar-moulding);
        border-radius: 0.25vmin;
        /* Lit from the top-left, so the top and left rails carry the gilt and
           the bottom and right fall into shadow. */
        background:
            linear-gradient(135deg,
                #f0dcaa 0%, #cda862 13%, #9a7133 29%, #e3c689 46%,
                #b78d47 60%, #7d5722 76%, #c19b58 90%, #6a4a1d 100%);
        /* Only the outer edge is drawn here. An inset shadow always grows from
           the outside in, so it cannot put the rebate at the inner edge where
           it belongs — that half of the profile is drawn by the mat's own
           outset ring instead, which is the right element for it anyway. */
        box-shadow:
            inset 0 0 0 calc(var(--ar-moulding) * 0.09) rgba(255,251,235,.85),
            inset 0 0 calc(var(--ar-moulding) * 0.5) calc(var(--ar-moulding) * 0.1) rgba(84,55,18,.5);
    }

    /* The mount board. The outset ring is the dark line where the moulding's
       rebate meets the card; the inset shadow along the top is that rebate
       throwing a shadow onto the board. Those two details are most of what
       gives a framed picture its depth. */
    .ar-frame-mat {
        position: relative;
        padding: var(--ar-mat);
        background: linear-gradient(160deg, #fdfbf6 0%, #f1ebdf 55%, #e3dbc9 100%);
        box-shadow:
            /* The rebate: the moulding steps down to the glass here, so this is
               the darkest line in the whole frame, with its shadow spilling
               back outward onto the wood. */
            0 0 0 calc(var(--ar-moulding) * 0.09) rgba(42,26,6,.9),
            0 0 calc(var(--ar-moulding) * 0.55) calc(var(--ar-moulding) * 0.14) rgba(38,23,5,.7),
            /* and that rebate throwing a shadow forward onto the board */
            inset 0 0.6vmin 1.4vmin rgba(60,45,25,.26),
            inset 0 -0.2vmin 0.7vmin rgba(60,45,25,.12);
    }
    /* The V-groove: the fine ruled line cut into the board a little way in from
       the window, standard on framed photographs. */
    .ar-frame-mat::before {
        content: ''; position: absolute; pointer-events: none;
        inset: calc(var(--ar-mat) * 0.42);
        border: 0.1vmin solid rgba(150,128,92,.42);
        box-shadow: 0 0.1vmin 0 rgba(255,255,255,.85);
    }

    /* The bevel: the 45° cut at the mat's window, showing the board's paler
       core. Lit from the top-left, so the top and left edges are the bright
       ones — reversing that is what makes a bevel look like a plain border. */
    .ar-frame-window {
        position: relative; line-height: 0;
        border: var(--ar-bevel) solid;
        border-color: #ffffff #ded5c1 #cabfa6 #fbf8f0;
        background: #000;
        box-shadow:
            0 0 0 0.1vmin rgba(96,76,44,.8),        /* the ruled edge of the cut */
            inset 0 0 1.8vmin rgba(0,0,0,.6);       /* the picture sitting behind it */
    }
    /* Glass: one soft diagonal sheen, kept faint so it never fights the video. */
    .ar-frame-window::after {
        content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 2;
        background: linear-gradient(122deg,
            rgba(255,255,255,.14) 0%,
            rgba(255,255,255,.05) 26%,
            rgba(255,255,255,0) 46%);
    }

    /* Two states, not one.
       is-visible lays the frame out so the video inside it can decode and play;
       is-ready is what actually shows it, and waits until the video reports its
       dimensions. The frame takes its size from the video, so revealing it
       first drew an empty gold box a few centimetres across whenever the file
       still had to load — which is exactly what a recipient saw on re-scan.
       Hiding with opacity rather than display is deliberate: a display:none
       video does not reliably start on a phone. */
    .ar-frame.is-visible { opacity: 0; }
    .ar-frame.is-visible.is-ready {
        animation: arFrameIn 620ms cubic-bezier(.2,.8,.25,1) both;
    }
    @keyframes arFrameIn {
        from { opacity: 0; transform: scale(.9) translateY(2.2vmin); }
        to   { opacity: 1; transform: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .ar-frame.is-visible.is-ready { animation: none; opacity: 1; }
    }

    /* Shown while the video loads, so the wait is never a bare empty screen. */
    #arLoading {
        position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
        z-index: 31; display: none; align-items: center; gap: 11px;
        font-size: 14.5px; color: rgba(255,255,255,.9);
        background: rgba(0,0,0,.5); backdrop-filter: blur(8px);
        border-radius: 999px; padding: 12px 20px; white-space: nowrap;
    }
    #arLoading.is-on { display: flex; }
    .ar-spinner {
        width: 17px; height: 17px; border-radius: 50%; flex: none;
        border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
        animation: arSpin 800ms linear infinite;
    }
    @keyframes arSpin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .ar-spinner { animation-duration: 2s; } }

    /* If the file will not load at all, say so and hand over the direct link
       rather than leaving an empty frame on screen. */
    #arVideoError {
        position: absolute; inset: 0; z-index: 36; display: none;
        flex-direction: column; align-items: center; justify-content: center;
        text-align: center; padding: 28px; gap: 16px;
    }
    #arVideoError.is-on { display: flex; }
    #arVideoError p { margin: 0; font-size: 15.5px; line-height: 1.55; color: #e7e7ee; max-width: 24em; }

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
    /* Anchored to the frame's bottom edge rather than offset by a guessed
       amount: the frame's own depth changes with vmin, and a fixed offset put
       the pill on top of the moulding on a narrow phone. */
    #arUnmute {
        position: absolute; left: 50%; transform: translateX(-50%);
        top: 100%; margin-top: 1.6vmin; z-index: 35; display: none;
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
    <?php if ($revealPhoto): ?>
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
            Point your camera at the <strong>photo in your frame</strong> and the video will start playing.
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
     square: filling them is the same action as framing the photo properly.

     Note what is deliberately absent on the public page — the photo itself.
     The instruction has to work for someone who has never seen the picture, so
     it names the thing they are holding (the frame on the wall, the photo in
     it) rather than asking them to recognise an image. -->
<div id="arStatus"<?= $isAdminTest ? ' class="has-topbar"' : '' ?> style="--ar-target-aspect:<?= $aspect > 0 ? round($aspect, 4) : 1 ?>">
    <div class="ar-guide-head">
        <h2><?= $isMulti ? 'Find your Living Photo' : 'Point at your framed photo' ?></h2>
        <p>
            <?php if ($revealPhoto): ?>
                Line the real photo up with the faded one, until it fills the corners
            <?php else: ?>
                Hold the phone straight on and fill the box with the photo. It doesn't have to be exact.
            <?php endif; ?>
        </p>
    </div>
    <div class="ar-reticle">
        <span class="ar-corner ar-corner-tl"></span>
        <span class="ar-corner ar-corner-tr"></span>
        <span class="ar-corner ar-corner-bl"></span>
        <span class="ar-corner ar-corner-br"></span>
        <?php if ($revealPhoto): ?>
            <img class="ar-ghost" src="<?= e($photoUrl) ?>" alt="" aria-hidden="true">
        <?php else: ?>
            <span class="ar-sweep-track" aria-hidden="true"><span class="ar-sweep"></span></span>
            <span class="ar-silhouette" aria-hidden="true">
                <!-- A framed picture, drawn as an outline. Shows the shape to
                     fill and nothing about the photo. -->
                <svg viewBox="0 0 64 52" fill="none" stroke="#fff" stroke-width="2.4"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="60" height="48" rx="4"/>
                    <rect x="9" y="9" width="46" height="34" rx="2" stroke-width="1.6" opacity=".75"/>
                    <path d="M13 38l12-12 8 8 6-5 12 11" stroke-width="1.6" opacity=".75"/>
                    <circle cx="22" cy="18" r="3.2" stroke-width="1.6" opacity=".75"/>
                </svg>
            </span>
        <?php endif; ?>
    </div>
    <div class="ar-guide-foot">
        <span class="ar-guide-arrow" aria-hidden="true">&#9650;</span>
        <p id="arHint">Point your camera at the photo</p>
        <button id="arTorch" type="button" aria-pressed="false">💡 <span data-label>Turn on light</span></button>
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
        <div class="ar-frame-lip">
            <div class="ar-frame-moulding">
                <div class="ar-frame-mat">
                    <div class="ar-frame-window">
                        <video id="arVideo" playsinline controls preload="auto"></video>
                        <div id="arYoutube"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Outside the moulding on purpose, so it hangs below the frame rather
             than covering the picture or the player's own controls. -->
        <button id="arUnmute" type="button">🔊 Tap for sound</button>
    </div>
    <div id="arLoading"><span class="ar-spinner" aria-hidden="true"></span>Opening your video…</div>
    <div id="arVideoError">
        <p>Your video could not be loaded. Check your connection and try again.</p>
        <button class="ar-btn" type="button" data-retry-video>Try again</button>
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
