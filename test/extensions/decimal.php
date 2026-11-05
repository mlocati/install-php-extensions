<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking decimal... ';

$constructor = new ReflectionMethod('Decimal\Decimal', '__construct');
if ($constructor->isPrivate()) {
    $a = Decimal\Decimal::valueOf('0.1');
    $b = Decimal\Decimal::valueOf('.2');
} else {
    $a = new Decimal\Decimal('0.1');
    $b = new Decimal\Decimal('.2');
}
$actual = (string) ($a + $b);
if ($actual !== '0.3') {
    throw new TestFailedException('The sum of two decimals returned an unexpected value: ' . $actual);
}

echo "passed.\n";
