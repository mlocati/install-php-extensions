<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking jsonpath... ';

if (!extension_loaded('jsonpath')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
