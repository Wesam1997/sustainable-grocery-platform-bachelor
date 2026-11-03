<?php
declare(strict_types=1);

final class OllamaDailyTipsGenerator
    implements DailyTipsGeneratorInterface
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

    public function generateDailyTips(
        array $previousTips = []
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHP cURL extension is not enabled.'
            );
        }

        $previousTips = array_values(
            array_filter(
                $previousTips,
                static fn (mixed $tip): bool =>
                    is_string($tip) &&
                    trim($tip) !== ''
            )
        );

        /*
         * Vi sender højst de seneste 30 tips til Ollama.
         * Det hjælper modellen med at undgå gentagelser.
         */
        $previousTips = array_slice(
            $previousTips,
            -30
        );

        $systemPrompt = <<<'PROMPT'
You write short sustainability tips for the About us page of a grocery shopping website called Flash Food.

Return only valid JSON with exactly one field named "tips".
The "tips" field must contain exactly three English strings.

Rules:
1. Each tip must be one complete sentence.
2. Each tip must contain between 8 and 28 words.
3. Encourage environmentally considerate grocery choices.
4. Encourage comparing similar products and choosing the lower-CO2e option.
5. Organic farming may be described positively in relation to soil, biodiversity, animal welfare, or limiting synthetic pesticides.
6. Never claim that organic products always have lower CO2e.
7. Never claim that lower CO2e automatically means healthier.
8. Do not provide medical, nutrition, or health advice.
9. Do not invent CO2e numbers, percentages, scientific studies, certifications, or sources.
10. Do not calculate or modify climate values.
11. Mention Green Points in no more than one tip.
12. Do not repeat or closely paraphrase a previous tip.
13. Use a friendly, positive and encouraging tone.
14. Do not use Markdown, headings, numbering, emojis, or bullet points.
PROMPT;

        $promptData = [
            'task' =>
                'Generate three new sustainability tips for today.',

            'website' => 'Flash Food',

            'previous_tips_to_avoid' => $previousTips,

            'required_output' => [
                'tips' => [
                    'First tip',
                    'Second tip',
                    'Third tip',
                ],
            ],
        ];

        $schema = [
            'type' => 'object',

            'properties' => [
                'tips' => [
                    'type' => 'array',

                    'items' => [
                        'type' => 'string',
                    ],

                    'minItems' => 3,
                    'maxItems' => 3,
                ],
            ],

            'required' => ['tips'],
            'additionalProperties' => false,
        ];

        $payload = json_encode(
            [
                'model' => $this->model,
                'stream' => false,
                'format' => $schema,
                'system' => $systemPrompt,

                'prompt' => json_encode(
                    $promptData,
                    JSON_UNESCAPED_UNICODE |
                    JSON_THROW_ON_ERROR
                ),

                'options' => [
                    'temperature' => 0.4,
                    'num_predict' => 250,
                ],
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_THROW_ON_ERROR
        );

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

            $response = curl_exec($handle);

            if ($response === false) {
                throw new RuntimeException(
                    'Ollama connection failed: ' .
                    curl_error($handle)
                );
            }

            $httpCode = (int) curl_getinfo(
                $handle,
                CURLINFO_HTTP_CODE
            );

            if (
                $httpCode < 200 ||
                $httpCode >= 300
            ) {
                throw new RuntimeException(
                    'Ollama returned HTTP status ' .
                    $httpCode
                );
            }

            $ollamaResponse = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($ollamaResponse)) {
                throw new RuntimeException(
                    'Ollama returned an invalid response.'
                );
            }

            if (
                array_key_exists('done', $ollamaResponse) &&
                $ollamaResponse['done'] !== true
            ) {
                throw new RuntimeException(
                    'Ollama did not complete generation.'
                );
            }

            $generatedJson =
                $ollamaResponse['response'] ?? null;

            if (
                !is_string($generatedJson) ||
                trim($generatedJson) === ''
            ) {
                throw new RuntimeException(
                    'Ollama returned empty daily tips.'
                );
            }

            $generatedData = json_decode(
                $generatedJson,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($generatedData)) {
                throw new RuntimeException(
                    'The generated tips are not valid JSON.'
                );
            }

            $tips = $generatedData['tips'] ?? null;

            if (
                !is_array($tips) ||
                count($tips) !== 3
            ) {
                throw new RuntimeException(
                    'Ollama must return exactly three tips.'
                );
            }

            $tips = array_map(
                static fn (mixed $tip): string =>
                    is_string($tip)
                        ? trim($tip)
                        : '',
                $tips
            );

            foreach ($tips as $tip) {
                if ($tip === '') {
                    throw new RuntimeException(
                        'Ollama returned an empty tip.'
                    );
                }
            }

            return array_values($tips);
        } finally {
            curl_close($handle);
        }
    }
}