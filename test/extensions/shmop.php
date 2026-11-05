<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking shmop... ';

$block = shmop_open(ftok(__FILE__, 't'), 'c', 0644, 4);
shmop_write($block, 'test', 0);
$actual = shmop_read($block, 0, 4);
shmop_delete($block);
if ($actual !== 'test') {
    throw new TestFailedException('shmop_read() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
