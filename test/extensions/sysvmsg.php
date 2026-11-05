<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sysvmsg... ';

$queue = msg_get_queue(ftok(__FILE__, 'm'));
msg_send($queue, 1, 'test');
$type = null;
$actual = null;
msg_receive($queue, 0, $type, 64, $actual);
msg_remove_queue($queue);
if ($actual !== 'test') {
    throw new TestFailedException('msg_receive() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
