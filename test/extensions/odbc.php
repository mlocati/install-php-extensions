<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking odbc... ';

if (!function_exists('odbc_connect')) {
    throw new TestFailedException('The function odbc_connect() is missing');
}

echo "passed.\n";
