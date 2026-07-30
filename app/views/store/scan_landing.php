<?php
/**
 * The evergreen /scan URL.
 *
 * Deliberately not a scanner. Matching against every active target at once is
 * slow and unreliable on a phone, and gets worse with every frame sold — so this
 * page points people at their own /scan/{slug} link instead of pretending to
 * search the whole catalogue.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Your Living Photo · <?= e($siteName) ?></title>
<meta name="description" content="Open the personal link on your Living Photo card to play the video hidden in your printed photo.">
<style>
  * { margin:0; padding:0; box-sizing: border-box; }
  body { min-height:100vh; display:flex; align-items:center; justify-content:center;
         font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         background:#0f0f14; color:#fff; text-align:center; padding:28px; }
  .card { max-width: 34em; }
  img { height: 46px; margin-bottom: 24px; }
  h1 { font-size: 26px; margin-bottom: 14px; }
  p { color:#b9b9c4; line-height:1.65; margin-bottom: 18px; }
  form { display:flex; gap:10px; margin: 26px 0 10px; flex-wrap: wrap; justify-content:center; }
  input { flex:1 1 200px; padding:14px 16px; border-radius:12px; border:1px solid #33333f;
          background:#1a1a22; color:#fff; font-size:16px; font-family:inherit; }
  button { padding:14px 26px; border:0; border-radius:12px; background:#e63946; color:#fff;
           font-size:16px; font-weight:700; cursor:pointer; font-family:inherit; }
  .hint { font-size:13px; color:#7c7c8a; }
  a { color:#e63946; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
  <div class="card">
    <img src="<?= e(productImage($logo)) ?>" alt="<?= e($siteName) ?>">
    <h1>Play your Living Photo</h1>
    <p>
      Your frame came with a small card carrying a personal link. Open that link and point your camera
      at the photo — the video plays automatically. No app to install.
    </p>
    <p>Enter the code from your card:</p>
    <form method="get" action="" id="scanForm">
      <input type="text" id="scanCode" name="code" placeholder="gdd-xxxxxx" autocomplete="off"
             autocapitalize="none" spellcheck="false" required>
      <button type="submit">Open</button>
    </form>
    <p class="hint" id="scanHint">The code is six characters after “gdd-”.</p>
  </div>

<script>
// Turn the typed code into the real per-frame URL. Kept client-side so a typo
// never hits the server, and the code is normalised the way people write it.
(function () {
  var form = document.getElementById('scanForm');
  var input = document.getElementById('scanCode');
  var hint = document.getElementById('scanHint');
  var base = <?= json_encode(rtrim(SITE_URL, '/') . '/scan/', JSON_UNESCAPED_SLASHES) ?>;

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var code = input.value.trim().toLowerCase().replace(/\s+/g, '');
    if (code.indexOf('gdd-') !== 0) code = 'gdd-' + code.replace(/^-+/, '');
    if (!/^gdd-[a-z2-9]{6}$/.test(code)) {
      hint.textContent = 'That code doesn’t look right — check the card and try again.';
      hint.style.color = '#f5b400';
      return;
    }
    window.location.href = base + code;
  });
})();
</script>
</body>
</html>
