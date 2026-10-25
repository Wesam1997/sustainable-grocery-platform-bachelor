<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (!in_array(
    $_SERVER['REMOTE_ADDR'] ?? '',
    ['127.0.0.1', '::1'],
    true
)) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

try {
    require_once __DIR__
        . '/../src/Contracts/LlmClientInterface.php';

    require_once __DIR__
        . '/../src/Infrastructure/Llm/OllamaLlmClient.php';

    $client = new OllamaLlmClient();

    $expected =
        'This package has estimated emissions equivalent '
        . 'to driving 3.3 km.';

    $text = $client->generateExplanation([
        'approved_explanations' => [$expected],
        'instruction' =>
            'Return exactly the approved explanation. '
            . 'Do not change its numbers or wording.'
    ]);

    echo json_encode([
        'success' => true,
        'model' => 'gemma3:1b',
        'explanation' => $text,
        'matches_approved_text' => $text === $expected
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}