<?php

/**
 * A partner's own customer — the person their AR content is made for. Every
 * query is scoped to one partner, so no partner can read another's list.
 */
class ArPartnerCustomer extends BaseModel
{
    protected string $table = 'ar_partner_customers';

    public function forPartner(int $partnerId, string $search = ''): array
    {
        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM ar_frames f WHERE f.partner_customer_id = c.id) AS content_count
                FROM ar_partner_customers c
                WHERE c.partner_id = ?";
        $params = [$partnerId];
        $search = trim($search);
        if ($search !== '') {
            $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.reference LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY c.created_at DESC, c.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Id => name, for the customer picker. */
    public function optionsForPartner(int $partnerId): array
    {
        $stmt = $this->db->prepare('SELECT id, name, phone FROM ar_partner_customers WHERE partner_id = ? ORDER BY name ASC');
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll();
    }

    public function findForPartner(int $partnerId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_partner_customers WHERE id = ? AND partner_id = ? LIMIT 1');
        $stmt->execute([$id, $partnerId]);
        return $stmt->fetch() ?: null;
    }

    public function countForPartner(int $partnerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ar_partner_customers WHERE partner_id = ?');
        $stmt->execute([$partnerId]);
        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        return $this->insertInto('ar_partner_customers', $data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('ar_partner_customers', $id, $data);
    }
}
