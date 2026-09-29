<?php

const PRICE_FILTER_MIN = 0;
const PRICE_FILTER_MAX = 500;

const WEIGHT_FILTER_MIN = 0;
const WEIGHT_FILTER_MAX = 5000;

/*
 * Foreløbige prototypegrænser.
 * Klassificeringen beregnes altid pr. kg.
 */
const CO2_LOW_MAX = 2.0;
const CO2_MEDIUM_MAX = 5.0;

const RESTAURANTS = ['Sakura'];
const MARKETS = ['ØkoHaven'];
const LOCAL_MERCHANTS = ['ØkoHaven'];

const MEAT_RED = [
    'okse', 'oksekød', 'kalv', 'svin', 'svinekød',
    'lam', 'lammekød', 'bøf', 'beef', 'pork', 'veal', 'lamb'
];

const MEAT_WHITE = [
    'kylling', 'høne', 'kalkun', 'chicken', 'turkey'
];

const FISH = [
    'fisk', 'laks', 'tun', 'torsk', 'sild', 'makrel',
    'ørred', 'reje', 'rejer', 'fish', 'salmon', 'tuna',
    'cod', 'trout', 'shrimp', 'shrimps', 'prawn',
    'prawns', 'seafood', 'ebi', 'scampi'
];


/* ---------- Tekst og vægt ---------- */

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

    if (!preg_match(
        '/(\d+(?:[.,]\d+)?)\s*(kg|g)\b/u',
        $title,
        $matches
    )) {
        return null;
    }

    $number = (float)str_replace(',', '.', $matches[1]);

    return $matches[2] === 'kg'
        ? (int)round($number * 1000)
        : (int)round($number);
}

function is_restaurant_product(array $row): bool
{
    return in_array(
        $row['merchant'] ?? '',
        RESTAURANTS,
        true
    );
}


/* ---------- Fælles databaseforespørgsel ---------- */

function catalog_select_sql(): string
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

        FROM flashfoodcart.proudkt AS p

        LEFT JOIN flashfoodcart.environmental_food_data AS e
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
                        r.weight_g / 1000.0
                        * d.co2e_kg_per_kg
                    )

                    ELSE NULL
                END AS recipe_co2e

            FROM flashfoodcart.product_recipe_ingredients AS r

            LEFT JOIN flashfoodcart.environmental_food_data AS d
                ON r.agb_code COLLATE utf8mb4_unicode_ci
                 = d.agb_code COLLATE utf8mb4_unicode_ci

            GROUP BY r.product_id
        ) AS rc
            ON rc.product_id = p.id
    ";
}


/* ---------- Klimaklassificering ---------- */

function climate_from_database($value): array
{
    if (
        !is_numeric($value)
        || !is_finite((float)$value)
        || (float)$value < 0
    ) {
        return [
            'label' => 'Unknown',
            'rank' => 0,
            'value' => null,
            'unit' => 'kg CO₂e/kg'
        ];
    }

    $value = (float)$value;

    if ($value <= CO2_LOW_MAX) {
        $label = 'Low';
        $rank = 1;
    } elseif ($value <= CO2_MEDIUM_MAX) {
        $label = 'Medium';
        $rank = 2;
    } else {
        $label = 'High';
        $rank = 3;
    }

    return [
        'label' => $label,
        'rank' => $rank,
        'value' => $value,
        'unit' => 'kg CO₂e/kg'
    ];
}


/*
 * Opskrifter:
 * - Restauranter: samlet værdi for portionen/boksen.
 * - Dagligvarer: opskriftens værdi omregnet til pr. kg.
 * - Ufuldstændige opskrifter får ingen samlet værdi.
 */
