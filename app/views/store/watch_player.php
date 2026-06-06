<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= e($siteName) ?> — Your Personal Video</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { height: 100%; background: #000; overflow: hidden; }
  .wrap { position: relative; width: 100%; height: 100vh; display: flex; align-items: center; justify-content: center; }
  video { width: 100%; height: 100%; object-fit: contain; background: #000; }
  .brand { position: absolute; top: 16px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 8px; z-index: 5; opacity: .92; }
  .brand img { height: 32px; }
  .brand span { color: #fff; font-weight: 700; font-family: sans-serif; font-size: 14px; text-shadow: 0 1px 4px rgba(0,0,0,.6); }
  .tap-hint { position: absolute; bottom: 26px; left: 50%; transform: translateX(-50%); color: #fff; font-family: sans-serif; font-size: 13px; opacity: .8; text-shadow: 0 1px 4px rgba(0,0,0,.6); }
</style>
</head>
<body>
<div class="wrap" id="wrap">
  <div class="brand"><img src="<?= e($logo) ?>" alt=""><span><?= e($siteName) ?></span></div>
  <video id="vid" src="<?= e($videoUrl) ?>" playsinline autoplay loop controlslist="nodownload noremoteplayback" disablepictureinpicture></video>
  <div class="tap-hint" id="tapHint">Tap to play / pause</div>
</div>
<script>
  var vid = document.getElementById('vid');
  var wrap = document.getElementById('wrap');
  vid.muted = false;
  vid.play().catch(function () { vid.muted = true; vid.play(); });
  wrap.addEventListener('click', function () {
    if (vid.paused) { vid.play(); } else { vid.pause(); }
  });
</script>
</body>
</html>
