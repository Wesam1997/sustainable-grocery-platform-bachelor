<?php
declare(strict_types=1);

final class OllamaLlmClient implements LlmClientInterface
{
    private string $url;
    private string $model;

    public function __construct(
        string $url = 'http://127.0.0.1:11434/api/generate',
        string $model = 'gemma3:1b'
    ) {
        $this->url = $url;
        $this->model = $model;
    }

    public function generateExplanation(array $input): string
    {
        $options = $input['comparison_options'] ?? [];

        if (
            !is_array($options)
            || count($options) !== 1
            || !is_array($options[0])
        ) {
            throw new InvalidArgumentException(
                'Exactly one comparison option is required.'
            );
        }

        $option = $options[0];

        $key = (string)($option['key'] ?? '');
        $equivalence = (string)($option['equivalence'] ?? '');
        $basis = (string)($option['product_basis'] ?? '');
        $illustrative = !empty($option['is_illustrative']);

        if (!in_array($key, ['car', 'shower', 'phone'], true)) {
            throw new InvalidArgumentException(
                'Unsupported comparison key.'
            );
        }

        if ($equivalence === '') {
            throw new InvalidArgumentException(
                'Comparison equivalence is missing.'
            );
        }

        if (
            !in_array(
                $basis,
                ['per serving', 'per package', 'per box'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported product basis.'
            );
        }

        $subject = match ($basis) {
            'per serving' => 'One serving',
            'per package' => 'This pack',
            'per box' => 'This box',
        };

        $activity = match ($key) {
            'car' => 'driving a petrol car',
            'shower' => 'taking a hot shower',
            'phone' => 'charging a phone',
        };

        /*
         * Short explanation instructions.
         *
         * PHP calculates all values.
         * Ollama only explains the selected comparison.
         */
        $systemPrompt = <<<'PROMPT'
You write very short climate-impact comparisons for a grocery website.

PHP has already calculated the climate values and selected the comparison.
You must NEVER calculate, change, round, or invent numbers.

Return ONLY a valid JSON object with exactly two fields:
{
  "comparison_key": "car",
  "explanation": "One serving ≈ driving 26.0 km by car."
}

IMPORTANT RULES:

1. Preserve the supplied comparison_key exactly.

2. Use the exact subject_phrase:
   - One serving
   - This pack
   - This box

3. If is_illustrative is false, begin with subject_phrase.

4. If is_illustrative is true, begin with:
   Estimated: [subject_phrase]

5. Use exactly ONE number.
   Preserve the exact supplied amount, including decimal zeros.

6. Use the ≈ symbol to express an approximate climate comparison.

7. Keep the explanation under 100 characters.

8. End the explanation with a period.

9. Never claim that the product saves, reduces,
   offsets, or avoids emissions.

10. Never describe the product's emissions as
    kilometres, minutes, or phone charges.

11. Never add environmental facts that were not supplied.

12. Never change the comparison activity.

REQUIRED SENTENCE PATTERNS:

CAR:
[subject_phrase] ≈ driving [amount] km by car.

SHOWER:
[subject_phrase] ≈ showering for [amount] minutes.

PHONE:
[subject_phrase] ≈ charging a phone [amount] times.

For illustrative values, add "Estimated: " before
the subject phrase.

Use the supplied number, subject, and comparison.
Do not copy numbers from examples.

Return JSON only.
No Markdown.
No additional explanation.
PROMPT;

        /*
         * Additional instructions for the selected activity.
         */
        $activityInstruction = match ($key) {
            'car' => <<<'RULE'
CAR:
Use "driving [amount] km by car".
The distance belongs to the car comparison,
not to the food product.
RULE,

            'shower' => <<<'RULE'
SHOWER:
Use "showering for [amount] minutes".
The minutes describe the shower duration,
not the product's emissions.
RULE,

            'phone' => <<<'RULE'
PHONE:
Use "charging a phone [amount] times".
The number describes phone charges,
not the product's emissions.
RULE,
        };

        $systemPrompt .= "\n\n" . $activityInstruction;

        /*
         * Information sent to Ollama.
         */
        $promptData = [
            'comparison_key' => $key,
            'exact_equivalence' => $equivalence,
            'product_basis' => $basis,
            'subject_phrase' => $subject,
            'is_illustrative' => $illustrative,
            'reference_activity' => $activity,
            'task' =>
                'Generate one short climate comparison using '
                . 'the required sentence pattern. '
                . 'Use the exact supplied number and subject. '
                . 'Return JSON only.',
        ];

        /*
         * Feedback if the previous response failed validation.
         */
        $feedback = trim(
            (string)($input['validation_feedback'] ?? '')
        );

        if ($feedback !== '') {
            $promptData['previous_invalid_explanation'] =
                (string)(
                    $input['previous_invalid_explanation'] ?? ''
                );

            $promptData['validation_feedback'] = $feedback;

            $promptData['retry_instruction'] =
                'The previous explanation failed validation. '
                . 'Correct all reported problems. '
                . 'Use the required short sentence pattern. '
                . 'Preserve the exact number and subject. '
                . 'Return JSON only.';
        }

        $prompt = json_encode(
            $promptData,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        /*
         * JSON schema ensures Ollama returns the expected structure.
         */
        $schema = [
            'type' => 'object',
            'properties' => [
                'comparison_key' => [
                    'type' => 'string',
                    'enum' => [$key],
                ],
                'explanation' => [
                    'type' => 'string',
                ],
            ],
            'required' => [
                'comparison_key',
                'explanation',
            ],
            'additionalProperties' => false,
        ];

        /*
         * Ollama request.
         */
        $payload = json_encode(
            [
                'model' => $this->model,
                'stream' => false,
                'format' => $schema,
                'system' => $systemPrompt,
                'prompt' => $prompt,
                'options' => [
                    'temperature' => 0,
                    'num_predict' => 120,
                ],
            ],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        /*
         * Check PHP cURL availability.
         */
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHP cURL extension is not enabled.'
            );
        }

        /*
         * Connect to Ollama.
         */
        $handle = curl_init($this->url);

        if ($handle === false) {
            throw new RuntimeException(
                'Could not initialize Ollama connection.'
            );
        }

        try {
            curl_setopt_array(
                $handle,
                [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 60,
                ]
            );

            /*
             * Send request.
             */
            $response = curl_exec($handle);

            if ($response === false) {
                throw new RuntimeException(
                    'Ollama connection failed: '
                    . curl_error($handle)
                );
            }

            /*
             * Check HTTP response.
             */
            $httpCode = (int)curl_getinfo(
                $handle,
                CURLINFO_HTTP_CODE
            );

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException(
                    'Ollama returned HTTP status '
                    . $httpCode
                );
            }

            /*
             * Decode Ollama response.
             */
            $decoded = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($decoded)) {
                throw new RuntimeException(
                    'Invalid Ollama response.'
                );
            }

            /*
             * Ensure generation completed.
             */
            if (
                array_key_exists('done', $decoded)
                && $decoded['done'] !== true
            ) {
                throw new RuntimeException(
                    'Ollama did not complete generation.'
                );
            }

            /*
             * Extract generated explanation.
             */
            $generated = $decoded['response'] ?? null;

            if (
                !is_string($generated)
                || trim($generated) === ''
            ) {
                throw new RuntimeException(
                    'Ollama returned an empty explanation.'
                );
            }

            /*
             * Return JSON response for PHP validation.
             */
            return trim($generated);
        } finally {
            curl_close($handle);
        }
    }
}
