<?php

class OrderVideoPhoto extends BaseModel
{
    protected string $table = 'order_video_photos';

    public function findByOrderItem(int $orderItemId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_video_photos WHERE order_item_id = ? LIMIT 1');
        $stmt->execute([$orderItemId]);
        return $stmt->fetch() ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_video_photos WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $orderItemId, string $token, string $videoPath, string $qrPath, string $scanUrl): int
    {
        return $this->insertInto('order_video_photos', [
            'order_item_id' => $orderItemId,
            'token' => $token,
            'admin_video_path' => $videoPath,
            'qr_code_path' => $qrPath,
            'scan_url' => $scanUrl,
            'is_active' => 1,
        ]);
    }

    public function generateUniqueToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16)); // 32 hex chars
        } while ($this->findByToken($token) !== null);
        return $token;
    }

    public function setActive(int $id, bool $active): bool
    {
        return $this->updateTable('order_video_photos', $id, ['is_active' => $active ? 1 : 0]);
    }

    /**
     * Order items across all orders that requested the video_photo option but have no upload yet.
     */
    public function pendingUploads(): array
    {
        $sql = "SELECT oi.id AS order_item_id, oi.order_id, oi.product_name_snapshot, oi.customization_json,
                       o.created_at, COALESCE(u.name, o.guest_email) AS customer_name
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                LEFT JOIN users u ON u.id = o.user_id
                LEFT JOIN order_video_photos ovp ON ovp.order_item_id = oi.id
                WHERE ovp.id IS NULL
                  AND JSON_SEARCH(oi.customization_json, 'one', 'video_photo', NULL, '$[*].option_type') IS NOT NULL
                ORDER BY o.created_at DESC";
        return $this->db->query($sql)->fetchAll();
    }
}
