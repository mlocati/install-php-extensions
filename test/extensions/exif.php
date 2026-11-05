<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking exif... ';

$actual = exif_tagname(0x010F);
if ($actual !== 'Make') {
    throw new TestFailedException('exif_tagname() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
