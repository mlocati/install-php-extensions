<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pthreads... ';

if (!class_exists('Thread')) {
    throw new TestFailedException('The class Thread is not available');
}

echo "passed.\n";
