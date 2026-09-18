/**
 * Partner portal behaviour.
 *
 * Uploads: each photo or video is POSTed on its own the moment it is picked,
 * with a progress bar, and the server answers with a token that goes into a
 * hidden field. The form itself then submits only tokens — so an album of
 * several videos never has to fit into one request under post_max_size.
 */
(function () {
  'use strict';

  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function formatNumber(n) {
    return Number(n).toLocaleString('en-IN');
  }

  function formatBytes(bytes) {
    return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + 'MB' : Math.round(bytes / 1024) + 'KB';
  }

  // ------------------------------------------------------------- uploads

  var pendingUploads = 0;

  /**
   * Wire one upload button. `opts.onChange` runs whenever its state changes, so
   * the form can re-check whether it may be submitted.
   */
  function bindUpload(button, opts) {
    var kind = button.getAttribute('data-upload');
    var input = button.querySelector('input[type=file]');
    var bar = button.querySelector('.bar');
    var label = button.querySelector('[data-label]');
    var originalLabel = label ? label.textContent : '';
    var scope = opts.scope;
    var token = scope.querySelector('[data-token="' + kind + '"]');
    var status = scope.querySelector('[data-status="' + kind + '"]') || scope.querySelector('[data-status="row"]');
    var xhr = null;

    function setStatus(text, tone) {
      if (!status) return;
      // Album rows share one status line between image and video.
      if (status.getAttribute('data-status') === 'row') {
        status.dataset[kind] = text ? (kind === 'photo' ? 'Image: ' : 'Video: ') + text : '';
        status.textContent = [status.dataset.photo, status.dataset.video].filter(Boolean).join(' · ');
        status.classList.toggle('is-error', tone === 'error');
        return;
      }
      status.textContent = text;
      status.className = 'upload-status' + (tone ? ' is-' + tone : '');
    }

    function reset(text, tone) {
      token.value = '';
      button.classList.remove('is-done');
      button.classList.toggle('is-error', tone === 'error');
      if (label) label.textContent = originalLabel;
      bar.style.width = '0';
      setStatus(text, tone);
      opts.onChange();
    }

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) return;
      if (xhr) { xhr.abort(); xhr = null; pendingUploads = Math.max(0, pendingUploads - 1); }

      var limit = kind === 'photo' ? opts.config.maxPhotoBytes : opts.config.maxVideoBytes;
      if (file.size > limit) {
        reset(file.name + ' is ' + formatBytes(file.size) + ' — the limit is ' + formatBytes(limit) + '.', 'error');
        input.value = '';
        return;
      }

      token.value = '';
      button.classList.remove('is-done', 'is-error');
      if (label) label.textContent = 'Uploading…';
      setStatus(file.name + ' — 0%');
      pendingUploads++;
      opts.onChange();

      var data = new FormData();
      data.append('csrf_token', csrf);
      data.append('kind', kind);
      data.append('file', file);

      xhr = new XMLHttpRequest();
      xhr.open('POST', opts.config.uploadUrl);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.addEventListener('progress', function (e) {
        if (!e.lengthComputable) return;
        var pct = Math.round(e.loaded / e.total * 100);
        bar.style.width = pct + '%';
        setStatus(file.name + ' — ' + pct + '%');
      });
      xhr.addEventListener('load', function () {
        pendingUploads = Math.max(0, pendingUploads - 1);
        xhr = null;
        var reply = null;
        try { reply = JSON.parse(this.responseText); } catch (e) { /* handled below */ }
        if (!reply || !reply.ok) {
          reset((reply && reply.error) || 'Upload failed (' + this.status + '). Please try again.', 'error');
          input.value = '';
          return;
        }
        token.value = reply.token;
        button.classList.add('is-done');
        if (label) label.textContent = '✓ ' + (kind === 'photo' ? 'Image' : 'Video');
        bar.style.width = '0';
        setStatus(file.name + ' uploaded');
        if (kind === 'video') checkDuration(file);
        opts.onChange();
      });
      xhr.addEventListener('error', function () {
        pendingUploads = Math.max(0, pendingUploads - 1);
        xhr = null;
        reset('Upload failed — check your connection and try again.', 'error');
        input.value = '';
      });
      xhr.send(data);
    });

    /** Warn when a video runs longer than the length being paid for. */
    function checkDuration(file) {
      if (!opts.durationFor) return;
      var probe = document.createElement('video');
      probe.preload = 'metadata';
      var src = URL.createObjectURL(file);
      probe.addEventListener('loadedmetadata', function () {
        URL.revokeObjectURL(src);
        button.dataset.videoSeconds = String(Math.round(probe.duration || 0));
        noteDuration();
      });
      probe.addEventListener('error', function () { URL.revokeObjectURL(src); });
      probe.src = src;
    }

    function noteDuration() {
      var seconds = parseInt(button.dataset.videoSeconds || '0', 10);
      var limit = opts.durationFor ? opts.durationFor() : 0;
      if (!seconds || !token.value) return;
      if (limit && seconds > limit + 1) {
        setStatus('video is ' + seconds + 's — only the first ' + limit + 's will play', 'warn');
      } else {
        setStatus('uploaded (' + seconds + 's)');
      }
    }

    button.noteDuration = noteDuration;
  }

  // ----------------------------------------------------------- create form

  var form = document.querySelector('[data-create-form]');
  if (form) initCreateForm(form);

  function initCreateForm(form) {
    var config = JSON.parse(form.querySelector('[data-create-config]').textContent);
    var isAlbum = config.kind === 'album';
    var submit = form.querySelector('[data-submit]');
    var submitHint = form.querySelector('[data-submit-hint]');
    var priceText = form.querySelector('[data-price-text]');
    var shortBanner = form.querySelector('[data-short]');
    var shortText = form.querySelector('[data-short-text]');
    var pagesEl = form.querySelector('[data-pages]');
    var template = form.querySelector('[data-page-template]');
    var addBtn = form.querySelector('[data-add-page]');
    var pageCount = form.querySelector('[data-page-count]');
    var nextIndex = 0;

    // --- customer picker: "+ New customer…" reveals name and phone
    var customerSelect = form.querySelector('[data-customer-select]');
    var newCustomer = form.querySelector('[data-new-customer]');
    function syncCustomer() {
      var isNew = customerSelect.value === 'new';
      newCustomer.hidden = !isNew;
      newCustomer.querySelector('input[name=new_customer_name]').required = isNew;
      if (isNew) newCustomer.querySelector('input').focus();
    }
    customerSelect.addEventListener('change', syncCustomer);

    function checked(name) {
      var el = form.querySelector('input[name="' + name + '"]:checked');
      return el ? el.value : null;
    }

    function pages() {
      return Array.prototype.slice.call(form.querySelectorAll('[data-page]'));
    }

    function durationOf(page) {
      if (!isAlbum) return parseInt(checked('duration'), 10);
      return parseInt(page.querySelector('[data-page-duration]').value, 10);
    }

    function refresh() {
      var list = pages();
      var validity = checked('validity');
      var vPrice = config.validityPrices[validity] || 0;
      var total = 0;
      var ready = true;

      list.forEach(function (page, i) {
        total += config.base + (config.durationPrices[durationOf(page)] || 0) + vPrice;
        page.querySelectorAll('[data-token]').forEach(function (t) { if (!t.value) ready = false; });
        var n = page.querySelector('[data-n]');
        if (n) n.textContent = i + 1;
        var remove = page.querySelector('[data-remove-page]');
        if (remove) remove.hidden = list.length === 1;
      });

      var short = total - config.balance;
      priceText.textContent = isAlbum
        ? 'Total: ' + formatNumber(total) + ' credits for ' + list.length + ' item' + (list.length === 1 ? '' : 's')
        : 'Price: ' + formatNumber(total) + ' credits';
      shortBanner.hidden = short <= 0;
      shortText.textContent = (isAlbum ? list.length + ' page' + (list.length === 1 ? '' : 's') + ' cost ' : 'It costs ')
        + formatNumber(total) + ' credits and you have ' + formatNumber(config.balance)
        + ' credit' + (config.balance === 1 ? '' : 's') + ' — ' + formatNumber(short) + ' credits short.';

      if (pageCount) pageCount.textContent = list.length;
      if (addBtn) addBtn.hidden = config.maxPages > 0 && list.length >= config.maxPages;

      var blocked = short > 0 || pendingUploads > 0 || !ready;
      submit.disabled = blocked;
      submitHint.textContent = pendingUploads > 0 ? 'Waiting for uploads to finish…'
        : !ready ? 'Upload ' + (isAlbum ? 'an image and a video for every row' : 'the image and the video') + ' to continue.'
        : '';

      // Duration notes depend on the length chosen.
      form.querySelectorAll('[data-upload="video"]').forEach(function (b) { if (b.noteDuration) b.noteDuration(); });
    }

    function bindPage(page) {
      page.querySelectorAll('[data-upload]').forEach(function (button) {
        bindUpload(button, {
          scope: page,
          config: config,
          onChange: refresh,
          durationFor: function () { return durationOf(page); },
        });
      });
    }

    function addPage() {
      if (config.maxPages > 0 && pages().length >= config.maxPages) return;
      var html = template.innerHTML.replace(/__i__/g, String(nextIndex++));
      var holder = document.createElement('div');
      holder.innerHTML = html.trim();
      var page = holder.firstElementChild;
      page.querySelector('[data-title]').value = 'DEx Content ' + (pages().length + 1);
      page.querySelector('[data-page-duration]').value = checked('default_duration') || '';
      page.querySelector('[data-page-mode]').value = checked('default_playback_mode') || 'fullscreen';
      page.querySelector('[data-page-duration]').addEventListener('change', refresh);
      page.querySelector('[data-remove-page]').addEventListener('click', function () {
        page.remove();
        refresh();
      });
      pagesEl.appendChild(page);
      bindPage(page);
      refresh();
    }

    if (isAlbum) {
      addBtn.addEventListener('click', addPage);
      addPage();
      addPage();
    } else {
      bindPage(form.querySelector('[data-page]'));
    }

    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-duration-choice], [data-validity-choice]')) refresh();
    });

    form.addEventListener('submit', function (e) {
      refresh();
      if (submit.disabled) {
        e.preventDefault();
        return;
      }
      submit.disabled = true;
      submit.textContent = 'Creating… this can take a minute';
    });

    syncCustomer();
    refresh();
  }

  // ------------------------------------------------------------ edit form

  var editForm = document.querySelector('[data-edit-form]');
  if (editForm) initEditForm(editForm);

  function initEditForm(editForm) {
    var config = JSON.parse(editForm.getAttribute('data-edit-form'));
    var submit = editForm.querySelector('[data-submit]');
    var hint = editForm.querySelector('[data-submit-hint]');
    var saving = false;

    function refresh() {
      if (saving) return;
      submit.disabled = pendingUploads > 0;
      hint.textContent = pendingUploads > 0 ? 'Waiting for uploads to finish…' : '';
    }

    editForm.querySelectorAll('[data-edit-item]').forEach(function (item) {
      var maxSeconds = parseInt(item.getAttribute('data-max-seconds'), 10) || 0;
      item.querySelectorAll('[data-upload]').forEach(function (button) {
        bindUpload(button, {
          scope: item,
          config: config,
          onChange: refresh,
          durationFor: function () { return maxSeconds; },
        });
      });
    });

    editForm.addEventListener('submit', function (e) {
      if (pendingUploads > 0) {
        e.preventDefault();
        return;
      }
      saving = true;
      submit.disabled = true;
      submit.textContent = hasPhotoToken()
        ? 'Saving… preparing new images can take a minute'
        : 'Saving…';
    });

    function hasPhotoToken() {
      return Array.prototype.some.call(editForm.querySelectorAll('[data-token="photo"]'), function (t) { return t.value; });
    }

    refresh();
  }

  // --------------------------------------------------------- copy buttons

  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.getAttribute('data-copy'));
      if (!input) return;
      var done = function () {
        var before = button.textContent;
        button.textContent = 'Copied';
        setTimeout(function () { button.textContent = before; }, 1500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(done, function () { input.select(); });
      } else {
        input.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* the text stays selected */ }
      }
    });
  });

  // Close the user menu when clicking elsewhere.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.p-user[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
})();
