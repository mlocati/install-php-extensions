<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking tidy... ';

$actual = tidy_repair_string('<p>test', ['show-body-only' => true]);
if (strpos($actual, '<p>test</p>') === false) {
    throw new TestFailedException('tidy_repair_string() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
