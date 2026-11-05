<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_firebird... ';

if (!in_array('firebird', PDO::getAvailableDrivers(), true)) {
    throw new TestFailedException('The PDO driver firebird is missing');
}

echo "passed.\n";
