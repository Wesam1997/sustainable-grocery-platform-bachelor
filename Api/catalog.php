<?php

/* ==================================================
   CATALOG CONFIGURATION
================================================== */

const PRICE_FILTER_MIN = 0;
const PRICE_FILTER_MAX = 500;

const WEIGHT_FILTER_MIN = 0;
const WEIGHT_FILTER_MAX = 5000;

/*
 * Temporary prototype thresholds in kg CO2e per kg of product.
 * Keep them in one place so they can be changed after the final
 * academic/source-based definition has been approved.
 */
const CO2_LOW_MAX = 2.0;
const CO2_MEDIUM_MAX = 5.0;

const RESTAURANTS = [
    'Sakura'
];

const MARKETS = [
    'ØkoHaven'
];

const LOCAL_MERCHANTS = [
    'ØkoHaven'
];


/* ==================================================
   PRODUCT WORDS USED ONLY FOR THE DIET FILTER
================================================== */

const MEAT_RED = [
    'okse',
    'oksekød',
    'kalv',
    'svin',
    'svinekød',
    'lam',
    'lammekød',
    'bøf',
    'beef',
    'pork',
    'veal',
    'lamb'
];

const MEAT_WHITE = [
    'kylling',
    'høne',
    'kalkun',
    'chicken',
    'turkey'
];

const FISH = [
    'fisk',
    'laks',
    'tun',
    'torsk',
    'sild',
    'makrel',
    'ørred',
    'reje',
    'rejer',
    'fish',
    'salmon',
    'tuna',
    'cod',
    'trout',
    'shrimp',
    'shrimps',
    'prawn',
    'prawns',
    'seafood',
    'ebi',
    'scampi'
];


/* ==================================================
   TEXT HELPERS
================================================== */

function to_lc($text): string
{
    return mb_strtolower((string)($text ?? ''), 'UTF-8');
}


function contains_any_word(string $text, array $words): bool
{
    foreach ($words as $word) {
        if (mb_stripos($text, $word, 0, 'UTF-8') !== false) {
            return true;
        }
    }

    return false;
}


function weight_grams_from_title($title): ?int
{
    $title = to_lc($title);

    if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|g)\b/u', $title, $matches)) {
        return null;
    }

    $number = (float)str_replace(',', '.', $matches[1]);
    $unit = $matches[2];

    if ($unit === 'kg') {
        return (int)round($number * 1000);
    }

    return (int)round($number);
}


/* ==================================================
   CLIMATE DATA
================================================== */

function climate_from_database($value): array
{
    /*
     * No agb_code or no AGRIBALYSE match means that the value is unknown.
     * We do not invent a climate value for these products.
     */
    if ($value === null || $value === '' || !is_numeric($value)) {
        return [
            'label' => 'Unknown',
            'rank' => 0,
            'value' => null,
            'unit' => 'kg CO₂e/kg'
        ];
    }

    $co2Value = (float)$value;

    if ($co2Value <= CO2_LOW_MAX) {
        $label = 'Low';
        $rank = 1;
    } elseif ($co2Value <= CO2_MEDIUM_MAX) {
        $label = 'Medium';
        $rank = 2;
    } else {
        $label = 'High';
        $rank = 3;
    }

    return [
        'label' => $label,
        'rank' => $rank,
        'value' => $co2Value,
        'unit' => 'kg CO₂e/kg'
    ];
}


/* ==================================================
   PRODUCT SIGNALS
================================================== */

function compute_signals(array $row): array
{
    $productText = to_lc(
        ($row['title'] ?? '') . ' ' . ($row['ingredients'] ?? '')
    );

    $merchant = $row['merchant'] ?? '';

    /* Diet is still identified from the title and ingredients. */
    $type = 'veg';

    if (contains_any_word($productText, MEAT_RED)) {
        $type = 'red';
    } elseif (contains_any_word($productText, MEAT_WHITE)) {
        $type = 'white';
    } elseif (contains_any_word($productText, FISH)) {
        $type = 'fish';
    }

    /* Climate impact now comes from environmental_food_data. */
    $co2 = climate_from_database($row['co2e_kg_per_kg'] ?? null);

    /* No verified water dataset is connected yet. */
    $water = [
        'label' => 'Unknown',
        'rank' => 0
    ];

    $local = in_array($merchant, LOCAL_MERCHANTS, true);

    /* Products without verified CO2 data receive no climate sorting score. */
    $ecoScore = 0.0;

    if ($co2['rank'] > 0) {
        $ecoScore = (4 - $co2['rank']) + ($local ? 0.5 : 0);
    }

    return [
        'diet' => $type,
        'co2' => $co2,
        'water' => $water,
        'local' => $local,
        'eco_score' => $ecoScore,
        'weight_g' => weight_grams_from_title($row['title'] ?? '')
    ];
}


/* ==================================================
   SQL FILTERS
================================================== */

function escaped_sql_list(mysqli $conn, array $values): string
{
    $escaped = array_map(
        function ($value) use ($conn) {
            return mysqli_real_escape_string($conn, $value);
        },
        $values
    );

    return "'" . implode("','", $escaped) . "'";
}


