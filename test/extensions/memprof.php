<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking memprof... ';

if (!function_exists('memprof_enabled')) {
    throw new TestFailedException('The function memprof_enabled() is not available');
}

echo "passed.\n";
