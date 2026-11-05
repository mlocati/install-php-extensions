<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mongo... ';

if (!class_exists('MongoClient')) {
    throw new TestFailedException('The class MongoClient is not available');
}

echo "passed.\n";
