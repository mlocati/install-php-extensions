<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sockets... ';

$pair = [];
if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair) !== true) {
    throw new TestFailedException('socket_create_pair() failed');
}
socket_write($pair[0], 'test');
$actual = socket_read($pair[1], 4);
socket_close($pair[0]);
socket_close($pair[1]);
if ($actual !== 'test') {
    throw new TestFailedException('socket_read() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
