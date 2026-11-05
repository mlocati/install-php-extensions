<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking csv... ';

if (!extension_loaded('csv')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
