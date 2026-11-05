<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking redis... ';

new Redis();
if (!defined('Redis::COMPRESSION_LZF')) {
    throw new TestFailedException('LZF support has not been enabled');
}
if (extension_loaded('igbinary') && !defined('Redis::SERIALIZER_IGBINARY')) {
    throw new TestFailedException('igbinary support has not been enabled');
}

echo "passed.\n";
