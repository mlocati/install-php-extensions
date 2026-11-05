<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking oauth... ';

$actual = oauth_urlencode('a b');
if ($actual !== 'a%20b') {
    throw new TestFailedException('oauth_urlencode() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
