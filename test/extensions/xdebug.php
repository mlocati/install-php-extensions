<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xdebug... ';

if (!extension_loaded('xdebug')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
