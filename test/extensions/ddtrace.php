<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ddtrace... ';

$version = phpversion('ddtrace');
if (!is_string($version) || $version === '') {
    throw new TestFailedException('Unable to determine the version of ddtrace');
}

echo "passed.\n";
