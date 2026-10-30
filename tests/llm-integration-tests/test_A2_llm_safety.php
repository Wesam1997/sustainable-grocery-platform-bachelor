<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Contracts/LlmClientInterface.php';
require_once __DIR__ . '/../../Api/instant_comparisons.php';
require_once __DIR__ . '/../../src/Infrastructure/Database/DatabaseConnection.php';

class FakeWrongLlm implements LlmClientInterface
{
    public int $calls = 0;

    public function generateExplanation(array $context): string
    {
        $this->calls++;

        return json_encode([
            'comparison_key' => $context['comparison_options'][0]['key'],
            'explanation' => 'This product has a climate impact comparable to driving a car for 999999 km.'
        ], JSON_THROW_ON_ERROR);
    }
}

$failures = 0;

function checkA2(string $name, bool $passed): void
{
    global $failures;
    echo ($passed ? 'PASS' : 'FAIL') . " - $name\n";
    if (!$passed) {
        $failures++;
    }
}

$connection = DatabaseConnection::open();

$directory = sys_get_temp_dir() . '/flashfood-a2-' . uniqid();
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

$llm = new FakeWrongLlm();

$status = $service->prepare($context, $llm);
$result = $service->read($context);

checkA2(
    'Invalid LLM explanation rejected',
    $status === 'generated_fallback'
);

checkA2(
    'Fallback text available to user',
    in_array($result['source'], ['calculated', 'cached_llm'], true)
);

checkA2(
    'Wrong number not displayed',
    !str_contains($result['text'], '999999')
);

checkA2(
    'Correct fallback text displayed',
    $result['text'] === $context['fallback']
);

checkA2(
    'LLM retried after invalid response',
    $llm->calls === 2
);

echo "Failures: $failures\n";

$connection->close();

exit($failures > 0 ? 1 : 0);


