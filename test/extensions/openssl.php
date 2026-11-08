<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking openssl... ';

if (!defined('OPENSSL_VERSION_TEXT')) {
    throw new TestFailedException('The constant OPENSSL_VERSION_TEXT is missing');
}
if (!function_exists('openssl_encrypt')) {
    throw new TestFailedException('The function openssl_encrypt() is missing');
}

echo "passed.\n";
