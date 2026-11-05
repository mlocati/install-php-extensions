<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking amqp... ';

$actual = (new AMQPConnection())->getHost();
if ($actual !== 'localhost') {
    throw new TestFailedException('AMQPConnection::getHost() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
