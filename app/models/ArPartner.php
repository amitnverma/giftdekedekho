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

    /** Registered online and waiting for the admin to activate it after payment. */
    public static function awaitingActivation(array $partner): bool
    {
        return empty($partner['is_active']) && ($partner['signup_status'] ?? null) === 'pending';
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
        $source = is_array($stored) ? $stored : self::DEFAULT_DURATION_PRICES;
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
        $source = is_array($stored) ? $stored : self::DEFAULT_VALIDITY_PRICES;
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
        $source = is_array($stored) ? $stored : self::DEFAULT_CREDIT_PACKS;
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
