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
     * Admin edit: replaces the order's customer/payment fields and item lines,
     * then re-derives subtotal and total from the saved lines so the figures
     * can never disagree with what the order actually contains.
     *
     * $items maps order_item id => ['quantity' => int, 'unit_price' => float].
     * Ids that don't belong to this order are ignored.
     */
    public function adminUpdate(int $orderId, array $fields, array $items): void
    {
        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare('UPDATE order_items SET quantity = ?, unit_price = ? WHERE id = ? AND order_id = ?');
            foreach ($items as $itemId => $line) {
                $upd->execute([max(1, (int)$line['quantity']), max(0, round((float)$line['unit_price'], 2)), (int)$itemId, $orderId]);
            }

            $stmt = $this->db->prepare('SELECT COALESCE(SUM(unit_price * quantity), 0) s FROM order_items WHERE order_id = ?');
            $stmt->execute([$orderId]);
            $subtotal = round((float)$stmt->fetch()['s'], 2);

            $discount = min(max(0, (float)$fields['discount']), $subtotal);
            $shipping = max(0, (float)$fields['shipping_charge']);
            $fields['discount'] = $discount;
            $fields['shipping_charge'] = $shipping;
            $fields['subtotal'] = $subtotal;
            $fields['total'] = round($subtotal - $discount + $shipping, 2);

            $this->updateTable('orders', $orderId, $fields);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Permanently delete an order. order_items and order_video_photos go with it
     * (ON DELETE CASCADE); AR frames made for its items are kept but unlinked
     * (ON DELETE SET NULL), so their slugs are returned for the admin to review.
     *
     * With $restore, the stock taken at checkout is put back and the coupon's
     * use is released — what you want when removing a test order.
     *
     * Returns ['files' => paths relative to UPLOAD_PATH that are now unreferenced,
     *          'ar_frames' => slugs of AR frames that were linked to the order].
     */
    public function adminDelete(int $orderId, bool $restore): array
    {
        $order = $this->find($orderId);
        if (!$order) return ['files' => [], 'ar_frames' => []];
        $items = $this->items($orderId);
        $itemIds = array_map(fn($i) => (int)$i['id'], $items);

        $files = [];
        $arFrames = [];
        if ($itemIds) {
            $in = implode(',', array_fill(0, count($itemIds), '?'));
            $stmt = $this->db->prepare("SELECT admin_video_path, qr_code_path FROM order_video_photos WHERE order_item_id IN ({$in})");
            $stmt->execute($itemIds);
            foreach ($stmt->fetchAll() as $row) {
                foreach (['admin_video_path', 'qr_code_path'] as $col) {
                    if (!empty($row[$col])) $files[] = $row[$col];
                }
            }
            if ((new ArFrame())->tableExists()) {
                $stmt = $this->db->prepare("SELECT slug FROM ar_frames WHERE order_item_id IN ({$in})");
                $stmt->execute($itemIds);
                $arFrames = array_column($stmt->fetchAll(), 'slug');
            }
        }

        $this->db->beginTransaction();
        try {
            if ($restore) {
                $stock = $this->db->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?');
                foreach ($items as $item) {
                    if (!empty($item['product_id'])) $stock->execute([(int)$item['quantity'], (int)$item['product_id']]);
                }
                if (!empty($order['coupon_id'])) {
                    $this->db->prepare('UPDATE coupons SET used_count = GREATEST(0, used_count - 1) WHERE id = ?')
                             ->execute([(int)$order['coupon_id']]);
                }
            }
            $this->db->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['files' => $files, 'ar_frames' => $arFrames];
    }

    /**
     * Admin order list with filters: status, payment_gateway, search, date_from, date_to
     */
    public function adminList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT o.*, u.name AS customer_name, u.email AS customer_email,
                    EXISTS (
                        SELECT 1 FROM order_items oi2
                        WHERE oi2.order_id = o.id
                          AND (oi2.customization_json LIKE '%photo_upload%' OR oi2.customization_json LIKE '%text_engraving%')
                    ) AS has_personalization
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
        if (!empty($filters['has_personalization'])) {
            $where[] = "EXISTS (SELECT 1 FROM order_items oi3 WHERE oi3.order_id = o.id AND (oi3.customization_json LIKE '%photo_upload%' OR oi3.customization_json LIKE '%text_engraving%'))";
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
