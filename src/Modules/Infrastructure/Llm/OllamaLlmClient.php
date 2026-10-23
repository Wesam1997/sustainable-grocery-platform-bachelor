<?php
declare(strict_types=1);

final class OllamaLlmClient implements LlmClientInterface
{
    public function generateExplanation(array $facts): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL er ikke aktiveret.');
        }

        $payload = json_encode([
            'model' => 'gemma3:1b',
            'stream' => false,
            'system' =>
                'You are a climate information explainer for a grocery website. '
                . 'PHP calculates all climate values and comparison options before you receive them. '
                . 'Choose exactly one supplied comparison option and write one short English sentence '
                . 'that helps a shopper understand the supplied product basis. '
                . 'Never calculate or change numbers, invent facts, or combine options. '
                . 'Return only the requested JSON object.',
            'format' => 'json',
            'prompt' => json_encode(
                $facts,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            'options' => [
                'temperature' => 0,
                'num_predict' => 220
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $handle = curl_init('http://127.0.0.1:11434/api/generate');

        if ($handle === false) {
            throw new RuntimeException('Kunne ikke starte forbindelsen.');
        }

        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 60
            ]);

            $response = curl_exec($handle);

            if ($response === false) {
                throw new RuntimeException(
                    'Ollama-forbindelsen fejlede: ' . curl_error($handle)
                );
            }

            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

            if ($status !== 200) {
                throw new RuntimeException(
                    'Ollama returnerede HTTP ' . $status
                );
            }

            $result = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $text = $result['response'] ?? null;

            if (!is_string($text) || trim($text) === '') {
                throw new RuntimeException('Ollama returnerede intet svar.');
            }

            if (($result['done'] ?? false) !== true) {
                throw new RuntimeException('Ollama blev ikke færdig.');
            }

            return trim($text);
        } finally {
            curl_close($handle);
        }
    }
}
