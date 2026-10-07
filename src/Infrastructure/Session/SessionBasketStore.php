<?php
declare(strict_types=1);

final class SessionBasketStore implements BasketStoreInterface
{
    public function load(string $key): array { return $_SESSION[$key] ?? []; }
    public function save(string $key, array $items): void { $_SESSION[$key] = $items; }
    public function clear(string $key): void { unset($_SESSION[$key]); }
}
