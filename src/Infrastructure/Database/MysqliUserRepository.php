<?php
declare(strict_types=1);

final class MysqliUserRepository implements UserRepositoryInterface
{
    public function __construct(private mysqli $connection) {}
    private function one(string $sql, string $type, $value): ?array
    {
        $stmt = $this->connection->prepare($sql);
        try { $stmt->bind_param($type, $value); $stmt->execute(); return $stmt->get_result()->fetch_assoc() ?: null; }
        finally { $stmt->close(); }
    }
    public function findByEmail(string $email): ?array { return $this->one('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1', 's', $email); }
    public function findPublicById(int $id): ?array { return $this->one('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1', 'i', $id); }
    public function create(string $name, string $email, string $hash): void
    {
        $stmt = $this->connection->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')");
        try { $stmt->bind_param('sss', $name, $email, $hash); $stmt->execute(); }
        finally { $stmt->close(); }
    }
}
