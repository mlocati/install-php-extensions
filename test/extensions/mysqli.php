<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mysqli... ';

if (mysqli_get_client_info() == '') {
    throw new TestFailedException('mysqli_get_client_info() failed');
}

echo "passed.\n";
