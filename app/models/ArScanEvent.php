<?php

/**
 * Openings of public scan pages, for partner analytics.
 *
 * One row per page open. The visitor column is a keyed hash of IP and browser,
 * so unique visitors can be counted without storing either.
 */
class ArScanEvent extends BaseModel
{
    protected string $table = 'ar_scan_events';

    public function record(int $frameId, ?int $partnerId, string $visitor): void
    {
        $this->insertInto('ar_scan_events', [
            'frame_id'   => $frameId,
            'partner_id' => $partnerId,
            'visitor'    => $visitor,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{opens: int, visitors: int, today: int} */
    public function totals(int $partnerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS opens, COUNT(DISTINCT visitor) AS visitors,
                    SUM(created_at >= ?) AS today
             FROM ar_scan_events WHERE partner_id = ?'
        );
        $stmt->execute([date('Y-m-d 00:00:00'), $partnerId]);
        $row = $stmt->fetch() ?: [];
        return [
            'opens'    => (int)($row['opens'] ?? 0),
            'visitors' => (int)($row['visitors'] ?? 0),
            'today'    => (int)($row['today'] ?? 0),
        ];
    }

    /**
     * Opens per day for the last $days days, oldest first, with empty days
     * filled in so a chart's x-axis is continuous.
     *
     * @return array<string, int> Y-m-d => opens
     */
    public function daily(int $partnerId, int $days = 30): array
    {
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $stmt = $this->db->prepare(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM ar_scan_events
             WHERE partner_id = ? AND created_at >= ? GROUP BY DATE(created_at)'
        );
        $stmt->execute([$partnerId, $start . ' 00:00:00']);
        $found = [];
        foreach ($stmt->fetchAll() as $row) {
            $found[$row['d']] = (int)$row['c'];
        }

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
            $out[$day] = $found[$day] ?? 0;
        }
        return $out;
    }

    /** @return array{opens: int, visitors: int} */
    public function frameTotals(int $frameId): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS opens, COUNT(DISTINCT visitor) AS visitors FROM ar_scan_events WHERE frame_id = ?');
        $stmt->execute([$frameId]);
        $row = $stmt->fetch() ?: [];
        return ['opens' => (int)($row['opens'] ?? 0), 'visitors' => (int)($row['visitors'] ?? 0)];
    }

    /** Opens per piece of content, most-opened first. */
    public function perFrame(int $partnerId, ?string $kind = null, int $limit = 200): array
    {
        $sql = "SELECT f.id, f.slug, f.title, f.content_kind, f.is_active, f.active_until,
                       c.name AS customer_name,
                       COUNT(e.id) AS opens,
                       COUNT(DISTINCT e.visitor) AS visitors,
                       MAX(e.created_at) AS last_opened
                FROM ar_frames f
                LEFT JOIN ar_scan_events e ON e.frame_id = f.id
                LEFT JOIN ar_partner_customers c ON c.id = f.partner_customer_id
                WHERE f.partner_id = ?";
        $params = [$partnerId];
        if ($kind !== null) {
            $sql .= ' AND f.content_kind = ?';
            $params[] = $kind;
        }
        $sql .= ' GROUP BY f.id ORDER BY opens DESC, f.created_at DESC LIMIT ' . max(1, $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
