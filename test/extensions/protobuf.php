<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking protobuf... ';

if (!class_exists('Google\\Protobuf\\Internal\\Message')) {
    throw new TestFailedException('The class Google\\Protobuf\\Internal\\Message is not available');
}

echo "passed.\n";
