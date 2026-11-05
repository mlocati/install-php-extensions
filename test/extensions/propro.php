<?php

require_once __DIR__ . '/_bootstrap.php';

echo 'Checking propro... ';

if (!class_exists('php\\PropertyProxy')) {
    throw new TestFailedException('The class php\\PropertyProxy is missing');
}

echo "passed.\n";
