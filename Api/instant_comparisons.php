<?php
declare(strict_types=1);

/**
 * Prepares and validates one climate comparison per product.
 *
 * PHP calculates all values.
 * Ollama may explain the selected comparison,
 * but cannot change the calculated values.
 */
final class FlashFoodInstantComparisons
{
    public const VERSION = 'instant-comparison-v7-short-validated';

    private array $references = [];
    private array $memo = [];
    private string $directory;

    public function __construct(
        mysqli $connection,
        ?string $directory = null
    ) {
        $this->directory = $directory
            ?? (
                getenv('FLASHFOOD_EXPLANATION_CACHE_DIR')
                ?: __DIR__ . '/../var/climate-explanations'
            );

        try {
            $result = $connection->query(
                "SELECT comparison_key, label, kg_co2e_per_unit, unit,
                        source_title, source_url, assumptions, is_example
                 FROM climate_equivalence_reference
                 WHERE enabled = 1
                   AND comparison_key IN ('car', 'phone', 'shower')
                 ORDER BY id ASC"
            );

            while ($row = $result->fetch_assoc()) {
                $key = (string)($row['comparison_key'] ?? '');
                $factor = $row['kg_co2e_per_unit'] ?? null;

                if (
                    !isset($this->references[$key])
                    && is_numeric($factor)
                    && is_finite((float)$factor)
                    && (float)$factor > 0
                ) {
                    $row['kg_co2e_per_unit'] = (float)$factor;
                    $this->references[$key] = $row;
                }
            }

            $result->free();
        } catch (Throwable $error) {
            error_log(
                'comparison.references_unavailable: '
                . get_class($error)
            );
        }
    }

    /**
     * Read climate facts already calculated elsewhere.
     */
    public static function facts(array $metrics): array
    {
        $facts = [
            'available' => false,
            'note' =>
                'A comparison requires a recorded weight matching the food reference.',
        ];

        $basis = (string)($metrics['basis'] ?? '');

        if (!empty($metrics['package']['available'])) {
            $facts = [
                'available' => true,
                'value' => $metrics['package']['value'] ?? null,
                'basis' => 'per package',
            ];
        } elseif (
            !empty($metrics['available'])
            && in_array(
                $basis,
                ['per serving', 'per box'],
                true
            )
        ) {
            $facts = [
                'available' => true,
                'value' => $metrics['value'] ?? null,
                'basis' => $basis,
            ];
        } elseif (empty($metrics['available'])) {
            $facts['note'] =
                'Climate information is not available.';
        }

        $facts['is_example'] =
            !empty($metrics['is_example'])
            || !empty($metrics['package']['is_example']);

        if (
            !empty($facts['available'])
            && (
                !is_numeric($facts['value'])
                || !is_finite((float)$facts['value'])
                || (float)$facts['value'] <= 0
            )
        ) {
            return [
                'available' => false,
                'note' =>
                    'A comparison is currently unavailable.',
            ];
        }

        return $facts;
    }

