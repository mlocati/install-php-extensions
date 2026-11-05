<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking lzf... ';

$actual = lzf_decompress(lzf_compress('test'));
if ($actual !== 'test') {
    throw new TestFailedException('lzf_decompress() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
