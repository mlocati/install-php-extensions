<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_mysql... ';

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    throw new TestFailedException('The PDO driver mysql is missing');
}

echo "passed.\n";
