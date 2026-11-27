<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking posix... ';

$actual = posix_getpid();
if ($actual !== getmypid()) {
    throw new TestFailedException('posix_getpid() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
