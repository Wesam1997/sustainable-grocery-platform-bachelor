<?php
declare(strict_types=1);

$source = file_get_contents(
    dirname(__DIR__) . '/Api/instant_comparisons.php'
);

if ($source === false) {
    exit("FAIL - Cannot read source file\n");
}

$start = strpos($source, 'private function validateExplanation(');
$end = strpos($source, 'public function read(', $start === false ? 0 : $start);

if ($start === false || $end === false || $end <= $start) {
    exit("FAIL - Validation method not found\n");
}

$method = substr($source, $start, $end - $start);

$checks = [
    'Selected comparison key' =>
        str_contains($method, "selected_option"),

    'Exact numeric value' =>
        str_contains($method, '$numbers !== [$candidate'),

    'Required unit' =>
        str_contains($method, '$unitPattern'),

    'Correct reference activity' =>
        str_contains($method, '$activityMeaningPattern'),

    'Correct product basis' =>
        str_contains($method, '$basisPattern'),

    'Climate impact meaning' =>
        str_contains($method, 'climate impact'),

    'Reject unsupported claims' =>
        str_contains($method, 'offset'),

    'Reject additional numbers' =>
        str_contains($method, 'preg_match_all'),
];

$failures = 0;

foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - $name\n";
    if (!$passed) {
        $failures++;
    }
}

echo "Failures: $failures\n";
exit($failures > 0 ? 1 : 0);
