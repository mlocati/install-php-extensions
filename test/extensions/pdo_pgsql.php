<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_pgsql... ';

if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    throw new TestFailedException('The PDO driver pgsql is missing');
}

echo "passed.\n";
