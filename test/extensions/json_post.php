<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking json_post... ';

if (!extension_loaded('json_post')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
