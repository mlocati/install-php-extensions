<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking http... ';

$actual = (new http\Url('http://www.example.com/path'))->host;
if ($actual !== 'www.example.com') {
    throw new TestFailedException('http\\Url returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
