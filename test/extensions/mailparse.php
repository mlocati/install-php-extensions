<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mailparse... ';

$addresses = mailparse_rfc822_parse_addresses('user@example.com');
if ($addresses[0]['address'] !== 'user@example.com') {
    throw new TestFailedException('mailparse_rfc822_parse_addresses() returned an unexpected value');
}

echo "passed.\n";
