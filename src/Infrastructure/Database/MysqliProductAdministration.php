<?php
declare(strict_types=1);

final class MysqliProductAdministration
    implements ProductAdministrationInterface
{
    public function __construct(
        private mysqli $connection
    ) {}

    public function sellers(): array
    {
        $result = $this->connection->query(
            'SELECT merchant, category
             FROM proudkt
             GROUP BY merchant, category
             ORDER BY merchant, category'
        );

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function climateFoods(): array
    {
        $result = $this->connection->query(
            'SELECT agb_code, product_name_en, co2e_kg_per_kg
             FROM environmental_food_data
             WHERE co2e_kg_per_kg >= 0
             ORDER BY product_name_en'
        );

        $foods = [];

        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $foods[(string) $row['agb_code']] = $row;
        }

        return $foods;
    }

    private function ingredientId(
        string $name,
        ?string $code
    ): int {
        $stmt = $this->connection->prepare(
            'SELECT id
             FROM ingredients
             WHERE ingredient_name = ?
               AND agb_code <=> ?
             LIMIT 1'
        );

        try {
            $stmt->bind_param('ss', $name, $code);

            if (!$stmt->execute()) {
                throw new RuntimeException(
                    'Ingredient lookup failed.'
                );
            }

            $row = $stmt->get_result()->fetch_assoc();
        } finally {
            $stmt->close();
        }

        if ($row !== null) {
            return (int) $row['id'];
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO ingredients (
                ingredient_name,
                agb_code
             ) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id)'
        );

        try {
            $stmt->bind_param('ss', $name, $code);

            if (!$stmt->execute()) {
                throw new RuntimeException(
                    'Ingredient creation failed.'
                );
            }

            return (int) $this->connection->insert_id;
        } finally {
            $stmt->close();
        }
    }

    public function create(
        array $product,
        array $recipe,
        int $isAssumed
    ): int {
        $this->connection->begin_transaction();

        try {
            $expiresAt = $product['expires_at'] ?? null;

            if (
                $expiresAt === ''
                || $expiresAt === '0000-00-00'
            ) {
                $expiresAt = null;
            }

            $productCode = $product['agb_code'] ?? null;

            if ($productCode === '') {
                $productCode = null;
            }

            $stmt = $this->connection->prepare(
                'INSERT INTO proudkt (
                    title,
                    merchant,
                    category,
                    ingredients,
                    image,
                    price,
                    expires_at,
                    agb_code
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $values = [
                $product['title'],
                $product['merchant'],
                $product['category'],
                $product['ingredients'],
                $product['image'],
                $product['price'],
                $expiresAt,
                $productCode
            ];

            try {
                $stmt->bind_param('ssissdss', ...$values);

                if (!$stmt->execute()) {
                    throw new RuntimeException(
                        'Product creation failed.'
                    );
                }

                $productId = (int) $this->connection->insert_id;
            } finally {
                $stmt->close();
            }

            if ($recipe !== []) {
                $stmt = $this->connection->prepare(
                    'INSERT INTO product_recipe_ingredients (
                        product_id,
                        ingredient_key,
                        ingredient_id,
                        weight_g,
                        is_assumed
                     ) VALUES (?, ?, ?, ?, ?)'
                );

                try {
                    $position = 0;

                    foreach ($recipe as $ingredient) {
                        $position++;

                        $key = 'ingredient_' . $position;

                        $name = mb_substr(
                            trim((string) ($ingredient['name'] ?? '')),
                            0,
                            150
                        );

                        $rawWeight = $ingredient['weight'] ?? null;

                        if (!is_numeric($rawWeight)) {
                            throw new InvalidArgumentException(
                                'Ingredient weight must be numeric.'
                            );
                        }

                        $weight = (float) $rawWeight;

                        if (
                            $name === ''
                            || !is_finite($weight)
                            || $weight <= 0
                        ) {
                            throw new InvalidArgumentException(
                                'Each ingredient needs a name '
                                . 'and a positive weight.'
                            );
                        }

                        $code = isset($ingredient['code'])
                            ? trim((string) $ingredient['code'])
                            : null;

                        if ($code === '') {
                            $code = null;
                        }

                        $ingredientId = $this->ingredientId(
                            $name,
                            $code
                        );

                        $stmt->bind_param(
                            'isidi',
                            $productId,
                            $key,
                            $ingredientId,
                            $weight,
                            $isAssumed
                        );

                        if (!$stmt->execute()) {
                            throw new RuntimeException(
                                'Recipe creation failed.'
                            );
                        }
                    }
                } finally {
                    $stmt->close();
                }
            }

            $this->connection->commit();

            return $productId;
        } catch (Throwable $error) {
            $this->connection->rollback();

            throw $error;
        }
    }
}