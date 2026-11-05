<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking igbinary... ';

$actual = igbinary_unserialize(igbinary_serialize([1, 'a']));
if ($actual !== [1, 'a']) {
    throw new TestFailedException('igbinary_unserialize() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
