<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xml... ';

$parser = xml_parser_create();
$values = [];
if (xml_parse_into_struct($parser, '<root>test</root>', $values) !== 1) {
    throw new TestFailedException('xml_parse_into_struct() failed: ' . xml_error_string(xml_get_error_code($parser)));
}
$actual = isset($values[0]['value']) ? $values[0]['value'] : null;
if ($actual !== 'test') {
    throw new TestFailedException('xml_parse_into_struct() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
