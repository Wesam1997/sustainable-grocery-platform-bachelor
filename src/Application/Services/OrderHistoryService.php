<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/OrderHistoryReaderInterface.php';

final class OrderHistoryService
{
    public function __construct(
        private OrderHistoryReaderInterface $orderHistoryReader
    ) {
    }

    /**
     * Get the most recent orders for a user.
     *
     * @return list<array{
     *     id: int,
     *     created_at: string,
     *     total: float,
     *     status: string
     * }>
     */
    public function getRecentOrders(
        int $userId,
        int $limit = 5
    ): array {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        return $this->orderHistoryReader->findRecentByUserId(
            $userId,
            $limit
        );
    }

    /**
     * Get the user's recorded climate impact.
     *
     * This represents emissions from purchases,
     * not emissions saved.
     */
    public function getClimateImpact(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'total_orders' => 0,
                'known_orders' => 0,
                'unknown_orders' => 0,
                'known_co2e_kg' => 0.0,
                'complete' => false,
            ];
        }

        return $this->orderHistoryReader
            ->findClimateImpactByUserId($userId);
    }

    /**
     * Get the user's earned Green Points.
     *
     * Points are awarded only after checkout
     * and stored with the completed order.
     *
     * Cancelled orders do not contribute.
     *
     * This method does not award new points.
     */
    public function getGreenPoints(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return max(
            0,
            $this->orderHistoryReader
                ->findGreenPointsByUserId($userId)
        );
    }
}