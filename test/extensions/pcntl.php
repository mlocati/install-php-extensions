<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking pcntl... ';

if (pcntl_signal(SIGTERM, SIG_DFL) !== true) {
    throw new TestFailedException('pcntl_signal() failed');
}

echo "passed.\n";
