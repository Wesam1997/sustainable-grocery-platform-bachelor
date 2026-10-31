<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Contracts/CartServiceInterface.php';
require_once __DIR__ . '/../src/Contracts/OrderRepositoryInterface.php';
require_once __DIR__ . '/../src/Modules/Orders/OrderService.php';

final class OrderServiceTest extends TestCase
{
    public function testCannotCheckoutEmptyCart(): void
    {
        $cart = $this->createMock(CartServiceInterface::class);

        $cart->method('items')->willReturn([]);

        // An empty cart must never be cleared.
        $cart->expects($this->never())
            ->method('clear');

        $orders = $this->createMock(
            OrderRepositoryInterface::class
        );

        // No order should be saved.
        $orders->expects($this->never())
            ->method('save');

        $service = new OrderService($cart, $orders);

        $this->expectException(InvalidArgumentException::class);

        $service->checkout(1, 'Test Address');
    }
    
public function testSuccessfulCheckoutCreatesOrder(): void
{
    $items = [
        19 => [
            'qty' => 2,
            'title' => 'Organic Chickpeas',
            'price' => 15.00
        ]
    ];

    $cart = $this->createMock(CartServiceInterface::class);

    $cart->method('items')->willReturn($items);
    $cart->method('total')->willReturn(30.00);

    $cart->expects($this->once())
        ->method('clear');

    $orders = $this->createMock(
        OrderRepositoryInterface::class
    );

    $orders->expects($this->once())
        ->method('save')
        ->with(
            1,
            'Test Address',
            $items,
            30.00
        )
        ->willReturn(1001);

    $service = new OrderService($cart, $orders);

    $orderId = $service->checkout(1, 'Test Address');

    $this->assertSame(1001, $orderId);
}

public function testCheckoutDoesNotClearCartWhenSavingFails(): void
{
    $items = [
        19 => [
            'qty' => 2,
            'title' => 'Organic Chickpeas',
            'price' => 15.00
        ]
    ];

    $cart = $this->createMock(CartServiceInterface::class);

    $cart->method('items')->willReturn($items);
    $cart->method('total')->willReturn(30.00);

    // Cart must remain unchanged if saving fails.
    $cart->expects($this->never())
        ->method('clear');

    $orders = $this->createMock(
        OrderRepositoryInterface::class
    );

    $orders->expects($this->once())
        ->method('save')
        ->willThrowException(
            new RuntimeException('Database unavailable')
        );

    $service = new OrderService($cart, $orders);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Database unavailable');

    $service->checkout(1, 'Test Address');
}

}
