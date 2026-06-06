<?php

class Shipping extends BaseModel
{
    protected string $table = 'shipping_rules';

    public function activeRule(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM shipping_rules WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        return $stmt->fetch() ?: null;
    }

    public function calculateCharge(float $subtotal): float
    {
        $rule = $this->activeRule();
        if (!$rule) return 0.0;
        if ($rule['free_above_amount'] !== null && $subtotal >= (float)$rule['free_above_amount']) {
            return 0.0;
        }
        return (float)$rule['flat_rate'];
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('shipping_rules', $id, $data);
    }

    public function create(array $data): int
    {
        return $this->insertInto('shipping_rules', $data);
    }

    // ---- Pincode serviceability ----

    public function checkPincode(string $pincode): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pincode_serviceability WHERE pincode = ? LIMIT 1');
        $stmt->execute([$pincode]);
        $row = $stmt->fetch();
        if (!$row) {
            // Default: serviceable with standard estimate if not explicitly listed
            return ['serviceable' => true, 'estimated_days' => 5];
        }
        return ['serviceable' => (bool)$row['is_serviceable'], 'estimated_days' => (int)$row['estimated_days']];
    }

    public function allPincodes(int $limit = 500): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pincode_serviceability ORDER BY pincode ASC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function upsertPincode(string $pincode, bool $serviceable, int $days): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pincode_serviceability (pincode, is_serviceable, estimated_days) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE is_serviceable = VALUES(is_serviceable), estimated_days = VALUES(estimated_days)'
        );
        return $stmt->execute([$pincode, $serviceable ? 1 : 0, $days]);
    }
}
