<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking operator... ';

if (!extension_loaded('operator')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
