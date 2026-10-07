<?php
declare(strict_types=1);

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?array;
    public function findPublicById(int $id): ?array;
    public function create(string $name, string $email, string $hash): void;
}
