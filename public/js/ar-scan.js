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
 *
 * Three tiers, then:
 *   INSTANT  one photo, one video, nothing to confuse it with — announce on the
 *            first tracked frame. There is no wrong answer available, so every
 *            extra frame is pure delay.
 *   FAST     several photos in one frame: a moment's confirmation, since the
 *            wrong one would mean the wrong video.
 *   CAREFUL  a wrong match has a real cost. See above.
 *
 * The floor is one genuine feature match plus a successful tracking pass —
 * MindAR only counts a frame at all once both have succeeded — so even INSTANT
 * is not guessing.
 */
const WARMUP_INSTANT = 0;
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

/**
 * How many videos to start downloading before anything has matched.
 *
 * The file used to be fetched from inside onTargetFound, which put a
 * multi-megabyte download between recognising the photo and seeing it move —
 * the pause people describe as "it takes a moment to start". Nothing about that
 * download needs the match: which videos this page could play is known when the
 * page loads. Starting them while the recipient is still lining the phone up
 * spends the seconds that were being wasted anyway, so by the time the tracker
 * locks on, the first frames are already decoded and play() is instant.
 *
 * Capped because a frame's videos are ~7MB each and a phone's uplink is not.
 * Only the scan-anything page is excluded outright (config.preloadVideos),
 * where the targets are every active frame on the site.
 */
const PRELOAD_MAX = 4;

/**
 * Videos after the first are warmed one at a time, not all at once: several
 * parallel downloads over one mobile connection would make the photo the
 * recipient is actually pointing at the slowest of them. Each waits for the
 * previous to buffer through, or for this long, whichever comes first.
 */
