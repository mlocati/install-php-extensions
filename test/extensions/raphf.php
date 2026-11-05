<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking raphf... ';

if (!extension_loaded('raphf')) {
    throw new TestFailedException('The extension raphf is not loaded');
}

echo "passed.\n";
