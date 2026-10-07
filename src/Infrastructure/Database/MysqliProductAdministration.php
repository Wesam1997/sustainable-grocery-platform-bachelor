<?php
declare(strict_types=1);

final class MysqliProductAdministration implements ProductAdministrationInterface
{
    public function __construct(private mysqli $connection) {}
    public function sellers(): array
    {
        return $this->connection->query('SELECT merchant, category FROM proudkt GROUP BY merchant, category ORDER BY merchant, category')->fetch_all(MYSQLI_ASSOC);
    }
    public function climateFoods(): array
    {
        $foods = [];
        foreach ($this->connection->query('SELECT agb_code, product_name_en, co2e_kg_per_kg FROM environmental_food_data WHERE co2e_kg_per_kg >= 0 ORDER BY product_name_en')->fetch_all(MYSQLI_ASSOC) as $row) $foods[(string)$row['agb_code']] = $row;
        return $foods;
    }
    public function create(array $product, array $recipe, int $isAssumed): int
    {
        $this->connection->begin_transaction();
        try {
            $stmt = $this->connection->prepare('INSERT INTO proudkt (title, merchant, category, ingredients, image, price, expires_at, agb_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $values = [$product['title'], $product['merchant'], $product['category'], $product['ingredients'], $product['image'], $product['price'], $product['expires_at'], $product['agb_code']];
            try { $stmt->bind_param('ssissdss', ...$values); $stmt->execute(); $id = $this->connection->insert_id; }
            finally { $stmt->close(); }
            if ($recipe) {
                $stmt = $this->connection->prepare('INSERT INTO product_recipe_ingredients (product_id, ingredient_key, ingredient_name, weight_g, agb_code, is_assumed) VALUES (?, ?, ?, ?, ?, ?)');
                try {
                    foreach ($recipe as $index => $ingredient) {
                        $key = 'ingredient_' . ($index + 1);
                        $name = mb_substr($ingredient['name'], 0, 150);
                        $weight = $ingredient['weight']; $code = $ingredient['code'];
                        $stmt->bind_param('issdsi', $id, $key, $name, $weight, $code, $isAssumed);
                        $stmt->execute();
                    }
                } finally { $stmt->close(); }
            }
            $this->connection->commit();
            return $id;
        } catch (Throwable $error) { $this->connection->rollback(); throw $error; }
    }
}
