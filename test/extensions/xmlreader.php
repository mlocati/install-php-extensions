<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xmlreader... ';

$reader = new XMLReader();
$reader->XML('<root>test</root>');
$actual = null;
while ($reader->read()) {
    if ($reader->nodeType === XMLReader::TEXT) {
        $actual = $reader->value;
    }
}
if ($actual !== 'test') {
    throw new TestFailedException('XMLReader returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
