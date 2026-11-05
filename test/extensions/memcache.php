<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking memcache... ';

if (!class_exists('Memcache')) {
    throw new TestFailedException('The class Memcache is not available');
}

echo "passed.\n";
