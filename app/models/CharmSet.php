<?php

/**
 * Charm library: reusable sets of selectable images ("charms") that customers
 * pick from a visual grid when customising a product (option_type = image_choice).
 */
class CharmSet extends BaseModel
{
    protected string $table = 'charm_sets';

    /** All sets with a charm count, newest first. */
    public function allWithCounts(): array
    {
        return $this->db->query(
            'SELECT cs.*, (SELECT COUNT(*) FROM charms c WHERE c.set_id = cs.id) AS charm_count
             FROM charm_sets cs ORDER BY cs.created_at DESC'
        )->fetchAll();
    }

    /** Sets available for binding to a product option (active only). */
    public function activeWithCounts(): array
    {
        return $this->db->query(
            'SELECT cs.*, (SELECT COUNT(*) FROM charms c WHERE c.set_id = cs.id AND c.is_active = 1) AS charm_count
             FROM charm_sets cs WHERE cs.is_active = 1 ORDER BY cs.name ASC'
        )->fetchAll();
    }

    public function create(string $name, bool $isActive = true): int
    {
        return $this->insertInto('charm_sets', [
            'name' => $name,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function updateSet(int $id, string $name, bool $isActive): bool
    {
        return $this->updateTable('charm_sets', $id, [
            'name' => $name,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    /** Charms within a set (all, for admin editing). */
    public function charms(int $setId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM charms WHERE set_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$setId]);
        return $stmt->fetchAll();
    }

    /** Active charms within a set (for the storefront grid). */
    public function activeCharms(int $setId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM charms WHERE set_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$setId]);
        return $stmt->fetchAll();
    }

    public function findCharm(int $charmId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM charms WHERE id = ? LIMIT 1');
        $stmt->execute([$charmId]);
        return $stmt->fetch() ?: null;
    }

    /** Highest sort_order currently used in a set (so new uploads append). */
    public function maxSort(int $setId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM charms WHERE set_id = ?');
        $stmt->execute([$setId]);
        return (int)$stmt->fetchColumn();
    }

    public function addCharm(int $setId, string $label, string $imagePath, float $extra, int $sort): int
    {
        return $this->insertInto('charms', [
            'set_id' => $setId,
            'label' => $label,
            'image_path' => $imagePath,
            'extra_charge' => $extra,
            'sort_order' => $sort,
            'is_active' => 1,
        ]);
    }

    public function updateCharm(int $charmId, array $data): bool
    {
        return $this->updateTable('charms', $charmId, $data);
    }

    public function deleteCharm(int $charmId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM charms WHERE id = ?');
        return $stmt->execute([$charmId]);
    }
}
