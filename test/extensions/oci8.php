<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking oci8... ';

if (oci_client_version() == '') {
    throw new TestFailedException('oci_client_version() failed');
}

echo "passed.\n";
