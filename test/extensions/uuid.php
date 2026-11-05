<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking uuid... ';

if (!uuid_is_valid(uuid_create())) {
    throw new TestFailedException('uuid_create() returned an invalid UUID');
}

echo "passed.\n";
