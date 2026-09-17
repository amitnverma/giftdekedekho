<?php

/**
 * Bot protection for the public sign-in, sign-up and forgot-password forms.
 *
 * Every protected form gets two invisible checks: a honeypot field that people
 * never see (bots fill it in) and a minimum time between the form being shown
 * and being sent. On top of that it gets a visible challenge:
 *
 *  - Cloudflare Turnstile, once a site key and secret key are saved under
 *    Admin → General Settings → Captcha. Usually invisible to real visitors.
 *  - Otherwise a built-in image code: distorted characters drawn as SVG
 *    strokes (no font files or GD needed), with a button for a new code.
 *
 * Usage: `<?= CaptchaService::field('register') ?>` inside the form, and
 * `CaptchaService::verify('register')` (returns an error message or null)
 * in the POST handler. The image is served from /captcha?form=register.
 */
class CaptchaService
{
    private const HONEYPOT = 'gdd_hp_url';
    private const SESSION_KEY = '_captcha';
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** The forms that can carry a captcha; anything else is refused. */
    public const FORMS = ['login', 'register', 'forgot-password', 'partner-login', 'partner-forgot-password'];

    /** Seconds a person needs, at minimum, to fill in each form. */
    private const MIN_SECONDS = ['register' => 3];

    private const CODE_LENGTH = 5;

    /**
     * Characters that cannot be mistaken for one another (no 0/O/C, 1/I/L, 5/S,
     * 8/B), each drawn as polylines on a 10 × 14 grid.
     */
    private const GLYPHS = [
        'A' => [[[0, 14], [5, 0], [10, 14]], [[2, 9], [8, 9]]],
        'E' => [[[10, 0], [0, 0], [0, 14], [10, 14]], [[0, 7], [7, 7]]],
        'F' => [[[10, 0], [0, 0], [0, 14]], [[0, 7], [7, 7]]],
        'H' => [[[0, 0], [0, 14]], [[10, 0], [10, 14]], [[0, 7], [10, 7]]],
        'K' => [[[0, 0], [0, 14]], [[10, 0], [0, 9]], [[3, 6], [10, 14]]],
        'M' => [[[0, 14], [0, 0], [5, 8], [10, 0], [10, 14]]],
        'N' => [[[0, 14], [0, 0], [10, 14], [10, 0]]],
        'P' => [[[0, 14], [0, 0], [7, 0], [10, 2], [10, 5], [7, 7], [0, 7]]],
        'R' => [[[0, 14], [0, 0], [7, 0], [10, 2], [10, 5], [7, 7], [0, 7]], [[5, 7], [10, 14]]],
        'T' => [[[0, 0], [10, 0]], [[5, 0], [5, 14]]],
        'U' => [[[0, 0], [0, 11], [3, 14], [7, 14], [10, 11], [10, 0]]],
        'V' => [[[0, 0], [5, 14], [10, 0]]],
        'X' => [[[0, 0], [10, 14]], [[10, 0], [0, 14]]],
        'Y' => [[[0, 0], [5, 7], [10, 0]], [[5, 7], [5, 14]]],
        '2' => [[[0, 3], [3, 0], [7, 0], [10, 3], [10, 6], [0, 14], [10, 14]]],
        '3' => [[[0, 1], [3, 0], [7, 0], [10, 3], [7, 7], [3, 7]], [[7, 7], [10, 10], [10, 11], [7, 14], [3, 14], [0, 13]]],
        '4' => [[[8, 14], [8, 0], [0, 10], [10, 10]]],
        '7' => [[[0, 0], [10, 0], [4, 14]]],
        '9' => [[[10, 7], [3, 7], [0, 4], [0, 3], [3, 0], [7, 0], [10, 3], [10, 11], [7, 14], [2, 14]]],
    ];

    public static function enabled(): bool
    {
        return (string)siteSetting('captcha_enabled', '1') !== '0';
    }

    public static function usesTurnstile(): bool
    {
        return trim((string)siteSetting('turnstile_site_key', '')) !== ''
            && trim((string)siteSetting('turnstile_secret_key', '')) !== '';
    }

