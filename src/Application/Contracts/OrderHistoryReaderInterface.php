<?php

declare(strict_types=1);

interface OrderHistoryReaderInterface
{
    /**
     * Retrieve the user's most recent orders.
     *
     * @return list<array{
     *     id: int,
     *     created_at: string,
     *     total: float,
     *     status: string
     * }>
     */
    public function findRecentByUserId(
        int $userId,
        int $limit = 5
    ): array;

    /**
     * Retrieve aggregated climate data
     * from the user's stored purchases.
     *
     * Only orders with a known total_co2e_kg
     * contribute to known_co2e_kg.
     *
     * @return array{
     *     total_orders: int,
     *     known_orders: int,
     *     unknown_orders: int,
     *     known_co2e_kg: float,
     *     complete: bool
     * }
     */
    public function findClimateImpactByUserId(
        int $userId
    ): array;

    /**
     * Retrieve the user's total earned Green Points.
     *
     * Only placed orders count.
     * Cancelled orders are excluded.
     *
     * Points are read from stored purchases,
     * not recalculated when viewing the profile.
     *
     * @return int
     */
    public function findGreenPointsByUserId(
        int $userId
    ): int;
}