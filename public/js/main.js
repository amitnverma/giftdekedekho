/* GiftDekeDekho — storefront JS */

/* ---- Header search: typewriter placeholder + category dropdown ---- */
(function () {
  'use strict';

  var wrap   = document.getElementById('gddHSearch');
  var input  = document.getElementById('gddHSearchInput');
  var drop   = document.getElementById('gddHSearchDrop');
  if (!wrap || !input || !drop) return;

  /* ── Category dropdown ── */
  function openDrop()  { drop.classList.add('open'); }
  function closeDrop() { drop.classList.remove('open'); }

  input.addEventListener('focus', function () {
    if (input.value.trim() === '') openDrop();
  });
  input.addEventListener('input', function () {
    if (input.value.trim() !== '') closeDrop(); else openDrop();
  });
  document.addEventListener('mousedown', function (e) {
    if (!wrap.contains(e.target)) closeDrop();
  });
  drop.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('mousedown', function (e) {
      e.preventDefault();
      closeDrop();
      window.location.href = a.href;
    });
  });

  /* ── Typewriter placeholder animation ── */
  var phrases = (window.GDD_SEARCH_PHRASES && window.GDD_SEARCH_PHRASES.length)
    ? window.GDD_SEARCH_PHRASES
    : ['Search personalised gifts…'];

  var pi = 0, ci = 0, deleting = false, timer = null;

  var TYPING_SPEED  = 55;   /* ms per character while typing */
  var DELETE_SPEED  = 30;   /* ms per character while deleting */
  var PAUSE_AFTER   = 2000; /* ms to wait when full text is displayed */
  var PAUSE_BEFORE  = 400;  /* ms to wait before typing next phrase */

  function tick() {
    /* pause animation while user is typing */
    if (document.activeElement === input) {
      timer = setTimeout(tick, 300);
      return;
    }

    var phrase = phrases[pi];

    if (!deleting) {
      /* typing in */
      ci++;
      input.placeholder = phrase.slice(0, ci);
      if (ci === phrase.length) {
        deleting = true;
        timer = setTimeout(tick, PAUSE_AFTER);
      } else {
        timer = setTimeout(tick, TYPING_SPEED);
      }
    } else {
      /* deleting */
      ci--;
      input.placeholder = phrase.slice(0, ci);
      if (ci === 0) {
        deleting = false;
        pi = (pi + 1) % phrases.length;
        timer = setTimeout(tick, PAUSE_BEFORE);
      } else {
        timer = setTimeout(tick, DELETE_SPEED);
      }
    }
  }

  /* start after a short delay so page feels settled */
  timer = setTimeout(tick, 800);
})();

