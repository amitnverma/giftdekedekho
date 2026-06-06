<?php

class Cart extends BaseModel
{
    protected string $table = 'cart';

    private function identity(): array
    {
        $sessionId = session_id();
        $userId = currentUserId();
        return [$sessionId, $userId];
    }

    public function items(): array
    {
        [$sessionId, $userId] = $this->identity();
        if ($userId) {
            $stmt = $this->db->prepare(
                'SELECT c.*, p.name, p.slug, p.base_price, p.sale_price, p.stock_qty,
                        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) AS thumbnail
                 FROM cart c JOIN products p ON p.id = c.product_id
                 WHERE c.user_id = ? OR c.session_id = ?
                 ORDER BY c.added_at DESC'
            );
            $stmt->execute([$userId, $sessionId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT c.*, p.name, p.slug, p.base_price, p.sale_price, p.stock_qty,
                        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) AS thumbnail
                 FROM cart c JOIN products p ON p.id = c.product_id
                 WHERE c.session_id = ?
                 ORDER BY c.added_at DESC'
            );
            $stmt->execute([$sessionId]);
        }
        return $stmt->fetchAll();
    }

    public function add(int $productId, int $quantity, array $customization): int
    {
        [$sessionId, $userId] = $this->identity();
        return $this->insertInto('cart', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'customization_json' => json_encode($customization),
        ]);
    }

    public function updateQuantity(int $cartId, int $quantity): bool
    {
        [$sessionId, $userId] = $this->identity();
        $stmt = $this->db->prepare('UPDATE cart SET quantity = ? WHERE id = ? AND (session_id = ? OR user_id = ?)');
        return $stmt->execute([$quantity, $cartId, $sessionId, $userId]);
    }

    public function removeItem(int $cartId): bool
    {
        [$sessionId, $userId] = $this->identity();
        $stmt = $this->db->prepare('DELETE FROM cart WHERE id = ? AND (session_id = ? OR user_id = ?)');
        return $stmt->execute([$cartId, $sessionId, $userId]);
    }

    public function clear(): bool
    {
        [$sessionId, $userId] = $this->identity();
        $stmt = $this->db->prepare('DELETE FROM cart WHERE session_id = ? OR user_id = ?');
        return $stmt->execute([$sessionId, $userId]);
    }

    public function mergeGuestCartIntoUser(int $userId): void
    {
        $sessionId = session_id();
        $stmt = $this->db->prepare('UPDATE cart SET user_id = ? WHERE session_id = ? AND user_id IS NULL');
        $stmt->execute([$userId, $sessionId]);
    }

    public function lineTotal(array $item): float
    {
        $price = $item['sale_price'] !== null ? (float)$item['sale_price'] : (float)$item['base_price'];
        $custom = json_decode($item['customization_json'] ?? '[]', true) ?: [];
        $extra = 0.0;
        foreach ($custom as $c) {
            $extra += (float)($c['extra_charge'] ?? 0);
        }
        return ($price + $extra) * (int)$item['quantity'];
    }

    public function subtotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += $this->lineTotal($item);
        }
        return $total;
    }
}
