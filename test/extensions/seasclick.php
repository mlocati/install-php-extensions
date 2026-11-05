<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking seasclick... ';

if (!class_exists('SeasClick')) {
    throw new TestFailedException('The class SeasClick is not available');
}

echo "passed.\n";
