<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($siteName) ?> — Link Unavailable</title>
<style>
  * { margin:0; padding:0; box-sizing: border-box; }
  body { height:100vh; display:flex; align-items:center; justify-content:center; font-family: sans-serif; background:#f8f9fb; text-align:center; }
  .card { padding: 40px; }
  img { height: 48px; margin-bottom: 18px; }
  h1 { font-size: 22px; margin-bottom: 10px; }
  p { color: #6b7280; }
</style>
</head>
<body>
  <div class="card">
    <img src="<?= e($logo) ?>" alt="">
    <h1>This link is no longer valid</h1>
    <p>The video you're looking for may have been removed or the link has expired.</p>
  </div>
</body>
</html>
