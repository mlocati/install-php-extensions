<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo... ';

if (!is_array(PDO::getAvailableDrivers())) {
    throw new TestFailedException('PDO::getAvailableDrivers() did not return an array');
}

echo "passed.\n";
