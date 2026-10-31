<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__
    . '/../src/Infrastructure/Database/MysqliOrderRepository.php';

require_once __DIR__
    . '/../src/Infrastructure/Persistence/MysqliOrderHistoryReader.php';

/**
 * Integration tests for automatic Green Points redemption.
 *
 * Database: flashfood_test
 *
 * Business rules:
 * - Low climate impact products earn 10 points per unit.
 * - 400 available points give an automatic 20% discount.
 * - Exactly 400 points are spent per redemption.
 * - Remaining points are preserved.
 * - Newly earned points are added after checkout.
 * - A failed checkout must not consume points.
 *
 * Tests use the real MariaDB repository.
 */
final class GreenPointsCheckoutIntegrationTest extends TestCase
{
    private mysqli $db;

    private int $userId = 0;

    private int $productId = 0;

    /**
     * Create isolated test data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = getenv('FLASHFOOD_TEST_DB_NAME')
            ?: 'flashfood_test';

        if ($database !== 'flashfood_test') {
            throw new RuntimeException(
                'Unsafe test database: ' . $database
            );
        }

        $this->db = new mysqli(
            getenv('FLASHFOOD_DB_HOST') ?: 'localhost',
            getenv('FLASHFOOD_DB_USER') ?: 'root',
            getenv('FLASHFOOD_DB_PASSWORD') ?: '',
            $database
        );

        $this->db->set_charset('utf8mb4');

        /*
         * Create a unique test user.
         */
        $email = 'green-points-'
            . bin2hex(random_bytes(8))
            . '@example.com';

        $name = 'Green Points Test User';
        $password = 'test';

        $stmt = $this->db->prepare(
            'INSERT INTO users
                (name, email, password_hash)
             VALUES (?, ?, ?)'
        );

        $stmt->bind_param(
            'sss',
            $name,
            $email,
            $password
        );

        $stmt->execute();

        $this->userId = (int) $this->db->insert_id;

        $stmt->close();

        /*
         * Create a test product.
         *
         * Price: 100 DKK
         *
         * Its climate classification is supplied
         * through the checkout callback.
         */
        $this->productId = random_int(
            900000,
            2000000000
        );

        $title = 'Test Oats';
        $merchant = 'Test Shop';
        $category = 1;
        $ingredients = '';
        $price = 100.00;

        $stmt = $this->db->prepare(
            'INSERT INTO proudkt
                (
                    id,
                    title,
                    merchant,
                    category,
                    ingredients,
                    price
                )
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'issisd',
            $this->productId,
            $title,
            $merchant,
            $category,
            $ingredients,
            $price
        );

