<?php
declare(strict_types=1);

final class ExplanationService implements ClimateExplanationInterface
{
    private string $lastSource = 'unavailable';

    public function __construct(
        private ?LlmClientInterface $llm,
        private LoggerInterface $logger
    ) {}

    public function lastSource(): string
    {
        return $this->lastSource;
    }

    public function explain(array $climate, ?array $comparison): string
    {
        $this->lastSource = 'unavailable';

        $fallback = (string)(
            $climate['note']
            ?? 'A comparison is currently unavailable.'
        );

        if (empty($climate['available']) || $comparison === null) {
            return $fallback;
        }

        $basis = (string)($comparison['basis'] ?? '');

        $subject = match ($basis) {
            'per package' => 'this pack',
            'per serving' => 'one serving',
            'per box' => 'this box',
            'per kg' => '1 kg of this product',
            default => null
        };

        $raw = $comparison['value'] ?? null;

        if (
            $subject === null ||
            !is_numeric($raw) ||
            !is_finite((float)$raw) ||
            (float)$raw < 0
        ) {
            return $fallback;
        }

        $value = (float)$raw;

        $amount = rtrim(
            rtrim(number_format($value, 1, '.', ''), '0'),
            '.'
        );

        if ($value > 0 && (float)$amount === 0.0) {
            $amount = 'less than 0.1';
        }

        $key = (string)($comparison['key'] ?? '');
        $unit = (string)($comparison['unit'] ?? '');

        $activity = match ($key . ':' . $unit) {
            'car:km' => 'driving ' . $amount . ' km in a petrol car',
            'phone:charge' => $amount . ' full phone charges',
            'shower:min' => $amount . ' minutes of a warm shower',
            default => null
        };

        if ($activity === null) {
            return $fallback;
        }

        // PHP controls all calculated climate facts.
        $approved = [
            'The estimated emissions for ' .
                $subject . ' are comparable to ' . $activity . '.',

            'For ' . $subject .
                ', the estimated emissions correspond to ' .
                $activity . '.'
        ];

        $this->lastSource = 'fallback';
        $phpFallback = $approved[0];

        if ($this->llm === null) {
            return $phpFallback;
        }

        // The current Ollama client supports these bases.
        if (!in_array(
            $basis,
            ['per serving', 'per package', 'per box'],
            true
        )) {
            return $phpFallback;
        }

        // Avoid sending a rounded zero or nonnumeric amount.
        if (str_contains($amount, 'less than')) {
            return $phpFallback;
        }

        $equivalence = match ($key . ':' . $unit) {
            'car:km' => $amount . ' km of driving in a petrol car',
            'shower:min' => $amount . ' minutes of a warm shower',
            'phone:charge' => $amount . ' full phone charges',
            default => null
        };

        if ($equivalence === null) {
            return $phpFallback;
        }

        // Input contract expected by OllamaLlmClient.
        $request = [
            'comparison_options' => [
                [
                    'key' => $key,
                    'equivalence' => $equivalence,
                    'product_basis' => $basis
                ]
            ]
        ];

        try {
            $rawResponse = $this->llm->generateExplanation($request);

            // OllamaLlmClient returns JSON.
            $decoded = json_decode(
                trim($rawResponse),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (
                !is_array($decoded) ||
                !isset(
                    $decoded['comparison_key'],
                    $decoded['explanation']
                ) ||
                count($decoded) !== 2
            ) {
                throw new UnexpectedValueException(
                    'Invalid LLM response structure.'
                );
            }

            if (
                !is_string($decoded['comparison_key']) ||
                $decoded['comparison_key'] !== $key ||
                !is_string($decoded['explanation'])
            ) {
                throw new UnexpectedValueException(
                    'Invalid LLM comparison.'
                );
            }

            $text = trim($decoded['explanation']);

            if (!$this->isValidExplanation(
                $text,
                $subject,
                $amount,
                $key,
                $unit
            )) {
                throw new UnexpectedValueException(
                    'LLM explanation failed validation.'
                );
            }

            $this->lastSource = 'llm';

            return $text;

        } catch (Throwable $error) {
            $this->logger->error(
                'llm.explanation_failed',
                [
                    'type' => get_class($error)
                ]
            );

            return $phpFallback;
        }
    }

    private function isValidExplanation(
        string $text,
        string $subject,
        string $amount,
        string $key,
        string $unit
    ): bool {
        if (
            $text === '' ||
            strlen($text) > 240 ||
            !str_ends_with($text, '.')
        ) {
            return false;
        }

        // Reject additional sentences and unsafe claims.
        if (preg_match('/[!?\r\n]/', $text)) {
            return false;
        }

        if (preg_match(
            '/\b(reduce|reduces|reduced|save|saves|saved|' .
            'avoid|avoids|avoided|offset|offsets|' .
            'increase|increases|decrease|decreases)\b/i',
            $text
        )) {
            return false;
        }

        // Product basis must be present.
        if (stripos($text, $subject) === false) {
            return false;
        }

        // The exact PHP-calculated amount must be preserved.
        preg_match_all(
            '/(?<![\p{L}\d])\d+(?:[.,]\d+)?(?![\p{L}\d])/u',
            $text,
            $matches
        );

        if (count($matches[0]) !== 1) {
            return false;
        }

        if ($matches[0][0] !== $amount) {
            return false;
        }

        // Require a comparison statement.
        if (!preg_match(
            '/\b(comparable to|equivalent to|corresponds to)\b/i',
            $text
        )) {
            return false;
        }

        // Reject conflicting comparison activities.
        if (preg_match(
            '/\b(cycling|bicycle|bike|biking|walking|' .
            'train|bus|flight|flying|airplane)\b/i',
            $text
        )) {
            return false;
        }

        // Reject statements that negate the comparison.
        if (preg_match(
            '/\b(rather than|instead of|not equivalent to|' .
            'not comparable to)\b/i',
            $text
        )) {
            return false;
        }

        // Check the expected activity and unit.
        return match ($key . ':' . $unit) {
            'car:km' =>
                (bool)preg_match('/\bkm\b/i', $text) &&
                (bool)preg_match('/\b(car|driving)\b/i', $text),

            'shower:min' =>
                (bool)preg_match('/\bshower\b/i', $text) &&
                (bool)preg_match('/\bminutes?\b/i', $text),

            'phone:charge' =>
                (bool)preg_match('/\bphone\b/i', $text) &&
                (bool)preg_match('/\b(charges?|charging)\b/i', $text),

            default => false
        };
    }
}
