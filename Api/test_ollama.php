<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/**
 * Ollama integration test.
 *
 * Tests the connection between PHP and Ollama.
 * Only allows CLI or local HTTP requests.
 */

$isCli = PHP_SAPI === 'cli';

$isLocal = in_array(
    $_SERVER['REMOTE_ADDR'] ?? '',
    ['127.0.0.1', '::1'],
    true
);

if (!$isCli && !$isLocal) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Access denied.'
    ]);

    exit;
}

try {
    // Load the required interface.
    require_once __DIR__
        . '/../src/Contracts/LlmClientInterface.php';

    // Correct path to OllamaLlmClient.
    require_once __DIR__
        . '/../src/Modules/Infrastructure/Llm/OllamaLlmClient.php';

    // Create the Ollama client.
    $client = new OllamaLlmClient();

    // PHP supplies the comparison facts.
    $request = [
        'comparison_options' => [
            [
                'key' => 'car',
                'equivalence' =>
                    '3.3 km of driving in a petrol car',
                'product_basis' => 'per package'
            ]
        ]
    ];

    // Send the request to Ollama.
    $rawResponse = $client->generateExplanation($request);

    // Decode the JSON returned by Ollama.
    $decoded = json_decode(
        trim($rawResponse),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // Validate the expected JSON structure.
    $validStructure =
        is_array($decoded) &&
        ($decoded['comparison_key'] ?? null) === 'car' &&
        is_string($decoded['explanation'] ?? null) &&
        trim($decoded['explanation']) !== '';

    if (!$validStructure) {
        throw new UnexpectedValueException(
            'Ollama returned an invalid response.'
        );
    }

    // Return the test result.
    echo json_encode([
        'success' => true,
        'model' => 'gemma3:1b',
        'comparison_key' => $decoded['comparison_key'],
        'explanation' => $decoded['explanation'],
        'valid_structure' => true
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
