<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking zstd... ';

$actual = zstd_uncompress(zstd_compress('Hello, world!'));
if ($actual !== 'Hello, world!') {
    throw new TestFailedException('zstd_uncompress(zstd_compress()) returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