    /** The HTML to place inside a protected form, just before its submit button. */
    public static function field(string $form): string
    {
        if (!self::enabled() || !in_array($form, self::FORMS, true)) {
            return '';
        }
        $_SESSION[self::SESSION_KEY][$form]['shown'] = time();

        // Off-screen rather than display:none, which some bots know to skip.
        $html = '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">'
            . '<label>Leave this empty<input type="text" name="' . self::HONEYPOT . '" value="" tabindex="-1" autocomplete="off"></label>'
            . '</div>';

        if (self::usesTurnstile()) {
            unset($_SESSION[self::SESSION_KEY][$form]['answer']);
            return $html . '<div class="gdd-turnstile" style="margin:4px 0 16px;min-height:65px" data-sitekey="'
                . e(trim((string)siteSetting('turnstile_site_key'))) . '"></div>' . self::turnstileScript();
        }

        $_SESSION[self::SESSION_KEY][$form]['answer'] = self::newCode();
        $id = 'captcha-' . $form;
        $src = url('/captcha?form=' . rawurlencode($form));

        return $html . self::widgetAssets()
            . '<div class="gdd-captcha" role="group" aria-labelledby="' . $id . '-title">'
            .   '<div class="gdd-captcha-head">'
            .     '<svg class="gdd-captcha-shield" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5" fill="none"/></svg>'
            .     '<span id="' . $id . '-title">Security check</span>'
            .     '<span class="gdd-captcha-note">Not case-sensitive</span>'
            .   '</div>'
            .   '<div class="gdd-captcha-body">'
            .     '<div class="gdd-captcha-code">'
            .       '<img class="gdd-captcha-img" src="' . e($src . '&v=' . bin2hex(random_bytes(4))) . '" data-src="' . e($src) . '" width="150" height="48" alt="Security code: type the characters shown in this image">'
            .       '<button type="button" class="gdd-captcha-refresh" title="Show a different code" aria-label="Show a different code">'
            .         '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg>'
            .       '</button>'
            .     '</div>'
            .     '<input type="text" id="' . $id . '" class="gdd-captcha-input" name="captcha_answer" placeholder="Enter code"'
            .       ' maxlength="' . self::CODE_LENGTH . '" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" required'
            .       ' aria-label="Type the characters from the security code image">'
            .   '</div>'
            . '</div>';
    }

    /** Null when the submission passes, otherwise the message to show. */
    public static function verify(string $form): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $state = $_SESSION[self::SESSION_KEY][$form] ?? [];
        // One use per render: a replayed POST has to load the form again.
        unset($_SESSION[self::SESSION_KEY][$form]);

        $failed = 'The security check failed. Please try again.';
        if (trim((string)($_POST[self::HONEYPOT] ?? '')) !== '') {
            return $failed;
        }
        $shown = (int)($state['shown'] ?? 0);
        if ($shown === 0) {
            return 'Your form expired. Please try again.';
        }
        if (time() - $shown < (self::MIN_SECONDS[$form] ?? 0)) {
            return $failed;
        }

        if (self::usesTurnstile()) {
            return self::verifyTurnstile((string)($_POST['cf-turnstile-response'] ?? ''))
                ? null : 'Please complete the security check and try again.';
        }

