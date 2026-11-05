<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking zookeeper... ';

if (!class_exists('Zookeeper')) {
    throw new TestFailedException('The class Zookeeper is not available');
}

echo "passed.\n";