    /**
     * Calculate usable comparisons in PHP.
     */
    private function comparisons(array $facts): array
    {
        $kg = $facts['value'] ?? null;

        if (
            empty($facts['available'])
            || !is_numeric($kg)
            || !is_finite((float)$kg)
            || (float)$kg <= 0
        ) {
            return [];
        }

        $rules = [
            'car' => [
                'unit' => 'km',
                'min' => 0.1,
                'max' => 100.0,
                'activity' => 'driving',
                'activity_text' =>
                    'driving {amount} km in a petrol car',
            ],
            'phone' => [
                'unit' => 'charge',
                'min' => 1.0,
                'max' => 200.0,
                'activity' => 'phone',
                'activity_text' =>
                    'fully charging a phone {amount} times',
            ],
            'shower' => [
                'unit' => 'min',
                'min' => 0.5,
                'max' => 60.0,
                'activity' => 'shower',
                'activity_text' =>
                    'taking a hot shower for {amount} minutes',
            ],
        ];

        $valid = [];
        $inDisplayRange = [];

        foreach ($rules as $key => $rule) {
            $reference = $this->references[$key] ?? null;

            if (
                $reference === null
                || (string)($reference['unit'] ?? '')
                    !== $rule['unit']
            ) {
                continue;
            }

            $factor = $reference['kg_co2e_per_unit'] ?? null;

            if (
                !is_numeric($factor)
                || !is_finite((float)$factor)
                || (float)$factor <= 0
            ) {
                continue;
            }

            $value = (float)$kg / (float)$factor;

            if (!is_finite($value) || $value <= 0) {
                continue;
            }

            $amount = number_format(
                $value,
                1,
                '.',
                ''
            );

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
                'activity_text' => str_replace(
                    '{amount}',
                    $spokenAmount,
                    $rule['activity_text']
                ),
                'label' =>
                    (string)($reference['label'] ?? $key),
                'source_title' =>
                    (string)($reference['source_title'] ?? ''),
                'source_url' =>
                    (string)($reference['source_url'] ?? ''),
                'assumptions' =>
                    (string)($reference['assumptions'] ?? ''),
                'is_example' =>
                    (bool)($reference['is_example'] ?? false)
                    || !empty($facts['is_example']),
            ];

            $valid[] = $candidate;

            if (
                $value >= $rule['min']
                && $value <= $rule['max']
            ) {
                $inDisplayRange[] = $candidate;
            }
        }

        return $inDisplayRange !== []
            ? $inDisplayRange
            : $valid;
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

    /**
     * Short, calculated PHP fallback.
     */
    private function fallback(
        array $facts,
        ?array $candidate
    ): string {
        $subject = self::subject(
            (string)($facts['basis'] ?? '')
        );

        if (
            $candidate === null
            || $subject === null
        ) {
            return (string)(
                $facts['note']
                ?? 'A comparison is currently unavailable.'
            );
        }

        $amount = (string)$candidate['spoken_amount'];

        $activity = match ($candidate['key']) {
            'car' =>
                'driving ' . $amount . ' km by car',
            'phone' =>
                'charging a phone ' . $amount . ' times',
            'shower' =>
                'showering for ' . $amount . ' minutes',
            default =>
                $candidate['activity_text'],
        };

        $prefix = !empty($candidate['is_example'])
            ? 'Estimated: '
            : '';

        return $prefix
            . $subject
            . ' ≈ '
            . $activity
            . '.';
    }

