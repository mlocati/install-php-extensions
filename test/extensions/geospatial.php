<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking geospatial... ';

if (!function_exists('haversine')) {
    throw new TestFailedException('The function haversine() is not available');
}

echo "passed.\n";
