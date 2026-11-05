<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_snowflake... ';

if (!in_array('snowflake', PDO::getAvailableDrivers(), true)) {
    throw new TestFailedException('The snowflake PDO driver is not available');
}

echo "passed.\n";
