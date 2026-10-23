<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/AtomicBasketStoreInterface.php';

final class FavoriteService implements FavoriteServiceInterface
{
    public function __construct(
        private ?ProductRepositoryInterface $products,
        private BasketStoreInterface $store
    ) {}

    public function items(): array
    {
        return $this->store->load('fav');
    }

    public function add(int $id): int
    {
        if ($id < 1) {
            throw new InvalidArgumentException('bad_id');
        }
        if ($this->products === null) {
            throw new LogicException('Product repository required for adding products.');
        }
        $product = $this->products->findBasic($id);
        if (!$product) {
            throw new OutOfBoundsException('not_found');
        }
        $items = $this->change(static function (array $items) use ($id, $product): array {
            $items[$id] ??= [
                'title' => $product['title'],
                'price' => (float) $product['price']
            ];
            return $items;
        });
        return count($items);
    }

    public function remove(int $id): void
    {
        $this->change(static function (array $items) use ($id): array {
            unset($items[$id]);
            return $items;
        });
    }

    public function clear(): void
    {
        $this->store->clear('fav');
    }

    private function change(callable $change): array
    {
        if ($this->store instanceof AtomicBasketStoreInterface) {
            return $this->store->mutate('fav', $change);
        }
        $items = $change($this->store->load('fav'));
        $this->store->save('fav', $items);
        return $items;
    }
}
