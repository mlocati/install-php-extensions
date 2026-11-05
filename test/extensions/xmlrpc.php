<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xmlrpc... ';

$actual = xmlrpc_decode(xmlrpc_encode(42));
if ($actual !== 42) {
    throw new TestFailedException('xmlrpc_decode() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
