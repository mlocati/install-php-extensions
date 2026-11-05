<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking wddx... ';

$actual = wddx_deserialize(wddx_serialize_value('test'));
if ($actual !== 'test') {
    throw new TestFailedException('wddx_deserialize() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
