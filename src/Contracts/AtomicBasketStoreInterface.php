<?php
declare(strict_types=1);

require_once __DIR__ . '/BasketStoreInterface.php';

/** Executes a read/modify/write operation as one storage operation. */
interface AtomicBasketStoreInterface extends BasketStoreInterface
{
    /** The callback receives the current items and returns the new items. */
    public function mutate(string $key, callable $change): array;
}
