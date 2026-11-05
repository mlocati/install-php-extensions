<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking yar... ';

if (!class_exists('Yar_Client')) {
    throw new TestFailedException('The class Yar_Client is not available');
}

echo "passed.\n";
