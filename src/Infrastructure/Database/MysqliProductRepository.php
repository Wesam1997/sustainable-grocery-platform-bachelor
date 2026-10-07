<?php
declare(strict_types=1);

final class MysqliProductRepository implements ProductRepositoryInterface
{
    public function __construct(private mysqli $connection) {}
    public static function selectSql(): string
    {
        return "
            SELECT
                p.*,
                e.product_name_en,
                e.co2e_kg_per_kg,
                e.data_quality_dqr,

                rc.recipe_count,
                rc.recipe_weight_g,
                rc.recipe_assumed,
                rc.recipe_co2e

            FROM proudkt AS p

            LEFT JOIN environmental_food_data AS e
                ON p.agb_code COLLATE utf8mb4_unicode_ci
                 = e.agb_code COLLATE utf8mb4_unicode_ci

            LEFT JOIN (
                SELECT
                    r.product_id,
                    COUNT(*) AS recipe_count,
                    SUM(r.weight_g) AS recipe_weight_g,
                    MAX(r.is_assumed) AS recipe_assumed,

                    CASE
                        WHEN COUNT(*) = COUNT(d.agb_code)
                         AND SUM(
                            CASE
                                WHEN r.weight_g > 0
                                 AND d.co2e_kg_per_kg >= 0
                                THEN 0
                                ELSE 1
                            END
                         ) = 0

                        THEN SUM(
                            (r.weight_g / 1000.0)
                            * d.co2e_kg_per_kg
                        )

                        ELSE NULL
                    END AS recipe_co2e

                FROM product_recipe_ingredients AS r

                LEFT JOIN environmental_food_data AS d
                    ON r.agb_code COLLATE utf8mb4_unicode_ci
                     = d.agb_code COLLATE utf8mb4_unicode_ci

                GROUP BY r.product_id
            ) AS rc
                ON rc.product_id = p.id
        ";
    }
    private function buildWhere(
        
        array $filters
    ): string {
        $where = [];

        if ($filters['type'] === 'restaurant') {
            $where[] =
                'p.merchant IN ('
                . $this->escapedList(RESTAURANTS)
                . ')';
        } elseif ($filters['type'] === 'market') {
            $where[] =
                'p.merchant IN ('
                . $this->escapedList(MARKETS)
                . ')';
        }

        if ($filters['q'] !== '') {
            $query = mysqli_real_escape_string(
                $this->connection,
                $filters['q']
            );

            $where[] =
                "(p.title LIKE '%$query%'"
                . " OR p.merchant LIKE '%$query%'"
                . " OR p.ingredients LIKE '%$query%')";
        }

        if ($filters['pmin'] !== null) {
            $where[] =
                'p.price >= '
                . (float)$filters['pmin'];
        }

        if ($filters['pmax'] !== null) {
            $where[] =
                'p.price <= '
                . (float)$filters['pmax'];
        }

        return $where
            ? ' WHERE ' . implode(' AND ', $where)
            : '';
    }
    private function escapedList(
        
        array $values
    ): string {
        $escaped = array_map(
            fn($value) => mysqli_real_escape_string(
                $this->connection,
                $value
            ),
            $values
        );

        return "'" . implode("','", $escaped) . "'";
    }
    private function query(string $sql, string $types = '', array $values = []): array
    {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) throw new RuntimeException('Product query preparation failed.');
        try {
            if ($types !== '') $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) throw new RuntimeException('Product query failed.');
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } finally { $stmt->close(); }
    }
    public function find(int $id): ?array
    {
        return $this->query(self::selectSql() . ' WHERE p.id = ? LIMIT 1', 'i', [$id])[0] ?? null;
    }
    public function findBasic(int $id): ?array
    {
        return $this->query('SELECT id, title, price FROM proudkt WHERE id = ? LIMIT 1', 'i', [$id])[0] ?? null;
    }
    public function listFiltered(array $filters): array
    {
        return $this->query(self::selectSql() . $this->buildWhere($filters) . ' ORDER BY p.id DESC');
    }
    public function related(string $merchant, int $id): array
    {
        return $this->query(self::selectSql() . ' WHERE p.merchant = ? AND p.id != ? ORDER BY p.id LIMIT 4', 'si', [$merchant, $id]);
    }
    public function vocabularyRows(): array
    {
        return $this->query('SELECT title, category, ingredients FROM proudkt');
    }
}

