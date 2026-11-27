<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xmlwriter... ';

$writer = new XMLWriter();
$writer->openMemory();
$writer->writeElement('root', 'test');
$actual = $writer->outputMemory();
if ($actual !== '<root>test</root>') {
    throw new TestFailedException('XMLWriter returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
