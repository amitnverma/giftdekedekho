<?php

/**
 * A B2B partner: a shop selling Living Photo AR to its own customers through a
 * branded portal at /partner/{slug}.
 *
 * Branding (name, logo, colour, contacts), what the partner may create and what
 * it costs are all stored here, so each partner's page is edited from the admin
 * rather than in code.
 */
class ArPartner extends BaseModel
{
    protected string $table = 'ar_partners';

    /** Credits per basic item for a new partner, until the admin sets their own rate. */
    public const DEFAULT_BASE_CREDITS = 99;

    /** Video lengths a partner can be offered, in seconds. */
    public const DURATIONS = [15, 30, 60, 120, 300, 600];

    /** How long content stays scannable. Key => [label, years or null for lifetime]. */
    public const VALIDITIES = [
        '1y'       => ['1 year', 1],
        '5y'       => ['5 years', 5],
        '10y'      => ['10 years', 10],
        'lifetime' => ['Lifetime', null],
    ];

    /**
     * Built-in defaults only. The live defaults are edited in Admin → AR
     * Partners → Sign-up plans & pricing — read them through signupPlan().
     */

    /** Surcharges on top of the base rate, as in the reference pricing. */
    public const DEFAULT_DURATION_PRICES = ['15' => 0, '30' => 84, '60' => 158, '120' => 297, '300' => 495, '600' => 990];
    public const DEFAULT_VALIDITY_PRICES = ['1y' => 0, '5y' => 198, '10y' => 297, 'lifetime' => 495];

    /** Price in rupees => credits received. Chosen so a 99-credit item works out at ₹90 / ₹95.19 / ₹98.02 / ₹98.80. */
    public const DEFAULT_CREDIT_PACKS = [
        ['price' => 1000,  'credits' => 1100],
        ['price' => 2500,  'credits' => 2600],
        ['price' => 10000, 'credits' => 10100],
        ['price' => 50000, 'credits' => 50100],
    ];

