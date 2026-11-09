<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking session... ';

if (session_status() !== PHP_SESSION_NONE) {
    throw new TestFailedException('session_status() returned an unexpected value: ' . var_export(session_status(), true));
}

echo "passed.\n";
