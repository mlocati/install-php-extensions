<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ffi... ';

$actual = FFI::cdef('int abs(int);')->abs(-5);
if ($actual !== 5) {
    throw new TestFailedException('FFI returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
