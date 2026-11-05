<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_dblib... ';

if (!in_array('dblib', PDO::getAvailableDrivers(), true)) {
    throw new TestFailedException('The PDO driver dblib is missing');
}

echo "passed.\n";
