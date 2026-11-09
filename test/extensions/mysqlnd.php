<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking mysqlnd... ';

// mysqlnd doesn't provide functions or classes: it's used by mysqli and pdo_mysql
if (!extension_loaded('mysqlnd')) {
    throw new TestFailedException('The mysqlnd extension is not loaded');
}

echo "passed.\n";
