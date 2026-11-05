<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking uopz... ';

if (!extension_loaded('uopz')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
