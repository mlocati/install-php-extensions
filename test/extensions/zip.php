<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking zip... ';

$file = tempnam(sys_get_temp_dir(), 'ipe');
unlink($file);
$zip = new ZipArchive();
if ($zip->open($file, ZipArchive::CREATE) !== true) {
    throw new TestFailedException('ZipArchive::open() failed');
}
$zip->addFromString('file.txt', 'test');
$zip->close();
$zip = new ZipArchive();
$zip->open($file);
$actual = $zip->getFromName('file.txt');
$zip->close();
unlink($file);
if ($actual !== 'test') {
    throw new TestFailedException('ZipArchive::getFromName() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
