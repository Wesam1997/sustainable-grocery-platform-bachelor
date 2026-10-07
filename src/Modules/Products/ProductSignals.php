<?php
declare(strict_types=1);

final class ProductSignals implements ProductSignalsInterface
{
    public function __construct(private ClimateServiceInterface $climate) {}
    public static function to_lc($text): string
    {
        return mb_strtolower(
            (string)($text ?? ''),
            'UTF-8'
        );
    }
    public static function contains_any_word(
        string $text,
        array $words
    ): bool {
        foreach ($words as $word) {
            if (
                mb_stripos(
                    $text,
                    $word,
                    0,
                    'UTF-8'
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }
    public static function weight_grams_from_title($title): ?int
    {
        $title = self::to_lc($title);

        if (
            !preg_match(
                '/(\d+(?:[.,]\d+)?)\s*(kg|g)\b/u',
                $title,
                $matches
            )
        ) {
            return null;
        }

        $number = (float)str_replace(
            ',',
            '.',
            $matches[1]
        );

        return $matches[2] === 'kg'
            ? (int)round($number * 1000)
            : (int)round($number);
    }
    public static function is_restaurant_product(array $row): bool
    {
        return in_array(
            $row['merchant'] ?? '',
            RESTAURANTS,
            true
        );
    }
    public function compute(array $row): array
    {
        $text = self::to_lc(
            ($row['title'] ?? '')
            . ' '
            . ($row['ingredients'] ?? '')
        );

        $diet = 'veg';

        if (self::contains_any_word($text, MEAT_RED)) {
            $diet = 'red';
        } elseif (self::contains_any_word($text, MEAT_WHITE)) {
            $diet = 'white';
        } elseif (self::contains_any_word($text, FISH)) {
            $diet = 'fish';
        }

        $co2 = $this->climate->calculate($row);

        $local = in_array(
            $row['merchant'] ?? '',
            LOCAL_MERCHANTS,
            true
        );

        $ecoScore = $co2['rank'] > 0
            ? (4 - $co2['rank']) + ($local ? 0.5 : 0)
            : 0.0;

        $weight = self::weight_grams_from_title(
            $row['title'] ?? ''
        );

        if (
            $weight === null
            && is_numeric($row['recipe_weight_g'] ?? null)
        ) {
            $weight = (int)round(
                (float)$row['recipe_weight_g']
            );
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
}
