<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/AtomicBasketStoreInterface.php';

final class CartService implements CartServiceInterface
{
    public function __construct(
        private ?ProductRepositoryInterface $products,
        private BasketStoreInterface $store
    ) {}

    public function items(): array
    {
        return $this->store->load('cart');
    }

    public function add(int $id, int $quantity): int
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
        $items = $this->change(function (array $items) use ($id, $quantity, $product): array {
            $items[$id] ??= [
                'qty' => 0,
                'title' => $product['title'],
                'price' => (float) $product['price']
            ];
            // The existing product page and database both support 1..99.
            $items[$id]['qty'] = (int) min(
                99,
                max(0, min(99, (int) $items[$id]['qty']))
                + max(1, min(99, $quantity))
            );
            return $items;
        });
        return $this->itemCount($items);
    }

    public function remove(int $id): void
    {
        $this->change(static function (array $items) use ($id): array {
            unset($items[$id]);
            return $items;
        });
    }

    public function update(int $id, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($id);
            return;
        }
        $this->change(static function (array $items) use ($id, $quantity): array {
            if (isset($items[$id])) {
                $items[$id]['qty'] = min(99, $quantity);
            }
            return $items;
        });
    }

    public function clear(): void
    {
        $this->store->clear('cart');
    }

    public function count(): int
    {
        return $this->itemCount($this->items());
    }

    public function total(): float
    {
        return (float) array_sum(array_map(
            static fn (array $item): float => (float) $item['price'] * (int) $item['qty'],
            $this->items()
        ));
    }

    private function itemCount(array $items): int
    {
        return (int) array_sum(array_map(
            static fn (array $item): int => (int) ($item['qty'] ?? 0),
            $items
        ));
    }

    private function change(callable $change): array
    {
        if ($this->store instanceof AtomicBasketStoreInterface) {
            return $this->store->mutate('cart', $change);
        }
        $items = $change($this->store->load('cart'));
        $this->store->save('cart', $items);
        return $items;
    }
}
