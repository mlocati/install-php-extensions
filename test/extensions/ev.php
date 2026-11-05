<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking ev... ';

if (!is_float(Ev::time())) {
    throw new TestFailedException('Ev::time() did not return a float');
}

echo "passed.\n";
