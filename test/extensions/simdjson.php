<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking simdjson... ';

$actual = simdjson_is_valid('[1]');
if ($actual !== true) {
    throw new TestFailedException('simdjson_is_valid() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
