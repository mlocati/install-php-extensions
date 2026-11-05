<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking gearman... ';

if (!class_exists('GearmanClient')) {
    throw new TestFailedException('The class GearmanClient is not available');
}

echo "passed.\n";
