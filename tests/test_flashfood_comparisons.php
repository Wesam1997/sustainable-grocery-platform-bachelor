<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root.'/src/Contracts/ComparisonReferenceInterface.php';
require_once $root.'/src/Contracts/ComparisonEngineInterface.php';
require_once $root.'/src/Contracts/ComparisonSelectorInterface.php';
require_once $root.'/src/Infrastructure/References/ArrayComparisonReferences.php';
require_once $root.'/src/Modules/Comparison/ComparisonEngine.php';
require_once $root.'/src/Modules/Comprsion/ComparisonSelector.php';
$refs = new ArrayComparisonReferences([
 'car'=>['kg_co2e_per_unit'=>0.16152,'unit'=>'km','is_example'=>false],
 'phone'=>['kg_co2e_per_unit'=>0.00154244,'unit'=>'charge','is_example'=>true],
 'shower'=>['kg_co2e_per_unit'=>0.02832,'unit'=>'min','is_example'=>true]
]);
$engine = new ComparisonEngine($refs);
$selector = new ComparisonSelector($engine);
$failures = 0;
function check(string $name, bool $ok): void {
 global $failures;
 echo ($ok?'PASS':'FAIL')." - $name\n";
 if (!$ok) $failures++;
}
foreach (['car'=>0.16152,'phone'=>0.00154244,'shower'=>0.02832] as $key=>$factor) {
 $result=$engine->compare($factor*10,'per_kg',$key);
 check("$key: 10 units", $result !== null && abs($result['value']-10)<0.000001);
}
check('Negative CO2 rejected', $engine->compare(-1,'per_kg','car')===null);
check('Unknown activity rejected', $engine->compare(1,'per_kg','unknown')===null);
check('Selector rejects zero', $selector->select(0,'per_kg',1)===null);
$selected=$selector->select(0.16152,'per_kg',1);
check('Selector returns valid comparison', $selected!==null && $selected['value']>0);
echo "Failures: $failures\n";
exit($failures ? 1 : 0);