<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mssql... ';

if (!function_exists('mssql_connect')) {
    throw new TestFailedException('The function mssql_connect() is missing');
}

echo "passed.\n";
