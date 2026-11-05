<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_odbc... ';

if (!in_array('odbc', PDO::getAvailableDrivers(), true)) {
    throw new TestFailedException('The PDO driver odbc is missing');
}

echo "passed.\n";
