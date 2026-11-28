<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking iconv... ';

$converted = iconv('UTF-8', 'ISO-8859-1', "\xC3\xA8");
if ($converted !== "\xE8") {
    throw new TestFailedException('iconv() returned an unexpected value: ' . var_export($converted, true));
}

echo "passed.\n";
