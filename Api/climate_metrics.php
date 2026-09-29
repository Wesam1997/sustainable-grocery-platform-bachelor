<?php

function climate_metrics(array $row, array $sig, int $rank): array
{
    $co2 = is_array($sig['co2'] ?? null)
        ? $sig['co2']
        : [];

    $rawValue = $co2['value'] ?? null;

    $available = is_numeric($rawValue)
        && is_finite((float)$rawValue)
        && (float)$rawValue >= 0;

    $value = $available ? (float)$rawValue : null;

    $rank = $available
        ? (int)($co2['rank'] ?? $rank)
        : 0;

    $className = match ($rank) {
        1 => 'low',
        2 => 'medium',
        3 => 'high',
        default => 'unknown'
    };

    $label = match ($rank) {
        1 => 'Low climate impact',
        2 => 'Medium climate impact',
        3 => 'High climate impact',
        default => 'Climate impact'
    };

    $basis = $co2['basis'] ?? 'per kg';

    $note = $available
        ? ($co2['note'] ?? '')
        : 'Climate impact information is not available.';

    return [
        'product_id' => (int)($row['id'] ?? 0),
        'available' => $available,
        'is_estimate' => true,
        'is_example' => (bool)($co2['is_example'] ?? false),

        'value' => $value,
        'formatted' => $available
            ? number_format($value, 2, '.', '')
            : '',

        'unit' => $basis,
        'full_unit' => 'kg CO₂e ' . $basis,

        'rank' => $rank,
        'label' => $label,
        'className' => $className,

        'note' => $note,
        'comparison' => $note,

        'classification_note' =>
            $co2['classification_note'] ?? '',

        /*
         * Kompatibilitet med den eksisterende visning.
         * 0.17 er en prototypeantagelse, ikke en verificeret faktor.
         */
        'driving_km' => $available
            ? (int)round($value / 0.17)
            : null,

        'rating_score' => match ($rank) {
            1 => 5,
            2 => 3,
            3 => 1,
            default => 0
        }
    ];
}