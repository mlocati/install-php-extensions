<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pdo_sqlsrv... ';

try {
    new PDO('sqlsrv:server=localhost; Authentication=SqlPassword; ConnectRetryCount=0; Database=example; LoginTimeout=1', 'userName', 'password');
} catch (PDOException $x) {
    // There's no server: an error coming from the driver means that the driver works
    if (!preg_match('/\[Microsoft\]\[ODBC Driver \d+ for SQL Server\]/', $x->getMessage())) {
        throw new TestFailedException('The Microsoft ODBC Driver for SQL Server is not working: ' . $x->getMessage());
    }
}

echo "passed.\n";
