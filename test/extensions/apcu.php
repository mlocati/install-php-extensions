<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking apcu... ';

if (!function_exists('apcu_fetch')) {
    throw new TestFailedException('The function apcu_fetch() is missing');
}

echo "passed.\n";
