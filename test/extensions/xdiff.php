<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking xdiff... ';

if (!is_string(xdiff_string_diff("a\n", "b\n"))) {
    throw new TestFailedException('xdiff_string_diff() did not return a string');
}

echo "passed.\n";
