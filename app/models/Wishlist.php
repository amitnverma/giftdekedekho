<?php

class Wishlist extends BaseModel
{
    protected string $table = 'wishlist';

    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT w.*, p.name, p.slug, p.base_price, p.sale_price,
                    (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) AS thumbnail
             FROM wishlist w JOIN products p ON p.id = w.product_id
             WHERE w.user_id = ? ORDER BY w.added_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function has(int $userId, int $productId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1');
        $stmt->execute([$userId, $productId]);
        return (bool)$stmt->fetch();
    }

    public function toggle(int $userId, int $productId): bool
    {
        if ($this->has($userId, $productId)) {
            $stmt = $this->db->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$userId, $productId]);
            return false; // now removed
        }
        $this->insertInto('wishlist', ['user_id' => $userId, 'product_id' => $productId]);
        return true; // now added
    }

    public function userIdsForProduct(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT product_id FROM wishlist WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'product_id');
    }
}
