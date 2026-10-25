<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Contracts/LlmClientInterface.php';
require_once __DIR__ . '/../Api/instant_comparisons.php';
require_once __DIR__ . '/../src/Infrastructure/Database/DatabaseConnection.php';

class FakeOfflineLlm implements LlmClientInterface
{
    public int $calls = 0;

    public function generateExplanation(array $facts): string
    {
        $this->calls++;
        throw new RuntimeException('Simulated Ollama connection failure');
    }
}

$failures = 0;

function checkA3(string $name, bool $passed): void
{
    global $failures;

    echo ($passed ? 'PASS' : 'FAIL') . " - $name\n";

    if (!$passed) {
        $failures++;
    }
}

$connection = DatabaseConnection::open();

$directory = sys_get_temp_dir() . '/flashfood-a3-' . uniqid();

$service = new FlashFoodInstantComparisons($connection, $directory);

$metrics = [
    'available' => true,
    'value' => 2.5,
    'unit' => 'per serving',
];

$context = $service->context($metrics, 1);

if ($context['selected_option'] === null) {
    exit("FAIL - No comparison option available\n");
}

$llm = new FakeOfflineLlm();

$status = null;
$result = null;
$crashed = false;

try {
    $status = $service->prepare($context, $llm);
    $result = $service->read($context);
} catch (Throwable $error) {
    $crashed = true;
    echo "ERROR: " . $error->getMessage() . "\n";
}

checkA3(
    'System survives LLM failure',
    !$crashed
);

checkA3(
    'PHP fallback activated',
    $status === 'generated_fallback'
);

checkA3(
    'Fallback explanation displayed',
    is_array($result)
    && ($result['text'] ?? null) === $context['fallback']
);

checkA3(
    'LLM failure was simulated',
    $llm->calls >= 1
);

$cacheFile = $directory . '/1.json';

checkA3(
    'Fallback saved in cache',
    is_file($cacheFile)
    && is_array($cached = json_decode(
        (string)file_get_contents($cacheFile),
        true
    ))
    && ($cached['text'] ?? null) === $context['fallback']
);

echo "\nFailures: $failures\n";

$connection->close();

exit($failures > 0 ? 1 : 0);
