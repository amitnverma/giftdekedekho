<?php

class Order extends BaseModel
{
    protected string $table = 'orders';

    public function create(array $data): int
    {
        return $this->insertInto('orders', $data);
    }

    public function addItem(array $data): int
    {
        return $this->insertInto('order_items', $data);
    }

    public function items(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT oi.*,
                (SELECT id FROM order_video_photos WHERE order_item_id = oi.id LIMIT 1) AS video_photo_id,
                (SELECT is_active FROM order_video_photos WHERE order_item_id = oi.id LIMIT 1) AS video_photo_active
             FROM order_items oi WHERE oi.order_id = ?'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function findWithItems(int $orderId): ?array
    {
        $order = $this->find($orderId);
        if (!$order) return null;
        $order['items'] = $this->items($orderId);
        return $order;
    }

    public function userOrders(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateStatus(int $orderId, string $status): bool
    {
        return $this->updateTable('orders', $orderId, ['order_status' => $status]);
    }

    public function updatePaymentStatus(int $orderId, string $status, ?string $reference = null): bool
    {
        $data = ['payment_status' => $status];
        if ($reference !== null) $data['payment_reference'] = $reference;
        return $this->updateTable('orders', $orderId, $data);
    }

    public function setShiprocketInfo(int $orderId, string $shipmentId, string $trackingUrl): bool
    {
        return $this->updateTable('orders', $orderId, [
            'shiprocket_order_id' => $shipmentId,
            'tracking_url' => $trackingUrl,
        ]);
    }

    public function setTracking(int $orderId, string $trackingNumber, ?string $trackingUrl = null): bool
    {
        return $this->updateTable('orders', $orderId, [
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
        ]);
    }

    /**
     * Admin order list with filters: status, payment_gateway, search, date_from, date_to
     */
    public function adminList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT o.*, u.name AS customer_name, u.email AS customer_email
                FROM orders o LEFT JOIN users u ON u.id = o.user_id
                {$whereSql} ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function adminCount(array $filters): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM orders o LEFT JOIN users u ON u.id = o.user_id {$whereSql}");
        foreach ($params as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->execute();
        return (int)$stmt->fetch()['c'];
    }

    private function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'o.order_status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['payment_gateway'])) {
            $where[] = 'o.payment_gateway = :gateway';
            $params['gateway'] = $filters['payment_gateway'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(o.id = :search_id OR u.name LIKE :search_name OR u.email LIKE :search_email OR o.guest_email LIKE :search_guest)';
            $params['search_id'] = is_numeric($filters['search']) ? (int)$filters['search'] : 0;
            $params['search_name'] = '%' . $filters['search'] . '%';
            $params['search_email'] = '%' . $filters['search'] . '%';
            $params['search_guest'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(o.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(o.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        return [$where, $params];
    }

    // ---- Dashboard KPIs ----

    public function todaysRevenue(): float
    {
        $stmt = $this->db->query("SELECT COALESCE(SUM(total),0) t FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()");
        return (float)$stmt->fetch()['t'];
    }

    public function todaysOrderCount(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) c FROM orders WHERE DATE(created_at) = CURDATE()');
        return (int)$stmt->fetch()['c'];
    }

    public function recentOrders(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, COALESCE(u.name, "Guest") AS customer_name
             FROM orders o LEFT JOIN users u ON u.id = o.user_id
             ORDER BY o.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function revenueChartData(string $range = 'daily'): array
    {
        $sql = match ($range) {
            'weekly' => "SELECT YEARWEEK(created_at) AS period, MIN(DATE(created_at)) AS label, SUM(total) AS revenue
                         FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                         GROUP BY period ORDER BY period ASC",
            'monthly' => "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, MIN(DATE_FORMAT(created_at, '%b %Y')) AS label, SUM(total) AS revenue
                          FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                          GROUP BY period ORDER BY period ASC",
            default => "SELECT DATE(created_at) AS period, MIN(DATE_FORMAT(created_at, '%d %b')) AS label, SUM(total) AS revenue
                        FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY period ORDER BY period ASC",
        };
        return $this->db->query($sql)->fetchAll();
    }

    public function exportRows(): array
    {
        return $this->db->query(
            "SELECT o.id, COALESCE(u.name, 'Guest') AS customer, COALESCE(u.email, o.guest_email) AS email,
                    o.total, o.payment_status, o.order_status, o.payment_gateway, o.created_at
             FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC"
        )->fetchAll();
    }
}
