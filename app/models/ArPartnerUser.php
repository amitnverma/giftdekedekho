<?php

/**
 * A login for a partner's portal. Kept apart from `users` so a partner account
 * can never reach the storefront account area or the admin.
 */
class ArPartnerUser extends BaseModel
{
    protected string $table = 'ar_partner_users';

    public const ROLES = [
        'owner'  => 'Owner',
        'editor' => 'Editor',
    ];

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_partner_users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function forPartner(int $partnerId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_partner_users WHERE partner_id = ? ORDER BY id ASC');
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll();
    }

    public function findForPartner(int $partnerId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_partner_users WHERE id = ? AND partner_id = ? LIMIT 1');
        $stmt->execute([$id, $partnerId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $partnerId, string $name, string $email, string $password, string $role): int
    {
        return $this->insertInto('ar_partner_users', [
            'partner_id'    => $partnerId,
            'name'          => $name,
            'email'         => strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role'          => isset(self::ROLES[$role]) ? $role : 'editor',
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('ar_partner_users', $id, $data);
    }

    public function setPassword(int $id, string $password): bool
    {
        return $this->updateTable('ar_partner_users', $id, ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]);
    }
}
