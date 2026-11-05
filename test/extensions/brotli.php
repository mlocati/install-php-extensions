<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking brotli... ';

$actual = brotli_uncompress(brotli_compress('Hello, world!'));
if ($actual !== 'Hello, world!') {
    throw new TestFailedException('brotli_uncompress(brotli_compress()) returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
