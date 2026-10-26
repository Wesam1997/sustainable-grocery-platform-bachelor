<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

require_once __DIR__ . '/../src/bootstrap.php';

final class CartRulesTest extends TestCase
{
    private CartService $cart;

    /** @var ProductRepositoryInterface&MockObject */
    private ProductRepositoryInterface&MockObject $products;

    /** @var BasketStoreInterface&MockObject */
    private BasketStoreInterface&MockObject $store;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $baskets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baskets = [];

        $this->products = $this->createMock(
            ProductRepositoryInterface::class
        );

        $this->store = $this->createMock(
            BasketStoreInterface::class
        );

        // Simulate loading a basket.
        $this->store->method('load')
            ->willReturnCallback(
                fn (string $key): array =>
                    $this->baskets[$key] ?? []
            );

        // Simulate saving a basket.
        $this->store->method('save')
            ->willReturnCallback(
                function (string $key, array $items): void {
                    $this->baskets[$key] = $items;
                }
            );

        // Simulate clearing a basket.
        $this->store->method('clear')
            ->willReturnCallback(
                function (string $key): void {
                    unset($this->baskets[$key]);
                }
            );

        $this->cart = new CartService(
            $this->products,
            $this->store
        );
    }

    private function product(
        int $id,
        float $price = 25.0
    ): array {
        return [
            'id' => $id,
            'title' => 'Test Product ' . $id,
            'price' => $price
        ];
    }

    public function testAddsValidProduct(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $count = $this->cart->add(1, 2);

        $this->assertSame(2, $count);
        $this->assertSame(2, $this->cart->items()[1]['qty']);
        $this->assertSame(25.0, $this->cart->items()[1]['price']);
    }

    public function testRejectsInvalidProductId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bad_id');

        $this->cart->add(0, 1);
    }

    public function testRejectsMissingProduct(): void
    {
        $this->products->method('findBasic')
            ->willReturn(null);

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('not_found');

        $this->cart->add(999, 1);
    }

    public function testAddingZeroQuantityAddsOne(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, 0);

        $this->assertSame(1, $this->cart->items()[1]['qty']);
    }

    public function testAddingNegativeQuantityAddsOne(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, -5);

        $this->assertSame(1, $this->cart->items()[1]['qty']);
    }

    public function testAddingSameProductIncreasesQuantity(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, 2);
        $this->cart->add(1, 3);

        $this->assertSame(5, $this->cart->items()[1]['qty']);
        $this->assertCount(1, $this->cart->items());
    }

    public function testQuantityCannotExceed99(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, 150);

        $this->assertSame(99, $this->cart->items()[1]['qty']);

        $this->cart->add(1, 10);

        $this->assertSame(99, $this->cart->items()[1]['qty']);
    }

    public function testUpdatesQuantity(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, 2);
        $this->cart->update(1, 5);

        $this->assertSame(5, $this->cart->items()[1]['qty']);
    }

    public function testUpdatingToZeroRemovesProduct(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, 2);
        $this->cart->update(1, 0);

        $this->assertSame([], $this->cart->items());
    }

    public function testRemovesProduct(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, 2);
        $this->cart->remove(1);

        $this->assertSame([], $this->cart->items());
    }

    public function testCalculatesTotalPrice(): void
    {
        $this->products->method('findBasic')
            ->willReturnCallback(
                fn (int $id): ?array => match ($id) {
                    1 => $this->product(1, 25.0),
                    2 => $this->product(2, 10.0),
                    default => null
                }
            );

        $this->cart->add(1, 2);
        $this->cart->add(2, 3);

        // 2 * 25 + 3 * 10 = 80.
        $this->assertEqualsWithDelta(
            80.0,
            $this->cart->total(),
            0.0001
        );

        $this->assertSame(5, $this->cart->count());
    }

    public function testClearsCart(): void
    {
        $this->products->method('findBasic')
            ->willReturn($this->product(1));

        $this->cart->add(1, 2);
        $this->cart->clear();

        $this->assertSame([], $this->cart->items());
        $this->assertSame(0, $this->cart->count());
        $this->assertSame(0.0, $this->cart->total());
    }

    public function testRequiresProductRepositoryForAdding(): void
    {
        $cart = new CartService(null, $this->store);

        $this->expectException(LogicException::class);

        $cart->add(1, 1);
    }
}
