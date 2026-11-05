<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pgsql... ';

if (!function_exists('pg_connect')) {
    throw new TestFailedException('The function pg_connect() is missing');
}
if (!defined('PGSQL_LIBPQ_VERSION')) {
    throw new TestFailedException('The constant PGSQL_LIBPQ_VERSION is missing');
}

echo "passed.\n";
