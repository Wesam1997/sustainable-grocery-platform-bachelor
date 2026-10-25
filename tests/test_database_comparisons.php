<?php
declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/src/Contracts/ComparisonReferenceInterface.php';
require_once $root . '/src/Infrastructure/Database/DatabaseConnection.php';
require_once $root . '/src/Infrastructure/References/MysqliComparisonReferences.php';

$connection = DatabaseConnection::open();
$references = new MysqliComparisonReferences($connection);

$expected = [
    'car' => ['factor' => 0.16152000, 'unit' => 'km'],
    'phone' => ['factor' => 0.00154244, 'unit' => 'charge'],
    'shower' => ['factor' => 0.02832000, 'unit' => 'min'],
];

$failures = 0;

foreach ($expected as $key => $data) {
    $result = $references->find($key);

    $passed = $result !== null
        && abs((float)$result['kg_co2e_per_unit'] - $data['factor']) < 0.00000001
        && $result['unit'] === $data['unit']
        && !empty($result['source']['title'])
        && !empty($result['source']['url']);

    echo ($passed ? 'PASS' : 'FAIL') . " - Database $key\n";

    if (!$passed) {
        $failures++;
    }
}

$connection->close();

echo "Failures: $failures\n";
exit($failures > 0 ? 1 : 0);
