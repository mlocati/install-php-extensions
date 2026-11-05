<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking seaslog... ';

if (!class_exists('SeasLog')) {
    throw new TestFailedException('The class SeasLog is not available');
}

echo "passed.\n";
