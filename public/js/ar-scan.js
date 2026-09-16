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
 *
 * Worded for someone who has never seen the photo. The public page withholds it
 * on purpose — it is the surprise — so no hint may ask the recipient to
 * recognise the picture, only to aim at the frame they are standing in front of.
 * The order follows what actually goes wrong, most common first: too far away,
 * then angle, then light, then glare off the glass.
 */
const HINTS = [
  { after: 0,     text: 'Fill the box with the photo' },
  { after: 5000,  text: 'Move closer, until the photo fills most of the box' },
  { after: 11000, text: 'Hold the phone straight on, flat to the photo — not at an angle' },
  // withTorch is used only when the camera actually offered a torch — on iOS
  // there is no such button, and pointing at one would be a dead end.
  { after: 17000, text: 'Try more light on the photo',
    withTorch: 'Try more light on the photo, or tap the light button below' },
  { after: 24000, text: 'Seeing a reflection? Step slightly to one side to clear the glare off the glass' },
  { after: 32000, text: 'Still nothing? Take the frame somewhere brighter and hold the phone steady' },
];

/**
 * How many consecutive tracked frames before a match is announced.
 *
 * MindAR's default is 5 (so, six frames), which is a visible pause between
 * pointing at the photo and anything happening — long enough that people move
 * the phone again and lose the lock, which is the loop that makes scanning feel
 * unreliable. Each pass is a real feature match, so a lower bar trades very
 * little confidence for a much faster response.
 *
 * A recipient's own /scan/{slug} can afford the lowest setting: every video it
 * could play belongs to that gift, so a spurious match costs at most a video
 * starting a moment early, or a sibling photo's video that one tap on "Scan
 * another photo" corrects. The higher bar is kept where a wrong match would
 * actually cost something — the scan-anything page, which could play a
 * stranger's video, and the admin live test, where a match is what marks a
 * photo verified and therefore printable. The page decides: config.careful.
 */
const WARMUP_FAST = 1;
const WARMUP_CAREFUL = 3;

/**
 * How many consecutive missed frames before a match is dropped. Raised from
 * MindAR's default of 5 because a hand-held phone drops frames constantly; the
 * default let a brief wobble count as "lost", which in overlay mode paused the
 * video mid-sentence.
 */
const MISS_TOLERANCE = 12;

/**
 * Pages with several photos make the video follow the photo: moving the camera
 * off a photo hides its video, and pointing at another plays that one, with
 * nothing to close in between.
 *
 * MindAR only reports "lost" after MISS_TOLERANCE missed frames, so a wobble is
 * already absorbed there. The grace period on top is for the camera briefly
 * sliding off the edge of the print; coming back within it carries on as if
 * the video never left. The fade is short enough that a switch between two
 * photos reads as one movement rather than two separate events.
 */
