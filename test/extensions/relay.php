<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking relay... ';

if (!class_exists('Relay\\Relay')) {
    throw new TestFailedException('The class Relay\\Relay is missing');
}

echo "passed.\n";
