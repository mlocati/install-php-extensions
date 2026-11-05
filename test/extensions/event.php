<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking event... ';

$base = new EventBase();
if (!is_string($base->getMethod()) || $base->getMethod() === '') {
    throw new TestFailedException('EventBase::getMethod() returned an unexpected value');
}
if (!class_exists('EventSslContext')) {
    throw new TestFailedException('event has not been compiled with OpenSSL support');
}
if (extension_loaded('sockets') && !method_exists('EventUtil', 'getSocketFd') && !method_exists('EventUtil', 'createSocket')) {
    throw new TestFailedException('event has not been compiled with sockets support');
}

echo "passed.\n";
