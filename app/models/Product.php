<?php

class Product extends BaseModel
{
    protected string $table = 'products';

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function images(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function customizationOptions(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_customization_options WHERE product_id = ? ORDER BY sort_order ASC');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function featured(int $limit = 8): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS thumbnail
             FROM products p WHERE p.is_featured = 1 AND p.is_active = 1 ORDER BY p.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function relatedProducts(int $categoryId, int $excludeId, int $limit = 4): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS thumbnail
             FROM products p
             WHERE p.id != ? AND p.is_active = 1
             AND EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?)
             ORDER BY RAND() LIMIT ?'
        );
        $stmt->bindValue(1, $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Filtered + sorted + paginated listing for category pages.
     */
    public function listByCategory(?int $categoryId, array $filters, string $sort, int $limit, int $offset): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        if ($categoryId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM product_categories pc JOIN categories c2 ON c2.id = pc.category_id WHERE pc.product_id = p.id AND (pc.category_id = :cat OR c2.parent_id = :cat_parent))';
            $params['cat'] = $categoryId;
            $params['cat_parent'] = $categoryId;
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'COALESCE(p.sale_price, p.base_price) >= :min_price';
            $params['min_price'] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'COALESCE(p.sale_price, p.base_price) <= :max_price';
            $params['max_price'] = $filters['max_price'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE :q_name OR p.short_description LIKE :q_desc)';
            $params['q_name'] = '%' . $filters['q'] . '%';
            $params['q_desc'] = '%' . $filters['q'] . '%';
        }

        $orderBy = match ($sort) {
            'price_asc' => 'effective_price ASC',
            'price_desc' => 'effective_price DESC',
            'newest' => 'p.created_at DESC',
            'rating' => 'avg_rating DESC',
            default => 'p.is_featured DESC, p.created_at DESC', // popularity
        };

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT p.*,
                    COALESCE(p.sale_price, p.base_price) AS effective_price,
                    (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS thumbnail,
                    (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND is_approved = 1) AS avg_rating,
                    (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND is_approved = 1) AS review_count
                FROM products p
                WHERE {$whereSql}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countByCategory(?int $categoryId, array $filters): int
    {
        $where = ['p.is_active = 1'];
        $params = [];
        if ($categoryId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM product_categories pc JOIN categories c2 ON c2.id = pc.category_id WHERE pc.product_id = p.id AND (pc.category_id = :cat OR c2.parent_id = :cat_parent))';
            $params['cat'] = $categoryId;
            $params['cat_parent'] = $categoryId;
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'COALESCE(p.sale_price, p.base_price) >= :min_price';
            $params['min_price'] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'COALESCE(p.sale_price, p.base_price) <= :max_price';
            $params['max_price'] = $filters['max_price'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE :q_name OR p.short_description LIKE :q_desc)';
            $params['q_name'] = '%' . $filters['q'] . '%';
            $params['q_desc'] = '%' . $filters['q'] . '%';
        }
        $whereSql = implode(' AND ', $where);
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM products p WHERE {$whereSql}");
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetch()['c'];
    }

    public function search(string $term, int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.name LIKE ? OR p.sku LIKE ? ORDER BY p.id DESC LIMIT ?");
        $like = "%{$term}%";
        $stmt->bindValue(1, $like);
        $stmt->bindValue(2, $like);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function adminList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildAdminFilters($filters);
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT p.*, c.name AS category_name,
                   (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) AS thumbnail
                FROM products p JOIN categories c ON c.id = p.category_id
                {$whereSql} ORDER BY p.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function adminCount(array $filters): int
    {
        [$where, $params] = $this->buildAdminFilters($filters);
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM products p JOIN categories c ON c.id = p.category_id {$whereSql}");
        foreach ($params as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->execute();
        return (int)$stmt->fetch()['c'];
    }

    private function buildAdminFilters(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE :search OR p.sku LIKE :search_sku)';
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search_sku'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = :category_id)';
            $params['category_id'] = $filters['category_id'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'p.is_active = :status';
            $params['status'] = $filters['status'];
        }
        return [$where, $params];
    }

    public function create(array $data): int
    {
        return $this->insertInto('products', $data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('products', $id, $data);
    }

    public function addImage(int $productId, string $path, int $sortOrder = 0, bool $isPrimary = false): int
    {
        if ($isPrimary) {
            $stmt = $this->db->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?');
            $stmt->execute([$productId]);
        }
        return $this->insertInto('product_images', [
            'product_id' => $productId,
            'image_path' => $path,
            'sort_order' => $sortOrder,
            'is_primary' => $isPrimary ? 1 : 0,
        ]);
    }

    public function setPrimaryImage(int $productId, int $imageId): bool
    {
        $stmt = $this->db->prepare('UPDATE product_images SET is_primary = (id = ?) WHERE product_id = ?');
        return $stmt->execute([$imageId, $productId]);
    }

    public function deleteImage(int $imageId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_images WHERE id = ?');
        return $stmt->execute([$imageId]);
    }

    public function categoryIds(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT category_id FROM product_categories WHERE product_id = ?');
        $stmt->execute([$productId]);
        return array_column($stmt->fetchAll(), 'category_id');
    }

    public function syncCategories(int $productId, array $categoryIds): void
    {
        $this->db->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$productId]);
        foreach (array_unique(array_filter(array_map('intval', $categoryIds))) as $catId) {
            $this->db->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)')
                ->execute([$productId, $catId]);
        }
    }

    public function replaceCustomizationOptions(int $productId, array $options): void
    {
        $del = $this->db->prepare('DELETE FROM product_customization_options WHERE product_id = ?');
        $del->execute([$productId]);
        foreach ($options as $i => $opt) {
            $this->insertInto('product_customization_options', [
                'product_id' => $productId,
                'option_type' => $opt['option_type'],
                'label' => $opt['label'],
                'is_required' => !empty($opt['is_required']) ? 1 : 0,
                'extra_charge' => $opt['extra_charge'] ?? 0,
                'char_limit' => $opt['char_limit'] ?: null,
                'sort_order' => $i,
            ]);
        }
    }

    public function lowStock(int $threshold): array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE stock_qty <= ? AND is_active = 1 ORDER BY stock_qty ASC');
        $stmt->execute([$threshold]);
        return $stmt->fetchAll();
    }

    public function decrementStock(int $productId, int $qty): bool
    {
        $stmt = $this->db->prepare('UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?');
        return $stmt->execute([$qty, $productId]);
    }

    public function topSelling(int $limit = 5): array
    {
        $sql = "SELECT p.id, p.name, p.slug,
                    (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) AS thumbnail,
                    SUM(oi.quantity) AS total_sold
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                JOIN orders o ON o.id = oi.order_id AND o.payment_status = 'paid'
                GROUP BY p.id ORDER BY total_sold DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function totalCount(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) c FROM products')->fetch()['c'];
    }
}
