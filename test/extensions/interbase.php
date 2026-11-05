<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking interbase... ';

if (!function_exists('ibase_connect')) {
    throw new TestFailedException('The function ibase_connect() is missing');
}

echo "passed.\n";