    /**
     * Whether the partner migration has been run. Code deploys before
     * migrations, so every partner entry point checks this first.
     */
    public function tableExists(): bool
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $exists = $this->db->query("SHOW TABLES LIKE 'ar_partners'")->fetch() !== false
                    && $this->db->query("SHOW COLUMNS FROM ar_frames LIKE 'partner_id'")->fetch() !== false;
            } catch (Throwable $e) {
                $exists = false;
            }
        }
        return $exists;
    }

    /**
     * Whether the public registration migration has been run. Until it has,
     * /partner/register says registration is not open yet.
     */
    public function signupsReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = $this->tableExists()
                    && $this->db->query("SHOW COLUMNS FROM ar_partners LIKE 'signup_status'")->fetch() !== false;
            } catch (Throwable $e) {
                $ready = false;
            }
        }
        return $ready;
    }

    /**
     * Whether the trial migration has been run. Until it has, registration
     * keeps the "pay, then we activate" flow and nothing is ever auto-deleted.
     */
    public function trialsReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = $this->signupsReady()
                    && $this->db->query("SHOW COLUMNS FROM ar_partners LIKE 'is_trial'")->fetch() !== false
                    && $this->db->query("SHOW COLUMNS FROM ar_frames LIKE 'delete_after'")->fetch() !== false;
            } catch (Throwable $e) {
                $ready = false;
            }
        }
        return $ready;
    }

    /** Registered online and waiting for the admin to activate it after payment. */
    public static function awaitingActivation(array $partner): bool
    {
        return empty($partner['is_active']) && ($partner['signup_status'] ?? null) === 'pending';
    }

    // ----------------------------------------------------------------- trials

    public const DEFAULT_TRIAL_CREDITS = 300;
    public const DEFAULT_TRIAL_DAYS = 3;

    /**
     * The trial as set in Admin → AR Partners: whether new registrations get
     * one, the credits it comes with, and how many days trial content lives.
     *
     * @return array{enabled: bool, credits: int, days: int}
     */
    public static function trialSettings(): array
    {
        $credits = siteSetting('dex_trial_credits', '');
        $days = siteSetting('dex_trial_days', '');
        return [
            'enabled' => (string)siteSetting('dex_trial_enabled', '1') === '1',
            'credits' => $credits === '' ? self::DEFAULT_TRIAL_CREDITS : max(0, (int)$credits),
            'days'    => $days === '' ? self::DEFAULT_TRIAL_DAYS : max(1, (int)$days),
        ];
    }

    /** Free trials registration may start from one network address in TRIAL_IP_WINDOW_DAYS. */
    public const TRIALS_PER_IP = 2;
    public const TRIAL_IP_WINDOW_DAYS = 30;

    /** Set in a browser that has started a free trial, so it cannot start another. */
    public const TRIAL_COOKIE = 'gdd_dex_trial';

    /** Whether the trial_ip column exists (2026_09_24_partner_trial_guard.sql). */
    public function trialIpReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = $this->trialsReady()
                    && $this->db->query("SHOW COLUMNS FROM ar_partners LIKE 'trial_ip'")->fetch() !== false;
            } catch (Throwable $e) {
                $ready = false;
            }
        }
        return $ready;
    }

    /**
     * Why a registration may not have a free trial, or null when it may.
     *
     * A trial is for a business's first try, so it is refused when that
     * business has evidently had one or an account already: the same mobile
     * number, the same email written another way (Gmail ignores dots and
     * "+anything"), a browser that already started a trial, or a network
     * address that started TRIALS_PER_IP trials recently. None of this stops
     * the registration itself — the applicant can still choose a paid pack,
     * and the admin can still grant a trial by hand.
     */
    public function trialRefusal(string $email, string $phone, string $ip, bool $browserUsedTrial): ?string
    {
        if ($browserUsedTrial) {
            return 'a free trial was already started in this browser';
        }

        $digits = substr(preg_replace('/\D/', '', $phone), -10);
        if (strlen($digits) === 10) {
            foreach ($this->db->query('SELECT whatsapp, contact_phone FROM ar_partners')->fetchAll() as $row) {
                foreach ([$row['whatsapp'], $row['contact_phone']] as $known) {
                    if ($known !== null && substr(preg_replace('/\D/', '', (string)$known), -10) === $digits) {
                        return 'this mobile number already has a DEx partner account';
                    }
                }
            }
        }

        $wanted = self::canonicalEmail($email);
        $domain = substr($wanted, strrpos($wanted, '@') + 1);
        $stmt = $this->db->prepare('SELECT email FROM ar_partner_users WHERE email LIKE ? OR email LIKE ?');
        $stmt->execute(['%@' . $domain, $domain === 'gmail.com' ? '%@googlemail.com' : '%@' . $domain]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $known) {
            if (self::canonicalEmail((string)$known) === $wanted) {
                return 'this email address already has a DEx partner account';
            }
        }

        if ($ip !== '' && $this->trialIpReady()) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM ar_partners WHERE trial_ip = ? AND created_at > ?');
            $stmt->execute([$ip, date('Y-m-d H:i:s', time() - 86400 * self::TRIAL_IP_WINDOW_DAYS)]);
            if ((int)$stmt->fetchColumn() >= self::TRIALS_PER_IP) {
                return 'free trials were already started from this internet connection recently';
            }
        }
        return null;
    }

    /** One spelling per mailbox: lower case, no "+tag", and for Gmail no dots. */
    public static function canonicalEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return $email;
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $plus = strpos($local, '+');
        if ($plus !== false) {
            $local = substr($local, 0, $plus);
        }
        if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }
        return $local . '@' . $domain;
    }

    // -------------------------------------------------- sign-up plans & pricing

    /** Settings key holding everything Admin → AR Partners → Sign-up plans edits (JSON). */
    public const PLAN_SETTING = 'dex_signup_plan';

    /** Wording the sign-up page falls back to when the admin has not set its own. */
    public const PLAN_TEXT_DEFAULTS = [
        'heading'          => 'Choose a credit pack — or try it free',
        'heading_no_trial' => 'Choose your credit pack',
        'bonus_label'      => '+{bonus} bonus',
        'perks'            => [
            'Your DEx content stays live for its full validity',
            'Your own branded DEx Studio',
            'No subscription — top up whenever you need',
        ],
        'trial_ribbon'     => 'Try first',
        'trial_title'      => 'Free',
        'trial_sub'        => 'No payment',
        'trial_warning'    => 'Deletes in {days} days',
        'buy_button'       => 'Register — {price} pack',
        'trial_button'     => 'Start my free trial',
        'buy_note'         => 'No payment is taken now: we contact you to arrange payment, then activate your account and add the credits.',
        'buy_footnote'     => 'No payment now · we activate your account once payment is received',
        // The deletion term is always shown as well; these are the other points.
        'trial_terms'      => [
            'No payment — your DEx Studio opens as soon as you register.',
            'One free trial per business. Buy a credit pack at any time to keep what you create from then on.',
        ],
    ];

    /**
     * The sign-up offer and the default pricing, as the admin set them, with
     * the built-in values for anything never saved.
     *
     * - packs: [{price, credits, badge}], cheapest first, as sold at sign-up
     *   and given to every new partner as their credit packs;
     * - preselected: index into packs of the highlighted, preselected pack
     *   (-1 for none);
     * - base_credits, duration_prices, validity_prices: what a new partner is
     *   charged, copied into their own row when they are created;
     * - show_per_item and the texts (PLAN_TEXT_DEFAULTS) for the page.
     */
    public static function signupPlan(): array
    {
        static $plan = null;
        if ($plan !== null) {
            return $plan;
        }
        $saved = self::decode(siteSetting(self::PLAN_SETTING, '')) ?? [];

        $packs = [];
        $source = isset($saved['packs']) && is_array($saved['packs']) ? $saved['packs']
            : array_map(fn($p) => $p + ['badge' => ''], self::DEFAULT_CREDIT_PACKS);
        foreach ($source as $pack) {
            $price = (int)($pack['price'] ?? 0);
            $credits = (int)($pack['credits'] ?? 0);
            if ($price > 0 && $credits > 0) {
                $packs[] = ['price' => $price, 'credits' => $credits, 'badge' => trim((string)($pack['badge'] ?? ''))];
            }
        }
        usort($packs, fn($a, $b) => $a['price'] <=> $b['price']);

        if (array_key_exists('preselected', $saved)) {
            $preselected = (int)$saved['preselected'];
        } else {
            // Before this page existed: the "Most popular" choice, else the second pack.
            $legacy = siteSetting('dex_recommended_pack', '');
            $preselected = $legacy !== '' ? (int)$legacy : (count($packs) > 1 ? 1 : 0);
            if (isset($packs[$preselected]) && $packs[$preselected]['badge'] === '') {
                $packs[$preselected]['badge'] = 'Most popular';
            }
        }
        if (!isset($packs[$preselected])) {
            $preselected = -1;
        }

        $texts = [];
        foreach (self::PLAN_TEXT_DEFAULTS as $key => $default) {
            $value = $saved[$key] ?? $default;
            $texts[$key] = is_array($default)
                ? array_values(array_filter(array_map('trim', (array)$value), fn($l) => $l !== ''))
                : trim((string)$value);
        }
        // These cannot be hidden: a blank falls back to the built-in wording.
        foreach (['heading', 'heading_no_trial', 'trial_title', 'trial_warning', 'buy_button', 'trial_button'] as $key) {
            if ($texts[$key] === '') {
                $texts[$key] = self::PLAN_TEXT_DEFAULTS[$key];
            }
        }

        return $plan = $texts + [
            'packs'           => $packs,
            'preselected'     => $preselected,
            'show_per_item'   => !array_key_exists('show_per_item', $saved) || !empty($saved['show_per_item']),
            'base_credits'    => max(1, (int)($saved['base_credits'] ?? self::DEFAULT_BASE_CREDITS)),
            'duration_prices' => is_array($saved['duration_prices'] ?? null) ? $saved['duration_prices'] : self::DEFAULT_DURATION_PRICES,
            'validity_prices' => is_array($saved['validity_prices'] ?? null) ? $saved['validity_prices'] : self::DEFAULT_VALIDITY_PRICES,
        ];
    }

    /**
     * The pricing columns a new partner starts with — a copy of the current
     * defaults, so changing the defaults later never reprices an existing partner.
     */
    public static function defaultPricingColumns(): array
    {
        $plan = self::signupPlan();
        return [
            'base_credits'    => $plan['base_credits'],
            'duration_prices' => json_encode(self::durationPrices(['duration_prices' => $plan['duration_prices']])),
            'validity_prices' => json_encode(self::validityPrices(['validity_prices' => $plan['validity_prices']])),
            'credit_packs'    => json_encode(array_map(fn($p) => ['price' => $p['price'], 'credits' => $p['credits']], $plan['packs'])),
        ];
    }

    /** "{bonus}", "{days}", "{credits}" filled into an admin-written label. */
    public static function planText(string $text, array $values): string
    {
        $pairs = [];
        foreach ($values as $key => $value) {
            $pairs['{' . $key . '}'] = is_int($value) ? number_format($value) : (string)$value;
        }
        return strtr($text, $pairs);
    }

    public static function isTrial(array $partner): bool
    {
        return !empty($partner['is_trial']);
    }

    /**
     * The validity trial content is recorded with: the cheapest one offered.
     * It never matters for how long the content lasts — delete_after decides
     * that — but it is what the content keeps if the admin ends the trial and
     * chooses to keep it.
     */
    public static function trialValidity(array $partner): ?string
    {
        $prices = self::validityPrices($partner);
        if (!$prices) {
            return null;
        }
        asort($prices);
        return (string)array_key_first($prices);
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_partners WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /** Addresses under /partner that belong to the shared seller sign-in, not to a partner. */
    public const RESERVED_SLUGS = ['login', 'forgot-password', 'register'];

    public function slugTaken(string $slug, int $exceptId = 0): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM ar_partners WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $exceptId]);
        return $stmt->fetch() !== false;
    }

    /** A free, non-reserved page address based on the name: "rose-gifts", then "rose-gifts-2"... */
    public function uniqueSlug(string $name): string
    {
        $base = substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(slugify($name))), '-'), 0, 50);
        // slugify() answers "item" for a name with nothing it can transliterate.
        if (strlen($base) < 2 || ($base === 'item' && stripos($name, 'item') === false)) {
            $base = 'partner';
        }
        for ($n = 1; $n < 500; $n++) {
            $slug = $n === 1 ? $base : $base . '-' . $n;
            if (!in_array($slug, self::RESERVED_SLUGS, true) && !$this->slugTaken($slug)) {
                return $slug;
            }
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    public function create(array $data): int
    {
        return $this->insertInto('ar_partners', $data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('ar_partners', $id, $data);
    }

    /** Every partner with the figures the admin list needs. */
    public function listWithStats(): array
    {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM ar_frames f WHERE f.partner_id = p.id) AS content_count,
                       (SELECT COUNT(*) FROM ar_partner_customers c WHERE c.partner_id = p.id) AS customer_count,
                       (SELECT COUNT(*) FROM ar_partner_users u WHERE u.partner_id = p.id AND u.is_active = 1) AS login_count,
                       (SELECT COUNT(*) FROM ar_scan_events e WHERE e.partner_id = p.id) AS opens,
                       (SELECT COUNT(*) FROM ar_partner_credit_requests r
                         WHERE r.partner_id = p.id AND r.status = 'pending') AS pending_requests
                FROM ar_partners p
                ORDER BY p.is_active DESC, p.name ASC";
        return $this->db->query($sql)->fetchAll();
    }

    // ----------------------------------------------------------- pricing data

    /**
     * Duration => surcharge for the options this partner is offered, in
     * ascending length. A stored price list may omit durations to withhold them.
     */
    public static function durationPrices(array $partner): array
    {
        $stored = self::decode($partner['duration_prices'] ?? null);
        $source = is_array($stored) ? $stored : self::signupPlan()['duration_prices'];
        $out = [];
        foreach (self::DURATIONS as $seconds) {
            if (array_key_exists((string)$seconds, $source) && $source[(string)$seconds] !== null) {
                $out[$seconds] = max(0, (int)$source[(string)$seconds]);
            }
        }
        return $out;
    }

    /** Validity key => surcharge for the options this partner is offered. */
    public static function validityPrices(array $partner): array
    {
        $stored = self::decode($partner['validity_prices'] ?? null);
        $source = is_array($stored) ? $stored : self::signupPlan()['validity_prices'];
        $out = [];
        foreach (array_keys(self::VALIDITIES) as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null) {
                $out[$key] = max(0, (int)$source[$key]);
            }
        }
        return $out;
    }

    /** Credit packs, cheapest first, with anything malformed dropped. */
    public static function creditPacks(array $partner): array
    {
        $stored = self::decode($partner['credit_packs'] ?? null);
        $source = is_array($stored) ? $stored : self::signupPlan()['packs'];
        $packs = [];
        foreach ($source as $pack) {
            $price = (int)($pack['price'] ?? 0);
            $credits = (int)($pack['credits'] ?? 0);
            if ($price > 0 && $credits > 0) {
                $packs[] = ['price' => $price, 'credits' => $credits];
            }
        }
        usort($packs, fn($a, $b) => $a['price'] <=> $b['price']);
        return $packs;
    }

    public static function durationLabel(int $seconds): string
    {
        return $seconds . 's';
    }

    public static function validityLabel(?string $key): string
    {
        return self::VALIDITIES[$key ?? ''][0] ?? (string)$key;
    }

    private static function decode($json): ?array
    {
        if (is_array($json)) {
            return $json;
        }
        if (!is_string($json) || trim($json) === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }
}
