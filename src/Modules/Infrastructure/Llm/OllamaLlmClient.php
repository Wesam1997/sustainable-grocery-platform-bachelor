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
        if (!is_array($options) || count($options) !== 1 || !is_array($options[0])) {
            throw new InvalidArgumentException('Exactly one comparison option is required.');
        }

        $option = $options[0];
        $key = (string)($option['key'] ?? '');
        $equivalence = (string)($option['equivalence'] ?? '');
        $basis = (string)($option['product_basis'] ?? '');
        $illustrative = !empty($option['is_illustrative']);

        if (!in_array($key, ['car', 'shower', 'phone'], true)) {
            throw new InvalidArgumentException('Unsupported comparison key.');
        }
        if ($equivalence === '') {
            throw new InvalidArgumentException('Comparison equivalence is missing.');
        }
        if (!in_array($basis, ['per serving', 'per package', 'per box'], true)) {
            throw new InvalidArgumentException('Unsupported product basis.');
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

        $activityInstruction = match ($key) {
            'car' => <<<'RULE'
CAR COMPARISON:
- The food product has an estimated climate impact comparable to driving a petrol car.
- Use the exact product subject given in subject_phrase as the first words.
- Explicitly include "climate impact" or "climate footprint".
- Say "comparable to" or "equivalent to".
- Include "driving", "car", the supplied number, and the literal unit "km".
- Do not write "kilometers" or "kilometres".
- Never imply driving causes or changes the product's emissions.
- Never describe the food product's emissions as a distance.
- Never omit the subject phrase or replace it with "the food product".
RULE,
            'shower' => <<<'RULE'
SHOWER COMPARISON:
- Begin with the exact subject_phrase: One serving, This pack, or This box.
- The FOOD PRODUCT has a climate impact comparable to TAKING A SHOWER.
- Describe the shower as an activity lasting the exact supplied number of minutes.
- Use "taking a shower for [supplied number] minutes" as the activity phrase.
- The product's climate impact is NOT measured in minutes.
- Never start the sentence with "Taking a shower".
- Never say "minutes of climate impact" or "produces minutes".
- Never claim reductions, savings, avoided emissions, or environmental benefits.
- Never imply showering changes the food product's emissions.
- Correct sentence pattern (use supplied subject and number, not these examples):
  "One serving has an estimated climate impact comparable to taking a shower for 12.3 minutes."
RULE,
            'phone' => <<<'RULE'
PHONE COMPARISON:
- Begin with the exact subject_phrase.
- Explain the product's estimated climate impact is comparable to charging a phone.
- Include the exact supplied number and "times" or "charges".
- Do not imply charging changes the food product's emissions.
RULE,
        };

        $systemPrompt = <<<'PROMPT'
You write concise, accurate climate-impact equivalence explanations for a grocery website.
PHP has already calculated the values and selected the comparison. Never calculate or invent numbers.

Return ONLY a JSON object with exactly two fields: "comparison_key" and "explanation".
The explanation must be ONE complete English sentence under 240 characters, ending with a period.

MANDATORY:
1. Begin the sentence with the exact supplied subject_phrase, including its capitalization.
2. Include the exact supplied amount and unit, preserving all trailing decimal zeros.
3. Include exactly one number. Do not invent other quantities.
4. Explicitly name the supplied reference activity.
5. Express comparable climate impact, not causation, and never reverse the meaning.
6. If is_illustrative is true, include "estimated", "illustrative", or "approximately".
7. Do not mention unprovided environmental facts, emission reductions, or savings.
9. Do not measure climate impact in km or minutes: those units belong to the reference activity.
8. Preserve comparison_key exactly.

The subject_phrase is not optional. "The food product" is NOT an acceptable substitute.
PROMPT;

        $systemPrompt .= "\n\n" . $activityInstruction;

        $promptData = [
            'comparison_key' => $key,
            'exact_equivalence' => $equivalence,
            'product_basis' => $basis,
            'subject_phrase' => $subject,
            'is_illustrative' => $illustrative,
            'reference_activity' => $activity,
            'task' => 'Write a new natural sentence about the climate impact of the exact subject_phrase. Use the exact amount and unit. Return only JSON.',
        ];

        $feedback = trim((string)($input['validation_feedback'] ?? ''));
        if ($feedback !== '') {
            $promptData['previous_invalid_explanation'] =
                (string)($input['previous_invalid_explanation'] ?? '');
            $promptData['validation_feedback'] = $feedback;
            $promptData['retry_instruction'] =
                'The previous sentence failed validation. Write a NEW corrected sentence. ' .
                'Fix every issue in validation_feedback. ' .
                'The explanation MUST begin with subject_phrase exactly and use the required unit.';
        }

        $prompt = json_encode($promptData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $schema = [
            'type' => 'object',
            'properties' => [
                'comparison_key' => ['type' => 'string', 'enum' => [$key]],
                'explanation' => ['type' => 'string'],
            ],
            'required' => ['comparison_key', 'explanation'],
            'additionalProperties' => false,
        ];

        $payload = json_encode([
            'model' => $this->model,
            'stream' => false,
            'format' => $schema,
            'system' => $systemPrompt,
            'prompt' => $prompt,
            'options' => ['temperature' => 0, 'num_predict' => 200],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not enabled.');
        }
        $handle = curl_init($this->url);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize Ollama connection.');
        }

        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($handle);
            if ($response === false) {
                throw new RuntimeException('Ollama connection failed: ' . curl_error($handle));
            }
            $httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('Ollama returned HTTP status ' . $httpCode);
            }
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid Ollama response.');
            }
            if (array_key_exists('done', $decoded) && $decoded['done'] !== true) {
                throw new RuntimeException('Ollama did not complete generation.');
            }
            $generated = $decoded['response'] ?? null;
            if (!is_string($generated) || trim($generated) === '') {
                throw new RuntimeException('Ollama returned an empty explanation.');
            }
            return trim($generated);
        } finally {
            curl_close($handle);
        }
    }
}
