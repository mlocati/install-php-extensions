<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking parallel... ';

$actual = (new parallel\Runtime())->run(static function () { return 42; })->value();
if ($actual !== 42) {
    throw new TestFailedException('parallel\\Runtime returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
