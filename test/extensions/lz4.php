<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking lz4... ';

$actual = lz4_uncompress(lz4_compress('Hello, world!'));
if ($actual !== 'Hello, world!') {
    throw new TestFailedException('lz4_uncompress() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
