<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mysql... ';

if (!function_exists('mysql_connect')) {
    throw new TestFailedException('The function mysql_connect() is missing');
}
if (mysql_get_client_info() == '') {
    throw new TestFailedException('mysql_get_client_info() failed');
}

echo "passed.\n";
