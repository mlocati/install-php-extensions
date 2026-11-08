<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking event... ';

$base = new EventBase();
if (!is_string($base->getMethod()) || $base->getMethod() === '') {
    throw new TestFailedException('EventBase::getMethod() returned an unexpected value');
}
// On Debian Stretch, event is built without OpenSSL if PHP uses OpenSSL 1.0
$withoutOpenSSL = defined('OPENSSL_VERSION_TEXT') && strpos(OPENSSL_VERSION_TEXT, 'OpenSSL 1.0.') === 0 && is_file('/etc/debian_version') && strpos((string) file_get_contents('/etc/debian_version'), '9.') === 0;
if (!$withoutOpenSSL && !class_exists('EventSslContext')) {
    throw new TestFailedException('event has not been compiled with OpenSSL support');
}
if (extension_loaded('sockets') && !method_exists('EventUtil', 'getSocketFd') && !method_exists('EventUtil', 'createSocket')) {
    throw new TestFailedException('event has not been compiled with sockets support');
}

echo "passed.\n";
