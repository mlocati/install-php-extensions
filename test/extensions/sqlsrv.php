<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sqlsrv... ';

sqlsrv_connect(
    'localhost, 50000',
    [
        'Authentication' => 'SqlPassword',
        'ConnectRetryCount' => 0,
        'Database' => 'example',
        'LoginTimeout' => 1,
        'UID' => 'userName',
        'PWD' => 'password',
    ]
);
// There's no server: an error coming from the driver means that the driver works
$messages = array_column((array) sqlsrv_errors(SQLSRV_ERR_ALL), 'message');
if (preg_grep('/\[Microsoft\]\[ODBC Driver \d+ for SQL Server\]/', $messages) === []) {
    throw new TestFailedException('The Microsoft ODBC Driver for SQL Server is not working: ' . implode("\n", $messages));
}

echo "passed.\n";
