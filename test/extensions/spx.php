<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking spx... ';

if (ini_get('spx.http_enabled') === false) {
    throw new TestFailedException('The settings of the extension are not available');
}

echo "passed.\n";
