<?php

class Category extends BaseModel
{
    protected string $table = 'categories';

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function activeTopLevel(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order, name');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function allActive(): array
    {
        return $this->db->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    }

    public function children(int $parentId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order, name');
        $stmt->execute([$parentId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        return $this->insertInto('categories', $data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('categories', $id, $data);
    }

    public function withProductCount(): array
    {
        $sql = "SELECT c.*, (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) AS product_count
                FROM categories c ORDER BY c.sort_order, c.name";
        return $this->db->query($sql)->fetchAll();
    }
}
