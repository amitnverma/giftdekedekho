<?php

class Review extends BaseModel
{
    protected string $table = 'reviews';

    public function approvedForProduct(int $productId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name AS user_name FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? AND r.is_approved = 1
             ORDER BY r.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $productId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function ratingSummary(int $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT rating, COUNT(*) AS cnt FROM reviews WHERE product_id = ? AND is_approved = 1 GROUP BY rating'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll();
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total = 0;
        $sum = 0;
        foreach ($rows as $row) {
            $dist[(int)$row['rating']] = (int)$row['cnt'];
            $total += (int)$row['cnt'];
            $sum += (int)$row['rating'] * (int)$row['cnt'];
        }
        $average = $total > 0 ? round($sum / $total, 1) : 0;
        return ['distribution' => $dist, 'total' => $total, 'average' => $average];
    }

    public function create(int $productId, int $userId, int $rating, ?string $title, ?string $body): int
    {
        return $this->insertInto('reviews', [
            'product_id' => $productId,
            'user_id' => $userId,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'is_approved' => 0,
        ]);
    }

    public function pending(): array
    {
        $stmt = $this->db->query(
            'SELECT r.*, u.name AS user_name, p.name AS product_name, p.slug AS product_slug
             FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
             WHERE r.is_approved = 0 ORDER BY r.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function all_(): array
    {
        $stmt = $this->db->query(
            'SELECT r.*, u.name AS user_name, p.name AS product_name, p.slug AS product_slug
             FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
             ORDER BY r.created_at DESC LIMIT 200'
        );
        return $stmt->fetchAll();
    }

    public function approve(int $id): bool
    {
        return $this->updateTable('reviews', $id, ['is_approved' => 1]);
    }

    public function reject(int $id): bool
    {
        return $this->delete($id);
    }

    public function bulkApprove(array $ids): bool
    {
        if (empty($ids)) return false;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE reviews SET is_approved = 1 WHERE id IN ($placeholders)");
        return $stmt->execute($ids);
    }
}
