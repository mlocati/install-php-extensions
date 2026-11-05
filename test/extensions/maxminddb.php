<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking maxminddb... ';

if (!class_exists('MaxMind\\Db\\Reader')) {
    throw new TestFailedException('The class MaxMind\\Db\\Reader is not available');
}

echo "passed.\n";
