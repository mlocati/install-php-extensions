<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ecma_intl... ';

if (!class_exists('Ecma\\Intl')) {
    throw new TestFailedException('The class Ecma\\Intl is not available');
}

echo "passed.\n";