const PRELOAD_STAGGER_MS = 2500;

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
  // One <video> per photo, created and pointed at its file before anything has
  // matched — see PRELOAD_MAX. Keyed by target index. Giving each photo its own
  // element (rather than reassigning one element's .src) is what lets the files
  // buffer in advance and what makes a re-scan replay from memory: assigning
  // .src resets the element even when the URL is identical, dropping the
  // buffer, the dimensions and the primed-for-autoplay flag with it.
  const videoPool = new Map();
  // Target indexes, least recently used first — see recycleOldestEntry().
  const poolOrder = [];
  // Scan-anything only: the URL currently loaded into the one shared element,
  // so a re-scan of the same frame does not reset it and refetch the file.
  let sharedVideoUrl = null;
  // The pooled element currently in the picture frame. The one in the markup is
  // the first slot, so a single-photo frame adds no elements at all.
  let currentVideo = els.video;
  let markupVideoTaken = false;
  // Set once the staggered warm-up has been kicked off, so the button tap and
  // start() can both ask for it without starting it twice.
  let preloadStarted = false;
  // The compiled target file, fetched ahead of the camera — see
  // prefetchTargetBundle(). Resolves to the URL MindAR should actually load.
  let targetSrcPromise = null;
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
    videosPreloaded: 0,
    targetBundlePrefetched: null,
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

  // ------------------------------------------------------------------ preload

  /** Photos this page could actually play through the <video> element. */
  function playableTargetIndexes() {
    const list = [];
    targets.forEach((target, index) => {
      if ((target.videoType === 'upload' || target.videoType === 'direct') && target.videoUrl) {
        list.push(index);
      }
    });
    return list.slice(0, PRELOAD_MAX);
  }

  /**
   * The <video> element that belongs to one photo, created on first ask.
   *
   * The element in the markup is handed out as the first slot, so the common
   * case — a frame with one photo — adds nothing to the DOM and behaves exactly
   * as it always did. Extra photos get a copy of it beside it, which keeps the
   * stylesheet in charge of how a video is sized and framed (hence the class
   * rather than the id: an id cannot be cloned).
   */
  function videoElementFor(index, target) {
    if (!target || !target.videoUrl) return null;

    // The scan-anything page keeps the original single shared element, pointed
    // at whichever frame matched. Its targets are every active photo on the
    // site, so an element — and a speculative download — per photo is not on
    // offer there, which is also why that page does not preload at all.
    if (!config.preloadVideos) {
      if (sharedVideoUrl !== target.videoUrl) {
        sharedVideoUrl = target.videoUrl;
        attachVideoListeners(els.video, target);
        els.video.src = target.videoUrl;
      }
      return els.video;
    }

    if (videoPool.has(index)) {
      touchPoolEntry(index);
      return videoPool.get(index);
    }

    let el = recycleOldestEntry();
    if (!el) {
      if (!markupVideoTaken) {
        el = els.video;
        markupVideoTaken = true;
      } else {
        el = els.video.cloneNode(false);
        el.removeAttribute('id');
        els.video.parentNode.insertBefore(el, els.video.nextSibling);
      }
    }
    el.playsInline = true;
    // Only headers for now. The warm-up decides which one is allowed to pull
    // down its whole file first; see startVideoPreload().
    el.preload = 'metadata';
    el.src = target.videoUrl;
    attachVideoListeners(el, target);
    videoPool.set(index, el);
    touchPoolEntry(index);
    return el;
  }

  /** Most recently used last, so the front of the list is what gets recycled. */
  function touchPoolEntry(index) {
    const at = poolOrder.indexOf(index);
    if (at !== -1) poolOrder.splice(at, 1);
    poolOrder.push(index);
  }

  /**
   * Hand back an element to be pointed at a different photo, once the pool is
   * full.
   *
   * Each entry is holding a buffered video — several megabytes — so the pool
   * cannot simply grow with every photo that matches. That matters most on the
   * scan-anything page, where the targets are every active frame on the site
   * and a curious recipient can keep finding new ones. The one being watched is
   * never recycled; the least recently used of the rest is.
   *
   * @return {HTMLVideoElement|null} null while there is still room.
   */
  function recycleOldestEntry() {
    if (videoPool.size < PRELOAD_MAX) return null;

    for (let i = 0; i < poolOrder.length; i++) {
      const index = poolOrder[i];
      const el = videoPool.get(index);
      if (!el || el === currentVideo) continue;
      // An overlay plane textures itself from a specific element; recycling one
      // would quietly show the wrong photo's video on that plane.
      if (el.__arPinned) continue;

      poolOrder.splice(i, 1);
      videoPool.delete(index);
      if (el.__arDetach) el.__arDetach();
      el.pause();
      el.removeAttribute('src');
      // Without this the element keeps the decoded buffer until it is collected.
      el.load();
      el.style.display = 'none';
      return el;
    }
    // Everything in the pool is in use — let it grow by one rather than pull
    // the video out from under the recipient.
    return null;
  }

  /**
   * Per-element, because each photo now has its own <video>: a listener bound
   * once to the markup element would not fire for the second photo of a frame.
   *
   * Detachable, because a recycled element is about to be pointed at a
   * different photo and must not keep enforcing the previous one's time limit.
   */
  function attachVideoListeners(el, target) {
    if (el.__arDetach) el.__arDetach();

    const onMeta = () => fitFrameToVideo(el);
    el.addEventListener('loadedmetadata', onMeta);

    // Partner content is sold by video length. A longer upload plays only up to
    // the length paid for, then stops — the same as trimming it, without
    // needing a video toolchain on the server. Seeking past the end lands on
    // the limit.
    const enforce = () => {
      const limit = target.maxSeconds;
      if (!limit || el.currentTime < limit) return;
      el.pause();
      // Only when clearly past it: the assignment itself fires 'seeked' again.
      if (el.currentTime > limit + 0.25) el.currentTime = limit;
    };
    el.addEventListener('timeupdate', enforce);
    el.addEventListener('seeked', enforce);

    el.__arDetach = () => {
      el.removeEventListener('loadedmetadata', onMeta);
      el.removeEventListener('timeupdate', enforce);
      el.removeEventListener('seeked', enforce);
      el.__arDetach = null;
    };
  }

  /**
   * Create every photo's element up front, without downloading anything yet.
   *
   * Separate from the downloading because this half has to run inside the tap
   * that starts the camera — that is the gesture which unlocks unmuted
   * playback, and it only unlocks elements that already exist.
   */
  function buildVideoPool() {
    if (!config.preloadVideos) return;
    playableTargetIndexes().forEach((index) => videoElementFor(index, targets[index]));
  }

  /**
   * Start pulling the files down, one at a time.
   *
   * Honours Save-Data: someone who has asked their phone not to spend data
   * should not have megabytes fetched on the chance they point the camera at
   * the right photo. They simply get the old behaviour — the download starts on
   * the match — which still works, just not instantly.
   */
  function startVideoPreload() {
    if (preloadStarted || !config.preloadVideos) return;
    preloadStarted = true;

    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (connection && connection.saveData) return;

    buildVideoPool();
    const queue = playableTargetIndexes();
    let next = 0;

    const warmNext = () => {
      if (next >= queue.length) return;
      const el = videoPool.get(queue[next++]);
      if (!el) { warmNext(); return; }
      // Never restart something the recipient is already watching: by the time
      // the queue reaches the later photos a match may well have happened.
      if (el === currentVideo && hasMatched) { warmNext(); return; }

      el.preload = 'auto';
      // preload is only a hint once loading has begun, so re-run the element's
      // own resource selection to make it count.
      //
      // Not for an element the start tap has already primed: that play() both
      // started it loading and unlocked it for unmuted autoplay, and there is
      // no reason to find out the hard way whether a reload would cost the
      // second of those. The gift playing silently would be a worse failure
      // than it buffering a little less eagerly.
      if (!el.__arPrimed) el.load();
      debug.videosPreloaded = next;
      renderDebug();

      // Move on when this one has buffered through, or when it has had its
      // share of the connection — whichever is sooner.
      let moved = false;
      const advance = () => {
        if (moved) return;
        moved = true;
        clearTimeout(timer);
        el.removeEventListener('canplaythrough', advance);
        el.removeEventListener('error', advance);
        warmNext();
      };
      const timer = setTimeout(advance, PRELOAD_STAGGER_MS);
      el.addEventListener('canplaythrough', advance);
      el.addEventListener('error', advance);
    };
    warmNext();
  }

  /**
   * Fetch the compiled target file ourselves, ahead of the camera.
   *
   * MindAR downloads it inside start(), after the camera is already running —
   * half a megabyte of feature data standing between "camera on" and "able to
   * recognise anything", on a connection that is idle during the permission
   * prompt. Fetching it here overlaps the two.
   *
   * Handed over as a blob: URL rather than trusting the HTTP cache, so the
   * saving does not depend on the server's cache headers or on the browser
   * choosing to reuse the entry. Falls back to the plain URL on any failure —
   * then MindAR fetches it exactly as before.
   */
  function prefetchTargetBundle() {
    if (targetSrcPromise) return targetSrcPromise;

    // The page starts this from an inline script while it is still parsing,
    // long before this module has finished downloading. Taking the promise it
    // left behind is what makes the head start count; fetching here as well
    // would download the file a second time.
    const pending = window.__arTargetPrefetch
      || fetch(config.targetUrl).then((res) => (res.ok ? res.blob() : null));

    targetSrcPromise = Promise.resolve(pending)
      .then((blob) => {
        debug.targetBundlePrefetched = !!blob;
        renderDebug();
        return blob ? URL.createObjectURL(blob) : config.targetUrl;
      })
      .catch(() => {
        debug.targetBundlePrefetched = false;
        renderDebug();
        return config.targetUrl;
      });
    return targetSrcPromise;
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
  function primeVideoElement(el) {
    const video = el || currentVideo;
    if (!video) return;
    video.muted = true;
    video.playsInline = true;
    const attempt = video.play();
    if (attempt && typeof attempt.then === 'function') {
      attempt.then(() => {
        video.pause();
        video.currentTime = 0;
        // play() forces the element to start loading, so a primed element is
        // already buffering and the warm-up must not call load() on it again.
        video.__arPrimed = true;
        debug.videoPrimed = true;
        renderDebug();
      }).catch(() => {
        debug.videoPrimed = false;
        renderDebug();
      });
    }
  }

  /**
   * Prime every photo's element, not just the first.
   *
   * The permission a gesture grants is per-element, so on a frame with several
   * photos the second one scanned would be refused sound and fall back to the
   * mute button — for no reason, since the same tap could have unlocked them
   * all. Called inside the gesture, which is why the whole pool is created
   * synchronously even though only the first starts downloading immediately.
   */
  function primeAllVideos() {
    buildVideoPool();
    if (videoPool.size === 0) { primeVideoElement(els.video); return; }
    videoPool.forEach((el) => primeVideoElement(el));
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
      if (!hasMatched) primeAllVideos();
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
  function fitFrameToVideo(el) {
    const video = el || currentVideo;
    if (!video || !video.videoWidth || !video.videoHeight) return;
    video.style.setProperty(
      '--ar-video-aspect',
      String(video.videoWidth / video.videoHeight)
    );
  }

  /**
   * Put this photo's element in the picture frame, and take the previous one
   * out. Only the current element is ever displayed, so a frame with several
   * photos never stacks two videos on top of each other.
   */
  function setCurrentVideo(el) {
    if (!el || el === currentVideo) return;
    if (currentVideo) {
      currentVideo.pause();
      currentVideo.style.display = 'none';
    }
    currentVideo = el;
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
    const video = currentVideo;
    video.style.display = 'block';

    // HAVE_METADATA or better: dimensions are known, so nothing to wait for.
    // With the file preloaded this is now the usual case rather than the
    // exception — which is the whole point: no spinner, no empty frame, just
    // the video.
    if (video.readyState >= 1) {
      fitFrameToVideo(video);
      revealFrame();
    } else {
      if (els.loading) els.loading.classList.add('is-on');
      video.addEventListener('loadedmetadata', () => {
        fitFrameToVideo(video);
        revealFrame();
      }, { once: true });
      video.addEventListener('error', showVideoError, { once: true });
    }

    video.muted = false;
    const attempt = video.play();
    if (attempt && typeof attempt.catch === 'function') {
      attempt.catch(() => {
        // Priming did not take (or was refused). Rather than leave a still
        // frame, start it muted and offer sound in one tap.
        video.muted = true;
        video.play().catch(() => {});
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
      currentVideo.muted = false;
      currentVideo.play().catch(() => {});
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
    // Each photo keeps its own element and its own buffered file, so a second
    // scan replays from memory instead of refetching. Rewinding here is what
    // makes that replay start at the beginning rather than resuming on the last
    // frame. Every pooled element is hidden, not just the current one, so a
    // switch between photos can never leave two videos in the frame.
    videoPool.forEach((el) => { el.style.display = 'none'; });
    if (currentVideo) {
      currentVideo.pause();
      currentVideo.style.display = 'none';
      if (rewind && currentVideo.readyState >= 1) currentVideo.currentTime = 0;
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
  function buildOverlayPlane(anchor, target, video) {
    // The photo's own element, so a frame with several overlay photos textures
    // each plane with the right video. A module runs in strict mode, so the
    // alias this once assigned to had to be declared — losing that declaration
    // threw a ReferenceError while the anchors were being built, which surfaced
    // to the recipient as "the camera could not be started".
    video.muted = false;
    video.loop = false;
    video.playsInline = true;

    const texture = new THREE.VideoTexture(video);
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

    // Both downloads start now and run while the permission prompt is on
    // screen, which is otherwise dead time on an idle connection.
    prefetchTargetBundle();
    startVideoPreload();

    try {
      // Ask for the camera before building anything.
      //
      // MindAR rejects with no error object at all when getUserMedia fails
      // (`.catch((err) => { console.log(...); reject(); })`), so by the time it
      // reaches our handler there is nothing left to report and every failure
      // looks identical. Requesting first means we see the real DOMException
      // and can say what actually went wrong. The permission is then already
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

      // Resolved by now in all but the fastest permission grants, since it was
      // started before the prompt went up.
      const imageTargetSrc = await prefetchTargetBundle();

      mindarThree = new MindARThree({
        container: els.container,
        imageTargetSrc: imageTargetSrc,
        // Our own guidance replaces MindAR's built-in overlays.
        uiScanning: 'no',
        uiLoading: 'no',
        uiError: 'no',
        // Announce a match sooner, and hold it through a shaky hand. The
        // careful bar is for pages where a wrong match has a cost: /scan, which
        // could play someone else's video, and the admin test, where a match
        // marks the frame verified. config.verifyUrl is only set for the latter.
        //
        // A recipient's own frame with a single photo goes all the way down to
        // zero — play on the first tracked frame. There is exactly one image in
        // that .mind file and exactly one video it could play, so "wrong match"
        // is not a category that exists here, and every frame spent confirming
        // what the matcher already decided is a frame of the pause this is
        // meant to remove.
        warmupTolerance: config.careful
          ? WARMUP_CAREFUL
          : (targets.length === 1 ? WARMUP_INSTANT : WARMUP_FAST),
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
        // The photo's own preloaded element where this page pools them, and
        // the single shared one otherwise. Only resolved for overlay targets:
        // the full-screen path creates its element on the match instead, so
        // registering a hundred anchors never opens a hundred videos.
        const overlayVideo = useOverlay
          ? (config.preloadVideos ? videoElementFor(index, target) : els.video)
          : null;
        if (useOverlay && !overlayVideo) useOverlay = false;
        if (useOverlay) {
          try {
            buildOverlayPlane(anchor, target, overlayVideo);
            overlayVideo.__arPinned = true;
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

          // Direct links and uploaded files both play from this photo's own
          // <video> element; embedded providers build an iframe instead.
          //
          // Nothing is assigned here any more. The element already exists with
          // its .src set and, on every page but scan-anything, with the file
          // already buffering — which is what makes playback start now rather
          // than after a download. Assigning .src at this point would throw all
          // of that away: it resets the element even when the URL is identical,
          // dropping readyState to nothing and the dimensions to zero.
          const playbackVideo = videoElementFor(index, target);
          if (playbackVideo) setCurrentVideo(playbackVideo);

          if (useOverlay) {
            currentVideo.style.display = 'none'; // drawn through the WebGL texture
            currentVideo.play().catch(() => {
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
            currentVideo.pause();
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

      await mindarThree.start();
      // MindAR has read the bundle, so the blob it was handed can be released.
      if (imageTargetSrc !== config.targetUrl) URL.revokeObjectURL(imageTargetSrc);
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

  // The tap is preceded by a press, and the press is preceded by the pointer
  // arriving. Neither is a decision, but by the time either fires the camera is
  // already going to be started, so the downloads may as well be under way —
  // it is a couple of hundred milliseconds off the wait, for free.
  els.startBtn.addEventListener('pointerdown', () => {
    prefetchTargetBundle();
    startVideoPreload();
  }, { passive: true });

  els.startBtn.addEventListener('click', () => {
    if (cancelTouchPrime) cancelTouchPrime();
    // Must happen inside the tap itself — this is what allows every photo's
    // video to start on its own, with sound, when it is later recognised.
    primeAllVideos();
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
      // The one place a genuine reload is wanted, so the reset that .load()
      // performs is the point rather than something to avoid.
      if (currentVideo && currentVideo.src) currentVideo.load();
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
