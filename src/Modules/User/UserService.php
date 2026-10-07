<?php
declare(strict_types=1);

final class UserService implements UserServiceInterface
{
    public function __construct(private UserRepositoryInterface $users) {}
    public function findForLogin(string $email): ?array { return $this->users->findByEmail($email); }
    public function current(?int $id): ?array { return $id !== null && $id > 0 ? $this->users->findPublicById($id) : null; }
    public function register(string $name, string $email, string $password): void { $this->users->create($name, $email, password_hash($password, PASSWORD_BCRYPT)); }
    public function passwordMatches(?array $user, string $password): bool
    {
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $matches = password_verify($password, $user['password_hash'] ?? $dummy);
        return $user !== null && $matches;
    }
}
