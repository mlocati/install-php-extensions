<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking vips... ';

if (!is_string(vips_version())) {
    throw new TestFailedException('vips_version() did not return a string');
}

echo "passed.\n";
