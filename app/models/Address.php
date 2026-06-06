<?php

class Address extends BaseModel
{
    protected string $table = 'addresses';

    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function create(int $userId, array $data): int
    {
        if (!empty($data['is_default'])) {
            $this->clearDefault($userId);
        }
        $data['user_id'] = $userId;
        return $this->insertInto('addresses', $data);
    }

    public function update(int $id, int $userId, array $data): bool
    {
        if (!empty($data['is_default'])) {
            $this->clearDefault($userId);
        }
        $stmt = $this->db->prepare(
            'UPDATE addresses SET label=:label, address_line1=:address_line1, address_line2=:address_line2,
             city=:city, state=:state, pincode=:pincode, is_default=:is_default WHERE id=:id AND user_id=:user_id'
        );
        $data['id'] = $id;
        $data['user_id'] = $userId;
        $data['address_line2'] = $data['address_line2'] ?? null;
        $data['is_default'] = $data['is_default'] ?? 0;
        return $stmt->execute($data);
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    private function clearDefault(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function find_(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM addresses WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }
}
