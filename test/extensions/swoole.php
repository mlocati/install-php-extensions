<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking swoole... ';

$actual = swoole_version();
if (!preg_match('/^\d+\.\d+\.\d+/', $actual)) {
    throw new TestFailedException('swoole_version() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
