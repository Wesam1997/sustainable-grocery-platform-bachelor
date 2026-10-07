<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
function climate_service(): ClimateCalculatorRegistry { static $service; return $service ??= ClimateServiceFactory::create(RESTAURANTS); }
function catalog_select_sql(): string { return MysqliProductRepository::selectSql(); }
function parse_filters(array $source): array { return CatalogFilters::parse($source); }
function to_lc($text): string { return ProductSignals::to_lc($text); }
function contains_any_word(string $text, array $words): bool { return ProductSignals::contains_any_word($text, $words); }
function weight_grams_from_title($title): ?int { return ProductSignals::weight_grams_from_title($title); }
function is_restaurant_product(array $row): bool { return ProductSignals::is_restaurant_product($row); }
function climate_from_database($value): array { return (new ClimateService(climate_service()))->classify($value); }
function product_climate(array $row): array { return (new ClimateService(climate_service()))->calculate($row); }
function compute_signals(array $row): array { return (new ProductSignals(new ClimateService(climate_service())))->compute($row); }
function get_catalog_product(mysqli $conn, int $id): ?array { return flashfood_application($conn)->catalog()->find($id); }
function get_catalog_items(mysqli $conn, array $params): array { return flashfood_application($conn)->catalog()->items($params); }

