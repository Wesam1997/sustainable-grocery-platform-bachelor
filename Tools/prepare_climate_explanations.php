<?php
declare(strict_types=1);
// Run from XAMPP's PHP CLI; never expose model generation through a public URL.
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
        fwrite(STDERR, "Usage: php tools/prepare_climate_explanations.php [--limit=100]\n");
        exit(1);
    }
    $limit = min(10000, (int)$match[1]);
}
$lock = fopen(sys_get_temp_dir() . '/flashfood-prepare-' . substr(hash('sha256', __DIR__), 0, 16) . '.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Preparation is already running, or a lock could not be opened.\n");
    exit(1);
}
try {
    if (!class_exists('OllamaLlmClient')) throw new RuntimeException('OllamaLlmClient must be loaded by your existing application bootstrap.');
    $llm = new OllamaLlmClient();
    $service = new FlashFoodInstantComparisons($conn);
    $rows = $conn->query('SELECT id FROM proudkt ORDER BY id');
    $attempted = 0;
    $failed = 0;
    while ($record = $rows->fetch_assoc()) {
        $id = (int)$record['id'];
        $row = get_catalog_product($conn, $id);
        if ($row === null) continue;
        $sig = $row['_sig'] ?? compute_signals($row);
        $metrics = climate_metrics($row, $sig, (int)($sig['co2']['rank'] ?? 0));
        $context = $service->context($metrics, $id);
        if (empty($context['options'])) {
            echo "Product $id: no valid comparison options\n";
            continue;
        }
        if ($service->read($context)['source'] === 'cached_llm') continue;
        if ($attempted >= $limit) break;
        $attempted++;
        try {
            $status = $service->prepare($context, $llm);
            echo "Product $id: $status\n";
        } catch (Throwable $error) {
            $failed++;
            fwrite(STDERR, "Product $id: " . $error->getMessage() . " (instant calculated text remains available)\n");
            // Stop immediately on a model failure instead of repeating a timeout for every product.
            break;
        }
    }
    $rows->free();
    echo "Finished: $attempted attempted, $failed failed. Unchanged cached products were skipped.\n";
    exit($failed > 0 ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

