<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking smbclient... ';

if (!is_string(smbclient_version())) {
    throw new TestFailedException('smbclient_version() did not return a string');
}

echo "passed.\n";
