<?php
declare(strict_types=1);

final class CartService implements CartServiceInterface
{
    public function __construct(private ?ProductRepositoryInterface $products, private BasketStoreInterface $store) {}
    public function items(): array { return $this->store->load('cart'); }
    public function add(int $id, int $quantity): int
    {
        if ($id < 1) throw new InvalidArgumentException('bad_id');
        if ($this->products === null) throw new LogicException('Product repository required for adding products.');
        $product = $this->products->findBasic($id);
        if (!$product) throw new OutOfBoundsException('not_found');
        $items = $this->items();
        $items[$id] ??= ['qty' => 0, 'title' => $product['title'], 'price' => (float)$product['price']];
        $items[$id]['qty'] += max(1, $quantity);
        $this->store->save('cart', $items);
        return $this->count();
    }
    public function remove(int $id): void { $items = $this->items(); unset($items[$id]); $this->store->save('cart', $items); }
    public function update(int $id, int $quantity): void
    {
        if ($quantity <= 0) { $this->remove($id); return; }
        $items = $this->items();
        if (isset($items[$id])) { $items[$id]['qty'] = $quantity; $this->store->save('cart', $items); }
    }
    public function clear(): void { $this->store->clear('cart'); }
    public function count(): int { return array_sum(array_map(static fn(array $item): int => (int)($item['qty'] ?? 0), $this->items())); }
    public function total(): float { return (float)array_sum(array_map(static fn(array $item): float => (float)$item['price'] * (int)$item['qty'], $this->items())); }
}
