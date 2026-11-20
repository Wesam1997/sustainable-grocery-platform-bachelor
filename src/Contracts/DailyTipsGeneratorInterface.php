<?php
declare(strict_types=1);

/**
 * Generates sustainability tips for the About us page.
 *
 * The generator may write explanatory text,
 * but it must never calculate or modify CO2e values.
 */
interface DailyTipsGeneratorInterface
{
    /**
     * Generate exactly three daily sustainability tips.
     *
     * Previous tips are provided to reduce repetition.
     *
     * @param array<int, string> $previousTips
     *
     * @return array<int, string>
     */
    public function generateDailyTips(
        array $previousTips = []
    ): array;
}