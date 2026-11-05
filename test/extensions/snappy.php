<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking snappy... ';

$actual = snappy_uncompress(snappy_compress('Hello, world!'));
if ($actual !== 'Hello, world!') {
    throw new TestFailedException('snappy_uncompress() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
