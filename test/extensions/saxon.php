<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking saxon... ';

$version = (new Saxon\SaxonProcessor())->version();
if (!preg_match('/SaxonC-\w+ \d[.\d]*/', $version)) {
    throw new TestFailedException("Failed to parse the SaxonC version: {$version}");
}

echo "passed.\n";
