<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking phpy... ';

$uname = PyCore::import('os')->uname();
$sysname = (string) $uname[0];
if ($sysname !== php_uname('s')) {
    throw new TestFailedException('os.uname()[0] returned an unexpected value: ' . $sysname);
}

echo "passed.\n";
