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
    fallback: document.getElementById('arFallback'),
    frame: document.getElementById('arFrame'),
    unmute: document.getElementById('arUnmute'),
  };

  let mindarThree = null;
  let hasMatched = false;
  let hintTimer = null;
  let startedAt = 0;

  // One entry per target in the .mind file, in the same order. A single-frame
  // page supplies an array of one, so /scan and /scan/{slug} run identical code.
  const targets = Array.isArray(config.targets) ? config.targets : [];
  // Which target matched — playback reads from this rather than global config.
  let active = null;

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
    targetCount: targets.length,
    matchedSlug: null,
    videoPrimed: null,
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
   * Unlock the <video> element for later automatic playback.
   *
   * Browsers refuse unmuted playback that is not tied to a user gesture, and by
   * the time the photo is recognised that gesture is long gone. Starting the
   * element muted *inside* the "Start camera" tap and immediately pausing marks
   * it as user-initiated, so the real play() on match is allowed — with sound,
   * and with no second tap. Verified: without this, a delayed unmuted play()
   * is blocked; with it, the same call plays.
   *
   * Only possible for a real video element. An iframe cannot be primed, which
   * is why embedded providers still need one tap for sound.
   */
  function primeVideoElement() {
    if (!els.video) return;
    els.video.muted = true;
    els.video.playsInline = true;
    const attempt = els.video.play();
    if (attempt && typeof attempt.then === 'function') {
      attempt.then(() => {
        els.video.pause();
        els.video.currentTime = 0;
        debug.videoPrimed = true;
        renderDebug();
      }).catch(() => {
        debug.videoPrimed = false;
        renderDebug();
      });
    }
  }

  /**
   * Play a self-hosted or directly-linked video. Thanks to priming this starts
   * on its own, with sound, the instant the photo is recognised.
   */
  function playUploadedVideo() {
    els.video.style.display = 'block';
    els.video.muted = false;
    const attempt = els.video.play();
    if (attempt && typeof attempt.catch === 'function') {
      attempt.catch(() => {
        // Priming did not take (or was refused). Rather than leave a still
        // frame, start it muted and offer sound in one tap.
        els.video.muted = true;
        els.video.play().catch(() => {});
        showUnmuteButton();
      });
    }
  }

  /** One tap to restore sound when autoplay was only allowed muted. */
  function showUnmuteButton() {
    if (!els.unmute) return;
    els.unmute.style.display = 'block';
    els.unmute.onclick = () => {
      els.unmute.style.display = 'none';
      els.video.muted = false;
      els.video.play().catch(() => {});
    };
  }

  /**
   * Build the embed for whichever provider this frame uses.
   *
   * `origin` is passed to YouTube because the player rejects embeds whose
   * origin it cannot verify — that surfaces as "player configuration error
   * 153", which looks like a broken video to the recipient. youtube.com is
   * used rather than youtube-nocookie.com for the same reason: the nocookie
   * host refuses embeds in more situations.
   */
  function buildEmbedIframe() {
    let src;

    if (active.videoType === 'vimeo') {
      // muted=1 so autoplay is permitted without a gesture — an iframe cannot
      // be primed the way a video element can, and motion starting on its own
      // matters more than the first second having sound. The unmute button
      // restores audio in one tap.
      src = 'https://player.vimeo.com/video/' + encodeURIComponent(active.vimeoId) +
        '?autoplay=1&muted=1&playsinline=1&title=0&byline=0&portrait=0';
    } else {
      const params = new URLSearchParams({
        autoplay: '1',
        playsinline: '1',
        rel: '0',
        modestbranding: '1',
        origin: window.location.origin,
      });
      src = 'https://www.youtube.com/embed/' + encodeURIComponent(active.youtubeId) +
        '?' + params.toString();
    }

    // Built on demand so nothing loads (or phones home to the provider) until
    // the recipient actually asks for the video.
    els.youtube.innerHTML =
      '<iframe src="' + src + '" title="Your video" frameborder="0" allowfullscreen' +
      ' allow="autoplay; encrypted-media; picture-in-picture"></iframe>';
    // Same trap as the uploaded-video path: '' would revert to the
    // stylesheet's `display: none` and show a blank screen.
    els.youtube.style.display = 'block';

    if (active.videoType === 'vimeo') {
      // Vimeo starts muted so it can autoplay; offer sound in one tap. The
      // button drives the iframe by reloading it unmuted, which needs no SDK.
      if (els.unmute) {
        els.unmute.style.display = 'block';
        els.unmute.onclick = () => {
          els.unmute.style.display = 'none';
          els.youtube.innerHTML = els.youtube.innerHTML
            .replace('&muted=1', '')
            .replace('autoplay=1', 'autoplay=1');
        };
      }
    }

    showFallbackLink();
  }

  /**
   * A way out if the embed refuses to play.
   *
   * An iframe reports "loaded" even when the provider renders its own error
   * inside it, so a failed embed cannot be detected reliably without pulling in
   * each provider's player API. Instead the recipient always gets a visible
   * link to open the video directly — small and out of the way when the embed
   * works, and the difference between a working gift and a dead end when it
   * does not.
   */
  function showFallbackLink() {
    if (!els.fallback || !active.watchUrl) return;
    const link = els.fallback.querySelector('a');
    link.href = active.watchUrl;
    els.fallback.style.display = 'block';
  }

  /**
   * Embedded providers always go through an explicit tap.
   *
   * iOS Safari blocks unmuted autoplay inside an iframe, and unlike a <video>
   * element there is no way to detect that failure without pulling in each
   * provider's player API. Rather than gamble — and risk the recipient staring
   * at a bare play button with no explanation — the tap is made deliberate. It
   * also guarantees sound: the tap is a fresh user gesture, so autoplay is
   * permitted. Playing a silent video would defeat the point of the gift.
   */
  function playEmbedded() {
    els.tapToPlay.style.display = 'flex';
    els.tapToPlay.onclick = () => {
      els.tapToPlay.style.display = 'none';
      buildEmbedIframe();
    };
  }

  function showFullscreenPlayer() {
    els.player.style.display = 'flex';
    els.status.style.display = 'none';
    if (els.frame) els.frame.classList.add('is-visible');

    // Vimeo, uploaded files and direct links all start on their own the moment
    // the photo is recognised — no tap. YouTube is the exception: it refuses to
    // embed on this domain, so it keeps an explicit tap and the escape link
    // rather than silently showing its own error screen.
    if (active.videoType === 'youtube') {
      playEmbedded();
    } else if (active.videoType === 'vimeo') {
      buildEmbedIframe();
    } else {
      playUploadedVideo();
    }
  }

  function closePlayer() {
    els.player.style.display = 'none';
    // Clearing innerHTML stops playback; hiding the container too keeps it from
    // sitting over the camera view as an invisible block on the next scan.
    els.youtube.innerHTML = '';
    els.youtube.style.display = 'none';
    if (els.fallback) els.fallback.style.display = 'none';
    if (els.unmute) els.unmute.style.display = 'none';
    if (els.frame) els.frame.classList.remove('is-visible');
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

      // One anchor per target in the .mind file. With a single frame that is a
      // loop of one; on /scan it is every active frame, and whichever fires
      // tells us which customer's video to play.
      targets.forEach((target, index) => {
        const anchor = mindarThree.addAnchor(index);
        // Overlay needs the video as a WebGL texture, which only works for a
        // real video element — an iframe cannot be textured.
        const useOverlay = target.playbackMode === 'overlay'
          && (target.videoType === 'upload' || target.videoType === 'direct');
        if (useOverlay) buildOverlayPlane(anchor);

        anchor.onTargetFound = () => {
          // Ignore a second target firing while a video is already playing.
          if (hasMatched) return;

          active = target;
          hasMatched = true;
          debug.targetFoundCount++;
          debug.matchedSlug = target.slug || null;
          renderDebug();
          stopHints();

          if (!target.videoType) {
            // Recognised, but this frame has no playable video yet.
            showError('This Living Photo is still being prepared. Please try again a little later.', false);
            return;
          }

          // Direct links and uploaded files both play from a URL in the
          // <video> element; embedded providers build an iframe instead.
          if ((target.videoType === 'upload' || target.videoType === 'direct') && target.videoUrl) {
            els.video.src = target.videoUrl;
          }

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
          if (useOverlay && active === target) {
            els.video.pause();
            hasMatched = false;
            active = null;
            startHints();
          }
          // Full-screen playback deliberately survives losing the target —
          // people lower the phone once the video starts.
        };
      });

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

  els.startBtn.addEventListener('click', () => {
    // Must happen inside the tap itself — this is what allows the video to
    // start on its own, with sound, when the photo is later recognised.
    primeVideoElement();
    start();
  });
  els.closeBtn.addEventListener('click', closePlayer);
  els.error.querySelector('[data-retry]').addEventListener('click', () => {
    els.error.style.display = 'none';
    start();
  });
}
