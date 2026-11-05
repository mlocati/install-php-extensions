<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking gnupg... ';

if (!function_exists('gnupg_init')) {
    throw new TestFailedException('The function gnupg_init() is not available');
}

echo "passed.\n";
