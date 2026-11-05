<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking bz2... ';

$actual = bzdecompress(bzcompress('test'));
if ($actual !== 'test') {
    throw new TestFailedException('bzdecompress() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
