<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking luasandbox... ';

$actual = (new LuaSandbox())->loadString('return 1 + 1')->call();
if ($actual !== [2]) {
    throw new TestFailedException('LuaSandboxFunction::call() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
