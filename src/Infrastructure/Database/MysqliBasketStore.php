<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/AtomicBasketStoreInterface.php';
require_once __DIR__ . '/../Session/SessionBasketStore.php';

final class MysqliBasketStore implements AtomicBasketStoreInterface
{
    private BasketStoreInterface $guest;
    private bool $mutating = false;

    public function __construct(
        private mysqli $connection,
        ?BasketStoreInterface $guest = null
    ) {
        $this->guest = $guest ?? new SessionBasketStore();
    }

    public function load(string $key): array
    {
        $this->checkKey($key);
        $userId = $this->userId();

        return $userId === null
            ? $this->guest->load($key)
            : $this->loadStored($key, $userId);
    }

    public function save(string $key, array $items): void
    {
        $this->mutate($key, static fn (array $current): array => $items);
    }

    public function clear(string $key): void
    {
        $this->mutate($key, static fn (array $current): array => []);
    }

    public function mutate(string $key, callable $change): array
    {
        $this->checkKey($key);
        $userId = $this->userId();

        if ($userId === null) {
            $items = $this->validatedItems($key, $change($this->guest->load($key)));
            $this->guest->save($key, $items);
            return $items;
        }

        if ($this->mutating) {
            throw new LogicException('Nested basket mutations are not supported.');
        }

        if (!$this->connection->begin_transaction()) {
            throw new RuntimeException('Basket transaction could not start.');
        }
        $this->mutating = true;

        try {
            // Every mutation locks the same user row FIRST. This also serializes
            // active-cart creation across requests using this adapter.
            $users = $this->read(
                'SELECT id FROM users WHERE id = ? FOR UPDATE',
                'i',
                [$userId]
            );
            if ($users === []) {
                throw new OutOfBoundsException('user_not_found');
            }

            $before = $this->loadStored($key, $userId);
            $after = $this->validatedItems($key, $change($before));

            if ($key === 'fav') {
                $this->saveFavorites($userId, $before, $after);
            } else {
                $this->saveCart($userId, $before, $after);
            }

            // Read names and current prices from proudkt, never from the input.
            $result = $this->loadStored($key, $userId);

            if (!$this->connection->commit()) {
                throw new RuntimeException('Basket transaction could not commit.');
            }
            return $result;
        } catch (Throwable $error) {
            $this->connection->rollback();
            throw $error;
        } finally {
            $this->mutating = false;
        }
    }

    private function userId(): ?int
    {
        // Resolve on EVERY call: Application may already exist before login.
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) && $id > 0 ? $id : null;
    }

    private function checkKey(string $key): void
    {
        if (!in_array($key, ['cart', 'fav'], true)) {
            throw new InvalidArgumentException('Unsupported basket key.');
        }
    }

    private function validatedItems(string $key, mixed $items): array
    {
        if (!is_array($items)) {
            throw new UnexpectedValueException('Basket callback must return an array.');
        }
        $result = [];
        foreach ($items as $id => $item) {
            $productId = filter_var($id, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            if ($productId === false || !is_array($item)) {
                throw new InvalidArgumentException('bad_id');
            }
            if ($key === 'cart') {
                $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 99]
                ]);
                if ($quantity === false) {
                    throw new InvalidArgumentException('bad_quantity');
                }
                $item['qty'] = $quantity;
            }
            $result[(int) $productId] = $item;
        }
        return $result;
    }

    private function activeCartId(int $userId): ?int
    {
        $rows = $this->read(
            "SELECT id FROM carts
             WHERE user_id = ? AND status = 'active'
             ORDER BY id LIMIT 2",
            'i',
            [$userId]
        );
        if (count($rows) > 1) {
            throw new RuntimeException('More than one active cart exists for this user.');
        }
        return $rows === [] ? null : (int) $rows[0]['id'];
    }

    private function loadStored(string $key, int $userId): array
    {
        if ($key === 'fav') {
            $rows = $this->read(
                'SELECT p.id, p.title, p.price
                 FROM favorites AS f
                 JOIN proudkt AS p ON p.id = f.product_id
                 WHERE f.user_id = ? ORDER BY p.id',
                'i',
                [$userId]
            );
        } else {
            $cartId = $this->activeCartId($userId);
            if ($cartId === null) {
                return [];
            }
            $rows = $this->read(
                'SELECT p.id, p.title, p.price, ci.quantity
                 FROM cart_items AS ci
                 JOIN proudkt AS p ON p.id = ci.product_id
                 WHERE ci.cart_id = ? ORDER BY p.id',
                'i',
                [$cartId]
            );
        }

        $items = [];
        foreach ($rows as $row) {
            $item = [
                'title' => (string) $row['title'],
                'price' => (float) $row['price']
            ];
            if ($key === 'cart') {
                $item['qty'] = (int) $row['quantity'];
            }
            $items[(int) $row['id']] = $item;
        }
        return $items;
    }

    private function saveFavorites(int $userId, array $before, array $after): void
    {
        foreach (array_diff_key($before, $after) as $productId => $item) {
            $this->execute(
                'DELETE FROM favorites WHERE user_id = ? AND product_id = ?',
                'ii',
                [$userId, $productId]
            );
        }
        foreach (array_diff_key($after, $before) as $productId => $item) {
            $this->execute(
                'INSERT INTO favorites (user_id, product_id) VALUES (?, ?)',
                'ii',
                [$userId, $productId]
            );
        }
    }

    private function saveCart(int $userId, array $before, array $after): void
    {
        $cartId = $this->activeCartId($userId);
        if ($cartId === null) {
            if ($after === []) {
                return;
            }
            $this->execute(
                "INSERT INTO carts (user_id, status) VALUES (?, 'active')",
                'i',
                [$userId]
            );
            $cartId = (int) $this->connection->insert_id;
        }

        $changed = false;
        foreach (array_diff_key($before, $after) as $productId => $item) {
            $this->execute(
                'DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?',
                'ii',
                [$cartId, $productId]
            );
            $changed = true;
        }
        foreach ($after as $productId => $item) {
            if (isset($before[$productId])
                && (int) $before[$productId]['qty'] === $item['qty']) {
                continue;
            }
            $this->execute(
                'INSERT INTO cart_items (cart_id, product_id, quantity)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                'iii',
                [$cartId, $productId, $item['qty']]
            );
            $changed = true;
        }
        if ($changed) {
            $this->execute(
                'UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                'i',
                [$cartId]
            );
        }
    }

    private function statement(string $sql, string $types, array $values): mysqli_stmt
    {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Basket query preparation failed.');
        }
        try {
            if ($types !== '') {
                $stmt->bind_param($types, ...$values);
            }
            if (!$stmt->execute()) {
                throw new RuntimeException('Basket query failed.');
            }
            return $stmt;
        } catch (Throwable $error) {
            $stmt->close();
            throw $error;
        }
    }

    private function read(string $sql, string $types, array $values): array
    {
        $stmt = $this->statement($sql, $types, $values);
        try {
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } finally {
            $stmt->close();
        }
    }

    private function execute(string $sql, string $types, array $values): void
    {
        $stmt = $this->statement($sql, $types, $values);
        $stmt->close();
    }
}
