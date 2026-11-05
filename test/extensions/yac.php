<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking yac... ';

if (!class_exists('Yac')) {
    throw new TestFailedException('The class Yac is not available');
}

echo "passed.\n";
