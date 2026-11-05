<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_oci... ';

if (!in_array('oci', PDO::getAvailableDrivers(), true)) {
    throw new TestFailedException('The PDO driver oci is missing');
}

echo "passed.\n";
