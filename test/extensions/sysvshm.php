<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sysvshm... ';

$memory = shm_attach(ftok(__FILE__, 's'), 1024);
shm_put_var($memory, 1, 'test');
$actual = shm_get_var($memory, 1);
shm_remove($memory);
shm_detach($memory);
if ($actual !== 'test') {
    throw new TestFailedException('shm_get_var() returned an unexpected value: ' . var_export($actual, true));
}

echo "passed.\n";
