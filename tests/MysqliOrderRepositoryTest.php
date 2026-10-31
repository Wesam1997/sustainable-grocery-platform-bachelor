<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Contracts/OrderRepositoryInterface.php';
require_once __DIR__ . '/../src/Infrastructure/Database/MysqliOrderRepository.php';

final class MysqliOrderRepositoryTest extends TestCase
{
    private mysqli $db;

    protected function setUp(): void
    {
        $database = getenv('FLASHFOOD_TEST_DB_NAME')
            ?: 'flashfood_test';

        if ($database !== 'flashfood_test') {
            throw new RuntimeException('Unsafe test database.');
        }

        $this->db = new mysqli(
            getenv('FLASHFOOD_DB_HOST') ?: 'localhost',
            getenv('FLASHFOOD_DB_USER') ?: 'root',
            getenv('FLASHFOOD_DB_PASSWORD') ?: '',
            $database
        );

        $this->db->set_charset('utf8mb4');
        $this->db->begin_transaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollback();
            $this->db->close();
        }
    }

    public function testCheckoutCreatesOrderAndOrderItems(): void
    {
        $this->db->query("
            INSERT INTO users (name, email, password_hash)
            VALUES ('Test User', 'checkout-test@example.com', 'test')
        ");

        $userId = (int) $this->db->insert_id;

        $this->db->query("
            INSERT INTO proudkt
                (id, title, merchant, category, ingredients, price)
            VALUES
                (99991, 'Test Oats', 'Test Shop', 1, '', 15.00)
        ");

        $items = [
            99991 => [
                'title' => 'Test Oats',
                'qty' => 2,
                'price' => 15.00
            ]
        ];

        $repository = new MysqliOrderRepository($this->db);

        $orderId = $repository->save(
            $userId,
            'Test Address',
            $items,
            30.00
        );

        $this->assertGreaterThan(0, $orderId);

        $result = $this->db->query(
            "SELECT total_price FROM orders WHERE id = $orderId"
        );

        $order = $result->fetch_assoc();

        $this->assertNotNull($order);
        $this->assertEquals(30.00, (float) $order['total_price']);

        $result = $this->db->query(
            "SELECT quantity, unit_price
             FROM order_items
             WHERE order_id = $orderId"
        );

        $item = $result->fetch_assoc();

        $this->assertNotNull($item);
        $this->assertSame(2, (int) $item['quantity']);
        $this->assertEquals(15.00, (float) $item['unit_price']);
    }
}
