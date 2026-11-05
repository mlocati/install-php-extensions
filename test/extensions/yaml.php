<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking yaml... ';

$actual = yaml_parse('a: 1');
if ($actual !== ['a' => 1]) {
    throw new TestFailedException('yaml_parse() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
