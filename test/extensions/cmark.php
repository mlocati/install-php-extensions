<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking cmark... ';

$actual = CommonMark\Render\HTML(CommonMark\Parse("# Test\n"));
if ($actual !== "<h1>Test</h1>\n") {
    throw new TestFailedException('CommonMark\Render\HTML() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
