<?php
declare(strict_types=1);

/**
 * Read-only climate overview for the current basket.
 *
 * Uses the same catalog signals and climate metrics
 * as the product pages.
 *
 * Calculates:
 * - Total CO2e emissions for the cart
 * - CO2e emissions for each cart line
 * - Low / Medium / High product distribution
 * - Driving equivalence
 * - Weighted average CO2e per kg
 * - Overall cart climate classification
 */
final class CartClimateSummaryService
{
    public function __construct(
        private CatalogServiceInterface $catalog,
        private ClimateMetricsInterface $metrics
    ) {}

    /**
     * @param array<int, array<string, mixed>> $cart
     * @return array<string, mixed>
     */
    public function summarize(array $cart): array
    {
        $lines = [];

        // Total emissions for products with known CO2e.
        $knownTotal = 0.0;

        // Emissions included in the weighted classification.
        $classifiedTotal = 0.0;

        // Total product weight in kilograms.
        $totalWeightKg = 0.0;

        // Quantity of items included in classification.
        $classifiedCount = 0;

        // Quantity of items with known emissions.
        $knownCount = 0;

        // Quantity of items with unknown emissions.
        $unknownCount = 0;

        // Number of items in each climate category.
        $breakdown = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'unknown' => 0
        ];

        foreach ($cart as $productId => $item) {
            $id = (int) $productId;
            $qty = (int) ($item['qty'] ?? 0);

            if ($id < 1 || $qty < 1) {
                continue;
            }

            // Get product data and calculated signals.
            $row = $this->catalog->find($id);

            $signals = is_array($row['_sig'] ?? null)
                ? $row['_sig']
                : [];

            $climate = is_array($signals['co2'] ?? null)
                ? $signals['co2']
                : [];

            // Existing product classification.
            $rank = (int) ($climate['rank'] ?? 0);

            if (!in_array($rank, [1, 2, 3], true)) {
                $rank = 0;
            }

            $category = match ($rank) {
                1 => 'low',
                2 => 'medium',
                3 => 'high',
                default => 'unknown'
            };

            $breakdown[$category] += $qty;

            // CO2e per purchased item.
            $perItem = null;

            // Product weight used for the climate calculation.
            $weightG = null;

            if (
                $row !== null
                && ($climate['available'] ?? false) === true
            ) {
                // Use the same calculated weight as ProductSignals.
                $row['climate_weight_g'] =
                    $row['climate_weight_g']
                    ?? ($signals['weight_g'] ?? null);

                $formatted = $this->metrics->format(
                    $row,
                    ['co2' => $climate],
                    $rank
                );

                $package = $formatted['package'] ?? [];

                /*
                 * Grocery products:
                 * Calculate CO2e for the actual package weight.
                 */
                if (
                    ($package['available'] ?? false) === true
                ) {
                    $perItem = $this->validAmount(
                        $package['value'] ?? null
                    );

                    $weightG = $this->validPositiveAmount(
                        $package['calculation_weight_g']
                            ?? $row['climate_weight_g']
                            ?? null
                    );

                /*
                 * Restaurant products:
                 * One cart item represents one serving or box.
                 */
                } elseif (
                    ($climate['calculation_type'] ?? '')
                        === 'recipe_estimate'
                    && ($climate['is_restaurant'] ?? false) === true
                    && in_array(
                        $climate['basis'] ?? null,
                        ['per serving', 'per box'],
                        true
                    )
                ) {
                    $perItem = $this->validAmount(
                        $climate['value'] ?? null
                    );

                    // A known serving weight is required
                    // for the per-kg classification.
                    $weightG = $this->validPositiveAmount(
                        $row['climate_weight_g']
                            ?? $signals['weight_g']
                            ?? null
                    );
                }
            }

            // Total emissions for the selected quantity.
            $lineTotal = $perItem !== null
                ? $perItem * $qty
                : null;

            if (
                $lineTotal !== null
                && is_finite($lineTotal)
            ) {
                $knownTotal += $lineTotal;
                $knownCount += $qty;

                /*
                 * Weighted average classification:
                 *
                 * CO2e per kg =
                 * total emissions / total product weight
                 *
                 * Only include items with valid emissions
                 * AND a valid weight.
                 */
                if ($weightG !== null) {
                    $lineWeightKg = ($weightG / 1000) * $qty;

                    if (
                        $lineWeightKg > 0
                        && is_finite($lineWeightKg)
                    ) {
                        $classifiedTotal += $lineTotal;
                        $totalWeightKg += $lineWeightKg;
                        $classifiedCount += $qty;
                    }
                }
            } else {
                $lineTotal = null;
                $unknownCount += $qty;
            }

            // Data used to display each cart product.
            $lines[] = [
                'id' => $id,
                'title' => (string) ($item['title'] ?? ''),
                'price' => (float) ($item['price'] ?? 0),
                'qty' => $qty,
                'image' => (string) ($row['image'] ?? ''),
                'rank' => $rank,
                'label' => match ($rank) {
                    1 => 'Low',
                    2 => 'Medium',
                    3 => 'High',
                    default => 'Unknown'
                },
                'co2e_line' => $lineTotal
            ];
        }

