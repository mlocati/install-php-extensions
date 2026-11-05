<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking gettext... ';

$actual = gettext('test');
if ($actual !== 'test') {
    throw new TestFailedException('gettext() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
