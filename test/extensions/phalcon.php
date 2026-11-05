<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking phalcon... ';

if (!class_exists('Phalcon\Support\Version') && !class_exists('Phalcon\Version')) {
    throw new TestFailedException('The class Phalcon\Version is not available');
}

echo "passed.\n";
