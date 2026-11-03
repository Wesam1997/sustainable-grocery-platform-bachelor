<?php
declare(strict_types=1);

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: public, max-age=300'
);

header('Allow: GET');

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
) {
    http_response_code(405);

    echo json_encode(
        [
            'success' => false,
            'message' =>
                'Only GET requests are allowed.',
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {
    /*
     * Load the project bootstrap.
     */
    require_once __DIR__ .
        '/../src/bootstrap.php';

    /*
     * Load Daily Tips classes explicitly.
     */
    require_once __DIR__ .
        '/../src/Contracts/' .
        'DailyTipsGeneratorInterface.php';

    require_once __DIR__ .
        '/../src/Modules/Infrastructure/Llm/' .
        'OllamaDailyTipsGenerator.php';

    require_once __DIR__ .
        '/../src/Modules/DailyTips/' .
        'DailyTipsService.php';

    /*
     * Ollama is optional.
     */
    $enableOllama =
        getenv('FLASHFOOD_ENABLE_OLLAMA') === '1';

    $generator = $enableOllama
        ? new OllamaDailyTipsGenerator()
        : null;

    $service = new DailyTipsService(
        $generator
    );

    $result = $service->getToday();

    echo json_encode(
        [
            'success' => true,
            'date' => $result['date'],
            'tips' => $result['tips'],
            'source' => $result['source'],
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE |
        JSON_THROW_ON_ERROR
    );
} catch (Throwable $error) {
    error_log(
        'daily_tips.endpoint_failed: ' .
        get_class($error) .
        ': ' .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' =>
                'Daily tips could not be loaded.',
        ],
        JSON_UNESCAPED_UNICODE
    );
}