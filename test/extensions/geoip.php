<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking geoip... ';

if (!function_exists('geoip_db_avail')) {
    throw new TestFailedException('The function geoip_db_avail() is not available');
}

echo "passed.\n";
