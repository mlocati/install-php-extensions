<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking jsmin... ';

if (strpos(jsmin("var a = 1;\n"), 'var a=1') === false) {
    throw new TestFailedException('jsmin() returned an unexpected value');
}

echo "passed.\n";
