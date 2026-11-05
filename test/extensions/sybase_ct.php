<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sybase_ct... ';

if (!function_exists('sybase_connect')) {
    throw new TestFailedException('The function sybase_connect() is missing');
}

echo "passed.\n";
