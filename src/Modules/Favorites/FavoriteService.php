<?php
declare(strict_types=1);

final class FavoriteService implements FavoriteServiceInterface
{
    public function __construct(private ?ProductRepositoryInterface $products, private BasketStoreInterface $store) {}
    public function items(): array { return $this->store->load('fav'); }
    public function add(int $id): int
    {
        if ($id < 1) throw new InvalidArgumentException('bad_id');
        if ($this->products === null) throw new LogicException('Product repository required for adding products.');
        $product = $this->products->findBasic($id);
        if (!$product) throw new OutOfBoundsException('not_found');
        $items = $this->items();
        $items[$id] ??= ['title' => $product['title'], 'price' => (float)$product['price']];
        $this->store->save('fav', $items);
        return count($items);
    }
    public function remove(int $id): void { $items = $this->items(); unset($items[$id]); $this->store->save('fav', $items); }
    public function clear(): void { $this->store->clear('fav'); }
}