        $stmt->execute();
        $stmt->close();
    }

    /**
     * Remove test data after each test.
     *
     * Explicit cleanup is necessary because
     * placeActiveCart() commits its transaction.
     */
    protected function tearDown(): void
    {
        try {
            if (!isset($this->db)) {
                return;
            }

            $this->db->begin_transaction();

            if ($this->userId > 0) {
                /*
                 * Delete purchased order items.
                 */
                $stmt = $this->db->prepare(
                    'DELETE FROM order_items
                     WHERE order_id IN (
                         SELECT id
                         FROM orders
                         WHERE user_id = ?
                     )'
                );

                $stmt->bind_param(
                    'i',
                    $this->userId
                );

                $stmt->execute();
                $stmt->close();

                /*
                 * Delete test orders.
                 */
                $stmt = $this->db->prepare(
                    'DELETE FROM orders
                     WHERE user_id = ?'
                );

                $stmt->bind_param(
                    'i',
                    $this->userId
                );

                $stmt->execute();
                $stmt->close();

                /*
                 * Delete cart items.
                 */
                $stmt = $this->db->prepare(
                    'DELETE FROM cart_items
                     WHERE cart_id IN (
                         SELECT id
                         FROM carts
                         WHERE user_id = ?
                     )'
                );

                $stmt->bind_param(
                    'i',
                    $this->userId
                );

                $stmt->execute();
                $stmt->close();

                /*
                 * Delete test carts.
                 */
                $stmt = $this->db->prepare(
                    'DELETE FROM carts
                     WHERE user_id = ?'
                );

                $stmt->bind_param(
                    'i',
                    $this->userId
                );

                $stmt->execute();
                $stmt->close();
            }

            /*
             * Delete test product.
             */
            if ($this->productId > 0) {
                $stmt = $this->db->prepare(
                    'DELETE FROM proudkt
                     WHERE id = ?'
                );

                $stmt->bind_param(
                    'i',
                    $this->productId
                );

                $stmt->execute();
                $stmt->close();
            }

            /*
             * Delete test user.
             */
            if ($this->userId > 0) {
                $stmt = $this->db->prepare(
                    'DELETE FROM users
                     WHERE id = ?'
                );

                $stmt->bind_param(
                    'i',
                    $this->userId
                );

                $stmt->execute();
                $stmt->close();
            }

            $this->db->commit();

        } catch (Throwable $error) {
            if (isset($this->db)) {
                $this->db->rollback();
            }

            throw $error;

        } finally {
            if (isset($this->db)) {
                $this->db->close();
            }

            parent::tearDown();
        }
    }

    /**
     * Simulate Green Points earned
     * from a previous placed order.
     */
    private function seedPoints(int $points): void
    {
        if ($points < 0) {
            throw new InvalidArgumentException(
                'Points cannot be negative.'
            );
        }

        $stmt = $this->db->prepare(
            "INSERT INTO orders
                (
                    user_id,
                    delivery_name,
                    delivery_address,
                    status,
                    total_price,
                    green_points_earned,
                    green_points_spent,
                    discount_amount
                )
             VALUES
                (
                    ?,
                    'Test User',
                    'Test Address',
                    'placed',
                    0.00,
                    ?,
                    0,
                    0.00
                )"
        );

        $stmt->bind_param(
            'ii',
            $this->userId,
            $points
        );

        $stmt->execute();
        $stmt->close();
    }

    /**
     * Create an active shopping cart
     * with one product costing 100 DKK.
     */
    private function createCart(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO carts
                (user_id, status)
             VALUES (?, 'active')"
        );

        $stmt->bind_param(
            'i',
            $this->userId
        );

        $stmt->execute();

        $cartId = (int) $this->db->insert_id;

        $stmt->close();

        $stmt = $this->db->prepare(
            'INSERT INTO cart_items
                (cart_id, product_id, quantity)
             VALUES (?, ?, ?)'
        );

        $quantity = 1;

        $stmt->bind_param(
            'iii',
            $cartId,
            $this->productId,
            $quantity
        );

        $stmt->execute();
        $stmt->close();

        return $cartId;
    }

    /**
     * Simulate the climate calculation
     * performed during checkout.
     *
     * Climate rank 1 = Low.
     * Each purchased unit earns 10 points.
     */
    private function climateCallback(): callable
    {
        return static function (array $lines): array {
            $summaryLines = [];

            foreach ($lines as $line) {
                $quantity = (int) $line['quantity'];

                $summaryLines[] = [
                    'id' => (int) $line['product_id'],
                    'qty' => $quantity,
                    'rank' => 1,
                    'co2e_line' => 1.0 * $quantity,
                    'green_points_earned' => 10 * $quantity,
                ];
            }

            return [
                'lines' => $summaryLines,
            ];
        };
    }

    /**
     * Place an order through the real repository.
     */
    private function checkout(): int
    {
        $repository = new MysqliOrderRepository(
            $this->db
        );

        return $repository->placeActiveCart(
            $this->userId,
            'Test Delivery Address',
            $this->climateCallback()
        );
    }

    /**
     * Retrieve a saved order.
     */
    private function getOrder(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                total_price,
                green_points_earned,
                green_points_spent,
                discount_amount,
                status
             FROM orders
             WHERE id = ?
               AND user_id = ?'
        );

        $stmt->bind_param(
            'ii',
            $orderId,
            $this->userId
        );

        $stmt->execute();

        $order = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if ($order === null) {
            throw new RuntimeException(
                'Test order not found.'
            );
        }

        return $order;
    }

    /**
     * Read the available balance using
     * the real order history reader.
     *
     * This avoids unsigned subtraction
     * directly inside SQL.
     */
    private function getBalance(): int
    {
        $reader = new MysqliOrderHistoryReader(
            $this->db
        );

        return $reader->findGreenPointsByUserId(
            $this->userId
        );
    }

    /**
     * Retrieve the status of a cart.
     */
    private function getCartStatus(int $cartId): string
    {
        $stmt = $this->db->prepare(
            'SELECT status
             FROM carts
             WHERE id = ?
               AND user_id = ?'
        );

        $stmt->bind_param(
            'ii',
            $cartId,
            $this->userId
        );

        $stmt->execute();

        $cart = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if ($cart === null) {
            throw new RuntimeException(
                'Test cart not found.'
            );
        }

        return (string) $cart['status'];
    }

    /**
     * TEST 1
     *
     * 399 points must not trigger a discount.
     *
     * Newly earned points must not be used
     * for redemption on the same order.
     */
    public function test399PointsDoesNotGiveDiscount(): void
    {
        $this->seedPoints(399);

        $cartId = $this->createCart();

        $orderId = $this->checkout();

        $order = $this->getOrder($orderId);

        $this->assertEqualsWithDelta(
            100.00,
            (float) $order['total_price'],
            0.001
        );

        $this->assertEqualsWithDelta(
            0.00,
            (float) $order['discount_amount'],
            0.001
        );

        $this->assertSame(
            0,
            (int) $order['green_points_spent']
        );

        $this->assertSame(
            10,
            (int) $order['green_points_earned']
        );

        // 399 + 10 = 409.
        $this->assertSame(
            409,
            $this->getBalance()
        );

        $this->assertSame(
            'ordered',
            $this->getCartStatus($cartId)
        );
    }

    /**
     * TEST 2
     *
     * Exactly 400 points automatically
     * trigger a 20% discount.
     */
    public function test400PointsGivesAutomatic20PercentDiscount(): void
    {
        $this->seedPoints(400);

        $cartId = $this->createCart();

        $orderId = $this->checkout();

        $order = $this->getOrder($orderId);

        // Original price: 100 DKK.
        // Discount: 20 DKK.
        // Final price: 80 DKK.

        $this->assertEqualsWithDelta(
            80.00,
            (float) $order['total_price'],
            0.001
        );

        $this->assertEqualsWithDelta(
            20.00,
            (float) $order['discount_amount'],
            0.001
        );

        $this->assertSame(
            400,
            (int) $order['green_points_spent']
        );

        $this->assertSame(
            10,
            (int) $order['green_points_earned']
        );

        // 400 - 400 + 10 = 10.
        $this->assertSame(
            10,
            $this->getBalance()
        );

        $this->assertSame(
            'placed',
            (string) $order['status']
        );

        $this->assertSame(
            'ordered',
            $this->getCartStatus($cartId)
        );
    }

    /**
     * TEST 3
     *
     * Remaining points must be preserved
     * after automatic redemption.
     */
    public function test450PointsLeavesRemainingPoints(): void
    {
        $this->seedPoints(450);

        $this->createCart();

        $orderId = $this->checkout();

        $order = $this->getOrder($orderId);

        $this->assertEqualsWithDelta(
            80.00,
            (float) $order['total_price'],
            0.001
        );

        $this->assertEqualsWithDelta(
            20.00,
            (float) $order['discount_amount'],
            0.001
        );

        $this->assertSame(
            400,
            (int) $order['green_points_spent']
        );

        $this->assertSame(
            10,
            (int) $order['green_points_earned']
        );

        // 450 - 400 + 10 = 60.
        $this->assertSame(
            60,
            $this->getBalance()
        );
    }

    /**
     * TEST 4
     *
     * The same points must not be redeemed
     * again on a later checkout.
     *
     * This tests sequential purchases,
     * not simultaneous requests.
     */
    public function testPointsCannotBeRedeemedTwice(): void
    {
        $this->seedPoints(400);

        /*
         * First purchase.
         */
        $this->createCart();

        $firstOrderId = $this->checkout();

        $firstOrder = $this->getOrder(
            $firstOrderId
        );

        $this->assertSame(
            400,
            (int) $firstOrder['green_points_spent']
        );

        $this->assertEqualsWithDelta(
            80.00,
            (float) $firstOrder['total_price'],
            0.001
        );

        // 10 points remain after first checkout.
        $this->assertSame(
            10,
            $this->getBalance()
        );

        /*
         * Second purchase.
         */
        $this->createCart();

        $secondOrderId = $this->checkout();

        $secondOrder = $this->getOrder(
            $secondOrderId
        );

        /*
         * No second discount:
         * only 10 points were available.
         */
        $this->assertSame(
            0,
            (int) $secondOrder['green_points_spent']
        );

        $this->assertEqualsWithDelta(
            0.00,
            (float) $secondOrder['discount_amount'],
            0.001
        );

        $this->assertEqualsWithDelta(
            100.00,
            (float) $secondOrder['total_price'],
            0.001
        );

        /*
         * First purchase earned 10.
         * Second purchase earned 10.
         *
         * Final balance = 20.
         */
        $this->assertSame(
            20,
            $this->getBalance()
        );
    }

    /**
     * TEST 5
     *
     * Failed checkout must not consume points.
     *
     * The cart must remain active,
     * allowing the customer to retry.
     */
    public function testFailedCheckoutDoesNotConsumePoints(): void
    {
        $this->seedPoints(400);

        $cartId = $this->createCart();

        $repository = new MysqliOrderRepository(
            $this->db
        );

        /*
         * Simulate a climate calculation failure.
         */
        $failedCallback = static function (
            array $lines
        ): array {
            throw new RuntimeException(
                'simulated_climate_failure'
            );
        };

        /*
         * Checkout must throw the expected error.
         */
        try {
            $repository->placeActiveCart(
                $this->userId,
                'Test Delivery Address',
                $failedCallback
            );

            $this->fail(
                'Checkout should have failed.'
            );

        } catch (RuntimeException $error) {
            $this->assertSame(
                'simulated_climate_failure',
                $error->getMessage()
            );
        }

        /*
         * Points must remain unchanged.
         */
        $this->assertSame(
            400,
            $this->getBalance()
        );

        /*
         * Cart must still be active.
         */
        $this->assertSame(
            'active',
            $this->getCartStatus($cartId)
        );

        /*
         * No checkout order should exist.
         *
         * Only the previously seeded
         * points order should be present.
         */
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS order_count
             FROM orders
             WHERE user_id = ?'
        );

        $stmt->bind_param(
            'i',
            $this->userId
        );

        $stmt->execute();

        $row = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        $this->assertSame(
            1,
            (int) $row['order_count']
        );

        /*
         * Retry with a valid climate callback.
         */
        $orderId = $this->checkout();

        $order = $this->getOrder($orderId);

        $this->assertSame(
            400,
            (int) $order['green_points_spent']
        );

        $this->assertEqualsWithDelta(
            20.00,
            (float) $order['discount_amount'],
            0.001
        );

        $this->assertEqualsWithDelta(
            80.00,
            (float) $order['total_price'],
            0.001
        );

        $this->assertSame(
            10,
            $this->getBalance()
        );

        $this->assertSame(
            'ordered',
            $this->getCartStatus($cartId)
        );
    }
}