function build_sql_where(mysqli $conn, array $filters): string
{
    $where = [];

    if ($filters['type'] === 'restaurant') {
        $where[] = 'p.merchant IN (' . escaped_sql_list($conn, RESTAURANTS) . ')';
    } elseif ($filters['type'] === 'market') {
        $where[] = 'p.merchant IN (' . escaped_sql_list($conn, MARKETS) . ')';
    }

    if ($filters['q'] !== '') {
        $query = mysqli_real_escape_string($conn, $filters['q']);
        $where[] = "(p.title LIKE '%$query%'"
            . " OR p.merchant LIKE '%$query%'"
            . " OR p.ingredients LIKE '%$query%')";
    }

    if ($filters['pmin'] !== null) {
        $where[] = 'p.price >= ' . (float)$filters['pmin'];
    }

    if ($filters['pmax'] !== null) {
        $where[] = 'p.price <= ' . (float)$filters['pmax'];
    }

    if (empty($where)) {
        return '';
    }

    return ' WHERE ' . implode(' AND ', $where);
}


/* ==================================================
   PARSE FILTERS
================================================== */

function parse_filters(array $src): array
{
    $pmin = isset($src['pmin']) ? (float)$src['pmin'] : null;
    $pmax = isset($src['pmax']) ? (float)$src['pmax'] : null;

    if ($pmin !== null && $pmin <= PRICE_FILTER_MIN) {
        $pmin = null;
    }

    if ($pmax !== null && $pmax >= PRICE_FILTER_MAX) {
        $pmax = null;
    }

    $wmin = isset($src['wmin']) ? (int)$src['wmin'] : null;
    $wmax = isset($src['wmax']) ? (int)$src['wmax'] : null;

    if ($wmin !== null && $wmin <= WEIGHT_FILTER_MIN) {
        $wmin = null;
    }

    if ($wmax !== null && $wmax >= WEIGHT_FILTER_MAX) {
        $wmax = null;
    }

    return [
        'type' => $src['type'] ?? 'all',
        'q' => isset($src['q']) ? trim((string)$src['q']) : '',
        'pmin' => $pmin,
        'pmax' => $pmax,
        'wmin' => $wmin,
        'wmax' => $wmax,
        'onlyVeg' => isset($src['veg']) && $src['veg'] === '1',
        'onlyMeat' => isset($src['meat']) && $src['meat'] === '1',
        'lowCO2' => isset($src['lowco2']) && $src['lowco2'] === '1',
        'midCO2' => isset($src['midco2']) && $src['midco2'] === '1',
        'highCO2' => isset($src['highco2']) && $src['highco2'] === '1',
        'localOnly' => isset($src['local']) && $src['local'] === '1',
        'sort' => $src['sort'] ?? 'eco'
    ];
}


/* ==================================================
   GET PRODUCTS
================================================== */

function get_catalog_items(mysqli $conn, array $params): array
{
    $filters = parse_filters($params);

    /*
     * LEFT JOIN keeps every product in the catalogue.
     * If agb_code is missing, the environmental columns are NULL.
     */
    $sql = "
        SELECT
            p.*,
            e.product_name_en,
            e.co2e_kg_per_kg,
            e.data_quality_dqr
        FROM flashfoodcart.proudkt AS p
        LEFT JOIN flashfoodcart.environmental_food_data AS e
            ON p.agb_code = e.agb_code
    ";

    $sql .= build_sql_where($conn, $filters);
    $sql .= ' ORDER BY p.id DESC';

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return [];
    }

    $items = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $signals = compute_signals($row);

        if (
            $filters['onlyVeg']
            && !$filters['onlyMeat']
            && $signals['diet'] !== 'veg'
        ) {
            continue;
        }

        if (
            $filters['onlyMeat']
            && !$filters['onlyVeg']
            && $signals['diet'] === 'veg'
        ) {
            continue;
        }

        $selectedCO2 = [];

        if ($filters['lowCO2']) {
            $selectedCO2[] = 1;
        }

        if ($filters['midCO2']) {
            $selectedCO2[] = 2;
        }

        if ($filters['highCO2']) {
            $selectedCO2[] = 3;
        }

        if (
            !empty($selectedCO2)
            && !in_array($signals['co2']['rank'], $selectedCO2, true)
        ) {
            continue;
        }

        if ($filters['localOnly'] && !$signals['local']) {
            continue;
        }

        if (
            $filters['wmin'] !== null
            && (
                $signals['weight_g'] === null
                || $signals['weight_g'] < $filters['wmin']
            )
        ) {
            continue;
        }

        if (
            $filters['wmax'] !== null
            && (
                $signals['weight_g'] === null
                || $signals['weight_g'] > $filters['wmax']
            )
        ) {
            continue;
        }

        $row['_sig'] = $signals;
        $items[] = $row;
    }

    usort(
        $items,
        function ($a, $b) use ($filters) {
            switch ($filters['sort']) {
                case 'price_asc':
                    return (float)$a['price'] <=> (float)$b['price'];

                case 'price_desc':
                    return (float)$b['price'] <=> (float)$a['price'];

                case 'newest':
                    return (int)$b['id'] <=> (int)$a['id'];

                default:
                    $comparison = $b['_sig']['eco_score'] <=> $a['_sig']['eco_score'];

                    if ($comparison === 0) {
                        return (float)$a['price'] <=> (float)$b['price'];
                    }

                    return $comparison;
            }
        }
    );

    return $items;
}

