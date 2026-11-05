<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking opcache... ';

if (!in_array('Zend OPcache', get_loaded_extensions(true), true)) {
    throw new TestFailedException('The Zend OPcache extension is not loaded');
}
if (!function_exists('opcache_get_status')) {
    throw new TestFailedException('The function opcache_get_status() is missing');
}

echo "passed.\n";
