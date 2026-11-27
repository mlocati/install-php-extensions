<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking fileinfo... ';

$finfo = new finfo(FILEINFO_MIME_TYPE);
$actual = $finfo->buffer("\x89PNG\r\n\x1A\n\x00\x00\x00\x0DIHDR");
if ($actual !== 'image/png') {
    throw new TestFailedException('finfo::buffer() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
