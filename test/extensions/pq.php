<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pq... ';

if (!class_exists('pq\\Connection')) {
    throw new TestFailedException('The class pq\\Connection is not available');
}

echo "passed.\n";
