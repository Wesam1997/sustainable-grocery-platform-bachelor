<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/OrderRepositoryInterface.php';
require_once __DIR__ . '/../../Modules/Rewards/GreenPointsService.php';

final class MysqliOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Legacy repository operation.
     *
     * The caller manages the transaction.
     *
     * Automatic reward redemption is handled by
     * placeActiveCart(), not this legacy method.
     */
    public function save(
        int $userId,
        string $address,
        array $items,
        float $total
    ): int {
        if ($userId <= 0) {
            throw new InvalidArgumentException('invalid_user');
        }

        $address = trim($address);

        if ($address === '') {
            throw new InvalidArgumentException('address_required');
        }

        if ($items === []) {
            throw new InvalidArgumentException('cart_empty');
        }

        $user = $this->findUser($userId);
        $lines = $this->buildLines($items);

        return $this->insertOrder(
            $userId,
            (string) $user['name'],
            $address,
            $lines,
            $this->calculateTotal($lines),
            null,
            0,
            0,
            0.0
        );
    }

    /**
     * Atomically places the active database cart.
     *
     * The optional callback calculates climate data
     * and Green Points for the purchased products.
     *
     * Automatic discount rules:
     * - At least 400 available points are required.
     * - 400 points are spent automatically.
     * - A 20% discount is applied to the order.
     * - Newly earned points are not available
     *   for redemption on the same order.
     * - All changes happen in one transaction.
     *
     * @param callable(array):array|null $calculateClimate
     */
    public function placeActiveCart(
        int $userId,
        string $address,
        ?callable $calculateClimate = null
    ): int {
        if ($userId <= 0) {
            throw new InvalidArgumentException('invalid_user');
        }

        $address = trim($address);

        if ($address === '') {
            throw new InvalidArgumentException('address_required');
        }

        $this->db->begin_transaction();

        try {
            /*
             * Lock the user.
             *
             * Concurrent checkouts for the same user
             * must wait for this transaction.
             *
             * This prevents the same Green Points
             * from being redeemed twice.
             */
            $stmt = $this->db->prepare(
                'SELECT id, name
                 FROM users
                 WHERE id = ?
                 FOR UPDATE'
            );

            $stmt->bind_param('i', $userId);
            $stmt->execute();

            $user = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();

            if (!$user) {
                throw new InvalidArgumentException(
                    'user_not_found'
                );
            }

            /*
             * Find the active cart.
             */
            $stmt = $this->db->prepare(
                "SELECT id
                 FROM carts
                 WHERE user_id = ?
                   AND status = 'active'
                 ORDER BY id"
            );

            $stmt->bind_param('i', $userId);
            $stmt->execute();

            $carts = $stmt
                ->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            if (count($carts) !== 1) {
                if ($carts === []) {
                    throw new InvalidArgumentException(
                        'cart_empty'
                    );
                }

                throw new RuntimeException(
                    'multiple_active_carts'
                );
            }

            $cartId = (int) $carts[0]['id'];

            /*
             * Read the purchased products.
             */
            $stmt = $this->db->prepare(
                'SELECT
                    ci.product_id,
                    ci.quantity,
                    p.title,
                    p.price
                 FROM cart_items ci
                 INNER JOIN proudkt p
                    ON p.id = ci.product_id
                 WHERE ci.cart_id = ?
                 ORDER BY ci.product_id'
            );

            $stmt->bind_param('i', $cartId);
            $stmt->execute();

            $rows = $stmt
                ->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            if ($rows === []) {
                throw new InvalidArgumentException(
                    'cart_empty'
                );
            }

            $lines = [];

            foreach ($rows as $row) {
                $quantity = (int) $row['quantity'];
                $price = (float) $row['price'];

                if ($quantity < 1 || $quantity > 99) {
                    throw new InvalidArgumentException(
                        'invalid_order_item'
                    );
                }

                if (!is_finite($price) || $price < 0) {
                    throw new InvalidArgumentException(
                        'invalid_product_price'
                    );
                }

                $lines[] = [
                    'product_id' => (int) $row['product_id'],
                    'title' => (string) $row['title'],
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'line_total' => round(
                        $price * $quantity,
                        2
                    ),
                    'co2e_kg' => null,
                    'climate_rank' => 0,
                    'green_points_earned' => 0,
                ];
            }

            /*
             * Calculate climate data and earned points.
             */
            $totalCo2e = null;
            $earnedPoints = 0;

            if ($calculateClimate !== null) {
                $summary = $calculateClimate($lines);

                if (!is_array($summary)) {
                    throw new RuntimeException(
                        'invalid_climate_summary'
                    );
                }

                [
                    $lines,
                    $totalCo2e,
                    $earnedPoints
                ] = $this->applyClimateSummary(
                    $lines,
                    $summary
                );
            }

            /*
             * Calculate the subtotal before discount.
             */
            $subtotal = $this->calculateTotal($lines);

            /*
             * Retrieve available Green Points.
             *
             * Only previously placed orders count.
             *
             * Earned points minus spent points
             * gives the available balance.
             *
             * The user row is already locked,
             * so concurrent checkouts cannot
             * redeem the same balance.
             */
            $availablePoints = $this
                ->findAvailableGreenPoints($userId);

            /*
             * Automatic reward redemption.
             */
            $rewardService = new GreenPointsService();

            $spentPoints = 0;
            $discountAmount = 0.0;

            if ($rewardService->canRedeem($availablePoints)) {
                $spentPoints =
                    GreenPointsService::REWARD_THRESHOLD;

                $discountAmount = $rewardService
                    ->calculateDiscount($subtotal);
            }

            /*
             * Calculate final payable total.
             */
            $finalTotal = round(
                $subtotal - $discountAmount,
                2
            );

            if (
                !is_finite($finalTotal)
                || $finalTotal < 0
            ) {
                throw new RuntimeException(
                    'invalid_final_order_total'
                );
            }

            /*
             * Save the order.
             *
             * The order records:
             * - Final price
             * - Climate impact
             * - Earned Green Points
             * - Spent Green Points
             * - Discount amount
             */
            $orderId = $this->insertOrder(
                $userId,
                (string) $user['name'],
                $address,
                $lines,
                $finalTotal,
                $totalCo2e,
                $earnedPoints,
                $spentPoints,
                $discountAmount
            );

            /*
             * Mark the cart as ordered.
             */
            $stmt = $this->db->prepare(
                "UPDATE carts
                 SET status = 'ordered',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND user_id = ?
                   AND status = 'active'"
            );

            $stmt->bind_param(
                'ii',
                $cartId,
                $userId
            );

            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();

                throw new RuntimeException(
                    'cart_status_update_failed'
                );
            }

            $stmt->close();

            /*
             * Commit everything together.
             *
             * If any operation fails, the order,
             * discount and point spending are
             * all rolled back.
             */
            $this->db->commit();

            return $orderId;

        } catch (Throwable $error) {
            $this->db->rollback();

            throw $error;
        }
    }

    /**
     * Retrieve the user's available Green Points.
     *
     * Available balance:
     *
     * SUM(earned points) - SUM(spent points)
     *
     * Only placed orders are included.
     *
     * Must be called during checkout after
     * locking the corresponding user row.
     */
    private function findAvailableGreenPoints(
        int $userId
    ): int {
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

        $stmt = $this->db->prepare($sql);

        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $row = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        $earnedPoints = (int) (
            $row['earned_points'] ?? 0
        );

        $spentPoints = (int) (
            $row['spent_points'] ?? 0
        );

        if ($spentPoints > $earnedPoints) {
            throw new RuntimeException(
                'invalid_green_points_balance'
            );
        }

        return $earnedPoints - $spentPoints;
    }

    /**
     * Match climate results to purchased products.
     *
     * The summary comes from CartClimateSummaryService.
     *
     * Each product has:
     * - id
     * - qty
     * - rank
     * - co2e_line
     * - green_points_earned (when calculated)
     *
     * Climate ranks:
     * 1 = Low
     * 2 = Medium
     * 3 = High
     * 0 = Unknown
     */
    private function applyClimateSummary(
        array $lines,
        array $summary
    ): array {
        $summaryLines = $summary['lines'] ?? null;

        if (!is_array($summaryLines)) {
            throw new RuntimeException(
                'invalid_climate_summary'
            );
        }

        $byProduct = [];

        foreach ($summaryLines as $entry) {
            if (
                !is_array($entry)
                || !isset($entry['id'], $entry['qty'])
            ) {
                throw new RuntimeException(
                    'invalid_climate_line'
                );
            }

            $id = (int) $entry['id'];

            if (
                $id <= 0
                || isset($byProduct[$id])
            ) {
                throw new RuntimeException(
                    'duplicate_or_invalid_climate_line'
                );
            }

            $byProduct[$id] = $entry;
        }

        if (count($byProduct) !== count($lines)) {
            throw new RuntimeException(
                'climate_line_count_mismatch'
            );
        }

        $knownTotal = 0.0;
        $complete = true;
        $earnedPoints = 0;

        foreach ($lines as &$line) {
            $id = (int) $line['product_id'];

            $entry = $byProduct[$id] ?? null;

            if (
                $entry === null
                || (int) $entry['qty'] !== $line['quantity']
            ) {
                throw new RuntimeException(
                    'climate_line_mismatch'
                );
            }

            /*
             * Save the climate classification
             * at purchase time.
             */
            $rank = (int) ($entry['rank'] ?? 0);

            if (!in_array($rank, [0, 1, 2, 3], true)) {
                throw new RuntimeException(
                    'invalid_climate_rank'
                );
            }

            $line['climate_rank'] = $rank;

            /*
             * Points are calculated by GreenPointsService
             * through the checkout callback.
             */
            $points = $entry['green_points_earned'] ?? 0;

            if (
                !is_int($points)
                || $points < 0
            ) {
                throw new RuntimeException(
                    'invalid_green_points'
                );
            }

            /*
             * Medium, High and Unknown
             * never earn points.
             */
            if ($rank !== 1 && $points !== 0) {
                throw new RuntimeException(
                    'points_not_allowed_for_product'
                );
            }

            /*
             * Low products may earn
             * 10 points per purchased unit.
             */
            if (
                $rank === 1
                && $points !== 0
                && $points !== (
                    (int) $line['quantity']
                    * GreenPointsService::POINTS_PER_LOW_PRODUCT
                )
            ) {
                throw new RuntimeException(
                    'invalid_low_product_points'
                );
            }

            $line['green_points_earned'] = $points;

            $earnedPoints += $points;

            /*
             * Existing CO2e calculation.
             */
            $value = $entry['co2e_line'] ?? null;

            if ($value === null) {
                $line['co2e_kg'] = null;
                $complete = false;

                continue;
            }

            if (
                !is_numeric($value)
                || !is_finite((float) $value)
                || (float) $value < 0
            ) {
                throw new RuntimeException(
                    'invalid_climate_value'
                );
            }

            $line['co2e_kg'] = round(
                (float) $value,
                4
            );

            $knownTotal += $line['co2e_kg'];
        }

        unset($line);

        /*
         * Total CO2e is complete only when
         * every product has known emissions.
         */
        $totalCo2e = $complete
            ? round($knownTotal, 4)
            : null;

        return [
            $lines,
            $totalCo2e,
            $earnedPoints
        ];
    }

    /**
     * Find an existing user.
     */
    private function findUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name
             FROM users
             WHERE id = ?'
        );

        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $user = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$user) {
            throw new InvalidArgumentException(
                'user_not_found'
            );
        }

        return $user;
    }

    /**
     * Build order lines for legacy save().
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        $stmt = $this->db->prepare(
            'SELECT title, price
             FROM proudkt
             WHERE id = ?'
        );

        try {
            foreach ($items as $productId => $item) {
                $productId = (int) $productId;

                $quantity = (int) (
                    $item['qty'] ?? 0
                );

                if (
                    $productId <= 0
                    || $quantity < 1
                    || $quantity > 99
                ) {
                    throw new InvalidArgumentException(
                        'invalid_order_item'
                    );
                }

                $stmt->bind_param(
                    'i',
                    $productId
                );

                $stmt->execute();

                $product = $stmt
                    ->get_result()
                    ->fetch_assoc();

                if (!$product) {
                    throw new InvalidArgumentException(
                        'product_not_found'
                    );
                }

                $price = (float) $product['price'];

                if (
                    !is_finite($price)
                    || $price < 0
                ) {
                    throw new InvalidArgumentException(
                        'invalid_product_price'
                    );
                }

                $lines[] = [
                    'product_id' => $productId,
                    'title' => (string) $product['title'],
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'line_total' => round(
                        $price * $quantity,
                        2
                    ),
                    'co2e_kg' => null,
                    'climate_rank' => 0,
                    'green_points_earned' => 0
                ];
            }
        } finally {
            $stmt->close();
        }

        return $lines;
    }

    /**
     * Calculate order total from purchased lines.
     */
    private function calculateTotal(array $lines): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            $total += (float) $line['line_total'];
        }

        return round($total, 2);
    }

    /**
     * Save the order and all purchased products.
     *
     * Green Points are stored with the order.
     *
     * Discount and spent points are recorded
     * in the same transaction as the purchase.
     */
    private function insertOrder(
        int $userId,
        string $name,
        string $address,
        array $lines,
        float $total,
        ?float $totalCo2e,
        int $earnedPoints = 0,
        int $spentPoints = 0,
        float $discountAmount = 0.0
    ): int {
        /*
         * Insert the order.
         */
        $stmt = $this->db->prepare(
            'INSERT INTO orders
                (
                    user_id,
                    delivery_name,
                    delivery_address,
                    total_price,
                    total_co2e_kg,
                    green_points_earned,
                    green_points_spent,
                    discount_amount
                )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'issddiid',
            $userId,
            $name,
            $address,
            $total,
            $totalCo2e,
            $earnedPoints,
            $spentPoints,
            $discountAmount
        );

        $stmt->execute();

        $orderId = (int) $this->db->insert_id;

        $stmt->close();

        /*
         * Insert individual purchased products.
         */
        $stmt = $this->db->prepare(
            'INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    product_title,
                    quantity,
                    unit_price,
                    line_total,
                    co2e_kg,
                    climate_rank,
                    green_points_earned
                )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        try {
            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];

                $title = (string) $line['title'];

                $quantity = (int) $line['quantity'];

                $unitPrice = (float) $line['unit_price'];

                $lineTotal = (float) $line['line_total'];

                $co2e = $line['co2e_kg'] ?? null;

                $rank = (int) (
                    $line['climate_rank'] ?? 0
                );

                $points = (int) (
                    $line['green_points_earned'] ?? 0
                );

                $stmt->bind_param(
                    'iisidddii',
                    $orderId,
                    $productId,
                    $title,
                    $quantity,
                    $unitPrice,
                    $lineTotal,
                    $co2e,
                    $rank,
                    $points
                );

                $stmt->execute();
            }
        } finally {
            $stmt->close();
        }

        return $orderId;
    }
}
