<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

function checkResult(
    string $name,
    bool $passed
): void {
    echo ($passed ? 'PASS' : 'FAIL') . ' - ' . $name . PHP_EOL;

    if (!$passed) {
        $GLOBALS['failures']++;
    }
}

$GLOBALS['failures'] = 0;

final class FakeExplanationLlm implements LlmClientInterface
{
    public array $lastInput = [];
    public int $calls = 0;

    public function __construct(
        private string $response,
        private bool $throwError = false
    ) {}

    public function generateExplanation(array $facts): string
    {
        $this->calls++;
        $this->lastInput = $facts;

        if ($this->throwError) {
            throw new RuntimeException('Simulated Ollama failure');
        }

        return $this->response;
    }
}

$climate = [
    'available' => true,
    'note' => 'Climate data unavailable.'
];

$comparison = [
    'key' => 'car',
    'value' => 3.3,
    'unit' => 'km',
    'basis' => 'per package'
];

$validResponse = json_encode([
    'comparison_key' => 'car',
    'explanation' =>
        'The climate impact of this pack is comparable to driving 3.3 km in a petrol car.'
], JSON_THROW_ON_ERROR);

// Test 1: Valid LLM response.
$validLlm = new FakeExplanationLlm($validResponse);

$service = new ExplanationService(
    $validLlm,
    new SystemLogger()
);

$result = $service->explain($climate, $comparison);

checkResult(
    'Valid LLM response accepted',
    $service->lastSource() === 'llm'
);

checkResult(
    'Correct number preserved',
    str_contains($result, '3.3 km')
);

checkResult(
    'Correct comparison options sent',
    ($validLlm->lastInput['comparison_options'][0]['key'] ?? null) === 'car'
);

// Test 2: Incorrect number.
$wrongNumber = new FakeExplanationLlm(
    json_encode([
        'comparison_key' => 'car',
        'explanation' =>
            'The climate impact of this pack is comparable to driving 999 km in a petrol car.'
    ], JSON_THROW_ON_ERROR)
);

$service = new ExplanationService(
    $wrongNumber,
    new SystemLogger()
);

$result = $service->explain($climate, $comparison);

checkResult(
    'Wrong number rejected',
    $service->lastSource() === 'fallback'
        && !str_contains($result, '999')
);

// Test 3: Incorrect comparison key.
$wrongKey = new FakeExplanationLlm(
    json_encode([
        'comparison_key' => 'shower',
        'explanation' =>
            'The climate impact of this pack is comparable to driving 3.3 km in a petrol car.'
    ], JSON_THROW_ON_ERROR)
);

$service = new ExplanationService(
    $wrongKey,
    new SystemLogger()
);

$service->explain($climate, $comparison);

checkResult(
    'Wrong comparison key rejected',
    $service->lastSource() === 'fallback'
);

// Test 4: Ollama failure.
$failedLlm = new FakeExplanationLlm('', true);

$service = new ExplanationService(
    $failedLlm,
    new SystemLogger()
);

$result = $service->explain($climate, $comparison);

checkResult(
    'Ollama failure activates fallback',
    $service->lastSource() === 'fallback'
        && str_contains($result, '3.3 km')
);

// Test 5: No LLM client.
$service = new ExplanationService(
    null,
    new SystemLogger()
);

$result = $service->explain($climate, $comparison);

checkResult(
    'System works without Ollama',
    $service->lastSource() === 'fallback'
        && str_contains($result, '3.3 km')
);

// Test 6: Per-kg basis.
$perKg = $comparison;
$perKg['basis'] = 'per kg';

$service = new ExplanationService(
    new FakeExplanationLlm($validResponse),
    new SystemLogger()
);

$service->explain($climate, $perKg);

checkResult(
    'Per-kg uses deterministic fallback',
    $service->lastSource() === 'fallback'
);

// Test 7: Wrong activity mentioned.
$wrongActivity = new FakeExplanationLlm(
    json_encode([
        'comparison_key' => 'car',
        'explanation' =>
            'The climate impact of this pack is comparable to 3.3 km of cycling rather than driving a car.'
    ], JSON_THROW_ON_ERROR)
);

$service = new ExplanationService(
    $wrongActivity,
    new SystemLogger()
);

$service->explain($climate, $comparison);

checkResult(
    'Incorrect activity rejected',
    $service->lastSource() === 'fallback'
);

echo PHP_EOL;
echo 'Failures: ' . $GLOBALS['failures'] . PHP_EOL;

exit($GLOBALS['failures'] === 0 ? 0 : 1);