        $answer = strtoupper(preg_replace('/\s+/', '', (string)($_POST['captcha_answer'] ?? '')));
        if (!isset($state['answer']) || $answer === '' || !hash_equals((string)$state['answer'], $answer)) {
            return 'The security code did not match. Please enter the new code shown.';
        }
        return null;
    }

    /**
     * GET /captcha?form=login — the code image for one form. With &refresh=1
     * it first swaps in a new code, for the refresh button.
     */
    public static function serveImage(string $form, bool $refresh): void
    {
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex');
        if (!in_array($form, self::FORMS, true) || !isset($_SESSION[self::SESSION_KEY][$form]['shown'])) {
            http_response_code(404);
            exit;
        }
        if ($refresh || empty($_SESSION[self::SESSION_KEY][$form]['answer'])) {
            $_SESSION[self::SESSION_KEY][$form]['answer'] = self::newCode();
        }
        header('Content-Type: image/svg+xml; charset=utf-8');
        echo self::renderSvg((string)$_SESSION[self::SESSION_KEY][$form]['answer']);
        exit;
    }

    private static function newCode(): string
    {
        $chars = array_keys(self::GLYPHS);
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, count($chars) - 1)];
        }
        return $code;
    }

    /** Distorted characters over crossing curves and speckles. */
    private static function renderSvg(string $code): string
    {
        $w = 150;
        $h = 48;
        $f = static fn(float $n): string => rtrim(rtrim(sprintf('%.1F', $n), '0'), '.');
        $rand = static fn(float $min, float $max): float => $min + (random_int(0, 10000) / 10000) * ($max - $min);
        $inks = ['#1f2937', '#374151', '#1e3a8a', '#7f1d1d', '#14532d', '#4c1d95'];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f8fafc"/><stop offset="1" stop-color="#e2e8f0"/></linearGradient></defs>'
            . '<rect width="' . $w . '" height="' . $h . '" fill="url(#g)"/>';

        for ($i = 0; $i < 28; $i++) {
            $svg .= '<circle cx="' . $f($rand(0, $w)) . '" cy="' . $f($rand(0, $h)) . '" r="' . $f($rand(0.6, 1.6))
                . '" fill="#94a3b8" opacity="' . $f($rand(0.3, 0.7)) . '"/>';
        }
        for ($i = 0; $i < 3; $i++) {
            $svg .= '<path d="M' . $f($rand(-5, 10)) . ' ' . $f($rand(5, $h - 5))
                . ' C' . $f($rand(30, 60)) . ' ' . $f($rand(-10, $h + 10)) . ' ' . $f($rand(90, 120)) . ' ' . $f($rand(-10, $h + 10))
                . ' ' . $f($rand($w - 10, $w + 5)) . ' ' . $f($rand(5, $h - 5))
                . '" fill="none" stroke="#64748b" stroke-width="' . $f($rand(1, 1.8)) . '" opacity="0.55"/>';
        }

        $step = ($w - 28) / strlen($code);
        foreach (str_split($code) as $i => $ch) {
            $scale = $rand(1.9, 2.2);
            $cx = 14 + $step * $i + ($step - 10 * $scale) / 2 + $rand(-1.5, 1.5);
            $cy = ($h - 14 * $scale) / 2 + $rand(-4, 4);
            $d = '';
            foreach (self::GLYPHS[$ch] as $line) {
                foreach ($line as $n => [$x, $y]) {
                    $d .= ($n === 0 ? 'M' : 'L') . $f($x + $rand(-0.6, 0.6)) . ' ' . $f($y + $rand(-0.6, 0.6));
                }
            }
            $svg .= '<path d="' . $d . '" fill="none" stroke="' . $inks[random_int(0, count($inks) - 1)] . '"'
                . ' stroke-width="' . $f($rand(1.2, 1.55)) . '" stroke-linecap="round" stroke-linejoin="round"'
                . ' transform="translate(' . $f($cx) . ' ' . $f($cy) . ') rotate(' . $f($rand(-16, 16)) . ' 5 7)'
                . ' skewX(' . $f($rand(-10, 10)) . ') scale(' . $f($scale) . ')"/>';
        }

        $svg .= '<path d="M0 ' . $f($rand(15, 33)) . ' Q' . $f($w / 2) . ' ' . $f($rand(0, $h)) . ' ' . $w . ' ' . $f($rand(15, 33))
            . '" fill="none" stroke="#334155" stroke-width="1.1" opacity="0.6"/>';
        return $svg . '</svg>';
    }

    private static function verifyTurnstile(string $token): bool
    {
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }
        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret'   => trim((string)siteSetting('turnstile_secret_key')),
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);

        // If Cloudflare cannot be reached, or the saved keys are wrong, let
        // people through rather than lock every customer and partner out;
        // the honeypot and timing checks above still applied.
        if ($body === false) {
            error_log('Turnstile verify unreachable, allowing submission: ' . $err);
            return true;
        }
        $reply = json_decode((string)$body, true);
        if (!is_array($reply)) {
            error_log('Turnstile verify returned an unreadable reply, allowing submission.');
            return true;
        }
        if (!empty($reply['success'])) {
            return true;
        }
        $codes = array_map('strval', (array)($reply['error-codes'] ?? []));
        if (array_intersect($codes, ['missing-input-secret', 'invalid-input-secret', 'internal-error'])) {
            error_log('Turnstile is misconfigured (' . implode(', ', $codes) . '), allowing submission. Check the keys in Admin → General Settings.');
            return true;
        }
        return false;
    }

    /**
     * Styles and the refresh behaviour for the built-in widget, printed once
     * per page. Self-contained so it looks the same on the storefront and in
     * every partner portal; it picks up the partner's --brand colour.
     */
    private static function widgetAssets(): string
    {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;
        return <<<'HTML'
<style>
.gdd-captcha { --gc-accent: var(--brand, var(--color-primary, #e63946)); margin: 4px 0 18px; border: 1px solid #e5e7eb; border-radius: 12px; background: #f9fafb; padding: 12px 12px 12px; font-size: 14px; }
.gdd-captcha-head { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; color: #374151; font-weight: 600; font-size: 13px; }
.gdd-captcha-shield { width: 16px; height: 16px; flex: none; fill: #16a34a; }
.gdd-captcha-shield path + path { stroke: #fff; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.gdd-captcha-note { margin-left: auto; font-weight: 400; color: #6b7280; font-size: 12px; }
.gdd-captcha-body { display: flex; gap: 10px; align-items: stretch; flex-wrap: wrap; }
.gdd-captcha-code { display: flex; flex: none; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; background: #fff; }
.gdd-captcha-img { display: block; width: 150px; height: 48px; user-select: none; -webkit-user-drag: none; }
.gdd-captcha-refresh { display: grid; place-items: center; width: 40px; padding: 0; margin: 0; border: 0; border-left: 1px solid #e5e7eb; background: #fff; color: #4b5563; cursor: pointer; }
.gdd-captcha-refresh:hover { background: #f3f4f6; color: #111827; }
.gdd-captcha-refresh:focus-visible { outline: 2px solid var(--gc-accent); outline-offset: -2px; }
.gdd-captcha-refresh svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; transition: transform .35s ease; }
.gdd-captcha-refresh.is-spinning svg { transform: rotate(360deg); }
.gdd-captcha input.gdd-captcha-input { flex: 1 1 110px; min-width: 110px; width: auto; height: 50px; margin: 0; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; color: #111827;
  font: 600 18px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .3em; text-transform: uppercase; box-sizing: border-box; }
.gdd-captcha input.gdd-captcha-input::placeholder { font-family: system-ui, -apple-system, sans-serif; font-size: 14px; font-weight: 400; letter-spacing: normal; text-transform: none; color: #9ca3af; }
.gdd-captcha input.gdd-captcha-input:focus { outline: 2px solid var(--gc-accent); outline-offset: 0; border-color: transparent; }
@media (max-width: 360px) { .gdd-captcha-code, .gdd-captcha input.gdd-captcha-input { flex-basis: 100%; } .gdd-captcha-img { flex: 1; width: auto; } }
</style>
<script>
document.addEventListener('click', function (e) {
  var btn = e.target.closest && e.target.closest('.gdd-captcha-refresh');
  if (!btn) return;
  var box = btn.closest('.gdd-captcha'), img = box.querySelector('.gdd-captcha-img'), input = box.querySelector('.gdd-captcha-input');
  img.src = img.getAttribute('data-src') + '&refresh=1&v=' + Date.now();
  btn.classList.remove('is-spinning'); void btn.offsetWidth; btn.classList.add('is-spinning');
  input.value = ''; input.focus();
});
</script>
HTML;
    }

    /**
     * Loads Turnstile once per page and renders each widget when its form is
     * visible — the storefront's Register form sits in a hidden tab at first.
     */
    private static function turnstileScript(): string
    {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;
        return <<<'HTML'
<script>
(function () {
  function renderVisible() {
    if (!window.turnstile) return;
    document.querySelectorAll('.gdd-turnstile:not([data-rendered])').forEach(function (el) {
      if (el.offsetParent === null) return;
      el.setAttribute('data-rendered', '1');
      window.turnstile.render(el, { sitekey: el.getAttribute('data-sitekey'), size: 'flexible' });
    });
  }
  window.gddTurnstileReady = renderVisible;
  document.addEventListener('click', function () { setTimeout(renderVisible, 0); });
})();
</script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=gddTurnstileReady" async defer></script>
HTML;
    }
}
