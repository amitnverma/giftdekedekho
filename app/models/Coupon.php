<?php

class Coupon extends BaseModel
{
    protected string $table = 'coupons';

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Validate coupon for a given subtotal. Returns ['ok' => bool, 'message' => string, 'discount' => float, 'coupon' => array|null]
     */
    public function validate(string $code, float $subtotal): array
    {
        $coupon = $this->findByCode($code);
        if (!$coupon) {
            return ['ok' => false, 'message' => 'Invalid coupon code.', 'discount' => 0, 'coupon' => null];
        }
        if (!$coupon['is_active']) {
            return ['ok' => false, 'message' => 'This coupon is no longer active.', 'discount' => 0, 'coupon' => null];
        }
        $today = date('Y-m-d');
        if ($today < $coupon['valid_from'] || $today > $coupon['valid_to']) {
            return ['ok' => false, 'message' => 'This coupon has expired.', 'discount' => 0, 'coupon' => null];
        }
        if ($coupon['max_uses'] !== null && $coupon['used_count'] >= $coupon['max_uses']) {
            return ['ok' => false, 'message' => 'This coupon has reached its usage limit.', 'discount' => 0, 'coupon' => null];
        }
        if ($subtotal < (float)$coupon['min_order_value']) {
            return ['ok' => false, 'message' => 'Minimum order value of ' . formatPrice($coupon['min_order_value']) . ' required for this coupon.', 'discount' => 0, 'coupon' => null];
        }

        $discount = $coupon['discount_type'] === 'percent'
            ? round($subtotal * ((float)$coupon['discount_value'] / 100), 2)
            : (float)$coupon['discount_value'];

        $discount = min($discount, $subtotal);

        return ['ok' => true, 'message' => 'Coupon applied successfully!', 'discount' => $discount, 'coupon' => $coupon];
    }

    public function incrementUsage(int $couponId): bool
    {
        $stmt = $this->db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?');
        return $stmt->execute([$couponId]);
    }

    public function create(array $data): int
    {
        $data['code'] = strtoupper(trim($data['code']));
        return $this->insertInto('coupons', $data);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['code'])) $data['code'] = strtoupper(trim($data['code']));
        return $this->updateTable('coupons', $id, $data);
    }
}
