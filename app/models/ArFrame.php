<?php

class ArFrame extends BaseModel
{
    protected string $table = 'ar_frames';

    /** Status order is the pipeline order; both channels share it up to 'printed'. */
    public const STATUSES = [
        'pending_setup'     => 'Pending Setup',
        'target_generated'  => 'Target Generated',
        'verified'          => 'Verified',
        'printed'           => 'Printed',
        'shipped'           => 'Shipped',
        'handed_over'       => 'Handed Over',
    ];

    public const CHANNELS = [
        'online'   => 'Online Order',
        'in_store' => 'Walk-in',
    ];

    /**
     * Whether the ar_frames table has been created yet.
     *
     * Deployment is a plain `git reset --hard` with no migration step, so the
     * code always lands before the schema does. Callers use this to explain
     * what to run instead of throwing a raw SQL error at whoever clicks first.
     * Mirrors the intent of BaseModel::columnExists for a whole table.
     */
    public function tableExists(): bool
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $stmt = $this->db->query("SHOW TABLES LIKE 'ar_frames'");
                $exists = $stmt->fetch() !== false;
            } catch (Throwable $e) {
                $exists = false;
            }
        }
        return $exists;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_frames WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function findByOrderItem(int $orderItemId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_frames WHERE order_item_id = ? LIMIT 1');
        $stmt->execute([$orderItemId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Short, human-readable, unguessable-enough slug for the public scan URL.
     * Printed on an instruction card, so it avoids characters that are easy to
     * misread by hand (0/O, 1/l/I).
     */
    public function generateUniqueSlug(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $slug = 'gdd-' . $suffix;
        } while ($this->findBySlug($slug) !== null);
        return $slug;
    }

    /** Run a prepared read-only query. Used by services that need a custom projection. */
    public function rawQuery(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        return $this->insertInto('ar_frames', $data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('ar_frames', $id, $data);
    }

    /**
     * Frames with the order and customer context needed by the queue view.
     * LEFT JOINs throughout because walk-in frames have no order at all.
     *
     * @param array{status?: string, channel?: string, search?: string} $filters
     */
    public function paginated(array $filters, int $perPage, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $sql = "SELECT f.*,
                       oi.order_id,
                       oi.product_name_snapshot,
                       COALESCE(u.name, o.guest_email, f.customer_name) AS display_customer,
                       COALESCE(u.phone, o.guest_phone, f.customer_phone) AS display_phone,
                       a.name AS created_by_name
                FROM ar_frames f
                LEFT JOIN order_items oi ON oi.id = f.order_item_id
                LEFT JOIN orders o      ON o.id  = oi.order_id
                LEFT JOIN users u       ON u.id  = o.user_id
                LEFT JOIN users a       ON a.id  = f.created_by
                {$where}
                ORDER BY f.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countFiltered(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT COUNT(*) AS c
                FROM ar_frames f
                LEFT JOIN order_items oi ON oi.id = f.order_item_id
                LEFT JOIN orders o      ON o.id  = oi.order_id
                LEFT JOIN users u       ON u.id  = o.user_id
                {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)($stmt->fetch()['c'] ?? 0);
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        $status = $filters['status'] ?? '';
        if ($status !== '' && isset(self::STATUSES[$status])) {
            $clauses[] = 'f.status = :status';
            $params['status'] = $status;
        }

        $channel = $filters['channel'] ?? '';
        if ($channel !== '' && isset(self::CHANNELS[$channel])) {
            $clauses[] = 'f.channel = :channel';
            $params['channel'] = $channel;
        }

        // "Needs attention": not yet verified, i.e. the actionable part of the queue.
        if (!empty($filters['unverified'])) {
            $clauses[] = 'f.verified_at IS NULL';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            // Each occurrence needs its own placeholder: this connection runs with
            // PDO::ATTR_EMULATE_PREPARES off, and native prepares reject a named
            // parameter reused across the statement.
            $like = '%' . $search . '%';
            $clauses[] = '(f.slug LIKE :s1 OR f.customer_name LIKE :s2 OR f.customer_phone LIKE :s3'
                       . ' OR u.name LIKE :s4 OR o.guest_email LIKE :s5 OR CAST(oi.order_id AS CHAR) = :s6)';
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
            $params['s4'] = $like;
            $params['s5'] = $like;
            $params['s6'] = ltrim($search, '#');
        }

        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /** Counts per status, for the queue's summary tiles. */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($this->db->query('SELECT status, COUNT(*) AS c FROM ar_frames GROUP BY status')->fetchAll() as $row) {
            $counts[$row['status']] = (int)$row['c'];
        }
        return $counts;
    }

    /**
     * Full record plus joined context for the detail page.
     */
    public function findWithContext(int $id): ?array
    {
        $sql = "SELECT f.*,
                       oi.order_id,
                       oi.product_name_snapshot,
                       oi.customization_json,
                       o.order_status,
                       COALESCE(u.name, o.guest_email, f.customer_name) AS display_customer,
                       COALESCE(u.phone, o.guest_phone, f.customer_phone) AS display_phone,
                       a.name AS created_by_name
                FROM ar_frames f
                LEFT JOIN order_items oi ON oi.id = f.order_item_id
                LEFT JOIN orders o      ON o.id  = oi.order_id
                LEFT JOIN users u       ON u.id  = o.user_id
                LEFT JOIN users a       ON a.id  = f.created_by
                WHERE f.id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Which statuses an admin may move this frame to *manually*.
     *
     * The live-scan test is a hard gate, not a checkbox: 'verified' is absent
     * from every list here because the only way to reach it is passing the real
     * camera test, which sets verified_at and the status together. So there is
     * deliberately no manual path from 'target_generated' onwards — the UI
     * offers the live test instead. For walk-ins that is the entire point: you
     * prove the frame works while the customer is still at the counter rather
     * than finding out days later.
     *
     * Terminal state differs by channel — online frames ship, walk-ins are
     * handed over.
     */
    public static function allowedTransitions(array $frame): array
    {
        switch ($frame['status']) {
            case 'pending_setup':
            case 'target_generated':
                return [];  // needs a target, then a passing live scan test
            case 'verified':
                return ['printed'];
            case 'printed':
                return ($frame['channel'] ?? 'online') === 'online'
                    ? ['shipped']
                    : ['handed_over'];
            default:
                return [];
        }
    }

    /**
     * Whether the frame is ready for its live camera test — a target exists but
     * no successful scan has been recorded yet.
     */
    public static function awaitingLiveTest(array $frame): bool
    {
        return !empty($frame['target_path']) && empty($frame['verified_at']);
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUSES[$status ?? ''] ?? (string)$status;
    }

    public static function channelLabel(?string $channel): string
    {
        return self::CHANNELS[$channel ?? ''] ?? (string)$channel;
    }
}
