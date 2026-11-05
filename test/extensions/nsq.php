<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking nsq... ';

if (!class_exists('Nsq')) {
    throw new TestFailedException('The class Nsq is not available');
}

echo "passed.\n";
