<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/CartServiceInterface.php';
require_once __DIR__ . '/../../Contracts/OrderRepositoryInterface.php';
require_once __DIR__ . '/../Cart/CartClimateSummaryService.php';
require_once __DIR__ . '/../Rewards/GreenPointsService.php';

final class OrderService
{
    public function __construct(
        private CartServiceInterface $cart,
        private OrderRepositoryInterface $orders,
        private ?CartClimateSummaryService $climateSummary = null,
        private ?GreenPointsService $greenPoints = null
    ) {}

    public function checkout(int $userId, string $address): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('invalid_user');
        }

        $address = trim($address);

        if ($address === '') {
            throw new InvalidArgumentException('address_required');
        }

        if ($this->orders instanceof MysqliOrderRepository) {
            if ($this->climateSummary === null) {
                return $this->orders->placeActiveCart(
                    $userId,
                    $address
                );
            }

            return $this->orders->placeActiveCart(
                $userId,
                $address,
                function (array $lines): array {
                    $cart = [];

                    foreach ($lines as $line) {
                        $productId = (int) $line['product_id'];

                        $cart[$productId] = [
                            'qty' => (int) $line['quantity'],
                            'title' => (string) $line['title'],
                            'price' => (float) $line['unit_price']
                        ];
                    }

                    // Reuse the existing climate calculation.
                    $summary = $this->climateSummary->summarize($cart);

                    // Calculate points for individual products.
                    $rewards = $this->greenPoints
                        ?? new GreenPointsService();

                    foreach ($summary['lines'] as &$item) {
                        $item['green_points_earned'] =
                            $rewards->calculatePoints([
                                [
                                    'quantity' => (int) ($item['qty'] ?? 0),
                                    'climate_rank' => (int) ($item['rank'] ?? 0)
                                ]
                            ]);
                    }

                    unset($item);

                    return $summary;
                }
            );
        }

        // Compatibility with existing repository tests.
        $items = $this->cart->items();

        if ($items === []) {
            throw new InvalidArgumentException('cart_empty');
        }

        $orderId = $this->orders->save(
            $userId,
            $address,
            $items,
            $this->cart->total()
        );

        $this->cart->clear();

        return $orderId;
    }
}