(function () {
  'use strict';

  // ---- Mobile nav toggle (legacy) ----
  var hamburger = document.getElementById('hamburgerBtn');
  var nav = document.getElementById('mainNav');
  if (hamburger && nav) {
    hamburger.addEventListener('click', function () { nav.classList.toggle('open'); });
  }

  // ---- Mobile slide-in nav (modern header) ----
  var burger = document.getElementById('gddBurgerBtn');
  var mobileNav = document.getElementById('gddMobileNav');
  if (burger && mobileNav) {
    burger.addEventListener('click', function () { mobileNav.classList.add('open'); });
    mobileNav.querySelectorAll('[data-mobile-nav-close]').forEach(function (el) {
      el.addEventListener('click', function () { mobileNav.classList.remove('open'); });
    });
  }

  // ---- Generic AJAX helper ----
  window.gddFetch = function (url, options) {
    options = options || {};
    options.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
    return fetch(url, options).then(function (r) { return r.json(); });
  };

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var input = document.querySelector('input[name="csrf_token"]');
    return meta ? meta.content : (input ? input.value : '');
  }
  window.gddCsrf = csrfToken;

  // ---- Tabs (product detail) ----
  document.querySelectorAll('.tab-buttons').forEach(function (tabBar) {
    var buttons = tabBar.querySelectorAll('button');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-tab');
        var panelGroup = tabBar.parentElement;
        buttons.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        panelGroup.querySelectorAll('.tab-panel').forEach(function (p) {
          p.classList.toggle('active', p.getAttribute('data-tab') === target);
        });
      });
    });
  });

  // ---- Auth tabs (login / register) ----
  document.querySelectorAll('.auth-tabs button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = btn.getAttribute('data-auth');
      document.querySelectorAll('.auth-tabs button').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('.auth-form').forEach(function (f) { f.classList.remove('active'); });
      btn.classList.add('active');
      var form = document.querySelector('.auth-form[data-auth="' + target + '"]');
      if (form) form.classList.add('active');
    });
  });

  // ---- Gallery ----
  var mainImg = document.getElementById('galleryMainImg');
  document.querySelectorAll('.gallery-thumbs img').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      document.querySelectorAll('.gallery-thumbs img').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
      if (mainImg) mainImg.src = thumb.getAttribute('data-full') || thumb.src;
    });
  });

  // Zoom modal
  var zoomModal = document.getElementById('zoomModal');
  var zoomImg = document.getElementById('zoomImg');
  var galleryMain = document.querySelector('.gallery-main');
  if (galleryMain && zoomModal && zoomImg) {
    galleryMain.addEventListener('click', function () {
      zoomImg.src = mainImg.src;
      zoomModal.classList.add('open');
    });
    zoomModal.addEventListener('click', function () { zoomModal.classList.remove('open'); });
  }

  // ---- Wishlist toggle ----
  document.querySelectorAll('.wishlist-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var productId = btn.getAttribute('data-product-id');
      gddFetch(window.GDD_BASE_URL + '/api/wishlist-toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + encodeURIComponent(productId) + '&csrf_token=' + encodeURIComponent(csrfToken())
      }).then(function (data) {
        if (data.requires_login) {
          window.location.href = window.GDD_BASE_URL + '/account/login';
          return;
        }
        btn.classList.toggle('active', data.added);
        btn.textContent = data.added ? '♥' : '♡';
      });
    });
  });

  // ---- Character counters for engraving ----
  document.querySelectorAll('[data-char-limit]').forEach(function (input) {
    var limit = parseInt(input.getAttribute('data-char-limit'), 10);
    var counter = document.querySelector('[data-counter-for="' + input.id + '"]');
    var preview = document.querySelector('[data-preview-for="' + input.id + '"]');
    function update() {
      var len = input.value.length;
      if (counter) counter.textContent = len + ' / ' + limit;
      if (preview) preview.textContent = input.value || 'Your custom text preview will appear here…';
    }
    input.addEventListener('input', function () {
      if (input.value.length > limit) input.value = input.value.slice(0, limit);
      update();
    });
    update();
  });

  // ---- Photo upload preview / crop (Cropper.js) ----
  document.querySelectorAll('.photo-upload-input').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files[0];
      var wrap = document.querySelector('[data-crop-for="' + input.id + '"]');
      if (!file || !wrap) return;
      if (file.size > 5 * 1024 * 1024) {
        alert('File too large. Maximum size is 5MB.');
        input.value = '';
        return;
      }
      var allowed = ['image/jpeg', 'image/png', 'image/jpg'];
      if (allowed.indexOf(file.type) === -1) {
        alert('Only JPG and PNG files are allowed.');
        input.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function (e) {
        wrap.innerHTML = '<img id="cropImg_' + input.id + '" src="' + e.target.result + '" style="max-width:100%">';
        if (window.Cropper) {
          var imgEl = document.getElementById('cropImg_' + input.id);
          if (input._cropper) input._cropper.destroy();
          input._cropper = new Cropper(imgEl, { viewMode: 1, aspectRatio: NaN, autoCropArea: 1 });
        }
      };
      reader.readAsDataURL(file);
    });
  });

  // ---- Live customization extra-charge total ----
  function recalcCustomTotal() {
    var total = 0;
    document.querySelectorAll('.customize-option [data-extra]').forEach(function (el) {
      var extra = parseFloat(el.getAttribute('data-extra')) || 0;
      var active = false;
      if (el.type === 'checkbox') active = el.checked;
      else active = !!el.value;
      if (active) total += extra;
    });
    var out = document.getElementById('customExtraTotal');
    if (out) out.textContent = total > 0 ? '+ ₹' + total.toFixed(2) + ' for customization' : '';
  }
  document.querySelectorAll('.customize-option [data-extra]').forEach(function (el) {
    el.addEventListener('change', recalcCustomTotal);
    el.addEventListener('input', recalcCustomTotal);
  });
  recalcCustomTotal();

  // ---- Pincode checker ----
  var pincodeBtn = document.getElementById('pincodeCheckBtn');
  if (pincodeBtn) {
    pincodeBtn.addEventListener('click', function () {
      var input = document.getElementById('pincodeInput');
      var resultEl = document.getElementById('pincodeResult');
      var pin = input.value.trim();
      if (!/^\d{6}$/.test(pin)) {
        resultEl.textContent = 'Please enter a valid 6-digit pincode.';
        resultEl.className = 'pincode-result fail';
        return;
      }
      resultEl.textContent = 'Checking…';
      resultEl.className = 'pincode-result';
      gddFetch(window.GDD_BASE_URL + '/api/check-pincode.php?pincode=' + encodeURIComponent(pin))
        .then(function (data) {
          if (data.serviceable) {
            resultEl.textContent = '✓ Delivery available! Estimated delivery in ' + data.estimated_days + ' days.';
            resultEl.className = 'pincode-result ok';
          } else {
            resultEl.textContent = '✗ Sorry, we currently do not deliver to this pincode.';
            resultEl.className = 'pincode-result fail';
          }
        })
        .catch(function () {
          resultEl.textContent = 'Could not check pincode right now. Please try again.';
          resultEl.className = 'pincode-result fail';
        });
    });
  }

  // ---- Cart quantity stepper (AJAX) ----
  document.querySelectorAll('.qty-stepper').forEach(function (stepper) {
    var input = stepper.querySelector('input');
    var cartId = stepper.getAttribute('data-cart-id');
    function send(qty) {
      gddFetch(window.GDD_BASE_URL + '/cart/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cart_id=' + encodeURIComponent(cartId) + '&quantity=' + encodeURIComponent(qty) + '&csrf_token=' + encodeURIComponent(csrfToken())
      }).then(function () { window.location.reload(); });
    }
    stepper.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var qty = parseInt(input.value, 10) || 1;
        qty = btn.getAttribute('data-action') === 'inc' ? qty + 1 : Math.max(1, qty - 1);
        input.value = qty;
        send(qty);
      });
    });
  });

  // ---- Coupon AJAX ----
  var couponBtn = document.getElementById('applyCouponBtn');
  if (couponBtn) {
    couponBtn.addEventListener('click', function () {
      var code = document.getElementById('couponInput').value.trim();
      var msg = document.getElementById('couponMessage');
      if (!code) return;
      gddFetch(window.GDD_BASE_URL + '/cart/coupon', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'code=' + encodeURIComponent(code) + '&csrf_token=' + encodeURIComponent(csrfToken())
      }).then(function (data) {
        msg.textContent = data.message;
        msg.style.color = data.ok ? '#1b7a43' : '#b3261e';
        if (data.ok) window.location.reload();
      });
    });
  }

  // ---- Star rating selector for review form ----
  document.querySelectorAll('.star-input').forEach(function (group) {
    var stars = group.querySelectorAll('span');
    var hidden = document.getElementById(group.getAttribute('data-target'));
    stars.forEach(function (star, idx) {
      star.addEventListener('click', function () {
        hidden.value = idx + 1;
        stars.forEach(function (s, i) { s.classList.toggle('star-full', i <= idx); s.classList.toggle('star-empty', i > idx); });
      });
    });
  });
})();

