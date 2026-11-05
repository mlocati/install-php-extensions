<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mcrypt... ';

if (!function_exists('mcrypt_encrypt')) {
    throw new TestFailedException('The function mcrypt_encrypt() is missing');
}
if (!defined('MCRYPT_RIJNDAEL_128')) {
    throw new TestFailedException('The constant MCRYPT_RIJNDAEL_128 is missing');
}

echo "passed.\n";
