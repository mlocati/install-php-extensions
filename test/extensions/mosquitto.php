<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mosquitto... ';

if (!class_exists('Mosquitto\\Client')) {
    throw new TestFailedException('The class Mosquitto\\Client is not available');
}

echo "passed.\n";