/* ---- Scroll-reveal animations (home page & beyond) ---- */
(function () {
  'use strict';
  var els = document.querySelectorAll('.reveal, .reveal-stagger');
  if (!els.length) return;
  if (!('IntersectionObserver' in window)) {
    els.forEach(function (el) { el.classList.add('is-visible'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });
  els.forEach(function (el) { io.observe(el); });
})();

/* ---- Animated stat counters (home hero) ---- */
(function () {
  'use strict';
  var counters = document.querySelectorAll('[data-count-to]');
  if (!counters.length || !('IntersectionObserver' in window)) return;
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var el = entry.target;
      io.unobserve(el);
      var to = parseFloat(el.getAttribute('data-count-to')) || 0;
      var suffix = el.getAttribute('data-suffix') || '';
      var duration = 1100;
      var start = null;
      function step(ts) {
        if (!start) start = ts;
        var progress = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = (to % 1 === 0 ? Math.floor(to * eased) : (to * eased).toFixed(1)) + suffix;
        if (progress < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    });
  }, { threshold: 0.4 });
  counters.forEach(function (el) { io.observe(el); });
})();

/* ---- Newsletter signup (home page) ---- */
(function () {
  'use strict';
  var form = document.getElementById('gddNewsletterForm');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = document.getElementById('gddNewsletterMsg');
    var email = (document.getElementById('gddNewsletterEmail') || {}).value || '';
    if (msg) {
      msg.textContent = email ? 'Thanks for subscribing! Watch your inbox for festive offers 🎉' : 'Please enter a valid email address.';
      msg.style.color = email ? '#1b7a43' : '#b3261e';
    }
    form.reset();
  });
})();

