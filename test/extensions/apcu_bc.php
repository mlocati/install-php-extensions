<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking apcu_bc... ';

if (!function_exists('apc_fetch')) {
    throw new TestFailedException('The function apc_fetch() is not available');
}

echo "passed.\n";
