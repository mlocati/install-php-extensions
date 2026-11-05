<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mbstring... ';

$actual = mb_strlen("\xC3\xA8", 'UTF-8');
if ($actual !== 1) {
    throw new TestFailedException('mb_strlen() returned an unexpected value: ' . var_export($actual, true));
}
// The mb_ereg functions are deprecated since PHP 8.6
if (PHP_VERSION_ID < 80600) {
    $actual = mb_ereg_replace('a+', 'x', 'caab');
    if ($actual !== 'cxb') {
        throw new TestFailedException('mb_ereg_replace() returned an unexpected value: ' . var_export($actual, true));
    }
}

echo "passed.\n";