function product_climate(array $row): array
{
    $isMeal = is_restaurant_product($row);
    $hasRecipe = (int)($row['recipe_count'] ?? 0) > 0;

    $perKg = null;
    $displayValue = null;

    $basis = $isMeal ? 'per serving' : 'per kg';

    if (
        $isMeal
        && stripos((string)($row['title'] ?? ''), 'box') !== false
    ) {
        $basis = 'per box';
    }

    if ($hasRecipe) {
        $total = $row['recipe_co2e'] ?? null;
        $grams = (float)($row['recipe_weight_g'] ?? 0);

        if (
            is_numeric($total)
            && is_finite((float)$total)
            && (float)$total >= 0
            && $grams > 0
        ) {
            $perKg = (float)$total / ($grams / 1000.0);

            $displayValue = $isMeal
                ? (float)$total
                : $perKg;
        }

        $isExample = !empty($row['recipe_assumed']);

        $note = $isExample
            ? 'Estimated from an example recipe and average ingredient data. Actual ingredients and quantities may vary.'
            : 'Estimated from ingredient weights and average climate data.';
    } else {
        $raw = $row['co2e_kg_per_kg'] ?? null;
        $isExample = false;

        if (
            is_numeric($raw)
            && is_finite((float)$raw)
            && (float)$raw >= 0
        ) {
            $perKg = (float)$raw;

            if ($isMeal) {
                $grams = weight_grams_from_title(
                    $row['title'] ?? ''
                );

                if ($grams !== null && $grams > 0) {
                    $displayValue = $perKg * $grams / 1000.0;
                }
            } else {
                $displayValue = $perKg;
            }
        }

        $note = 'Estimated from average climate data for the matched food.';
    }

    /*
     * Farve og filter bruger pr.-kg-værdien.
     * Visningsværdien kan være pr. portion eller boks.
     */
    $co2 = climate_from_database(
        $displayValue === null ? null : $perKg
    );

    $co2['value'] = $displayValue;
    $co2['per_kg'] = $perKg;
    $co2['basis'] = $basis;
    $co2['unit'] = 'kg CO₂e ' . $basis;
    $co2['is_estimate'] = true;
    $co2['is_example'] = $isExample;
    $co2['note'] = $note;

    $co2['classification_note'] =
        'Prototype categories are based on emissions per kg: '
        . 'Low up to 2; Medium above 2 and up to 5; High above 5.';

    return $co2;
}


/* ---------- Produktsignaler ---------- */

function compute_signals(array $row): array
{
    $text = to_lc(
        ($row['title'] ?? '') . ' '
        . ($row['ingredients'] ?? '')
    );

    $diet = 'veg';

    if (contains_any_word($text, MEAT_RED)) {
        $diet = 'red';
    } elseif (contains_any_word($text, MEAT_WHITE)) {
        $diet = 'white';
    } elseif (contains_any_word($text, FISH)) {
        $diet = 'fish';
    }

    $co2 = product_climate($row);

    $local = in_array(
        $row['merchant'] ?? '',
        LOCAL_MERCHANTS,
        true
    );

    $ecoScore = $co2['rank'] > 0
        ? (4 - $co2['rank']) + ($local ? 0.5 : 0)
        : 0.0;

    $weight = weight_grams_from_title($row['title'] ?? '');

    if (
        $weight === null
        && is_numeric($row['recipe_weight_g'] ?? null)
    ) {
        $weight = (int)round((float)$row['recipe_weight_g']);
    }

    return [
        'diet' => $diet,
        'co2' => $co2,
        'water' => [
            'label' => 'Unknown',
            'rank' => 0
        ],
        'local' => $local,
        'eco_score' => $ecoScore,
        'weight_g' => $weight
    ];
}


/* ---------- Filtre ---------- */

function escaped_sql_list(mysqli $conn, array $values): string
{
    $escaped = array_map(
        fn($value) => mysqli_real_escape_string($conn, $value),
        $values
    );

    return "'" . implode("','", $escaped) . "'";
}

