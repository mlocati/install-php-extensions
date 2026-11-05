<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking bcmath... ';

$actual = bcadd('1.5', '2.5', 1);
if ($actual !== '4.0') {
    throw new TestFailedException('bcadd() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
