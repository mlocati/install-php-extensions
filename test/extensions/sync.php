<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking sync... ';

new SyncMutex();

echo "passed.\n";
