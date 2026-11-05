<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking gmp... ';

$actual = gmp_strval(gmp_add('1', '2'));
if ($actual !== '3') {
    throw new TestFailedException('gmp_add() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
