<?php

abstract class BaseModel
{
    protected PDO $db;
    protected string $table = '';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}")->fetchAll();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    protected function insertInto(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    protected function updateTable(string $table, int $id, array $data): bool
    {
        $sets = implode(',', array_map(fn($c) => "{$c} = :{$c}", array_keys($data)));
        $data['__id'] = $id;
        $sql = "UPDATE {$table} SET {$sets} WHERE id = :__id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
}