/* ---- Hero floater mouse-parallax (3D depth) ---- */
(function () {
  'use strict';
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var hero = document.querySelector('.gdd-hero2');
  if (!hero) return;
  var floaters = hero.querySelectorAll('.gdd-floater[data-depth]');
  if (!floaters.length || window.innerWidth < 1080) return;
  var raf = null;

  hero.addEventListener('mousemove', function (e) {
    if (raf) return;
    raf = requestAnimationFrame(function () {
      raf = null;
      var r = hero.getBoundingClientRect();
      // -0.5 .. 0.5 offset from hero center
      var nx = (e.clientX - r.left) / r.width - 0.5;
      var ny = (e.clientY - r.top) / r.height - 0.5;
      floaters.forEach(function (el) {
        var depth = parseFloat(el.getAttribute('data-depth')) || 20;
        el.style.setProperty('--px', (-nx * depth).toFixed(1) + 'px');
        el.style.setProperty('--py', (-ny * depth).toFixed(1) + 'px');
      });
    });
  });

  hero.addEventListener('mouseleave', function () {
    floaters.forEach(function (el) {
      el.style.setProperty('--px', '0px');
      el.style.setProperty('--py', '0px');
    });
  });
})();

/* ---- Hero Before/After Drag Slider ---- */
(function () {
  'use strict';

  var showcase = document.querySelector('.gdd-ht-showcase');
  var divider  = document.querySelector('.gdd-ht-divider');
  if (!showcase) return;

  var dragging = false;

  function setSplit(pct) {
    pct = Math.max(2, Math.min(98, pct));
    showcase.style.setProperty('--cr', (100 - pct).toFixed(1) + '%');
    if (divider) divider.style.left = pct.toFixed(1) + '%';
  }

  function animateTo(targetPct, durationMs) {
    var cr = showcase.style.getPropertyValue('--cr');
    var startPct = cr ? 100 - parseFloat(cr) : 50;
    var start = null;
    function step(ts) {
      if (!start) start = ts;
      var p    = Math.min((ts - start) / durationMs, 1);
      var ease = 1 - Math.pow(1 - p, 3);
      setSplit(startPct + (targetPct - startPct) * ease);
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  setSplit(80); /* start: left image 80% visible, right 20% */

  /* Mouse hover — split follows cursor */
  showcase.addEventListener('mousemove', function (e) {
    if (dragging) return;
    var rect = showcase.getBoundingClientRect();
    setSplit(((e.clientX - rect.left) / rect.width) * 100);
  });
  showcase.addEventListener('mouseleave', function () {
    if (!dragging) animateTo(50, 700);
  });

  /* Mouse drag */
  showcase.addEventListener('mousedown', function (e) {
    dragging = true;
    showcase.classList.add('dragging');
    var rect = showcase.getBoundingClientRect();
    setSplit(((e.clientX - rect.left) / rect.width) * 100);
  });
  document.addEventListener('mousemove', function (e) {
    if (!dragging) return;
    var rect = showcase.getBoundingClientRect();
    setSplit(((e.clientX - rect.left) / rect.width) * 100);
  });
  document.addEventListener('mouseup', function () {
    if (!dragging) return;
    dragging = false;
    showcase.classList.remove('dragging');
  });

  /* Touch */
  showcase.addEventListener('touchstart', function (e) {
    dragging = true;
    showcase.classList.add('dragging');
    var rect = showcase.getBoundingClientRect();
    setSplit(((e.touches[0].clientX - rect.left) / rect.width) * 100);
  }, { passive: true });
  showcase.addEventListener('touchmove', function (e) {
    var rect = showcase.getBoundingClientRect();
    setSplit(((e.touches[0].clientX - rect.left) / rect.width) * 100);
    e.preventDefault();
  }, { passive: false });
  showcase.addEventListener('touchend', function () {
    dragging = false;
    showcase.classList.remove('dragging');
    animateTo(50, 600);
  });

  /* Intro: starts at 80% then eases to 50% so visitor notices the slider */
  setTimeout(function () { animateTo(50, 900); }, 1200);
})();
