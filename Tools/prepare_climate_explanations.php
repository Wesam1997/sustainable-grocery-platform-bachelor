<?php
declare(strict_types=1);

// Generate climate explanations locally using PHP CLI.
// This script must never be accessible as a public web endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Api/connect.php';
require_once __DIR__ . '/../Api/catalog.php';
require_once __DIR__ . '/../Api/climate_metrics.php';
require_once __DIR__ . '/../Api/instant_comparisons.php';

$limit = 100;

foreach (array_slice($argv, 1) as $arg) {
    if (!preg_match('/^--limit=([1-9][0-9]*)$/', $arg, $match)) {
        fwrite(
            STDERR,
            "Usage: php Tools/prepare_climate_explanations.php [--limit=100]\n"
        );
        exit(1);
    }

    $limit = min(10000, (int) $match[1]);
}

$lockPath = sys_get_temp_dir()
    . '/flashfood-prepare-'
    . substr(hash('sha256', __DIR__), 0, 16)
    . '.lock';

$lock = fopen($lockPath, 'c');

if ($lock === false) {
    fwrite(STDERR, "Cannot open preparation lock.\n");
    exit(1);
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    fwrite(STDERR, "Preparation is already running.\n");
    exit(1);
}

$attempted = 0;
$failed = 0;
$generated = 0;
$fallback = 0;
$skipped = 0;

try {
    if (!class_exists('OllamaLlmClient')) {
        throw new RuntimeException(
            'OllamaLlmClient must be loaded by the application bootstrap.'
        );
    }

    $llm = new OllamaLlmClient();
    $service = new FlashFoodInstantComparisons($conn);

    $rows = $conn->query(
        'SELECT id FROM proudkt ORDER BY id'
    );

    try {
        while ($record = $rows->fetch_assoc()) {
            $id = (int) $record['id'];

            $row = get_catalog_product($conn, $id);

            if ($row === null) {
                continue;
            }

            $sig = $row['_sig'] ?? compute_signals($row);

            $metrics = climate_metrics(
                $row,
                $sig,
                (int) ($sig['co2']['rank'] ?? 0)
            );

            $context = $service->context($metrics, $id);

            if (empty($context['options'])) {
                echo "Product $id: no valid comparison options\n";
                continue;
            }

            if ($service->read($context)['source'] === 'cached_llm') {
                $skipped++;
                continue;
            }

            if ($attempted >= $limit) {
                break;
            }

            $attempted++;

            try {
                $status = $service->prepare($context, $llm);

                echo "Product $id: $status\n";

                if ($status === 'generated') {
                    $generated++;
                } elseif ($status === 'generated_fallback') {
                    $fallback++;
                }
            } catch (Throwable $error) {
                $failed++;

                fwrite(
                    STDERR,
                    "Product $id: "
                    . $error->getMessage()
                    . "\n"
                );

                break;
            }
        }
    } finally {
        $rows->free();
    }

    echo "\n========== TEST SUMMARY ==========\n";
    echo "Attempted: $attempted\n";
    echo "LLM accepted: $generated\n";
    echo "Fallback used: $fallback\n";
    echo "Cached products skipped: $skipped\n";
    echo "Processing errors: $failed\n";

    if ($attempted > 0) {
        $acceptance = round(
            ($generated / $attempted) * 100,
            1
        );

        echo "LLM acceptance rate: $acceptance%\n";
    }

    echo "==================================\n";

} catch (Throwable $error) {
    $failed++;

    fwrite(
        STDERR,
        get_class($error)
        . ': '
        . $error->getMessage()
        . "\n"
    );
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($failed > 0 ? 1 : 0);
