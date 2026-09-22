<?php

/* Shared climate values for the listing and product details. */
function climate_metrics(array $row, array $sig, int $rank): array
{
    $co2Data = is_array($sig['co2'] ?? null) ? $sig['co2'] : [];
    $co2Value = null;

    foreach (['value', 'kg_co2e', 'co2e', 'amount'] as $key) {
        if (isset($co2Data[$key]) && is_numeric($co2Data[$key])) {
            $co2Value = (float)$co2Data[$key];
            break;
        }
    }

    $isExample = $co2Value === null;
    if ($co2Value === null) {
        $co2Value = match ($rank) {
            1 => 0.8,
            2 => 2.4,
            3 => 27.0,
            default => 0.0,
        };
    }

    $rowType = mb_strtolower((string)($row['type'] ?? $row['category'] ?? ''));
    $isMeal = str_contains($rowType, 'restaurant')
        || str_contains($rowType, 'meal')
        || str_contains($rowType, 'dish');

    return [
        'is_example' => $isExample,
        'available' => !$isExample || in_array($rank, [1, 2, 3], true),
        'value' => $co2Value,
        'formatted' => number_format($co2Value, 1, '.', ''),
        'unit' => $isMeal ? 'per serving' : 'per kg',
        /* Approximate comparison used by the prototype: 0.17 kg CO2e/km. */
        'driving_km' => max(1, (int)round($co2Value / 0.17)),
        'comparison' => match ($rank) {
            1 => 'Lower emissions than similar products.',
            2 => 'Moderate emissions compared with similar products.',
            3 => 'Higher emissions than similar products.',
            default => 'Climate impact information is not available.',
        },
        /* A higher visual score means a lower climate impact. */
        'rating_score' => match ($rank) {
            1 => 5,
            2 => 3,
            3 => 1,
            default => 0,
        },
    ];
}

