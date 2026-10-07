-- Optional migration. Run manually in phpMyAdmin against flashfoodcart.
-- No real emission factors are invented or seeded by this migration.
CREATE TABLE IF NOT EXISTS climate_equivalence_reference (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comparison_key VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    kg_co2e_per_unit DECIMAL(14,8) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    source_title VARCHAR(255) NOT NULL,
    source_url TEXT NOT NULL,
    assumptions TEXT,
    is_example BOOLEAN NOT NULL DEFAULT FALSE,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    CHECK (kg_co2e_per_unit > 0)
);
