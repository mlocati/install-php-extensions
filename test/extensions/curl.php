<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking curl... ';

$version = curl_version();
if (!is_array($version) || empty($version['version'])) {
    throw new TestFailedException('curl_version() returned an unexpected value: ' . var_export($version, true));
}

echo "passed.\n";
