<?php

declare(strict_types=1);

final class GreenPointsService
{
    public const POINTS_PER_LOW_PRODUCT = 10;
    public const REWARD_THRESHOLD = 400;
    public const DISCOUNT_PERCENT = 20;

    /**
     * Calculate Green Points from purchased products.
     *
     * Climate ranks:
     * 1 = Low
     * 2 = Medium
     * 3 = High
     * 0 = Unknown
     *
     * Only Low Climate Impact products earn points.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function calculatePoints(array $items): int
    {
        $points = 0;

        foreach ($items as $item) {

            // OrderRepository uses "quantity".
            // Climate summaries may use "qty".
            $quantity = (int) (
                $item['quantity']
                ?? $item['qty']
                ?? 0
            );

            $rank = (int) (
                $item['climate_rank'] ?? 0
            );

            if ($quantity <= 0) {
                continue;
            }

            // Only Low Climate Impact earns points.
            if ($rank !== 1) {
                continue;
            }

            $points += (
                $quantity * self::POINTS_PER_LOW_PRODUCT
            );
        }

        return $points;
    }

    public function canRedeem(int $balance): bool
    {
        return $balance >= self::REWARD_THRESHOLD;
    }

    public function pointsRemaining(int $balance): int
    {
        return max(
            0,
            self::REWARD_THRESHOLD - $balance
        );
    }

    public function calculateDiscount(float $orderTotal): float
    {
        if (!is_finite($orderTotal) || $orderTotal < 0) {
            throw new InvalidArgumentException(
                'Order total cannot be negative.'
            );
        }

        return round(
            $orderTotal * self::DISCOUNT_PERCENT / 100,
            2
        );
    }
}