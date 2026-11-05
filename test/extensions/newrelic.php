<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking newrelic... ';

if (!function_exists('newrelic_start_transaction')) {
    throw new TestFailedException('The function newrelic_start_transaction() is missing');
}

echo "passed.\n";
