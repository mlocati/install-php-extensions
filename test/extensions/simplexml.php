<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking simplexml... ';

$xml = simplexml_load_string('<root><item>test</item></root>');
$actual = $xml === false ? false : (string) $xml->item;
if ($actual !== 'test') {
    throw new TestFailedException('simplexml_load_string() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
