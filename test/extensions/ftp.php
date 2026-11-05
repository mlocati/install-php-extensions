<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ftp... ';

if (!function_exists('ftp_ssl_connect')) {
    throw new TestFailedException('The function ftp_ssl_connect() is missing');
}

echo "passed.\n";
