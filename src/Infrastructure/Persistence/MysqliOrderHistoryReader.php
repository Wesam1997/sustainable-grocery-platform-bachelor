<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Application/Contracts/OrderHistoryReaderInterface.php';

/**
 * Database reader for order history, climate impact
 * and Green Points.
 *
 * Responsibilities:
 * - Retrieve the user's recent orders
 * - Retrieve purchased products for each order
 * - Aggregate recorded CO2e from placed purchases
 * - Retrieve the available Green Points balance
 * - Read stored discount and redemption information
 *
 * No presentation logic belongs here.
 */
final class MysqliOrderHistoryReader implements OrderHistoryReaderInterface
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    /**
     * Retrieve recent orders with their purchased items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findRecentByUserId(
        int $userId,
        int $limit = 5
    ): array {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        /*
         * Retrieve orders first.
         *
         * LIMIT applies to orders, not products,
         * so purchased items are not cut off.
         */
        $sql = "
            SELECT
                id,
                created_at,
                total_price,
                total_co2e_kg,
                green_points_earned,
                green_points_spent,
                discount_amount,
                status
            FROM orders
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT ?
        ";

        $statement = $this->connection->prepare($sql);

        $statement->bind_param(
            'ii',
            $userId,
            $limit
        );

        $statement->execute();

        $result = $statement->get_result();

        $orders = [];
        $orderIds = [];

        while ($row = $result->fetch_assoc()) {
            $orderId = (int) $row['id'];

            $orders[$orderId] = [
                'id' => $orderId,

                'created_at' => (string) $row['created_at'],

                /*
                 * Final order total after any discount.
                 */
                'total' => (float) $row['total_price'],

                'total_co2e_kg' =>
                    $row['total_co2e_kg'] !== null
                        ? (float) $row['total_co2e_kg']
                        : null,

                /*
                 * Green Points earned on this order.
                 */
                'green_points_earned' => (int) (
                    $row['green_points_earned'] ?? 0
                ),

                /*
                 * Green Points redeemed on this order.
                 */
                'green_points_spent' => (int) (
                    $row['green_points_spent'] ?? 0
                ),

                /*
                 * Monetary discount applied at checkout.
                 */
                'discount_amount' => (float) (
                    $row['discount_amount'] ?? 0
                ),

                'status' => (string) $row['status'],

                'items' => [],
            ];

            $orderIds[] = $orderId;
        }

        $statement->close();

        if ($orderIds === []) {
            return [];
        }

        /*
         * Retrieve all purchased products
         * for the selected orders in one query.
         *
         * This avoids one query per order.
         */
        $placeholders = implode(
            ',',
            array_fill(0, count($orderIds), '?')
        );

        $itemsSql = "
            SELECT
                oi.order_id,
                oi.product_id,
                oi.product_title,
                oi.quantity,
                oi.unit_price,
                oi.line_total,
                oi.co2e_kg,
                oi.climate_rank,
                oi.green_points_earned
            FROM order_items AS oi
            INNER JOIN orders AS o
                ON o.id = oi.order_id
            WHERE oi.order_id IN ($placeholders)
              AND o.user_id = ?
            ORDER BY oi.order_id DESC, oi.id ASC
        ";

        $itemsStatement = $this->connection->prepare(
            $itemsSql
        );

        $parameters = [...$orderIds, $userId];

        $types = str_repeat(
            'i',
            count($parameters)
        );

        $itemsStatement->bind_param(
            $types,
            ...$parameters
        );

        $itemsStatement->execute();

        $itemsResult = $itemsStatement->get_result();

        while ($item = $itemsResult->fetch_assoc()) {
            $orderId = (int) $item['order_id'];

            if (!isset($orders[$orderId])) {
                continue;
            }

            $orders[$orderId]['items'][] = [
                'product_id' => (int) $item['product_id'],

                'title' => (string) $item['product_title'],

                'quantity' => (int) $item['quantity'],

                'unit_price' => (float) $item['unit_price'],

                'line_total' => (float) $item['line_total'],

                'co2e_kg' => $item['co2e_kg'] !== null
                    ? (float) $item['co2e_kg']
                    : null,

                'climate_rank' => (int) (
                    $item['climate_rank'] ?? 0
                ),

                'green_points_earned' => (int) (
                    $item['green_points_earned'] ?? 0
                ),
            ];
        }

        $itemsStatement->close();

        /*
         * Return a normal indexed array.
         *
         * Orders remain newest first.
         */
        return array_values($orders);
    }

    /**
     * Aggregate recorded climate emissions.
     *
     * Only placed orders are included.
     *
     * Orders with missing climate data are counted
     * but excluded from the known emissions total.
     *
     * Recorded emissions are not climate savings.
     */
    public function findClimateImpactByUserId(
        int $userId
    ): array {
        if ($userId <= 0) {
            return [
                'total_orders' => 0,
                'known_orders' => 0,
                'unknown_orders' => 0,
                'known_co2e_kg' => 0.0,
                'complete' => false,
            ];
        }

        $sql = "
            SELECT
                COUNT(*) AS total_orders,
                COUNT(total_co2e_kg) AS known_orders,
                COALESCE(
                    SUM(total_co2e_kg),
                    0
                ) AS known_co2e_kg
            FROM orders
            WHERE user_id = ?
              AND status = 'placed'
        ";

        $statement = $this->connection->prepare($sql);

        $statement->bind_param(
            'i',
            $userId
        );

        $statement->execute();

        $row = $statement
            ->get_result()
            ->fetch_assoc();

        $statement->close();

        $totalOrders = (int) (
            $row['total_orders'] ?? 0
        );

        $knownOrders = (int) (
            $row['known_orders'] ?? 0
        );

        $unknownOrders = $totalOrders - $knownOrders;

        return [
            'total_orders' => $totalOrders,
            'known_orders' => $knownOrders,
            'unknown_orders' => $unknownOrders,

            'known_co2e_kg' => (float) (
                $row['known_co2e_kg'] ?? 0
            ),

            'complete' => $totalOrders > 0
                && $unknownOrders === 0,
        ];
    }

    /**
     * Retrieve the user's available Green Points.
     *
     * Available balance:
     *
     * Total earned points - Total spent points
     *
     * Rules:
     * - Only placed orders count.
     * - Cancelled orders are excluded.
     * - Points are read from stored orders.
     * - No points are recalculated here.
     * - Viewing the profile never awards points.
     * - Redeemed points are deducted from the balance.
     *
     * This method returns the current balance,
     * not the lifetime total of earned points.
     */
    public function findGreenPointsByUserId(
        int $userId
    ): int {
        if ($userId <= 0) {
            return 0;
        }

        $sql = "
            SELECT
                COALESCE(
                    SUM(green_points_earned),
                    0
                ) AS earned_points,

                COALESCE(
                    SUM(green_points_spent),
                    0
                ) AS spent_points

            FROM orders
            WHERE user_id = ?
              AND status = 'placed'
        ";

        $statement = $this->connection->prepare($sql);

        $statement->bind_param(
            'i',
            $userId
        );

        $statement->execute();

        $row = $statement
            ->get_result()
            ->fetch_assoc();

        $statement->close();

        $earnedPoints = (int) (
            $row['earned_points'] ?? 0
        );

        $spentPoints = (int) (
            $row['spent_points'] ?? 0
        );

        /*
         * Do not display a negative balance.
         */
        return max(
            0,
            $earnedPoints - $spentPoints
        );
    }
}
