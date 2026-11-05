<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking wikidiff2... ';

if (!is_array(json_decode(wikidiff2_inline_json_diff("a\nb\nc", "a\nd\nc", 1), true))) {
    throw new TestFailedException('wikidiff2_inline_json_diff() returned an unexpected value');
}

echo "passed.\n";
