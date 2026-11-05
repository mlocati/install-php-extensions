<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking tideways... ';

if (!class_exists('Tideways\\Profiler')) {
    throw new TestFailedException('The class Tideways\\Profiler is missing');
}

echo "passed.\n";
