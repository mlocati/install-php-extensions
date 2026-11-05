<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xhprof... ';

if (!function_exists('xhprof_enable')) {
    throw new TestFailedException('The function xhprof_enable() is not available');
}

echo "passed.\n";
