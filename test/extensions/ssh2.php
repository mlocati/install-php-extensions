<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ssh2... ';

if (!function_exists('ssh2_connect')) {
    throw new TestFailedException('The function ssh2_connect() is not available');
}

echo "passed.\n";
