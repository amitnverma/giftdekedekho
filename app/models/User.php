<?php

class User extends BaseModel
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $name, string $email, string $password, ?string $phone = null, string $role = 'customer'): int
    {
        return $this->insertInto('users', [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'phone' => $phone,
            'role' => $role,
        ]);
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public function updateProfile(int $id, string $name, ?string $phone): bool
    {
        return $this->updateTable('users', $id, ['name' => $name, 'phone' => $phone]);
    }

    public function updatePassword(int $id, string $password): bool
    {
        return $this->updateTable('users', $id, ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]);
    }

    public function search(string $term, int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE role = 'customer' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY id DESC LIMIT ?");
        $like = "%{$term}%";
        $stmt->bindValue(1, $like);
        $stmt->bindValue(2, $like);
        $stmt->bindValue(3, $like);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function customerCount(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) c FROM users WHERE role = 'customer'")->fetch()['c'];
    }

    public function newCustomersToday(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM users WHERE role = 'customer' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        return (int)$stmt->fetch()['c'];
    }
}
