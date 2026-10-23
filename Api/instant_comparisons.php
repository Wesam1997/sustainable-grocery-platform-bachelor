<?php
declare(strict_types=1);

/**
 * Prepares and validates one LLM-selected climate comparison per product.
 * The page read path only reads the prepared cache or uses the PHP fallback.
 */
final class FlashFoodInstantComparisons
{
    public const VERSION = 'instant-comparison-v4-product-rotation-fallback';

    private array $references = [];
    private array $memo = [];
    private string $directory;

    public function __construct(mysqli $connection, ?string $directory = null)
    {
        $this->directory = $directory ?? (getenv('FLASHFOOD_EXPLANATION_CACHE_DIR') ?: __DIR__ . '/../var/climate-explanations');

        try {
            $result = $connection->query("SELECT comparison_key, label, kg_co2e_per_unit, unit,
                source_title, source_url, assumptions, is_example
                FROM climate_equivalence_reference
                WHERE enabled = 1 AND comparison_key IN ('car', 'phone', 'shower') ORDER BY id ASC");

            while ($row = $result->fetch_assoc()) {
                $key = (string)($row['comparison_key'] ?? '');
                $factor = $row['kg_co2e_per_unit'] ?? null;
                if (!isset($this->references[$key]) && is_numeric($factor)
                    && is_finite((float)$factor) && (float)$factor > 0) {
                    $row['kg_co2e_per_unit'] = (float)$factor;
                    $this->references[$key] = $row;
                }
            }
            $result->free();
        } catch (Throwable $error) {
            error_log('comparison.references_unavailable: ' . get_class($error));
        }
    }

    /** Facts are already calculated by the climate metrics layer. */
    public static function facts(array $metrics): array
    {
        $facts = ['available' => false, 'note' => 'A comparison requires a recorded weight matching the food reference.'];
        $basis = (string)($metrics['unit'] ?? '');

        if (!empty($metrics['package']['available'])) {
            $facts = ['available' => true, 'value' => $metrics['package']['value'] ?? null, 'basis' => 'per package'];
        } elseif (!empty($metrics['available']) && in_array($basis, ['per serving', 'per box'], true)) {
            $facts = ['available' => true, 'value' => $metrics['value'] ?? null, 'basis' => $basis];
        } elseif (empty($metrics['available'])) {
            $facts['note'] = 'Climate information is not available.';
        }

        $facts['is_example'] = !empty($metrics['is_example']) || !empty($metrics['package']['is_example']);
        if (!empty($facts['available']) && (!is_numeric($facts['value'])
            || !is_finite((float)$facts['value']) || (float)$facts['value'] <= 0)) {
            return ['available' => false, 'note' => 'A comparison is currently unavailable.'];
        }
        return $facts;
    }

    /** Calculate every usable comparison in PHP; the LLM only chooses among these. */
    private function comparisons(array $facts): array
    {
        $kg = $facts['value'] ?? null;
        if (empty($facts['available']) || !is_numeric($kg) || !is_finite((float)$kg) || (float)$kg <= 0) {
            return [];
        }

        $rules = [
            'car' => ['unit' => 'km', 'min' => 0.1, 'max' => 100.0, 'activity' => 'driving', 'activity_text' => 'driving {amount} km in a petrol car'],
            'phone' => ['unit' => 'charge', 'min' => 1.0, 'max' => 200.0, 'activity' => 'phone', 'activity_text' => 'fully charging a phone {amount} times'],
            'shower' => ['unit' => 'min', 'min' => 0.5, 'max' => 60.0, 'activity' => 'shower', 'activity_text' => 'taking a hot shower for {amount} minutes'],
        ];

        $valid = [];
        $inDisplayRange = [];
        foreach ($rules as $key => $rule) {
            $reference = $this->references[$key] ?? null;
            if ($reference === null || (string)($reference['unit'] ?? '') !== $rule['unit']) {
                continue;
            }

            $factor = $reference['kg_co2e_per_unit'] ?? null;
            if (!is_numeric($factor) || !is_finite((float)$factor) || (float)$factor <= 0) {
                continue;
            }

            $value = (float)$kg / (float)$factor;
            if (!is_finite($value) || $value <= 0) {
                continue;
            }

            $amount = number_format($value, 1, '.', '');
            if ((float)$amount === 0.0) {
                $amount = '0.1';
                $spokenAmount = 'less than 0.1';
            } else {
                $spokenAmount = $amount;
            }

            $candidate = [
                'key' => $key,
                'value' => $value,
                'amount' => $amount,
                'spoken_amount' => $spokenAmount,
                'unit' => $rule['unit'],
                'basis' => (string)$facts['basis'],
                'activity' => $rule['activity'],
                'activity_text' => str_replace('{amount}', $spokenAmount, $rule['activity_text']),
                'label' => (string)($reference['label'] ?? $key),
                'source_title' => (string)($reference['source_title'] ?? ''),
                'source_url' => (string)($reference['source_url'] ?? ''),
                'assumptions' => (string)($reference['assumptions'] ?? ''),
                'is_example' => (bool)($reference['is_example'] ?? false) || !empty($facts['is_example']),
            ];
            $valid[] = $candidate;
            if ($value >= $rule['min'] && $value <= $rule['max']) {
                $inDisplayRange[] = $candidate;
            }
        }

        // Avoid tiny or implausibly large comparisons when there are reasonable options.
        return $inDisplayRange !== [] ? $inDisplayRange : $valid;
    }

    private static function subject(string $basis): ?string
    {
        return match ($basis) {
            'per package' => 'This pack',
            'per serving' => 'One serving',
            'per box' => 'This box',
            default => null,
        };
    }

    private function fallback(array $facts, ?array $candidate): string
    {
        $subject = self::subject((string)($facts['basis'] ?? ''));
        if ($candidate === null || $subject === null) {
            return (string)($facts['note'] ?? 'A comparison is currently unavailable.');
        }
        $prefix = $candidate['is_example'] ? 'Illustrative estimate: ' : '';
        return $prefix . $subject . "'s estimated climate footprint is comparable to " . $candidate['activity_text'] . '.';
    }

    public function context(array $metrics, int $id): array
    {
        $facts = self::facts($metrics);
        $options = $this->comparisons($facts);
        // Rotate eligible comparison types by stable product ID. With three
        // options, neighboring IDs get different comparisons and the pattern repeats.
        $selectedOption = $options === []
            ? null
            : $options[((max(1, $id) - 1) % count($options))];
        $subject = self::subject((string)($facts['basis'] ?? ''));
        $fallback = $this->fallback($facts, $selectedOption);

        $fingerprint = hash('sha256', json_encode([
            self::VERSION,
            getenv('FLASHFOOD_EXPLANATION_MODEL_VERSION') ?: 'default',
            $id,
            $facts,
            $options,
            $selectedOption,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));

        return [
            'product_id' => $id,
            'fingerprint' => $fingerprint,
            'facts' => $facts,
            'subject' => $subject,
            'options' => $options,
            'selected_option' => $selectedOption,
            'fallback' => $fallback,
        ];
    }

    /** Check the saved/model response against the PHP-calculated option set. */
    private function validateExplanation(array $context, mixed $key, mixed $text): bool
    {
        if (!is_string($key) || !is_string($text) || $context['subject'] === null) {
            return false;
        }
        // A stable PHP assignment guarantees variety across products; the LLM
        // may phrase only the comparison assigned to this product.
        $candidate = $context['selected_option'] ?? null;
        if (!is_array($candidate) || ($candidate['key'] ?? null) !== $key) {
            return false;
        }

        $text = trim($text);
        if ($text === '' || strlen($text) > 240 || str_contains($text, "\n") || str_contains($text, "\r")) {
            return false;
        }
        if (preg_match_all('/[.!?](?=\s|$)/u', $text) !== 1 || !preg_match('/[.!?]$/u', $text)) {
            return false;
        }

        preg_match_all('/(?<![\p{L}])\d+(?:[.,]\d+)?(?![\p{L}])/u', $text, $numberMatches);
        $numbers = array_map(static fn(string $number): string => str_replace(',', '.', $number), $numberMatches[0]);
        if ($numbers !== [$candidate['amount']]) {
            return false;
        }
        if ($candidate['spoken_amount'] === 'less than 0.1'
            && !preg_match('/\bless than 0\.1\b/i', $text)) {
            return false;
        }

        $activityPattern = match ($candidate['key']) {
            'car' => '/\b(driving|car)\b/i',
            'phone' => '/\b(charging|charge|phone)\b/i',
            'shower' => '/\b(shower|showering)\b/i',
            default => null,
        };
        $unitPattern = match ($candidate['key']) {
            'car' => '/\bkm\b/i',
            'phone' => '/\b(times|charges)\b/i',
            'shower' => '/\bminutes?\b/i',
            default => null,
        };
        if ($activityPattern === null || $unitPattern === null
            || !preg_match($activityPattern, $text) || !preg_match($unitPattern, $text)) {
            return false;
        }

        $basisPattern = match ($context['facts']['basis'] ?? '') {
            'per package' => '/\b(pack|package)\b/i',
            'per serving' => '/\b(serving|portion)\b/i',
            'per box' => '/\bbox\b/i',
            default => null,
        };
        if ($basisPattern === null || !preg_match($basisPattern, $text)) {
            return false;
        }

        if ($candidate['is_example'] && !preg_match('/\b(illustrative|estimate|estimated|approximate|approximately)\b/i', $text)) {
            return false;
        }

        return true;
    }

    public function read(array $context): array
    {
        $memoKey = $context['product_id'] . ':' . $context['fingerprint'];
        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $result = ['text' => $context['fallback'], 'source' => 'calculated'];
        $path = $this->directory . '/' . (int)$context['product_id'] . '.json';
        $raw = is_file($path) ? @file_get_contents($path, false, null, 0, 4096) : false;
        $cached = is_string($raw) ? json_decode($raw, true) : null;

        if (is_array($cached)
            && ($cached['fingerprint'] ?? '') === $context['fingerprint']
            && $this->validateExplanation($context, $cached['comparison_key'] ?? null, $cached['text'] ?? null)) {
            $result = ['text' => $cached['text'], 'source' => 'cached_llm'];
        }

        return $this->memo[$memoKey] = $result;
    }

    /** Called by the CLI preparation tool, never by a page request. */
    public function prepare(array $context, LlmClientInterface $llm): string
    {
        if ($context['options'] === [] || !is_array($context['selected_option'] ?? null)) {
            return 'unavailable';
        }
        if ($this->read($context)['source'] === 'cached_llm') {
            return 'cached';
        }

        $modelOptions = array_map(static function (array $option): array {
            return [
                'key' => $option['key'],
                'comparison' => $option['label'],
                'equivalence' => $option['spoken_amount'] . ' ' . $option['unit'],
                'product_basis' => $option['basis'],
                'is_illustrative' => $option['is_example'],
                'reference_source' => $option['source_title'],
                'reference_assumptions' => $option['assumptions'],
            ];
        }, [$context['selected_option']]);

        $selected = $context['selected_option'];
        $key = (string)$selected['key'];
        $text = $context['fallback'];
        $status = 'generated_fallback';

        try {
            $raw = trim($llm->generateExplanation([
                'product_basis' => $context['facts']['basis'],
                'calculated_product_emissions_kg_co2e' => $context['facts']['value'],
                'subject' => $context['subject'],
                'comparison_options' => $modelOptions,
                'task' => 'Write one short, natural English sentence using only the single supplied comparison.',
                'response_format' => [
                    'comparison_key' => 'the exact key of the supplied comparison option',
                    'explanation' => 'one sentence with the exact supplied amount and unit; if illustrative, use estimated, illustrative, or approximately',
                ],
                'rules' => [
                    'PHP has already calculated the number and selected the comparison. Do not change either.',
                    'Use the supplied comparison key exactly.',
                    'Do not add another activity, number, or unsupported fact.',
                    'Return only a JSON object with comparison_key and explanation.',
                ],
            ]));

            $answer = json_decode($raw, true);
            $responseKey = is_array($answer)
                ? ($answer['comparison_key'] ?? null)
                : null;
            $responseText = is_array($answer)
                ? ($answer['explanation'] ?? null)
                : null;

            if ($this->validateExplanation($context, $responseKey, $responseText)) {
                $key = $responseKey;
                $text = trim($responseText);
                $status = 'generated';
            } else {
                error_log('climate_explanation.invalid_llm_output product_id=' . (int)$context['product_id']);
            }
        } catch (Throwable $error) {
            error_log(
                'climate_explanation.llm_fallback product_id='
                . (int)$context['product_id']
                . ' type='
                . get_class($error)
            );
        }

        // The deterministic PHP text keeps product rotation working when the LLM fails validation or is offline.
        if (!$this->validateExplanation($context, $key, $text)) {
            $key = (string)$selected['key'];
            $text = $context['fallback'];
            $status = 'generated_fallback';
        }

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create explanation cache directory.');
        }
        $json = json_encode([
            'fingerprint' => $context['fingerprint'],
            'comparison_key' => $key,
            'text' => trim($text),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $temp = tempnam($this->directory, 'write-');
        if ($temp === false) {
            throw new RuntimeException('Cannot create cache file.');
        }
        try {
            if (file_put_contents($temp, $json, LOCK_EX) === false
                || !rename($temp, $this->directory . '/' . (int)$context['product_id'] . '.json')) {
                throw new RuntimeException('Cannot write explanation cache.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }

        unset($this->memo[$context['product_id'] . ':' . $context['fingerprint']]);
        return $status;
    }
}

