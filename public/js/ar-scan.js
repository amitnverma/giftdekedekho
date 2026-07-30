/**
 * Living Photo scanner — MindAR image tracking for the public /scan/{slug} page
 * and the admin live-test page (same code, so the test exercises the real thing).
 *
 * Loaded as an ES module. MindAR and three.js are served from this site
 * (public/js/vendor/mindar) rather than a CDN, and their bare "three" imports
 * were rewritten to relative paths — see tools/mindar-compile/README.txt.
 *
 * That matters for reliability, not neatness: a CDN import needs the phone to
 * have working internet on whatever Wi-Fi it is on, and an import map needs
 * iOS 16.4+. If either failed, this module never executed, so the Start button
 * had no handler and tapping it did nothing at all. Vendoring removes both
 * failure modes; the view also reports a load failure instead of dying quietly.
 */

import * as THREE from './vendor/mindar/three.module.js';
import { MindARThree } from './vendor/mindar/mindar-image-three.prod.js';

/**
 * Escalating guidance while nothing has matched yet. MindAR only tells us
 * "found" or "lost", so the useful signal for a stuck user is elapsed time.
 */
const HINTS = [
  { after: 0,     text: 'Point your camera at the photo in the frame' },
  { after: 6000,  text: 'Hold steady and fit the whole photo in the view' },
  { after: 12000, text: 'Try moving a little closer' },
  { after: 18000, text: 'More light helps — avoid glare and reflections on the glass' },
  { after: 26000, text: 'Still nothing? Try holding the phone straight on, parallel to the photo' },
];