    public function context(
        array $metrics,
        int $id
    ): array {
        $facts = self::facts($metrics);
        $options = $this->comparisons($facts);

        // Stable comparison rotation by product ID.
        $selectedOption = $options === []
            ? null
            : $options[
                ((max(1, $id) - 1) % count($options))
            ];

        $subject = self::subject(
            (string)($facts['basis'] ?? '')
        );

        $fallback = $this->fallback(
            $facts,
            $selectedOption
        );

        $fingerprint = hash(
            'sha256',
            json_encode(
                [
                    self::VERSION,
                    getenv('FLASHFOOD_EXPLANATION_MODEL_VERSION')
                        ?: 'default',
                    $id,
                    $facts,
                    $options,
                    $selectedOption,
                ],
                JSON_THROW_ON_ERROR
                    | JSON_INVALID_UTF8_SUBSTITUTE
            )
        );

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

    /**
     * Repair harmless car wording differences.
     * Never modify a calculated value.
     */
    private function repairExplanation(
        array $context,
        mixed $key,
        mixed $text
    ): mixed {
        if (
            $key !== 'car'
            || !is_string($text)
        ) {
            return $text;
        }

        $candidate = $context['selected_option'] ?? null;

        if (
            !is_array($candidate)
            || ($candidate['key'] ?? null) !== 'car'
        ) {
            return $text;
        }

        $text = trim($text);

        if (
            !preg_match(
                '/\b(comparable to|equivalent to)\b/i',
                $text
            )
            || !preg_match('/\bdriving\b/i', $text)
            || !preg_match('/\bcar\b/i', $text)
            || !preg_match(
                '/\b(climate impact|climate footprint)\b/i',
                $text
            )
        ) {
            return $text;
        }

        $text = preg_replace(
            '/\b(kilometers|kilometres)\b/i',
            'km',
            $text
        );

        $subject = self::subject(
            (string)($context['facts']['basis'] ?? '')
        );

        if ($subject === null) {
            return $text;
        }

        return preg_replace(
            '/^The food product[\x{2019}\x{0027}]s\b/ui',
            $subject . "'s",
            $text
        );
    }

    /**
     * Validate LLM text against PHP-calculated values.
     */
    private function validateExplanation(
        array $context,
        mixed $key,
        mixed $text
    ): bool {
        if (
            !is_string($key)
            || !is_string($text)
            || $context['subject'] === null
        ) {
            return false;
        }

        $candidate = $context['selected_option'] ?? null;

        if (
            !is_array($candidate)
            || ($candidate['key'] ?? null) !== $key
        ) {
            return false;
        }

        $text = trim($text);

        /*
         * PHP fallback is built from verified data.
         */
        if ($text === $context['fallback']) {
            return true;
        }

        /*
         * Accept the exact short Ollama sentence.
         * Values and activity must match PHP calculations.
         */
        $amount = (string)$candidate['spoken_amount'];
        $subject = (string)$context['subject'];

        $activity = match ($candidate['key']) {
            'car' =>
                'driving ' . $amount . ' km by car',
            'shower' =>
                'showering for ' . $amount . ' minutes',
            'phone' =>
                'charging a phone ' . $amount . ' times',
            default => null,
        };

        if ($activity !== null) {
            $prefix = !empty($candidate['is_example'])
                ? 'Estimated: '
                : '';

            $expected = $prefix
                . $subject
                . ' ≈ '
                . $activity
                . '.';

            if ($text === $expected) {
                return true;
            }
        }

        /*
         * Reject long or malformed responses.
         */
        if (
            $text === ''
            || strlen($text) > 120
            || str_contains($text, "\n")
            || str_contains($text, "\r")
        ) {
            return false;
        }

        if (
            preg_match_all(
                '/[.!?](?=\s|$)/u',
                $text
            ) !== 1
            || !preg_match('/[.!?]$/u', $text)
        ) {
            return false;
        }

        /*
         * Verify the exact number.
         */
        preg_match_all(
            '/(?<![\p{L}])\d+(?:[.,]\d+)?(?![\p{L}])/u',
            $text,
            $numberMatches
        );

        $numbers = array_map(
            static fn(string $number): string =>
                str_replace(',', '.', $number),
            $numberMatches[0]
        );

        if ($numbers !== [$candidate['amount']]) {
            return false;
        }

        if (
            $candidate['spoken_amount'] === 'less than 0.1'
            && !preg_match(
                '/\bless than 0\.1\b/i',
                $text
            )
        ) {
            return false;
        }

        /*
         * Verify activity and unit.
         */
        $activityPattern = match ($candidate['key']) {
            'car' =>
                '/\b(driving|car)\b/i',
            'phone' =>
                '/\b(charging|charge|phone)\b/i',
            'shower' =>
                '/\b(shower|showering)\b/i',
            default => null,
        };

        $unitPattern = match ($candidate['key']) {
            'car' =>
                '/\bkm\b/i',
            'phone' =>
                '/\b(times|charges)\b/i',
            'shower' =>
                '/\bminutes?\b/i',
            default => null,
        };

        if (
            $activityPattern === null
            || $unitPattern === null
            || !preg_match(
                $activityPattern,
                $text
            )
            || !preg_match(
                $unitPattern,
                $text
            )
        ) {
            return false;
        }

        /*
         * Verify product basis.
         */
        $basisPattern = match (
            $context['facts']['basis'] ?? ''
        ) {
            'per package' =>
                '/\b(pack|package)\b/i',
            'per serving' =>
                '/\b(serving|portion)\b/i',
            'per box' =>
                '/\bbox\b/i',
            default => null,
        };

        if (
            $basisPattern === null
            || !preg_match(
                $basisPattern,
                $text
            )
        ) {
            return false;
        }

        /*
         * Validate the meaning of the comparison.
         */
        $expectedSubject = preg_quote(
            (string)$context['subject'],
            '/'
        );

        if (
            !preg_match(
                '/^(?:Illustrative estimate:\s*)?'
                    . $expectedSubject
                    . '\b/iu',
                $text
            )
        ) {
            return false;
        }

        if (
            !preg_match(
                '/\b(climate impact|climate footprint)\b/i',
                $text
            )
            || !preg_match(
                '/\b(comparable to|equivalent to)\b/i',
                $text
            )
        ) {
            return false;
        }

        /*
         * Reject unsupported environmental claims.
         */
        if (
            preg_match(
                '/\b(reduc(?:e|es|ed|tion)|sav(?:e|es|ed|ing)|increase[ds]?|decrease[ds]?|avoid(?:s|ed)?|offset(?:s|ting)?)\b/i',
                $text
            )
            || preg_match(
                '/\b(?:minutes?|km|kilomet(?:er|re)s?)\s+of\s+(?:climate impact|climate footprint|emissions?)\b/i',
                $text
            )
        ) {
            return false;
        }

        /*
         * Ensure quantity belongs to the reference activity.
         */
        $amountPattern = preg_quote(
            (string)$candidate['amount'],
            '/'
        );

        $activityMeaningPattern = match ($candidate['key']) {
            'car' =>
                '/\bdriving\b[^.!?]*\bcar\b[^.!?]*\b'
                . $amountPattern
                . '\s*km\b|\bdriving\b[^.!?]*\b'
                . $amountPattern
                . '\s*km\b[^.!?]*\bcar\b/i',

            'shower' =>
                '/\b(?:taking|having)\b[^.!?]*\bshower\b[^.!?]*\b(?:for\s+)?'
                . $amountPattern
                . '\s*minutes?\b/i',

            'phone' =>
                '/\bcharg(?:e|es|ing)\b[^.!?]*\bphone\b[^.!?]*\b'
                . $amountPattern
                . '\s*(?:times|charges)\b/i',

            default => null,
        };

        if (
            $activityMeaningPattern === null
            || !preg_match(
                $activityMeaningPattern,
                $text
            )
        ) {
            return false;
        }

        if (
            $candidate['is_example']
            && !preg_match(
                '/\b(illustrative|estimate|estimated|approximate|approximately)\b/i',
                $text
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Read cached explanation or use the PHP fallback.
     */
    public function read(array $context): array
    {
        $memoKey = $context['product_id']
            . ':'
            . $context['fingerprint'];

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $result = [
            'text' => $context['fallback'],
            'source' => 'calculated',
        ];

        $path = $this->directory
            . '/'
            . (int)$context['product_id']
            . '.json';

        $raw = is_file($path)
            ? @file_get_contents(
                $path,
                false,
                null,
                0,
                4096
            )
            : false;

        $cached = is_string($raw)
            ? json_decode($raw, true)
            : null;

        if (
            is_array($cached)
            && ($cached['fingerprint'] ?? '')
                === $context['fingerprint']
            && $this->validateExplanation(
                $context,
                $cached['comparison_key'] ?? null,
                $cached['text'] ?? null
            )
        ) {
            $result = [
                'text' => $cached['text'],
                'source' => 'cached_llm',
            ];
        }

        return $this->memo[$memoKey] = $result;
    }

    /**
     * Prepare explanation using Ollama.
     * Called by CLI, not by page requests.
     */
    public function prepare(
        array $context,
        LlmClientInterface $llm
    ): string {
        if (
            $context['options'] === []
            || !is_array(
                $context['selected_option'] ?? null
            )
        ) {
            return 'unavailable';
        }

        if (
            $this->read($context)['source']
                === 'cached_llm'
        ) {
            return 'cached';
        }

        $modelOptions = array_map(
            static function (array $option): array {
                return [
                    'key' => $option['key'],
                    'comparison' => $option['label'],
                    'equivalence' =>
                        $option['spoken_amount']
                        . ' '
                        . $option['unit'],
                    'product_basis' => $option['basis'],
                    'is_illustrative' => $option['is_example'],
                    'reference_source' =>
                        $option['source_title'],
                    'reference_assumptions' =>
                        $option['assumptions'],
                ];
            },
            [$context['selected_option']]
        );

        $selected = $context['selected_option'];

        $key = (string)$selected['key'];
        $text = $context['fallback'];
        $status = 'generated_fallback';

        try {
            $request = [
                'product_basis' =>
                    $context['facts']['basis'],
                'calculated_product_emissions_kg_co2e' =>
                    $context['facts']['value'],
                'subject' =>
                    $context['subject'],
                'comparison_options' =>
                    $modelOptions,
            ];

            /*
             * One generation and one possible retry.
             */
            for (
                $attempt = 1;
                $attempt <= 2;
                $attempt++
            ) {
                $raw = trim(
                    $llm->generateExplanation($request)
                );

                fwrite(
                    STDERR,
                    "\n========== LLM DEBUG (attempt {$attempt}/2) ==========\n"
                );

                fwrite(
                    STDERR,
                    'Product ID: '
                    . $context['product_id']
                    . "\n"
                );

                fwrite(
                    STDERR,
                    'Expected comparison: '
                    . $selected['key']
                    . "\n"
                );

                fwrite(
                    STDERR,
                    'Expected amount: '
                    . $selected['amount']
                    . "\n"
                );

                fwrite(
                    STDERR,
                    'Expected basis: '
                    . $context['facts']['basis']
                    . "\n"
                );

                fwrite(
                    STDERR,
                    'LLM response: '
                    . $raw
                    . "\n"
                );

                fwrite(
                    STDERR,
                    "=============================================\n"
                );

                $answer = json_decode(
                    $raw,
                    true
                );

                $responseKey = is_array($answer)
                    ? (
                        $answer['comparison_key']
                        ?? null
                    )
                    : null;

                $responseText = is_array($answer)
                    ? (
                        $answer['explanation']
                        ?? null
                    )
                    : null;

                $originalResponseText = $responseText;

                $responseText = $this->repairExplanation(
                    $context,
                    $responseKey,
                    $responseText
                );

                if (
                    $this->validateExplanation(
                        $context,
                        $responseKey,
                        $responseText
                    )
                ) {
                    if (
                        $responseText
                        !== $originalResponseText
                    ) {
                        fwrite(
                            STDERR,
                            "LLM explanation repaired and validated\n"
                        );
                    }

                    $key = $responseKey;
                    $text = trim($responseText);
                    $status = 'generated';

                    fwrite(
                        STDERR,
                        "LLM accepted on attempt {$attempt}\n"
                    );

                    break;
                }

                /*
                 * Feedback for invalid model output.
                 */
                $feedback = [];
                $candidateAmount =
                    (string)$selected['amount'];

                $sentence = is_string($responseText)
                    ? $responseText
                    : '';

                if (
                    $responseKey
                    !== $selected['key']
                ) {
                    $feedback[] =
                        'Wrong comparison_key; use '
                        . $selected['key'];
                }

                preg_match_all(
                    '/(?<![\p{L}])\d+(?:[.,]\d+)?(?![\p{L}])/u',
                    $sentence,
                    $matches
                );

                $numbers = array_map(
                    static fn(string $n): string =>
                        str_replace(',', '.', $n),
                    $matches[0]
                );

                if (
                    $numbers
                    !== [$candidateAmount]
                ) {
                    $feedback[] =
                        'Use exactly one number: '
                        . $candidateAmount;
                }

                $basisPattern = match (
                    $context['facts']['basis']
                ) {
                    'per serving' =>
                        '/\b(serving|portion)\b/i',
                    'per package' =>
                        '/\b(pack|package)\b/i',
                    'per box' =>
                        '/\bbox\b/i',
                    default => '/(?!)/',
                };

                if (
                    !preg_match(
                        $basisPattern,
                        $sentence
                    )
                ) {
                    $feedback[] =
                        'Mention product basis: '
                        . $context['facts']['basis'];
                }

                $activity = match (
                    $selected['key']
                ) {
                    'car' =>
                        '/\b(driving|car)\b/i',
                    'shower' =>
                        '/\b(shower|showering)\b/i',
                    'phone' =>
                        '/\b(charging|charge|phone)\b/i',
                    default => '/(?!)/',
                };

                $unit = match (
                    $selected['key']
                ) {
                    'car' =>
                        '/\bkm\b/i',
                    'shower' =>
                        '/\bminutes?\b/i',
                    'phone' =>
                        '/\b(times|charges)\b/i',
                    default => '/(?!)/',
                };

                if (
                    !preg_match(
                        $activity,
                        $sentence
                    )
                ) {
                    $feedback[] =
                        'Mention the correct comparison activity';
                }

                if (
                    !preg_match(
                        $unit,
                        $sentence
                    )
                ) {
                    $feedback[] =
                        'Use the required comparison unit';
                }

                if (
                    $selected['is_example']
                    && !preg_match(
                        '/\b(illustrative|estimate|estimated|approximate|approximately)\b/i',
                        $sentence
                    )
                ) {
                    $feedback[] =
                        'Clearly say the comparison is estimated';
                }

                if ($feedback === []) {
                    $feedback[] =
                        'Use the exact short comparison pattern.';
                }

                $request['previous_invalid_explanation'] =
                    $sentence;

                $request['validation_feedback'] =
                    implode('; ', $feedback);

                error_log(
                    'climate_explanation.invalid_llm_output product_id='
                    . (int)$context['product_id']
                    . ' attempt='
                    . $attempt
                );
            }
        } catch (Throwable $error) {
            error_log(
                'climate_explanation.llm_fallback product_id='
                . (int)$context['product_id']
                . ' type='
                . get_class($error)
                . ' message='
                . $error->getMessage()
                . ' file='
                . $error->getFile()
                . ' line='
                . $error->getLine()
            );
        }

        /*
         * Fall back to PHP if the LLM fails validation.
         */
        if (
            !$this->validateExplanation(
                $context,
                $key,
                $text
            )
        ) {
            $key = (string)$selected['key'];
            $text = $context['fallback'];
            $status = 'generated_fallback';
        }

        /*
         * Create cache directory if needed.
         */
        if (
            !is_dir($this->directory)
            && !@mkdir(
                $this->directory,
                0700,
                true
            )
            && !is_dir($this->directory)
        ) {
            throw new RuntimeException(
                'Cannot create explanation cache directory.'
            );
        }

        $json = json_encode(
            [
                'fingerprint' =>
                    $context['fingerprint'],
                'comparison_key' =>
                    $key,
                'text' =>
                    trim($text),
            ],
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
        );

        $temp = tempnam(
            $this->directory,
            'write-'
        );

        if ($temp === false) {
            throw new RuntimeException(
                'Cannot create cache file.'
            );
        }

        try {
            if (
                file_put_contents(
                    $temp,
                    $json,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Cannot write explanation cache.'
                );
            }

            chmod($temp, 0640);

            if (
                !rename(
                    $temp,
                    $this->directory
                    . '/'
                    . (int)$context['product_id']
                    . '.json'
                )
            ) {
                throw new RuntimeException(
                    'Cannot replace explanation cache.'
                );
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }

        unset(
            $this->memo[
                $context['product_id']
                . ':'
                . $context['fingerprint']
            ]
        );

        return $status;
    }
}
