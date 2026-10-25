<?php
declare(strict_types=1);

$source = file_get_contents(
    dirname(__DIR__) . '/Api/instant_comparisons.php'
);

if ($source === false) {
    exit("FAIL - Source file not found\n");
}

$start = strpos($source, 'private function validateExplanation(');
$end = strpos($source, 'public function read(', $start);

if ($start === false || $end === false) {
    exit("FAIL - Validation method not found\n");
}

// Extract the real validation method, without modifying it.
$method = substr($source, $start, $end - $start);

$classCode = 'class ValidationTestHarness {' . $method . '}';

eval($classCode);

$reflection = new ReflectionMethod(
    ValidationTestHarness::class,
    'validateExplanation'
);

$reflection->setAccessible(true);
$validator = new ValidationTestHarness();

$context = [
    'subject' => 'This pack',
    'facts' => ['basis' => 'per package'],
    'selected_option' => [
        'key' => 'car',
        'amount' => '10',
        'spoken_amount' => '10',
        'is_example' => false
    ]
];

$tests = [
    'Valid explanation' => [
        'This pack has a climate impact comparable to driving a petrol car for 10 km.',
        true
    ],
    'Wrong number' => [
        'This pack has a climate impact comparable to driving a petrol car for 20 km.',
        false
    ],
    'Wrong unit' => [
        'This pack has a climate impact comparable to driving a petrol car for 10 miles.',
        false
    ],
    'Extra number' => [
        'This pack has a climate impact comparable to driving a petrol car for 10 km and 5 minutes.',
        false
    ],
    'Unsupported reduction claim' => [
        'This pack reduces climate impact comparable to driving a petrol car for 10 km.',
        false
    ],
    'Wrong subject' => [
        'One serving has a climate impact comparable to driving a petrol car for 10 km.',
        false
    ]
];

$failures = 0;

foreach ($tests as $name => [$text, $expected]) {
    $actual = $reflection->invoke(
        $validator,
        $context,
        'car',
        $text
    );

    $passed = $actual === $expected;

    echo ($passed ? 'PASS' : 'FAIL') . " - $name\n";

    if (!$passed) {
        $failures++;
    }
}

echo "Failures: $failures\n";
exit($failures > 0 ? 1 : 0);
