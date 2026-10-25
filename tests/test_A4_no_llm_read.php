<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Contracts/LlmClientInterface.php';
require_once __DIR__ . '/../Api/instant_comparisons.php';
require_once __DIR__ . '/../src/Infrastructure/Database/DatabaseConnection.php';

class CountingLlmA4 implements LlmClientInterface
{
    public int $calls = 0;

    public function generateExplanation(array $facts): string
    {
        $this->calls++;
        throw new RuntimeException('LLM must not be called during read');
    }
}

$failures = 0;

function checkA4(string $name, bool $passed): void
{
    global $failures;
    echo ($passed ? 'PASS' : 'FAIL') . " - $name\n";
    if (!$passed) {
        $failures++;
    }
}

$connection = DatabaseConnection::open();
$directory = sys_get_temp_dir() . '/flashfood-a4-' . uniqid();

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

$llm = new CountingLlmA4();

$first = $service->read($context);
$second = $service->read($context);

checkA4(
    'Read works without LLM',
    is_array($first) && !empty($first['text'])
);

checkA4(
    'No LLM calls during read',
    $llm->calls === 0
);

checkA4(
    'Fallback available without cache',
    $first['text'] === $context['fallback']
);

checkA4(
    'Repeated read returns same explanation',
    $first === $second
);

checkA4(
    'No cache preparation required',
    !is_file($directory . '/1.json')
);

echo "\nFailures: $failures\n";

$connection->close();
exit($failures > 0 ? 1 : 0);
