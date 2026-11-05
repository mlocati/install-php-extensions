<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking opencensus... ';

if (!function_exists('opencensus_trace_begin')) {
    throw new TestFailedException('The function opencensus_trace_begin() is not available');
}

echo "passed.\n";
