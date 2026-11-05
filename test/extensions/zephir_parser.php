<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking zephir_parser... ';

if (!function_exists('zephir_parse_file')) {
    throw new TestFailedException('The function zephir_parse_file() is not available');
}

echo "passed.\n";
