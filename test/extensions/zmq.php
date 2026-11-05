<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking zmq... ';

new ZMQContext();

echo "passed.\n";
