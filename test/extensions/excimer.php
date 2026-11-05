<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking excimer... ';

if (!class_exists('ExcimerProfiler')) {
    throw new TestFailedException('The class ExcimerProfiler is not available');
}

echo "passed.\n";
