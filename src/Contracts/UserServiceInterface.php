<?php
declare(strict_types=1);

interface UserServiceInterface
{
    public function findForLogin(string $email): ?array;
    public function current(?int $id): ?array;
    public function register(string $name, string $email, string $password): void;
    public function passwordMatches(?array $user, string $password): bool;
}
