<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking psr... ';

if (!interface_exists('Psr\Log\LoggerInterface')) {
    throw new TestFailedException('The interface Psr\Log\LoggerInterface is not available');
}

echo "passed.\n";
