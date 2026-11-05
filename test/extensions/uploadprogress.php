<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking uploadprogress... ';

if (!function_exists('uploadprogress_get_info')) {
    throw new TestFailedException('The function uploadprogress_get_info() is not available');
}

echo "passed.\n";
