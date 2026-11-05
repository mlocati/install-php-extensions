<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking timezonedb... ';

if (!extension_loaded('timezonedb')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