function parse_filters(array $src): array
{
    $pmin = isset($src['pmin']) ? (float)$src['pmin'] : null;
    $pmax = isset($src['pmax']) ? (float)$src['pmax'] : null;
    $wmin = isset($src['wmin']) ? (int)$src['wmin'] : null;
    $wmax = isset($src['wmax']) ? (int)$src['wmax'] : null;

    if ($pmin !== null && $pmin <= PRICE_FILTER_MIN) {
        $pmin = null;
    }

    if ($pmax !== null && $pmax >= PRICE_FILTER_MAX) {
        $pmax = null;
    }

    if ($wmin !== null && $wmin <= WEIGHT_FILTER_MIN) {
        $wmin = null;
    }

    if ($wmax !== null && $wmax >= WEIGHT_FILTER_MAX) {
        $wmax = null;
    }

    return [
        'type' => $src['type'] ?? 'all',
        'q' => trim((string)($src['q'] ?? '')),
        'pmin' => $pmin,
        'pmax' => $pmax,
        'wmin' => $wmin,
        'wmax' => $wmax,
        'onlyVeg' => (string)($src['veg'] ?? '') === '1',
        'onlyMeat' => (string)($src['meat'] ?? '') === '1',
        'lowCO2' => (string)($src['lowco2'] ?? '') === '1',
        'midCO2' => (string)($src['midco2'] ?? '') === '1',
        'highCO2' => (string)($src['highco2'] ?? '') === '1',
        'localOnly' => (string)($src['local'] ?? '') === '1',
        'sort' => $src['sort'] ?? 'eco'
    ];
}

function build_sql_where(mysqli $conn, array $filters): string
{
    $where = [];

    if ($filters['type'] === 'restaurant') {
        $where[] = 'p.merchant IN ('
            . escaped_sql_list($conn, RESTAURANTS) . ')';
    } elseif ($filters['type'] === 'market') {
        $where[] = 'p.merchant IN ('
            . escaped_sql_list($conn, MARKETS) . ')';
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

    return $where
        ? ' WHERE ' . implode(' AND ', $where)
        : '';
}


/* ---------- Hent et enkelt produkt ---------- */

function get_catalog_product(mysqli $conn, int $id): ?array
{
    $stmt = mysqli_prepare(
        $conn,
        catalog_select_sql() . ' WHERE p.id = ? LIMIT 1'
    );

    if (!$stmt) {
        throw new RuntimeException('Product query could not be prepared.');
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Product query failed.');
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    $row['_sig'] = compute_signals($row);

    return $row;
}


/* ---------- Hent og filtrer produktlisten ---------- */

function get_catalog_items(mysqli $conn, array $params): array
{
    $filters = parse_filters($params);

    $sql = catalog_select_sql()
        . build_sql_where($conn, $filters)
        . ' ORDER BY p.id DESC';

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        throw new RuntimeException('Catalogue query failed.');
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

    $items = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $sig = compute_signals($row);

        if (
            $filters['onlyVeg']
            && !$filters['onlyMeat']
            && $sig['diet'] !== 'veg'
        ) {
            continue;
        }

        if (
            $filters['onlyMeat']
            && !$filters['onlyVeg']
            && $sig['diet'] === 'veg'
        ) {
            continue;
        }

        if (
            $selectedCO2
            && !in_array($sig['co2']['rank'], $selectedCO2, true)
        ) {
            continue;
        }

        if ($filters['localOnly'] && !$sig['local']) {
            continue;
        }

        if (
            $filters['wmin'] !== null
            && (
                $sig['weight_g'] === null
                || $sig['weight_g'] < $filters['wmin']
            )
        ) {
            continue;
        }

        if (
            $filters['wmax'] !== null
            && (
                $sig['weight_g'] === null
                || $sig['weight_g'] > $filters['wmax']
            )
        ) {
            continue;
        }

        $row['_sig'] = $sig;
        $items[] = $row;
    }

    mysqli_free_result($result);

    usort($items, function ($a, $b) use ($filters) {
        switch ($filters['sort']) {
            case 'price_asc':
                return (float)$a['price'] <=> (float)$b['price'];

            case 'price_desc':
                return (float)$b['price'] <=> (float)$a['price'];

            case 'newest':
                return (int)$b['id'] <=> (int)$a['id'];

            default:
                $comparison = $b['_sig']['eco_score']
                    <=> $a['_sig']['eco_score'];

                return $comparison !== 0
                    ? $comparison
                    : (float)$a['price'] <=> (float)$b['price'];
        }
    });

    return $items;
}