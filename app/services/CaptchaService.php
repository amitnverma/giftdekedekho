<?php

/**
 * Bot protection for the public sign-in, sign-up and forgot-password forms.
 *
 * Every protected form gets two invisible checks: a honeypot field that people
 * never see (bots fill it in) and a minimum time between the form being shown
 * and being sent. On top of that it gets a visible challenge:
 *
 *  - Cloudflare Turnstile, once a site key and secret key are saved under
 *    Admin → General Settings → Security. Usually invisible to real visitors.
 *  - Otherwise a small built-in sum ("What is 4 + 7?"), so the forms are
 *    protected without any outside account.
 *
 * Usage: `<?= CaptchaService::field('register') ?>` inside the form, and
 * `CaptchaService::verify('register')` (returns an error message or null)
 * in the POST handler.
 */
class CaptchaService
{
    private const HONEYPOT = 'gdd_hp_url';
    private const SESSION_KEY = '_captcha';
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Seconds a person needs, at minimum, to fill in each form. */
    private const MIN_SECONDS = ['register' => 3];

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
        if (!self::enabled()) {
            return '';
        }
        $_SESSION[self::SESSION_KEY][$form]['shown'] = time();

        // Off-screen rather than display:none, which some bots know to skip.
        $html = '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">'
            . '<label>Leave this empty<input type="text" name="' . self::HONEYPOT . '" value="" tabindex="-1" autocomplete="off"></label>'
            . '</div>';

        if (self::usesTurnstile()) {
            unset($_SESSION[self::SESSION_KEY][$form]['answer']);
            return $html . '<div class="gdd-turnstile" style="margin:12px 0;min-height:65px" data-sitekey="'
                . e(trim((string)siteSetting('turnstile_site_key'))) . '"></div>' . self::turnstileScript();
        }

        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $_SESSION[self::SESSION_KEY][$form]['answer'] = $a + $b;
        $id = 'captcha-' . preg_replace('/[^a-z0-9-]/', '', strtolower($form));
        return $html . '<div class="form-group field">'
            . '<label for="' . e($id) . '">Quick check: what is ' . $a . ' + ' . $b . '?</label>'
            . '<input type="text" id="' . e($id) . '" name="captcha_answer" inputmode="numeric" pattern="[0-9]*" maxlength="3" autocomplete="off" required>'
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

        $answer = trim((string)($_POST['captcha_answer'] ?? ''));
        if (!isset($state['answer']) || !ctype_digit($answer) || (int)$answer !== (int)$state['answer']) {
            return 'That answer to the quick check was not right. Please try again.';
        }
        return null;
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
      window.turnstile.render(el, { sitekey: el.getAttribute('data-sitekey') });
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
