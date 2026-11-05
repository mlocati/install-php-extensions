<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sysvsem... ';

$semaphore = sem_get(ftok(__FILE__, 's'));
if (sem_acquire($semaphore) !== true || sem_release($semaphore) !== true) {
    throw new TestFailedException('Failed to acquire and release the semaphore');
}
sem_remove($semaphore);

echo "passed.\n";