export function initScanner(config) {
  const els = {
    container: document.getElementById('arContainer'),
    intro: document.getElementById('arIntro'),
    startBtn: document.getElementById('arStart'),
    status: document.getElementById('arStatus'),
    hint: document.getElementById('arHint'),
    error: document.getElementById('arError'),
    errorText: document.getElementById('arErrorText'),
    player: document.getElementById('arPlayer'),
    video: document.getElementById('arVideo'),
    youtube: document.getElementById('arYoutube'),
    tapToPlay: document.getElementById('arTapToPlay'),
    closeBtn: document.getElementById('arClose'),
    verified: document.getElementById('arVerified'),
  };

  let mindarThree = null;
  let hasMatched = false;
  let hintTimer = null;
  let startedAt = 0;
  let overlayVideo = null;

  /**
   * Live diagnostics, always collected and exposed on window.__arDebug.
   *
   * "Nothing happens" is the hardest report to act on, because it covers a dozen
   * different causes. This turns it into facts: did the library load, did the
   * camera start, at what resolution, how big is the search window, and has the
   * tracker ever locked on. Append ?debug=1 to the URL to see it on screen.
   */
  const debug = {
    scannerLoaded: true,
    cameraStarted: false,
    cameraResolution: null,
    cropSize: null,
    containerSize: null,
    targetFoundCount: 0,
    targetLostCount: 0,
    lastError: null,
    secureContext: window.isSecureContext,
    playbackMode: config.playbackMode,
    videoType: config.videoType,
  };
  window.__arDebug = debug;

  const debugPanel = document.getElementById('arDebug');

  function renderDebug() {
    if (!debugPanel) return;
    debugPanel.textContent = Object.keys(debug)
      .map(function (k) { return k + ': ' + JSON.stringify(debug[k]); })
      .join('\n');
  }
  renderDebug();

  const isOverlay = config.playbackMode === 'overlay';

  // ---------------------------------------------------------------- UI helpers

  function showError(message, showRetry) {
    stopHints();
    els.intro.style.display = 'none';
    els.status.style.display = 'none';
    els.errorText.textContent = message;
    els.error.style.display = 'flex';
    els.error.querySelector('[data-retry]').style.display = showRetry ? '' : 'none';
  }

  function startHints() {
    startedAt = Date.now();
    els.status.style.display = 'flex';
    hintTimer = setInterval(() => {
      if (hasMatched) return;
      const elapsed = Date.now() - startedAt;
      let current = HINTS[0].text;
      for (const hint of HINTS) {
        if (elapsed >= hint.after) current = hint.text;
      }
      if (els.hint.textContent !== current) els.hint.textContent = current;
    }, 500);
    els.hint.textContent = HINTS[0].text;
  }

  function stopHints() {
    if (hintTimer) clearInterval(hintTimer);
    hintTimer = null;
  }

  // ------------------------------------------------------------------ playback

  /**
   * Try to play with sound. Browsers block unmuted autoplay without a recent
   * user gesture, and the tap that started the camera may no longer count — so
   * fall back to an explicit tap rather than silently playing muted, which would
   * ruin the moment this product exists for.
   */
  function playUploadedVideo() {
    els.video.style.display = '';
    els.video.muted = false;
    const attempt = els.video.play();
    if (attempt && typeof attempt.catch === 'function') {
      attempt.catch(() => {
        els.tapToPlay.style.display = 'flex';
        els.tapToPlay.onclick = () => {
          els.tapToPlay.style.display = 'none';
          els.video.muted = false;
          els.video.play().catch(() => {});
        };
      });
    }
  }

  function buildYoutubeIframe() {
    // Built on demand rather than in the markup so nothing loads (or phones
    // home to YouTube) until the recipient actually asks for the video.
    const params = new URLSearchParams({
      autoplay: '1',
      playsinline: '1',
      rel: '0',
      modestbranding: '1',
    });
    els.youtube.innerHTML =
      '<iframe src="https://www.youtube-nocookie.com/embed/' +
      encodeURIComponent(config.youtubeId) + '?' + params.toString() + '"' +
      ' title="Your video" frameborder="0" allowfullscreen' +
      ' allow="autoplay; encrypted-media; picture-in-picture"></iframe>';
    els.youtube.style.display = '';
  }

  /**
   * YouTube always goes through an explicit tap.
   *
   * iOS Safari blocks unmuted autoplay inside an iframe, and unlike a <video>
   * element there is no way to detect that failure without pulling in the whole
   * YouTube Player API. Rather than gamble — and risk the recipient staring at
   * a bare play button with no explanation — the tap is made deliberate. It also
   * guarantees sound: the tap is a fresh user gesture, so autoplay is permitted.
   * Playing a silent video would defeat the point of the gift.
   */
  function playYoutube() {
    els.tapToPlay.style.display = 'flex';
    els.tapToPlay.onclick = () => {
      els.tapToPlay.style.display = 'none';
      buildYoutubeIframe();
    };
  }

  function showFullscreenPlayer() {
    els.player.style.display = 'flex';
    els.status.style.display = 'none';
    if (config.videoType === 'youtube') playYoutube();
    else playUploadedVideo();
  }

  function closePlayer() {
    els.player.style.display = 'none';
    els.youtube.innerHTML = '';
    if (els.video) {
      els.video.pause();
      els.video.style.display = 'none';
    }
    els.tapToPlay.style.display = 'none';
    hasMatched = false;
    startHints();
  }

  /**
   * Overlay mode: the video is textured onto a plane anchored to the photo in
   * the camera view. Only available for uploaded files — a YouTube iframe cannot
   * be used as a WebGL texture, so those always play full-screen.
   */
  function buildOverlayPlane(anchor) {
    overlayVideo = els.video;
    overlayVideo.muted = false;
    overlayVideo.loop = false;
    overlayVideo.playsInline = true;

    const texture = new THREE.VideoTexture(overlayVideo);
    const geometry = new THREE.PlaneGeometry(1, 1 / (config.aspect || 1));
    const material = new THREE.MeshBasicMaterial({ map: texture });
    const plane = new THREE.Mesh(geometry, material);
    anchor.group.add(plane);
  }

  // ----------------------------------------------------------------- reporting

  /**
   * Admin live-test only: record that a real match happened, which is what
   * unlocks printing/handover for this frame.
   */
  function reportVerified() {
    if (!config.verifyUrl) return;

    fetch(config.verifyUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': config.csrf,
      },
      body: 'csrf_token=' + encodeURIComponent(config.csrf),
    })
      .then((res) => res.json())
      .then((data) => {
        els.verified.style.display = 'flex';
        els.verified.querySelector('[data-message]').textContent = data.ok
          ? data.message
          : (data.message || 'Could not record the test result.');
      })
      .catch(() => {
        els.verified.style.display = 'flex';
        els.verified.querySelector('[data-message]').textContent =
          'The scan worked, but the result could not be saved. Check your connection and try again.';
      });
  }

  // --------------------------------------------------------------------- start

  async function start() {
    els.intro.style.display = 'none';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showError('This browser cannot use the camera. Please open this link in Safari (iPhone) or Chrome (Android).', false);
      return;
    }
    // getUserMedia is unavailable on insecure origins, which is a confusing
    // failure unless it's named explicitly.
    if (!window.isSecureContext) {
      showError('The camera needs a secure (https) connection. Please open this page over https.', false);
      return;
    }

    els.status.style.display = 'flex';
    els.hint.textContent = 'Starting camera…';

    try {
      mindarThree = new MindARThree({
        container: els.container,
        imageTargetSrc: config.targetUrl,
        // Our own guidance replaces MindAR's built-in overlays.
        uiScanning: 'no',
        uiLoading: 'no',
        uiError: 'no',
      });

      const anchor = mindarThree.addAnchor(0);
      const useOverlay = isOverlay && config.videoType === 'upload';
      if (useOverlay) buildOverlayPlane(anchor);

      anchor.onTargetFound = () => {
        hasMatched = true;
        debug.targetFoundCount++;
        renderDebug();
        stopHints();
        if (useOverlay) {
          els.video.style.display = 'none'; // drawn through the WebGL texture
          els.video.play().catch(() => {
            // Autoplay refused — fall back to the reliable full-screen path.
            showFullscreenPlayer();
          });
          els.status.style.display = 'none';
        } else {
          showFullscreenPlayer();
        }
        reportVerified();
      };

      anchor.onTargetLost = () => {
        debug.targetLostCount++;
        renderDebug();
        if (useOverlay) {
          els.video.pause();
          hasMatched = false;
          startHints();
        }
        // Full-screen playback deliberately survives losing the target — people
        // lower the phone once the video starts.
      };

      await mindarThree.start();
      const { renderer, scene, camera } = mindarThree;
      renderer.setAnimationLoop(() => renderer.render(scene, camera));

      // Record what the tracker is actually working with. A tiny container or a
      // low camera resolution shrinks the search window and is the difference
      // between instant recognition and never matching.
      debug.cameraStarted = true;
      if (mindarThree.video) {
        debug.cameraResolution = mindarThree.video.videoWidth + 'x' + mindarThree.video.videoHeight;
        const shortEdge = Math.min(mindarThree.video.videoWidth, mindarThree.video.videoHeight);
        debug.cropSize = Math.pow(2, Math.round(Math.log(shortEdge / 2) / Math.log(2)));
      }
      debug.containerSize = els.container.clientWidth + 'x' + els.container.clientHeight;
      renderDebug();

      startHints();
    } catch (err) {
      debug.lastError = String((err && err.name) || '') + ': ' + String((err && err.message) || err);
      renderDebug();
      const name = (err && err.name) || '';
      if (name === 'NotAllowedError' || name === 'SecurityError') {
        showError(
          'Camera access was blocked. Allow camera access for this page in your browser settings, then tap Try again.',
          true
        );
      } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        showError('No camera was found on this device.', false);
      } else {
        showError('Something went wrong starting the camera. Please reload the page and try again.', true);
      }
    }
  }

  els.startBtn.addEventListener('click', start);
  els.closeBtn.addEventListener('click', closePlayer);
  els.error.querySelector('[data-retry]').addEventListener('click', () => {
    els.error.style.display = 'none';
    start();
  });
}
