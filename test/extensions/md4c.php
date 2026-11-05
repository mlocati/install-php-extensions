<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking md4c... ';

$actual = md4c_toHtml("# Test\n");
if ($actual !== "<h1>Test</h1>\n") {
    throw new TestFailedException('md4c_toHtml() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
