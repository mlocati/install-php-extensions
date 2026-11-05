<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ip2location... ';

if (!function_exists('ip2location_open')) {
    throw new TestFailedException('The function ip2location_open() is not available');
}

echo "passed.\n";
