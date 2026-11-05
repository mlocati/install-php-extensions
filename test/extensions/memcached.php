<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking memcached... ';

new Memcached();
if (PHP_VERSION_ID >= 70000) {
    if (extension_loaded('igbinary') && !Memcached::HAVE_IGBINARY) {
        throw new TestFailedException('igbinary support has not been enabled');
    }
    if (extension_loaded('msgpack') && !Memcached::HAVE_MSGPACK) {
        throw new TestFailedException('msgpack support has not been enabled');
    }
}

echo "passed.\n";
