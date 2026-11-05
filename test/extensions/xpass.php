<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xpass... ';

if (!function_exists('crypt_gensalt')) {
    throw new TestFailedException('The function crypt_gensalt() is not available');
}

echo "passed.\n";
