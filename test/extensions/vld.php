<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking vld... ';

if (!extension_loaded('vld')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
