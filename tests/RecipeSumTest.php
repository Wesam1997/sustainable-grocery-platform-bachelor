<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Contracts/ProductRepositoryInterface.php';
require_once __DIR__ . '/../src/Infrastructure/Database/MysqliProductRepository.php';

final class RecipeSumTest extends TestCase
{
    private static mysqli $db;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('FLASHFOOD_TEST_DB_HOST') ?: '127.0.0.1';
        $user = getenv('FLASHFOOD_TEST_DB_USER') ?: 'root';
        $password = getenv('FLASHFOOD_TEST_DB_PASSWORD') ?: '';

        self::$db = new mysqli(
            $host,
            $user,
            $password,
            'flashfood_test'
        );

        self::$db->set_charset('utf8mb4');

        self::$db->query("
            CREATE TABLE IF NOT EXISTS proudkt (
                id INT PRIMARY KEY,
                title TEXT NOT NULL,
                merchant TEXT NOT NULL,
                category TINYINT NOT NULL,
                ingredients TEXT NOT NULL,
                image VARCHAR(255),
                price DECIMAL(10,2) NOT NULL,
                agb_code VARCHAR(30)
            )
        ");

        self::$db->query("
            CREATE TABLE IF NOT EXISTS environmental_food_data (
                agb_code VARCHAR(30) PRIMARY KEY,
                product_name_en VARCHAR(255) NOT NULL,
                co2e_kg_per_kg DECIMAL(12,4) NOT NULL,
                data_quality_dqr DECIMAL(4,2)
            )
        ");

        self::$db->query("
            CREATE TABLE IF NOT EXISTS ingredients (
                id INT UNSIGNED PRIMARY KEY,
                ingredient_name VARCHAR(150) NOT NULL,
                agb_code VARCHAR(30)
            )
        ");

        self::$db->query("
            CREATE TABLE IF NOT EXISTS product_recipe_ingredients (
                product_id INT NOT NULL,
                ingredient_key VARCHAR(60) NOT NULL,
                ingredient_id INT UNSIGNED NOT NULL,
                weight_g DECIMAL(10,2) NOT NULL,
                is_assumed TINYINT(1) NOT NULL DEFAULT 1
            )
        ");
    }

    protected function setUp(): void
    {
        // Only clear the dedicated test tables.
        foreach ([
            'product_recipe_ingredients',
            'ingredients',
            'environmental_food_data',
            'proudkt'
        ] as $table) {
            self::$db->query("DELETE FROM `$table`");
        }

        self::$db->query("
            INSERT INTO proudkt
                (id, title, merchant, category, ingredients, price)
            VALUES
                (1, 'Vegetable Meal', 'Test Restaurant', 1, 'Tomato, Rice', 50.00),
                (2, 'Plain Product', 'Test Market', 0, '', 10.00)
        ");

        self::$db->query("
            INSERT INTO environmental_food_data
                (agb_code, product_name_en, co2e_kg_per_kg)
            VALUES
                ('TOMATO', 'Tomato', 1.5000),
                ('RICE', 'Rice', 2.0000),
                ('NEGATIVE', 'Invalid factor', -1.0000)
        ");

        self::$db->query("
            INSERT INTO ingredients
                (id, ingredient_name, agb_code)
            VALUES
                (1, 'Tomato', 'TOMATO'),
                (2, 'Rice', 'RICE'),
                (3, 'Unknown', NULL),
                (4, 'Invalid', 'NEGATIVE')
        ");
    }

    private function addIngredient(
        int $ingredientId,
        float $weight,
        int $assumed = 0
    ): void {
        $stmt = self::$db->prepare("
            INSERT INTO product_recipe_ingredients
                (product_id, ingredient_key, ingredient_id, weight_g, is_assumed)
            VALUES
                (1, ?, ?, ?, ?)
        ");

        $key = 'ingredient_' . $ingredientId;
        $stmt->bind_param('sidi', $key, $ingredientId, $weight, $assumed);
        $stmt->execute();
        $stmt->close();
    }

    private function recipeResult(int $productId = 1): array
    {
        $sql = MysqliProductRepository::selectSql()
            . ' WHERE p.id = ?';

        $stmt = self::$db->prepare($sql);
        $stmt->bind_param('i', $productId);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertIsArray($result);

        return $result;
    }

    public function testCalculatesSumOfIngredients(): void
    {
        $this->addIngredient(1, 200);
        $this->addIngredient(2, 300);

        $result = $this->recipeResult();

        // (200/1000 * 1.5) + (300/1000 * 2.0) = 0.9
        $this->assertEqualsWithDelta(
            0.9,
            (float) $result['recipe_co2e'],
            0.0001
        );
    }

    public function testCalculatesTotalWeightAndCount(): void
    {
        $this->addIngredient(1, 200);
        $this->addIngredient(2, 300);

        $result = $this->recipeResult();

        $this->assertSame(2, (int) $result['recipe_count']);
        $this->assertEqualsWithDelta(
            500.0,
            (float) $result['recipe_weight_g'],
            0.0001
        );
    }

    public function testRejectsMissingEnvironmentalReference(): void
    {
        $this->addIngredient(1, 200);
        $this->addIngredient(3, 100);

        $result = $this->recipeResult();

        $this->assertNull($result['recipe_co2e']);
    }

    public function testRejectsZeroIngredientWeight(): void
    {
        $this->addIngredient(1, 0);

        $result = $this->recipeResult();

        $this->assertNull($result['recipe_co2e']);
    }

    public function testRejectsNegativeIngredientWeight(): void
    {
        $this->addIngredient(1, -100);

        $result = $this->recipeResult();

        $this->assertNull($result['recipe_co2e']);
    }

    public function testRejectsNegativeClimateFactor(): void
    {
        $this->addIngredient(4, 100);

        $result = $this->recipeResult();

        $this->assertNull($result['recipe_co2e']);
    }

    public function testMarksAssumedRecipe(): void
    {
        $this->addIngredient(1, 200, 1);
        $this->addIngredient(2, 300, 0);

        $result = $this->recipeResult();

        $this->assertSame(1, (int) $result['recipe_assumed']);
    }

    public function testProductWithoutRecipe(): void
    {
        $result = $this->recipeResult(2);

        $this->assertNull($result['recipe_count']);
        $this->assertNull($result['recipe_co2e']);
    }
}