        /*
         * Calculate the weighted average CO2e per kg.
         *
         * This uses the same measurement basis
         * as the existing product classification.
         */
        $averagePerKg = null;

        if ($totalWeightKg > 0) {
            $averagePerKg = $classifiedTotal / $totalWeightKg;

            if (
                !is_finite($averagePerKg)
                || $averagePerKg < 0
            ) {
                $averagePerKg = null;
            }
        }

        /*
         * Classify the cart using the same thresholds
         * as ClimateService:
         *
         * Low:    <= CO2_LOW_MAX
         * Medium: <= CO2_MEDIUM_MAX
         * High:   > CO2_MEDIUM_MAX
         */
        $cartClimateClass = 'unknown';
        $cartClimateRank = 0;

        if ($averagePerKg !== null) {
            if ($averagePerKg <= CO2_LOW_MAX) {
                $cartClimateClass = 'low';
                $cartClimateRank = 1;

            } elseif ($averagePerKg <= CO2_MEDIUM_MAX) {
                $cartClimateClass = 'medium';
                $cartClimateRank = 2;

            } else {
                $cartClimateClass = 'high';
                $cartClimateRank = 3;
            }
        }

        /*
         * Classification is complete only when
         * all items have known CO2e and valid weights.
         */
        $classificationComplete =
            $classifiedCount > 0
            && $classifiedCount === $knownCount
            && $unknownCount === 0;

        /*
         * Driving equivalence.
         *
         * Uses the existing comparison engine
         * and database-backed car reference.
         *
         * The driving distance is based on the
         * total known CO2e, not the average per kg.
         */
        $carComparison = null;

        if ($knownCount > 0) {
            $aggregate = $this->metrics->format(
                [],
                [
                    'co2' => [
                        'available' => true,
                        'value' => $knownTotal,
                        'basis' => 'per cart'
                    ]
                ],
                0
            );

            $candidate = $aggregate['driving_km'] ?? null;

            if (
                is_array($candidate)
                && ($candidate['unit'] ?? null) === 'km'
                && $this->validAmount(
                    $candidate['value'] ?? null
                ) !== null
            ) {
                $carComparison = $candidate;
            }
        }

        return [
            // Product display data.
            'lines' => $lines,

            // Total climate emissions.
            'known_total' => $knownTotal,
            'known_count' => $knownCount,
            'unknown_count' => $unknownCount,

            // Whether all cart items have known emissions.
            'complete' =>
                $knownCount > 0
                && $unknownCount === 0,

            // Individual product classifications.
            'breakdown' => $breakdown,

            // Driving equivalence.
            'car_comparison' => $carComparison,

            // Weighted cart climate calculation.
            'classified_total' => $classifiedTotal,
            'total_weight_kg' => $totalWeightKg,
            'classified_count' => $classifiedCount,
            'average_per_kg' => $averagePerKg,

            // Overall cart climate classification.
            'cart_climate_class' => $cartClimateClass,
            'cart_climate_rank' => $cartClimateRank,
            'classification_complete' =>
                $classificationComplete
        ];
    }

    /**
     * Validate a non-negative climate value.
     */
    private function validAmount(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return is_finite($amount) && $amount >= 0
            ? $amount
            : null;
    }

    /**
     * Validate a strictly positive product weight.
     */
    private function validPositiveAmount(
        mixed $value
    ): ?float {
        $amount = $this->validAmount($value);

        return $amount !== null && $amount > 0
            ? $amount
            : null;
    }
}
