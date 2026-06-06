<?php

class Settings extends BaseModel
{
    protected string $table = 'settings';

    public function get(string $key, $default = '')
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    public function getMany(array $keys): array
    {
        if (empty($keys)) return [];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
        $stmt->execute($keys);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    public function getAll(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    public function set(string $key, $value): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        return $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
    }

    public function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }
    }
}