const LOST_GRACE_MS = 300;
const LEAVE_FADE_MS = 220;

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
    torch: document.getElementById('arTorch'),
    loading: document.getElementById('arLoading'),
    videoError: document.getElementById('arVideoError'),
    // Only present when a frame has more than one photo.
    progress: document.getElementById('arProgress'),
    next: document.getElementById('arNext'),
  };

  let mindarThree = null;
  let hasMatched = false;
  let hintTimer = null;
  let startedAt = 0;
  // Set while a first-touch listener is waiting to prime the video; see
  // primeOnFirstTouch(). Calling it detaches the listener.
  let cancelTouchPrime = null;
  // The live camera track, kept so the torch can be switched back off. Null
  // until the camera starts, and stays null where there is no torch to offer.
  let torchTrack = null;
  let torchOn = false;
  // The URL currently loaded into the <video>. Assigning .src reloads the media
  // even when the URL is identical, so this is what makes a second scan of the
  // same photo reuse the file already in memory instead of fetching it again.
  let loadedVideoUrl = null;
  // See LOST_GRACE_MS. hideTimer runs while a lost photo's video waits out the
  // grace period; leaveTimer while it fades away. Either one being set means
  // the video is on its way out but can still be kept.
  const followPhoto = !!config.followPhoto;
  let hideTimer = null;
  let leaveTimer = null;

  // One entry per target in the .mind file, in the same order. A single-frame
  // page supplies an array of one, so /scan and /scan/{slug} run identical code.
  const targets = Array.isArray(config.targets) ? config.targets : [];
  // Which target matched — playback reads from this rather than global config.
  let active = null;
  // Indexes of the photos matched so far on this visit, for the progress count.
  const found = new Set();

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
    matchedItemId: null,
    foundCount: 0,
    followPhoto: !!config.followPhoto,
    videoPrimed: null,
    torchAvailable: false,
    torchOn: false,
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

  /**
   * Put the "Start camera" screen back after an automatic attempt failed.
   *
   * Not an error state. Some browsers only hand over the camera from a real
   * tap, and that refusal is indistinguishable here from someone denying
   * permission — so offer the button rather than accusing the recipient of
   * blocking their camera. If the tap fails too, that one does show the error.
   */
  function revertToIntro() {
    stopHints();
    els.status.style.display = 'none';
    els.error.style.display = 'none';
    els.intro.style.display = 'flex';
    els.startBtn.disabled = false;
  }

  /**
   * The first thing to say on the camera screen. Back from a video with photos
   * still to find, the useful instruction is to move on — not to fill the box
   * with a photo they have just successfully scanned.
   */
  function openingHint() {
    if (config.showProgress && found.size > 0 && found.size < targets.length) {
      return 'Now point at another photo';
    }
    return HINTS[0].text;
  }

  function startHints() {
    // Never two timers fighting over the hint text.
    stopHints();
    startedAt = Date.now();
    els.status.style.display = 'flex';
    const opening = openingHint();
    hintTimer = setInterval(() => {
      if (hasMatched) return;
      const elapsed = Date.now() - startedAt;
      let current = opening;
      for (const hint of HINTS) {
        if (hint.after > 0 && elapsed >= hint.after) {
          current = (hint.withTorch && torchTrack) ? hint.withTorch : hint.text;
        }
      }
      if (els.hint.textContent !== current) els.hint.textContent = current;
    }, 500);
    els.hint.textContent = opening;
  }

  /**
   * "2 of 3 photos found", for a frame with several photos. Without it nothing
   * on screen says there is more than one video to find, and the recipient
   * stops after the first.
   */
  function updateProgress() {
    if (!config.showProgress || !els.progress) return;
    const total = targets.length;
    if (found.size === 0) {
      els.progress.textContent = total + ' photos to find';
    } else if (found.size < total) {
      els.progress.textContent = found.size + ' of ' + total + ' photos found';
    } else {
      els.progress.textContent = 'All ' + total + ' photos found — scan any to watch again';
    }
    els.progress.hidden = false;
  }

  function stopHints() {
    if (hintTimer) clearInterval(hintTimer);
    hintTimer = null;
  }

  // --------------------------------------------------------------------- torch

  /**
   * Offer the phone's torch, when the camera actually has one to offer.
   *
   * Light is the most common reason a real print will not match, and unlike
   * distance or angle it is the one thing a recipient standing in a dim hallway
   * cannot fix by moving. The button is only revealed once the track reports
   * the capability, so it never appears as a dead control on iOS, where Safari
   * exposes no torch to the web at all.
   */
  function setupTorch() {
    if (!els.torch || !mindarThree || !mindarThree.video) return;

    const stream = mindarThree.video.srcObject;
    if (!stream || typeof stream.getVideoTracks !== 'function') return;

    const track = stream.getVideoTracks()[0];
    if (!track || typeof track.getCapabilities !== 'function') return;

    let capabilities;
    try {
      capabilities = track.getCapabilities() || {};
    } catch (err) {
      // Some browsers throw here before the track has settled. No torch, then.
      return;
    }
    if (!capabilities.torch) return;

    torchTrack = track;
    debug.torchAvailable = true;
    renderDebug();

    els.torch.style.display = 'inline-flex';
    els.torch.onclick = () => setTorch(!torchOn);
  }

  function setTorch(on) {
    if (!torchTrack) return;
    torchTrack.applyConstraints({ advanced: [{ torch: on }] })
      .then(() => {
        torchOn = on;
        els.torch.classList.toggle('is-on', on);
        els.torch.setAttribute('aria-pressed', String(on));
        const label = els.torch.querySelector('[data-label]');
        if (label) label.textContent = on ? 'Turn off light' : 'Turn on light';
        debug.torchOn = on;
        renderDebug();
      })
      .catch(() => {
        // Advertised but refused. Hide it rather than leave a button that lies.
        torchTrack = null;
        els.torch.style.display = 'none';
      });
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
   * Prime from the first touch anywhere on the scanning screen.
   *
   * Starting the camera automatically means there is no "Start camera" tap to
   * prime inside, and no browser allows unmuted playback that no gesture ever
   * authorised. People do touch the screen while lining a phone up on a photo,
   * so take the first touch that comes and spend it here.
   *
   * If none ever comes, playUploadedVideo() still starts the video muted and
   * offers sound in one tap — motion is never withheld waiting for a gesture.
   */
  function primeOnFirstTouch() {
    if (cancelTouchPrime) return;

    const handler = () => {
      cancelTouchPrime();
      // After a match this same call would mute and rewind a playing video.
      if (!hasMatched) primeVideoElement();
    };

    document.addEventListener('pointerdown', handler, { passive: true });
    cancelTouchPrime = () => {
      document.removeEventListener('pointerdown', handler);
      cancelTouchPrime = null;
    };
  }

  /**
   * Give the picture frame the video's real shape.
   *
   * The stylesheet has to assume 16:9 until the file says otherwise, and a
   * portrait video letterboxed inside a 16:9 box played at a fraction of the
   * screen it could have used. videoWidth/videoHeight are only known once
   * metadata has loaded, so this runs from the event and again at playback in
   * case the metadata beat the player to it.
   *
   * Set on the element rather than the player, so it can never reshape a
   * provider iframe, whose real ratio is not knowable from here.
   */
  function fitFrameToVideo() {
    if (!els.video || !els.video.videoWidth || !els.video.videoHeight) return;
    els.video.style.setProperty(
      '--ar-video-aspect',
      String(els.video.videoWidth / els.video.videoHeight)
    );
  }

  if (els.video) els.video.addEventListener('loadedmetadata', fitFrameToVideo);

  /**
   * Partner content is sold by video length. A longer upload plays only up to
   * the length paid for, then stops — the same as trimming it, without needing
   * a video toolchain on the server. Seeking past the end lands on the limit.
   */
  function enforceMaxSeconds() {
    const limit = active && active.maxSeconds;
    if (!limit || !els.video || els.video.currentTime < limit) return;
    els.video.pause();
    // Only when clearly past it: the assignment itself fires 'seeked' again.
    if (els.video.currentTime > limit + 0.25) els.video.currentTime = limit;
  }
  if (els.video) {
    els.video.addEventListener('timeupdate', enforceMaxSeconds);
    els.video.addEventListener('seeked', enforceMaxSeconds);
  }

  /**
   * Show the frame. Held back until the video inside it has a size, because the
   * frame is sized by its contents — see the two-state CSS.
   */
  function revealFrame() {
    if (els.loading) els.loading.classList.remove('is-on');
    if (els.frame) els.frame.classList.add('is-ready');
  }

  /**
   * The video will not load at all. Say so and offer the direct link, rather
   * than leaving an empty frame on screen with nothing in it.
   */
  function showVideoError() {
    if (els.loading) els.loading.classList.remove('is-on');
    if (els.frame) els.frame.classList.remove('is-ready');
    if (els.videoError) els.videoError.classList.add('is-on');
    showFallbackLink();
  }

  /**
   * Play a self-hosted or directly-linked video. Thanks to priming this starts
   * on its own, with sound, the instant the photo is recognised.
   *
   * The frame is only revealed once the file has reported its dimensions. A
   * video with no metadata yet has no size, so showing the frame around it drew
   * a tiny empty box — which is what a recipient saw every time the file had to
   * be fetched, and permanently if the fetch failed.
   */
  function playUploadedVideo() {
    els.video.style.display = 'block';

    // HAVE_METADATA or better: dimensions are known, so nothing to wait for.
    // This is the usual case on a re-scan now that the file is not reloaded.
    if (els.video.readyState >= 1) {
      fitFrameToVideo();
      revealFrame();
    } else {
      if (els.loading) els.loading.classList.add('is-on');
      els.video.addEventListener('loadedmetadata', () => {
        fitFrameToVideo();
        revealFrame();
      }, { once: true });
      els.video.addEventListener('error', showVideoError, { once: true });
    }

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
    // The camera stays visible around the video, so the recipient can see
    // which photo they are on and move to the next one.
    els.player.classList.toggle('is-follow', followPhoto);
    els.player.classList.remove('is-leaving');
    els.player.style.display = 'flex';
    els.status.style.display = 'none';
    if (els.frame) els.frame.classList.add('is-visible');
    if (els.next && config.showProgress) els.next.hidden = false;
    // The camera is hidden behind the player now, so the torch is only heat and
    // battery. Overlay playback and follow mode deliberately keep it: both are
    // still tracking the photo and need the light it was turned on for.
    if (torchOn && !followPhoto) setTorch(false);

    // Vimeo, uploaded files and direct links all start on their own the moment
    // the photo is recognised — no tap. YouTube is the exception: it refuses to
    // embed on this domain, so it keeps an explicit tap and the escape link
    // rather than silently showing its own error screen.
    if (active.videoType === 'youtube') {
      // An iframe is sized by the stylesheet, not by its contents, so there is
      // nothing to wait for — unlike the <video> path.
      revealFrame();
      playEmbedded();
    } else if (active.videoType === 'vimeo') {
      revealFrame();
      buildEmbedIframe();
    } else {
      playUploadedVideo();
    }
  }

  /**
   * Take the player off screen and stop what it is playing.
   *
   * @param {boolean} rewind Start from the beginning next time. The close
   *        button rewinds; a photo the camera merely moved off does not, so
   *        pointing back at it picks the video up where it left off.
   */
  function teardownPlayer(rewind) {
    cancelDismiss();
    els.player.style.display = 'none';
    // Clearing innerHTML stops playback; hiding the container too keeps it from
    // sitting over the camera view as an invisible block on the next scan.
    els.youtube.innerHTML = '';
    els.youtube.style.display = 'none';
    if (els.fallback) els.fallback.style.display = 'none';
    if (els.unmute) els.unmute.style.display = 'none';
    if (els.loading) els.loading.classList.remove('is-on');
    if (els.videoError) els.videoError.classList.remove('is-on');
    if (els.frame) els.frame.classList.remove('is-visible', 'is-ready');
    if (els.video) {
      els.video.pause();
      els.video.style.display = 'none';
      // The src is deliberately left in place so a second scan replays from
      // memory instead of refetching. Rewinding here is what makes that replay
      // start at the beginning rather than resuming on the last frame.
      if (rewind && els.video.readyState >= 1) els.video.currentTime = 0;
    }
    els.tapToPlay.style.display = 'none';
    if (els.next) els.next.hidden = true;
    hasMatched = false;
  }

  function closePlayer() {
    teardownPlayer(true);
    active = null;
    startHints();
  }

  /** The camera has been off the playing photo for the whole grace period: fade the video out. */
  function dismissPlayer() {
    hideTimer = null;
    els.player.classList.add('is-leaving');
    leaveTimer = setTimeout(() => {
      leaveTimer = null;
      teardownPlayer(false);
      active = null;
      startHints();
    }, LEAVE_FADE_MS);
  }

  /** Keep a video that was on its way out. */
  function cancelDismiss() {
    if (hideTimer) clearTimeout(hideTimer);
    if (leaveTimer) clearTimeout(leaveTimer);
    hideTimer = null;
    leaveTimer = null;
    els.player.classList.remove('is-leaving');
  }

  /**
   * Overlay mode: the video is textured onto a plane anchored to the photo in
   * the camera view. Only available for uploaded files — a YouTube iframe cannot
   * be used as a WebGL texture, so those always play full-screen.
   */
  function buildOverlayPlane(anchor, target) {
    // Uses els.video directly. A module runs in strict mode, so the alias this
    // previously assigned to had to be declared — losing that declaration threw
    // a ReferenceError while the anchors were being built, which surfaced to the
    // recipient as "the camera could not be started".
    els.video.muted = false;
    els.video.loop = false;
    els.video.playsInline = true;

    const texture = new THREE.VideoTexture(els.video);
    // Each photo's own shape — photos in one frame need not share one.
    const geometry = new THREE.PlaneGeometry(1, 1 / (target.aspect || 1));
    const material = new THREE.MeshBasicMaterial({ map: texture });
    const plane = new THREE.Mesh(geometry, material);
    anchor.group.add(plane);
  }

  // ----------------------------------------------------------------- reporting

  /**
   * Admin live-test only: record that a real match happened on this photo.
   * Once every photo of the frame has one, printing/handover is unlocked.
   */
  function reportVerified(target) {
    if (!config.verifyUrl) return;

    fetch(config.verifyUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': config.csrf,
      },
      body: 'csrf_token=' + encodeURIComponent(config.csrf) +
        '&item_id=' + encodeURIComponent(target.itemId || 0),
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

  /**
   * @param {boolean} isAuto Started on page load rather than from a tap. A
   *        failure then falls back to the intro screen instead of an error —
   *        see revertToIntro().
   */
  async function start(isAuto) {
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
        // Announce a match sooner, and hold it through a shaky hand. The
        // careful bar is for pages where a wrong match has a cost: /scan, which
        // could play someone else's video, and the admin test, where a match
        // marks the frame verified. config.verifyUrl is only set for the latter.
        warmupTolerance: config.careful ? WARMUP_CAREFUL : WARMUP_FAST,
        missTolerance: MISS_TOLERANCE,
      });

      // One anchor per target in the .mind file: one per photo of this frame,
      // or on /scan every active photo. Whichever fires says which video to play.
      targets.forEach((target, index) => {
        const anchor = mindarThree.addAnchor(index);

        // Overlay needs the video as a WebGL texture, which only works for a
        // real video element — an iframe cannot be textured.
        //
        // Wrapped because /scan registers every active frame at once: a single
        // bad target must not take the camera down for all the others. That is
        // exactly what happened when this threw — the whole scanner reported
        // "the camera could not be started" because of one frame's settings.
        let useOverlay = target.playbackMode === 'overlay'
          && (target.videoType === 'upload' || target.videoType === 'direct');
        if (useOverlay) {
          try {
            buildOverlayPlane(anchor, target);
          } catch (overlayErr) {
            useOverlay = false;   // fall back to full-screen for this frame
            debug.overlayErrors = (debug.overlayErrors || 0) + 1;
            renderDebug();
          }
        }

        anchor.onTargetFound = () => {
          // Back on the photo whose video was on its way out: carry on as if
          // the camera had never left it.
          if (active === target && (hideTimer || leaveTimer)) {
            cancelDismiss();
            return;
          }

          if (hasMatched) {
            // Ignore a second target firing while a video is already playing —
            // unless the video follows the photo, where the camera arriving on
            // another photo means that photo's video, straight away.
            if (!followPhoto || active === target) return;
            teardownPlayer(false);
          }

          // The admin test records each photo once, not on every re-find.
          const firstFind = !found.has(index);
          active = target;
          hasMatched = true;
          found.add(index);
          updateProgress();
          debug.targetFoundCount++;
          debug.matchedSlug = target.slug || null;
          debug.matchedItemId = target.itemId || null;
          debug.foundCount = found.size;
          renderDebug();
          stopHints();
          // Whatever gesture was going to prime the video is now moot, and a
          // stray tap on the player must not re-mute it.
          if (cancelTouchPrime) cancelTouchPrime();

          if (!target.videoType) {
            // Recognised, but this frame has no playable video yet.
            showError('This Living Photo is still being prepared. Please try again a little later.', false);
            return;
          }

          // Direct links and uploaded files both play from a URL in the
          // <video> element; embedded providers build an iframe instead.
          //
          // Only assigned when it actually changes. Setting .src resets the
          // media element even when the URL is identical — readyState drops to
          // nothing, the dimensions go to zero and the whole file is fetched
          // again. On a second scan of the same photo that meant the frame
          // reappeared empty while the video reloaded, and stayed empty if the
          // reload failed. On /scan, where a different frame really can match,
          // the URL differs and the load happens as before.
          if ((target.videoType === 'upload' || target.videoType === 'direct') && target.videoUrl) {
            if (loadedVideoUrl !== target.videoUrl) {
              loadedVideoUrl = target.videoUrl;
              els.video.src = target.videoUrl;
            }
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
          if (firstFind) reportVerified(target);
        };

        anchor.onTargetLost = () => {
          debug.targetLostCount++;
          renderDebug();
          if (useOverlay && active === target) {
            els.video.pause();
            hasMatched = false;
            active = null;
            startHints();
          } else if (followPhoto && hasMatched && active === target && !hideTimer && !leaveTimer) {
            hideTimer = setTimeout(dismissPlayer, LOST_GRACE_MS);
          }
          // On a single-photo frame full-screen playback deliberately survives
          // losing the target — there is nothing else to point at, and people
          // lower the phone once the video starts.
        };
      });

      // Ask for the camera ourselves before handing over to MindAR.
      //
      // MindAR rejects with no error object at all when getUserMedia fails
      // (`.catch((err) => { console.log(...); reject(); })`), so by the time it
      // reaches our handler there is nothing left to report and every failure
      // looks identical. Requesting first means we see the real DOMException and
      // can say what actually went wrong. The permission is then already
      // granted, so MindAR's own request resolves immediately.
      let probeStream = null;
      try {
        probeStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment' },
          audio: false,
        });
      } catch (permErr) {
        // Re-thrown so the single handler below formats the message.
        permErr.__fromProbe = true;
        throw permErr;
      } finally {
        // Release it immediately — MindAR opens its own stream, and holding two
        // can fail on devices that allow only one consumer of the camera.
        if (probeStream) probeStream.getTracks().forEach((t) => t.stop());
      }

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

      // Needs the running stream, so it cannot happen before start().
      setupTorch();
      startHints();
      updateProgress();
    } catch (err) {
      const name = (err && err.name) || '';
      const detail = (err && err.message) || '';
      debug.lastError = (name || 'Error') + (detail ? ': ' + detail : '') +
        (err && err.__fromProbe ? ' (camera request)' : ' (tracker start)');
      renderDebug();

      // An automatic attempt is allowed to fail quietly — offer the button and
      // let the tap produce a verdict we can actually trust.
      if (isAuto) {
        revertToIntro();
        return;
      }

      if (name === 'NotAllowedError' || name === 'SecurityError') {
        showError(
          'Camera access was blocked. Allow camera access for this site in your browser settings, then tap Try again. '
          + 'In a private/incognito window some browsers refuse the camera entirely — try a normal window.',
          true
        );
      } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') {
        showError('No usable camera was found on this device.', false);
      } else if (name === 'NotReadableError' || name === 'AbortError') {
        showError('The camera is being used by another app. Close it and tap Try again.', true);
      } else {
        // Never swallow the reason again — an unexplained failure is what made
        // this impossible to diagnose from a user's report.
        showError(
          'The camera could not be started' + (name ? ' (' + name + ')' : '') + '. '
          + 'Please reload the page and try again.',
          true
        );
      }
    }
  }

  els.startBtn.addEventListener('click', () => {
    if (cancelTouchPrime) cancelTouchPrime();
    // Must happen inside the tap itself — this is what allows the video to
    // start on its own, with sound, when the photo is later recognised.
    primeVideoElement();
    start(false);
  });
  els.closeBtn.addEventListener('click', closePlayer);
  if (els.next) els.next.addEventListener('click', closePlayer);

  // The one place a genuine reload is wanted: the previous attempt failed, so
  // there is nothing in memory worth reusing.
  const retryVideo = els.videoError && els.videoError.querySelector('[data-retry-video]');
  if (retryVideo) {
    retryVideo.addEventListener('click', () => {
      els.videoError.classList.remove('is-on');
      if (els.fallback) els.fallback.style.display = 'none';
      if (active && active.videoUrl) {
        loadedVideoUrl = active.videoUrl;
        els.video.src = active.videoUrl;
      }
      playUploadedVideo();
    });
  }
  els.error.querySelector('[data-retry]').addEventListener('click', () => {
    els.error.style.display = 'none';
    start(false);
  });

  /*
   * Straight to the camera when the page was reached by scanning a frame's own
   * QR sticker. Whoever scanned it has already said what they want and is
   * holding the phone up — an intro screen with a button is one tap asking them
   * to confirm a decision they just made.
   *
   * Only for a single known frame. The scan-anything page downloads every
   * active target, and starting a multi-megabyte download on someone's mobile
   * data before they have agreed to it is a different matter — that page keeps
   * its button and its size warning.
   */
  if (config.autoStart) {
    primeOnFirstTouch();
    start(true);
  }
}
