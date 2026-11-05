<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking grpc... ';

$actual = get_class(Grpc\Timeval::zero());
if ($actual !== 'Grpc\\Timeval') {
    throw new TestFailedException('Grpc\Timeval::zero() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
