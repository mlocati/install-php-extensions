<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pcov... ';

if (!function_exists('pcov\collect')) {
    throw new TestFailedException('The function pcov\collect() is not available');
}

echo "passed.\n";
