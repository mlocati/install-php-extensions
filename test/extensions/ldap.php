<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ldap... ';

if (ldap_connect('ldap://localhost') === false) {
    throw new TestFailedException('ldap_connect() failed');
}

echo "passed.\n";
