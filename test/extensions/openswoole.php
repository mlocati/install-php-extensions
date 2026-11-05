<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking openswoole... ';

if (!extension_loaded('openswoole')) {
    throw new TestFailedException('The extension is not loaded');
}

echo "passed.\n";
