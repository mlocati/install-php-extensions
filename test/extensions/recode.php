<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking recode... ';

$actual = recode_string('iso-8859-1..utf-8', "\xE8");
if ($actual !== "\xC3\xA8") {
    throw new TestFailedException('recode_string() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
